<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqArticleResource;
use App\Models\FaqArticle;
use Illuminate\Http\JsonResponse;

/**
 * Knowledge base / FAQ (PRD §22). Public, not auth:sanctum-gated — someone
 * locked out of their account (forgotten password, OTP trouble) still
 * needs to be able to search for help before they can sign in.
 */
class FaqController extends Controller
{
    public function index(): JsonResponse
    {
        $articles = FaqArticle::where('is_published', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => FaqArticleResource::collection($articles)]);
    }
}
