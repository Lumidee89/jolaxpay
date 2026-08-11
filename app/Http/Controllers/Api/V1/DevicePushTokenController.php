<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notifications\NotificationDispatcher;
use App\Enums\DeliveryChannel;
use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevicePushTokenController extends Controller
{
    public function __construct(private readonly NotificationDispatcher $notifier) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255', 'regex:/^(ExponentPushToken|ExpoPushToken)\\[[^\]]+\]$/'],
            'platform' => ['nullable', 'in:ios,android'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $token = DevicePushToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'] ?? null, 'device_name' => $data['device_name'] ?? null],
        );

        // This is the first point at which the backend knows where to send a
        // push notification on a newly authenticated device.
        $this->notifier->send($request->user(), 'login_success', DeliveryChannel::InApp, [
            'title' => 'Welcome back to JolaxPay',
            'body' => 'You have successfully signed in on this device.',
        ]);

        return response()->json(['data' => ['id' => $token->id]]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);

        $request->user()->devicePushTokens()->where('token', $data['token'])->delete();

        return response()->json(['message' => 'Push token removed.']);
    }
}
