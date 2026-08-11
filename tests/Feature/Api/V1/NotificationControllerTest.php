<?php

use App\Models\NotificationLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('excludes otp_* logs from the in-app notification feed', function () {
    NotificationLog::create(['user_id' => $this->user->id, 'type' => 'wallet_funded', 'channel' => 'in_app', 'payload' => ['amount' => '500'], 'status' => 'sent']);
    NotificationLog::create(['user_id' => $this->user->id, 'type' => 'otp_new_device_login', 'channel' => 'sms', 'payload' => ['code' => '123456'], 'status' => 'sent']);

    $response = $this->getJson('/api/v1/notifications');

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'wallet_funded');
});

it('does not count otp_* logs toward the unread badge', function () {
    NotificationLog::create(['user_id' => $this->user->id, 'type' => 'otp_new_device_login', 'channel' => 'sms', 'payload' => ['code' => '123456'], 'status' => 'sent']);

    $this->getJson('/api/v1/notifications')->assertJsonPath('meta.unread_count', 0);
});
