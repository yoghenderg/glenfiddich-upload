<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Support\MediaThumbnail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GalleryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function media(User $user, array $attributes = []): Media
    {
        return $user->media()->create($attributes + [
            'original_filename' => 'photo.jpg', 'disk' => 'local',
            'path' => 'media/'.Str::uuid(), 'type' => 'image',
            'mime_type' => 'image/jpeg', 'size_bytes' => 1024,
        ]);
    }

    public function test_gallery_returns_bounded_pages_with_no_overlaps(): void
    {
        $user = User::factory()->create(['is_staff' => true]);
        for ($i = 0; $i < 39; $i++) {
            $this->media($user);
        }
        $this->actingAs($user);
        $seen = [];
        foreach ([1 => 16, 2 => 16, 3 => 7] as $page => $count) {
            $response = $this->getJson('/gallery/feed?page='.$page)
                ->assertOk()->assertJsonCount($count, 'items')
                ->assertJsonPath('pagination.total', 39)
                ->assertJsonPath('pagination.page', $page);
            $this->assertSame($count, substr_count($response->json('html'), 'data-media-card='));
            $seen = [...$seen, ...array_column($response->json('items'), 'id')];
        }
        $this->assertCount(39, array_unique($seen));
        $this->get('/gallery')->assertViewHas('items', fn ($items) => count($items) === 16);
        $this->getJson('/gallery/feed?per_page=999&page=999')->assertJsonCount(7, 'items')->assertJsonPath('pagination.page', 3)->assertJsonPath('pagination.per_page', 16);
        $this->getJson('/gallery/feed?per_page=8')->assertJsonCount(8, 'items')->assertJsonPath('pagination.pages', 5);
    }

    public function test_unchanged_feed_has_no_body_and_changes_invalidate_the_etag(): void
    {
        $user = User::factory()->create(['is_staff' => true]);
        $media = $this->media($user);
        $this->actingAs($user);
        $etag = $this->getJson('/gallery/feed')->assertOk()->headers->get('ETag');
        $this->withHeader('If-None-Match', $etag)->getJson('/gallery/feed')->assertStatus(304)->assertContent('');
        $media->update(['title' => 'Updated title']);
        $this->getJson('/gallery/feed')->assertOk()->assertJsonPath('items.0.title', 'Updated title');
        $etag = $this->getJson('/gallery/feed')->headers->get('ETag');
        $media->delete();
        $this->withHeader('If-None-Match', $etag)->getJson('/gallery/feed')->assertOk()->assertJsonPath('pagination.total', 0);
    }

    public function test_small_cached_previews_preserve_the_original_and_are_deleted_with_media(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['is_staff' => true]);
        $file = UploadedFile::fake()->image('photo.jpg', 2400, 1600);
        $bytes = file_get_contents($file->getRealPath());
        $media = $this->media($user, ['size_bytes' => strlen($bytes)]);
        Storage::disk('local')->put($media->path, $bytes);
        $this->get(route('media.thumbnail', $media))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $path = MediaThumbnail::path($media);
        Storage::disk('local')->assertExists($path);
        [$width, $height] = getimagesize(Storage::disk('local')->path($path));
        $this->assertSame(800, $width);
        $this->assertLessThanOrEqual(800, $height);
        $this->assertSame($bytes, Storage::disk('local')->get($media->path));
        $this->get(route('media.file', $media))->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('/thumbnail', $media->galleryItem()['thumbnail']);
        $response = $this->get(route('media.thumbnail', $media));
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
        $this->withHeader('If-Modified-Since', $response->headers->get('Last-Modified'))
            ->get(route('media.thumbnail', $media))->assertStatus(304);
        $this->actingAs($user)->deleteJson(route('media.destroy', $media))->assertNoContent();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_filters_paginate_the_filtered_total_and_clamp_after_deletion(): void
    {
        $user = User::factory()->create(['is_staff' => true]);
        for ($i = 0; $i < 9; $i++) {
            $this->media($user)->forceFill(['created_at' => today()->subDay()])->save();
        }
        $this->media($user);
        $this->actingAs($user)->getJson('/gallery/feed?sort=yesterday&per_page=8&page=2')
            ->assertJsonCount(1, 'items')->assertJsonPath('pagination.total', 9);
        Media::whereDate('created_at', today()->subDay())->first()->delete();
        $this->getJson('/gallery/feed?sort=yesterday&per_page=8&page=2')
            ->assertJsonCount(8, 'items')->assertJsonPath('pagination.page', 1);
        $this->getJson('/gallery/feed?sort=today')->assertJsonCount(1, 'items')->assertJsonPath('pagination.total', 1);
    }
}
