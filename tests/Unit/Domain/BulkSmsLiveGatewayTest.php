<?php

use App\Domain\Notifications\BulkSmsLiveGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('sends via the API key endpoint when an API key is configured', function () {
    config(['notify.sms.api_key' => 'bsl_test_key', 'notify.sms.email' => null, 'notify.sms.password' => null, 'notify.sms.sender_id' => 'JolaxPay']);

    Http::fake(['api.bulksmslive.com/v2/app/sendsms' => Http::response([
        'status' => 1, 'msg' => 'Ok', 'msgid' => 'abc123', 'units' => 1, 'balance' => 500,
    ], 200)]);

    $sent = app(BulkSmsLiveGateway::class)->send('08031234567', 'Your code is 123456.');

    expect($sent)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.bulksmslive.com/v2/app/sendsms'
            && $request->hasHeader('Authorization', 'Bearer bsl_test_key')
            && $request['recipients'] === '2348031234567' // local "0..." normalized to international
            && $request['sender_name'] === 'JolaxPay'
            && $request['forcednd'] === 1;
    });
});

it('falls back to the email/password endpoint when no API key is configured', function () {
    config(['notify.sms.api_key' => null, 'notify.sms.email' => 'ops@jolaxpay.com', 'notify.sms.password' => 'secret']);

    Http::fake(['api.bulksmslive.com/v2/app/sms' => Http::response(['status' => 1, 'msgid' => 'x', 'balance' => 200], 200)]);

    $sent = app(BulkSmsLiveGateway::class)->send('2348031234567', 'Your code is 123456.');

    expect($sent)->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://api.bulksmslive.com/v2/app/sms'
        && $request['email'] === 'ops@jolaxpay.com'
        && $request['password'] === 'secret'
        && $request['recipients'] === '2348031234567'); // already international, passed through
});

it('returns false and logs when BulkSMSLive rejects the message', function () {
    config(['notify.sms.api_key' => 'bsl_test_key']);
    Log::spy();

    Http::fake(['api.bulksmslive.com/*' => Http::response(['status' => -5, 'msg' => 'Invalid Message content'], 200)]);

    $sent = app(BulkSmsLiveGateway::class)->send('08031234567', '');

    expect($sent)->toBeFalse();
    Log::shouldHaveReceived('error')->with('BulkSMSLive rejected the message', Mockery::any())->once();
});

it('returns false without calling out when the phone number cannot be normalized', function () {
    config(['notify.sms.api_key' => 'bsl_test_key']);
    Http::fake();

    $sent = app(BulkSmsLiveGateway::class)->send('', 'Your code is 123456.');

    expect($sent)->toBeFalse();
    Http::assertNothingSent();
});

it('returns false when the HTTP call itself fails', function () {
    config(['notify.sms.api_key' => 'bsl_test_key']);
    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(app(BulkSmsLiveGateway::class)->send('08031234567', 'Your code is 123456.'))->toBeFalse();
});

it('warns when the account balance comes back low after a successful send', function () {
    config(['notify.sms.api_key' => 'bsl_test_key']);
    Log::spy();

    Http::fake(['api.bulksmslive.com/*' => Http::response(['status' => 1, 'msgid' => 'x', 'balance' => 5], 200)]);

    app(BulkSmsLiveGateway::class)->send('08031234567', 'Your code is 123456.');

    Log::shouldHaveReceived('warning')->with('BulkSMSLive account balance is low — OTP delivery will start failing once it runs out.', Mockery::any())->once();
});
