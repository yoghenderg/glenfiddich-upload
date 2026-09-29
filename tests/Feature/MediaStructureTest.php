<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_keeps_metadata_and_survives_uploader_deletion(): void
    {
        $user = User::factory()->create(['is_staff' => true]);
        $media = $user->media()->create([
            'original_filename' => 'photo.jpg',
            'path' => 'media/photo.jpg',
            'type' => 'image',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'captured_at' => '2026-09-29 12:00:00',
        ])->fresh();
        $this->assertTrue(Str::isUuid($media->public_id));
        $this->assertSame($media->public_id, $media->getRouteKey());
        $this->assertTrue($media->uploader->is($user));
        $this->assertSame(1024, $media->size_bytes);
        $this->assertSame('local', $media->disk);
        $this->assertSame('2026-09-29', $media->captured_at->toDateString());
        $user->delete();
        $this->assertNull($media->fresh()->uploaded_by);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }
}
