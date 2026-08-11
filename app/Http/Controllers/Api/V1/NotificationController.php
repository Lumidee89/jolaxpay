<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // otp_* logs exist purely to drive the SMS/log side of OtpService
        // (an audit trail of what was sent, not something to surface) —
        // never meant for this feed. Harmless under SMS_DRIVER=log, but
        // a real driver fires one on every new-device login, which would
        // otherwise clutter the feed with a vague "new account update"
        // entry (NotificationLogResource has no specific copy for it —
        // deliberately: an OTP code has no business being repeated here).
        $notifications = $request->user()->notificationLogs()
            ->where('type', 'not like', 'otp_%')
            // A muted category (NotificationCategory, PRD §14) is skipped
            // at send time — it shouldn't reappear here either.
            ->where('status', '!=', 'skipped')
            ->latest()->limit(30)->get();

        return response()->json([
            'data' => NotificationLogResource::collection($notifications),
            'meta' => ['unread_count' => $notifications->whereNull('read_at')->count()],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $request->user()->notificationLogs()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => ['marked_read' => $count]]);
    }
}
