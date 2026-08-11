<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

it('updates the full name only, leaving email and phone untouched', function () {
    $user = User::factory()->create(['full_name' => 'Old Name']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/auth/profile', ['full_name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('user.full_name', 'New Name');

    expect($user->fresh())
        ->full_name->toBe('New Name')
        ->email_verified_at->not->toBeNull()
        ->phone_verified_at->not->toBeNull();
});

it('resets email_verified_at when the email changes', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/auth/profile', ['email' => 'new@example.com'])
        ->assertOk()
        ->assertJsonPath('user.email', 'new@example.com')
        ->assertJsonPath('user.email_verified', false);

    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('resets phone_verified_at when the phone number changes', function () {
    $user = User::factory()->create(['phone_number' => '+2348010000001']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/auth/profile', ['phone_number' => '+2348010000002'])
        ->assertOk()
        ->assertJsonPath('user.phone_verified', false);

    expect($user->fresh()->phone_verified_at)->toBeNull();
});

it('rejects an email already used by another account', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'mine@example.com']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/auth/profile', ['email' => 'taken@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects a phone number already used by another account', function () {
    User::factory()->create(['phone_number' => '+2348020000001']);
    $user = User::factory()->create(['phone_number' => '+2348020000002']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/auth/profile', ['phone_number' => '+2348020000001'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone_number');
});

it('uploads a profile picture and replaces the previous one', function () {
    Storage::fake('uploads');
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $first = UploadedFile::fake()->image('first.jpg');
    $response = $this->postJson('/api/v1/auth/avatar', ['avatar' => $first]);
    $response->assertOk();
    $firstPath = $user->fresh()->avatar_path;
    Storage::disk('uploads')->assertExists($firstPath);

    $second = UploadedFile::fake()->image('second.jpg');
    $this->postJson('/api/v1/auth/avatar', ['avatar' => $second])->assertOk();

    Storage::disk('uploads')->assertMissing($firstPath);
    Storage::disk('uploads')->assertExists($user->fresh()->avatar_path);
});
