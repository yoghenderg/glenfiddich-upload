<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    private function start(string $bytes, string $filename = 'photo.png'): string
    {
        return $this->postJson('/uploads', ['filename' => $filename, 'size' => strlen($bytes), 'idempotency_key' => 'test-upload-key-1234'])
            ->assertOk()->json('id');
    }

    private function chunk(string $id, string $bytes, int $index = 0)
    {
        return $this->post('/uploads/'.$id.'/chunks/'.$index, ['chunk' => UploadedFile::fake()->createWithContent('chunk.bin', $bytes)], ['Accept' => 'application/json']);
    }

    public function test_upload_retry_gallery_guest_and_download(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_staff' => true]));
        $image = UploadedFile::fake()->image('photo.png');
        $bytes = file_get_contents($image->getRealPath());
        $id = $this->start($bytes);
        $this->assertSame($id, $this->start($bytes));
        $this->chunk($id, $bytes)->assertOk()->assertJsonPath('next_index', 1);
        $this->chunk($id, $bytes)->assertOk()->assertJsonPath('next_index', 1);
        $this->postJson('/uploads/'.$id.'/complete')->assertOk()->assertJsonPath('complete', true);
        $this->postJson('/uploads/'.$id.'/complete')->assertOk()->assertJsonPath('complete', true);
        $this->assertDatabaseCount('media', 1);
        $media = Media::firstOrFail();
        $this->assertSame($bytes, Storage::disk('local')->get($media->path));
        $this->assertFalse(Storage::disk('local')->exists('uploads/'.$id.'/0'));
        $this->getJson('/gallery/feed')->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $media->public_id);
        $this->withoutExceptionHandling();
        $this->get('/gallery')->assertOk()->assertSee($media->public_id);
        $this->post('/logout');
        $this->get('/media/'.$media->public_id)->assertOk()->assertSee('Download photo');
        $this->get('/media/'.$media->public_id.'/file')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/media/'.$media->public_id.'/download')->assertDownload('photo.png');
    }

    public function test_fake_image_is_rejected_and_not_published(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_staff' => true]));
        $id = $this->start('not an image');
        $this->chunk($id, 'not an image')->assertOk();
        $this->postJson('/uploads/'.$id.'/complete')->assertUnprocessable();
        $this->assertDatabaseCount('media', 0);
        Storage::disk('local')->assertMissing('media/'.$id);
    }

    public function test_access_incomplete_upload_and_cancellation(): void
    {
        Storage::fake('local');
        $this->postJson('/uploads', [])->assertUnauthorized();
        $owner = User::factory()->create(['is_staff' => true]);
        $this->actingAs($owner);
        $id = $this->start('abc');
        $this->postJson('/uploads/'.$id.'/complete')->assertStatus(409);
        $this->chunk($id, 'ab')->assertUnprocessable();
        $this->chunk($id, 'abc', 1)->assertStatus(409);
        $this->actingAs(User::factory()->create(['is_staff' => true]));
        $this->getJson('/uploads/'.$id)->assertNotFound();
        $this->deleteJson('/uploads/'.$id)->assertNotFound();
        $this->actingAs($owner);
        $this->chunk($id, 'abc')->assertOk();
        $this->deleteJson('/uploads/'.$id)->assertOk();
        Storage::disk('local')->assertMissing('uploads/'.$id.'/0');
        $this->postJson('/uploads/'.$id.'/complete')->assertStatus(410);
    }

    public function test_multiple_chunks_and_size_limit(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_staff' => true]));
        $this->postJson('/uploads', ['filename'=>'photo.png', 'size'=>209715201, 'idempotency_key'=>'test-upload-key-1234'])->assertUnprocessable();
        $image = UploadedFile::fake()->image('photo.png');
        $bytes = file_get_contents($image->getRealPath()).str_repeat('x', 1048576);
        $id = $this->start($bytes);
        $this->chunk($id, substr($bytes, 0, 1048576))->assertOk();
        $this->getJson('/uploads/'.$id)->assertJsonPath('next_index', 1);
        $this->chunk($id, substr($bytes, 1048576), 1)->assertOk();
        $this->postJson('/uploads/'.$id.'/complete')->assertOk();
        $this->assertSame($bytes, Storage::disk('local')->get(Media::firstOrFail()->path));
    }
}
