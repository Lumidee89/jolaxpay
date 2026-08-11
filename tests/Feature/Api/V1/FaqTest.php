<?php

use App\Models\FaqArticle;

it('lists published FAQ articles without authentication', function () {
    FaqArticle::create(['category' => 'Getting Started', 'question' => 'Q1?', 'answer' => 'A1', 'sort_order' => 0, 'is_published' => true]);
    FaqArticle::create(['category' => 'Getting Started', 'question' => 'Q2?', 'answer' => 'A2', 'sort_order' => 1, 'is_published' => false]);

    $response = $this->getJson('/api/v1/faq');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.question'))->toBe('Q1?');
});
