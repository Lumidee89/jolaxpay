<?php

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->staff = User::factory()->create();
    $this->staff->assignRole('support');
});

it('allows authorized staff to manage announcements', function () {
    $this->actingAs($this->staff)->post(route('admin.announcements.store'), [
        'title' => 'Welcome', 'body' => 'Our latest update.', 'is_published' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $announcement = Announcement::firstOrFail();
    $this->get(route('admin.announcements.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Announcements/Index')->has('announcements.data', 1));
    $this->patch(route('admin.announcements.update', $announcement), [
        'title' => 'Updated title', 'body' => 'Updated message', 'is_published' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($announcement->fresh()->is_published)->toBeFalse();
    expect($announcement->fresh()->title)->toBe('Updated title');
    $this->delete(route('admin.announcements.destroy', $announcement))->assertRedirect();
    expect(Announcement::count())->toBe(0);
});

it('denies unauthorized staff every announcement management action', function () {
    $ops = User::factory()->create();
    $ops->assignRole('ops');
    $announcement = Announcement::create(['title' => 'News', 'body' => 'Message', 'is_published' => false]);
    $this->actingAs($ops)->get(route('admin.announcements.index'))->assertForbidden();
    $this->post(route('admin.announcements.store'), [])->assertForbidden();
    $this->patch(route('admin.announcements.update', $announcement), [])->assertForbidden();
    $this->delete(route('admin.announcements.destroy', $announcement))->assertForbidden();
});

it('validates announcement content before saving', function () {
    $this->actingAs($this->staff)->post(route('admin.announcements.store'), [
        'title' => str_repeat('a', 161), 'body' => '', 'is_published' => 'invalid',
    ])->assertSessionHasErrors(['title', 'body', 'is_published']);
    expect(Announcement::count())->toBe(0);
});

it('only exposes published announcements to authenticated mobile users newest first', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $older = Announcement::create(['title' => 'Older', 'body' => 'First', 'is_published' => true]);
    Announcement::create(['title' => 'Draft', 'body' => 'Private', 'is_published' => false]);
    Announcement::create(['title' => 'Newer', 'body' => 'Latest', 'is_published' => true]);
    $this->getJson('/api/v1/announcements')->assertUnauthorized();
    $this->withToken($token)->getJson('/api/v1/announcements')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Newer')->assertJsonPath('data.1.title', 'Older');
    $older->update(['is_published' => false]);
    $this->withToken($token)->getJson('/api/v1/announcements')->assertJsonCount(1, 'data');
    Announcement::query()->delete();
    $this->withToken($token)->getJson('/api/v1/announcements')->assertOk()->assertExactJson(['data' => []]);
});
