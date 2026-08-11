<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** PRD §14: per-category notification preferences (Settings). */
class NotificationPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $muted = $request->user()->notificationPreference?->muted_categories ?? [];

        return response()->json([
            'data' => array_map(fn (NotificationCategory $category) => [
                'category' => $category->value,
                'label' => $category->label(),
                'description' => $category->description(),
                'enabled' => ! in_array($category->value, $muted, true),
            ], NotificationCategory::cases()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::enum(NotificationCategory::class)],
            'enabled' => ['required', 'boolean'],
        ]);

        $preference = NotificationPreference::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['muted_categories' => []],
        );

        $muted = collect($preference->muted_categories)->reject(fn ($c) => $c === $data['category'])->values()->all();
        if (! $data['enabled']) {
            $muted[] = $data['category'];
        }
        $preference->update(['muted_categories' => $muted]);

        return response()->json([
            'data' => [
                'category' => $data['category'],
                'enabled' => $data['enabled'],
            ],
        ]);
    }
}
