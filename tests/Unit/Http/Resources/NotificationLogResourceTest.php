<?php

use App\Enums\DeliveryChannel;
use App\Http\Resources\NotificationLogResource;
use App\Models\NotificationLog;
use App\Models\User;

/**
 * Regression: `token_delivery`/`payment_confirmation` wording used to be
 * hardcoded for electricity ("your electricity token is ready") regardless
 * of what was actually purchased — an airtime/data/cable_tv/education
 * delivery notification said the same thing. TransactionService now rides
 * `service_type` along on the payload; this resource renders from it.
 */
function makeLog(string $type, array $payload): NotificationLog
{
    return NotificationLog::create([
        'user_id' => User::factory()->create()->id,
        'type' => $type,
        'channel' => DeliveryChannel::InApp,
        'payload' => $payload,
        'status' => 'sent',
    ]);
}

it('describes an airtime delivery as a recharge, not a token', function () {
    $log = makeLog('token_delivery', ['service_type' => 'airtime']);

    $resource = NotificationLogResource::make($log)->toArray(request());

    expect($resource['title'])->toBe('Airtime delivered')
        ->and($resource['body'])->toBe('Your airtime recharge is complete.')
        ->and($resource['body'])->not->toContain('electricity');
});

it('describes an education delivery as a pin', function () {
    $log = makeLog('token_delivery', ['service_type' => 'education']);

    $resource = NotificationLogResource::make($log)->toArray(request());

    expect($resource['title'])->toBe('Pin delivered')
        ->and($resource['body'])->toContain('exam pin');
});

it('still describes an electricity delivery as a token', function () {
    $log = makeLog('token_delivery', ['service_type' => 'electricity']);

    $resource = NotificationLogResource::make($log)->toArray(request());

    expect($resource['title'])->toBe('Token delivered')
        ->and($resource['body'])->toContain('electricity token');
});

it('defaults to electricity wording for a legacy log with no service_type', function () {
    $log = makeLog('token_delivery', []);

    $resource = NotificationLogResource::make($log)->toArray(request());

    expect($resource['title'])->toBe('Token delivered');
});

it('mentions the right service in the payment_confirmation body', function () {
    $log = makeLog('payment_confirmation', ['service_type' => 'cable_tv']);

    $resource = NotificationLogResource::make($log)->toArray(request());

    expect($resource['body'])->toContain('TV subscription');
});
