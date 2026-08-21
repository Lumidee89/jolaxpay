<?php

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

it('registers a new user with no BVN/NIN and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Adaeze Okafor',
        'phone_number' => '+2348011111111',
        'email' => 'adaeze@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'device_name' => 'iPhone-15',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.full_name', 'Adaeze Okafor')
        ->assertJsonStructure(['user', 'token']);

    expect(User::where('email', 'adaeze@example.com')->exists())->toBeTrue();
});

it('rejects registration with a duplicate phone number', function () {
    User::factory()->create(['phone_number' => '+2348011111111']);

    $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Someone Else',
        'phone_number' => '+2348011111111',
        'email' => 'someone@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'device_name' => 'Android-1',
    ])->assertUnprocessable()->assertJsonValidationErrors('phone_number');
});

it('logs a known device straight in with a token', function () {
    $user = User::factory()->create(['password' => 'Password123']);
    $user->createToken('iPhone-15'); // pre-existing token for this device name

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password123',
        'device_name' => 'iPhone-15',
    ]);

    $response->assertOk()->assertJsonStructure(['user', 'token']);
});

it('challenges a new device with an OTP instead of issuing a token', function () {
    $user = User::factory()->create(['password' => 'Password123']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password123',
        'device_name' => 'brand-new-device',
    ]);

    $response->assertOk()
        ->assertJson(['requires_otp' => true])
        ->assertJsonMissing(['token']);

    expect(Otp::where('identifier', $user->phone_number)->where('purpose', 'new_device_login')->exists())->toBeTrue();
});

it('actually sends the new-device OTP through BulkSMSLive when that driver is active', function () {
    config(['notify.sms.driver' => 'bulksmslive', 'notify.sms.api_key' => 'bsl_test_key']);
    Http::fake(['api.bulksmslive.com/*' => Http::response(['status' => 1, 'msgid' => 'x', 'balance' => 500], 200)]);

    $user = User::factory()->create(['password' => 'Password123', 'phone_number' => '08031234567']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password123',
        'device_name' => 'brand-new-device',
    ])->assertOk()->assertJson(['requires_otp' => true]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'bulksmslive.com')
        && $request['recipients'] === '2348031234567'
        && str_contains($request['message'], 'verification code'));
});

it('logs a new device straight in when AUTH_BYPASS_LOGIN_OTP is enabled', function () {
    config(['identity.bypass_login_otp' => true]);

    $user = User::factory()->create(['password' => 'Password123']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password123',
        'device_name' => 'brand-new-device',
    ]);

    $response->assertOk()->assertJsonStructure(['user', 'token']);

    expect(Otp::where('identifier', $user->phone_number)->where('purpose', 'new_device_login')->exists())->toBeFalse();
});

it('rejects login with the wrong password', function () {
    $user = User::factory()->create(['password' => 'Password123']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'WrongPassword',
        'device_name' => 'iPhone-15',
    ])->assertUnprocessable();
});

it('resets a password using an SMS OTP sent to the registered phone number', function () {
    $user = User::factory()->create(['password' => 'OldPassword123']);

    $this->postJson('/api/v1/auth/password/forgot', [
        'phone_number' => $user->phone_number,
    ])->assertOk();

    $otp = Otp::where('identifier', $user->phone_number)
        ->where('purpose', 'password_reset')
        ->latest('id')
        ->firstOrFail();

    // The OTP model stores only a hash; issue a known code for the reset assertion.
    $otp->update(['code_hash' => Hash::make('123456')]);

    $this->postJson('/api/v1/auth/password/reset', [
        'phone_number' => $user->phone_number,
        'code' => '123456',
        'password' => 'NewPassword123',
        'password_confirmation' => 'NewPassword123',
    ])->assertOk();

    expect(Hash::check('NewPassword123', $user->fresh()->password))->toBeTrue();
});

it('requires a new-device OTP for business accounts too', function () {
    $user = User::factory()->create(['password' => 'Password123', 'account_type' => 'agent']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Password123',
        'device_name' => 'new-business-device',
    ])->assertOk()->assertJson(['requires_otp' => true, 'purpose' => 'new_device_login']);
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('iPhone-15');

    $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
