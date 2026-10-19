<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeleteMediaTest extends TestCase
{
    use RefreshDatabase;

    private function media(User $user, string $type = 'image'): Media
    {
        return $user->media()->create([
            'original_filename' => $type === 'image' ? 'photo.jpg' : 'video.mp4',
            'disk' => 'local', 'path' => 'media/'.Str::uuid(),
            'poster_path' => 'media/'.Str::uuid(), 'type' => $type,
            'mime_type' => $type === 'image' ? 'image/jpeg' : 'video/mp4',
            'size_bytes' => 1024,
        ]);
    }

    public function test_only_staff_can_delete_media(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['is_staff' => true]);
        $media = $this->media($staff);
        Storage::disk('local')->put($media->path, 'photo');
        $url = route('media.destroy', $media);

        $this->deleteJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_staff' => false]))->deleteJson($url)->assertForbidden();
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('local')->assertExists($media->path);
    }

    public function test_confirmed_deletion_removes_media_and_files_and_cancels_completed_upload(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['is_staff' => true]);
        $media = $this->media($staff);
        $other = $this->media($staff);
        Storage::disk('local')->put($media->path, 'photo');
        Storage::disk('local')->put($media->poster_path, 'poster');
        Storage::disk('local')->put($other->path, 'other photo');
        $session = UploadSession::create([
            'id' => (string) Str::uuid(), 'user_id' => $staff->id,
            'idempotency_key' => 'completed-upload-1234', 'filename' => 'photo.jpg',
            'size' => 1024, 'next_index' => 1, 'media_id' => $media->id,
        ]);

        $this->actingAs($staff)->get('/gallery')->assertOk()
            ->assertSee('data-card-action="delete"', false)->assertSee('Are you sure?');
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        $this->deleteJson(route('media.destroy', $media))->assertNoContent();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('local')->assertMissing($media->path);
        Storage::disk('local')->assertMissing($media->poster_path);
        Storage::disk('local')->assertExists($other->path);
        $this->assertTrue($session->fresh()->cancelled);
        $this->assertNull($session->fresh()->media_id);
        $this->postJson('/uploads/'.$session->id.'/complete')->assertGone();
        $this->getJson('/gallery/feed')->assertJsonCount(1, 'items');
        $this->get('/media/'.$media->public_id)->assertNotFound();
        $this->get('/media/'.$media->public_id.'/file')->assertNotFound();
        $this->deleteJson(route('media.destroy', $media))->assertNotFound();
    }

    public function test_staff_can_delete_video_with_an_already_missing_file(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['is_staff' => true]);
        $media = $this->media($staff, 'video');
        $this->actingAs($staff)->deleteJson(route('media.destroy', $media))->assertNoContent();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }
}
