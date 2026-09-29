<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\UploadSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    private const CHUNK = 1048576;

    public function create(Request $request)
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:209715200'],
            'idempotency_key' => ['required', 'string', 'regex:/^[a-zA-Z0-9-]{16,64}$/'],
        ]);
        abort_unless(in_array(strtolower(pathinfo($data['filename'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov']), 422, 'Unsupported file extension.');
        $session = UploadSession::firstOrCreate(['user_id' => $request->user()->id, 'idempotency_key' => $data['idempotency_key']], ['id' => (string) Str::uuid(), 'filename' => basename(str_replace('\\', '/', $data['filename'])), 'size' => $data['size']]);
        abort_unless($session->size === (int) $data['size'] && $session->filename === basename(str_replace('\\', '/', $data['filename'])), 409, 'Upload key belongs to a different file.');
        abort_if($session->cancelled, 410, 'Upload was cancelled. Choose the file again.');

        return response()->json(['id' => $session->id, 'chunk_size' => self::CHUNK]);
    }

    private function locked(Request $request, string $id, callable $action)
    {
        return DB::transaction(function () use ($request, $id, $action) {
            $session = UploadSession::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($id);
            abort_if($session->cancelled, 410, 'Upload was cancelled.');

            return $action($session);
        });
    }

    private function state(UploadSession $session): array
    {
        $media = $session->media_id ? Media::findOrFail($session->media_id) : null;

        return ['next_index' => $session->next_index, 'complete' => (bool) $media, 'url' => $media ? route('media.guest', $media->public_id) : null];
    }

    public function status(Request $request, string $id)
    {
        return $this->locked($request, $id, fn ($s) => response()->json($this->state($s)));
    }

    public function chunk(Request $request, string $id, int $index)
    {
        $request->validate(['chunk' => ['required', 'file', 'max:1024']]);

        return $this->locked($request, $id, function ($s) use ($request, $index) {
            abort_if($s->media_id, 409, 'Upload is already complete.');
            abort_unless($index >= 0 && $index <= $s->next_index && $index < ceil($s->size / self::CHUNK), 409, 'Unexpected chunk index.');
            $expected = min(self::CHUNK, $s->size - $index * self::CHUNK);
            abort_unless($request->file('chunk')->getSize() === $expected, 422, 'Incorrect chunk size.');
            if ($index === $s->next_index) {
                $stored = $request->file('chunk')->storeAs('uploads/'.$s->id, (string) $index, 'local');
                abort_unless($stored, 500, 'Could not store upload chunk.');
                $s->increment('next_index');
            } else {
                $existing = Storage::disk('local')->path('uploads/'.$s->id.'/'.$index);
                abort_unless(is_file($existing) && hash_equals(hash_file('sha256', $existing), hash_file('sha256', $request->file('chunk')->getRealPath())), 409, 'Chunk differs from the accepted data.');
            }

            return response()->json(['next_index' => $index + 1]);
        });
    }

    public function complete(Request $request, string $id)
    {
        return $this->locked($request, $id, function ($s) use ($request) {
            if ($s->media_id) {
                return response()->json($this->state($s));
            }
            abort_unless($s->next_index === (int) ceil($s->size / self::CHUNK), 409, 'Upload is incomplete.');
            $disk = Storage::disk('local');
            $path = 'media/'.$s->id;
            $disk->makeDirectory('media');
            $out = fopen($disk->path($path), 'wb');
            abort_unless($out, 500, 'Could not assemble upload.');
            try {
                for ($i = 0; $i < $s->next_index; $i++) {
                    $in = $disk->readStream('uploads/'.$s->id.'/'.$i);
                    if (! is_resource($in)) {
                        throw new \RuntimeException('Missing upload chunk.');
                    }
                    try {
                        if (stream_copy_to_stream($in, $out) === false) {
                            throw new \RuntimeException('Could not assemble upload.');
                        }
                    } finally {
                        fclose($in);
                    }
                }
            } finally {
                fclose($out);
            }
            try {
                abort_unless($disk->size($path) === $s->size, 422, 'File size mismatch.');
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($disk->path($path));
                $extensions = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp'], 'video/mp4' => ['mp4'], 'video/quicktime' => ['mov']];
                abort_unless(isset($extensions[$mime]) && in_array(strtolower(pathinfo($s->filename, PATHINFO_EXTENSION)), $extensions[$mime]), 422, 'File contents do not match a supported image or video.');
                $image = str_starts_with($mime, 'image/');
                $dimensions = $image ? @getimagesize($disk->path($path)) : null;
                abort_if($image && ! $dimensions, 422, 'Invalid image.');
                $media = $request->user()->media()->create(['original_filename' => $s->filename, 'path' => $path, 'disk' => 'local', 'type' => $image ? 'image' : 'video', 'mime_type' => $mime, 'size_bytes' => $s->size, 'width' => $dimensions[0] ?? null, 'height' => $dimensions[1] ?? null, 'captured_at' => now()]);
                $s->media_id = $media->id;
                $s->save();
            } catch (\Throwable $e) {
                $disk->delete($path);
                throw $e;
            }
            $disk->deleteDirectory('uploads/'.$s->id);

            return response()->json($this->state($s));
        });
    }

    public function cancel(Request $request, string $id)
    {
        return $this->locked($request, $id, function ($s) {
            abort_if($s->media_id, 409, 'Upload is already complete.');
            $s->cancelled = true;
            $s->save();
            Storage::disk('local')->deleteDirectory('uploads/'.$s->id);

            return response()->json(['cancelled' => true]);
        });
    }
}
