<?php

use App\Domain\Notifications\NotificationDispatcher;
use App\Enums\DeliveryChannel;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('lists all notification categories as enabled by default', function () {
    $response = $this->getJson('/api/v1/notification-preferences');

    $response->assertOk();
    expect(collect($response->json('data'))->every(fn ($row) => $row['enabled'] === true))->toBeTrue()
        ->and($response->json('data'))->toHaveCount(4);
});

it('mutes and unmutes a category', function () {
    $this->patchJson('/api/v1/notification-preferences', ['category' => 'wallet', 'enabled' => false])
        ->assertOk()->assertJsonPath('data.enabled', false);

    $index = $this->getJson('/api/v1/notification-preferences');
    $wallet = collect($index->json('data'))->firstWhere('category', 'wallet');
    expect($wallet['enabled'])->toBeFalse();

    $this->patchJson('/api/v1/notification-preferences', ['category' => 'wallet', 'enabled' => true])
        ->assertOk()->assertJsonPath('data.enabled', true);
});

it('skips delivery (and the in-app feed) for a muted category, but still sends an unmuted one', function () {
    $this->patchJson('/api/v1/notification-preferences', ['category' => 'wallet', 'enabled' => false]);

    $dispatcher = app(NotificationDispatcher::class);
    $mutedLog = $dispatcher->send($this->user, 'wallet_funded', DeliveryChannel::InApp, ['amount' => '100']);
    $sentLog = $dispatcher->send($this->user, 'login_success', DeliveryChannel::InApp, []);

    expect($mutedLog->status)->toBe('skipped')
        ->and($sentLog->status)->toBe('sent');

    $feed = $this->getJson('/api/v1/notifications');
    $types = collect($feed->json('data'))->pluck('type');
    expect($types)->not->toContain('wallet_funded')
        ->and($types)->toContain('login_success');
});

it('never gates an OTP notification, regardless of preferences', function () {
    $this->patchJson('/api/v1/notification-preferences', ['category' => 'security', 'enabled' => false]);

    $dispatcher = app(NotificationDispatcher::class);
    $log = $dispatcher->send($this->user, 'otp_new_device_login', DeliveryChannel::Sms, ['code' => '123456']);

    expect($log->status)->toBe('sent');
});
