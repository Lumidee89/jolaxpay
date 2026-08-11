<?php

use App\Models\FaqArticle;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->staff = User::factory()->create();
    $this->staff->assignRole('support');
});

it('lets support staff create, update, and delete FAQ articles', function () {
    $this->actingAs($this->staff)->post(route('admin.faq.store'), [
        'category' => 'Wallet & Payments',
        'question' => 'How do I fund my wallet?',
        'answer' => 'Go to Wallet and tap Top Up.',
    ])->assertRedirect();

    $article = FaqArticle::firstOrFail();
    expect($article->question)->toBe('How do I fund my wallet?');

    $this->actingAs($this->staff)->patch(route('admin.faq.update', $article->id), [
        'category' => 'Wallet & Payments',
        'question' => 'How do I fund my wallet?',
        'answer' => 'Updated answer.',
        'is_published' => false,
    ])->assertRedirect();

    expect($article->fresh()->answer)->toBe('Updated answer.')
        ->and($article->fresh()->is_published)->toBeFalse();

    $this->actingAs($this->staff)->delete(route('admin.faq.destroy', $article->id))->assertRedirect();
    expect(FaqArticle::count())->toBe(0);
});

it('blocks a non-support staff member from managing FAQ articles', function () {
    $ops = User::factory()->create();
    $ops->assignRole('ops');

    $this->actingAs($ops)->get(route('admin.faq.index'))->assertForbidden();
});
