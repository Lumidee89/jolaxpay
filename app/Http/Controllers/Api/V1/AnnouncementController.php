<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Announcement::where('is_published', true)
            ->latest('id')->get(['id', 'title', 'body', 'created_at'])]);
    }
}
