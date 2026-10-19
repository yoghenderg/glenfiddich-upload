<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\UploadSession;
use App\Support\MediaThumbnail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class DemoMediaController extends Controller
{
    public function upload(Request $request): View
    {
        return $this->gallery($request);
    }

    public function gallery(Request $request): View
    {
        $state = $request->query('state', 'ready');
        if (! in_array($state, ['ready', 'empty', 'loading', 'error', 'reconnecting'], true)) {
            $state = 'ready';
        }

        $sort = $request->query('sort', 'none');
        if (! in_array($sort, ['none', 'today', 'yesterday'], true)) {
            $sort = 'none';
        }

        return view('pages.moments', $this->galleryData($request, $sort, $state === 'empty') + [
            'state' => $state,
            'sort' => $sort,
            'activeView' => $request->routeIs('upload') ? 'upload' : 'gallery',
        ]);
    }

    public function feed(Request $request)
    {
        $sort = $request->query('sort', 'none');

        $data = $this->galleryData($request, is_string($sort) ? $sort : 'none');
        $etag = '"'.$data['version'].'"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'private, no-cache'];
        if (in_array($etag, array_map('trim', explode(',', $request->header('If-None-Match', ''))), true)) {
            return response('', 304, $headers);
        }

        return response()->json([
            ...$data,
            'html' => view('partials.gallery-results', $data + ['state' => 'ready'])->render(),
            'updated_at' => now()->toIso8601String(),
        ], 200, $headers);
    }

    private function galleryData(Request $request, string $sort, bool $empty = false): array
    {
        $query = Media::latest('created_at')->orderByDesc('id');
        if (in_array($sort, ['today', 'yesterday'], true)) {
            $day = $sort === 'today' ? today() : today()->subDay();
            $query->whereBetween('created_at', [$day, $day->copy()->endOfDay()]);
        }

        $size = $request->integer('per_page', 16);
        $size = in_array($size, [8, 16, 24, 32], true) ? $size : 16;
        $total = $empty ? 0 : (clone $query)->count();
        $pages = max(1, (int) ceil($total / $size));
        $page = max(1, min($pages, $request->integer('page', 1)));
        $items = $total ? $query->forPage($page, $size)->get()->map(fn (Media $media) => $media->galleryItem())->all() : [];
        $pagination = ['page' => $page, 'pages' => $pages, 'per_page' => $size, 'total' => $total];

        return ['items' => $items, 'pagination' => $pagination, 'version' => hash('sha256', json_encode([$items, $pagination]))];
    }

    public function guest(string $id): View
    {
        $media = Media::where('public_id', $id)->firstOrFail();

        return view('pages.guest', ['item' => $media->galleryItem()]);
    }

    public function file(Request $request, Media $media)
    {
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        $response = response()->file(Storage::disk($media->disk)->path($media->path), [
            'Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
        $response->setAutoLastModified();
        $response->isNotModified($request);
        return $response;
    }

    public function thumbnail(Request $request, Media $media)
    {
        abort_unless($media->type === 'image', 404);
        try {
            $path = MediaThumbnail::generate($media);
        } catch (\Throwable $error) {
            report($error);
            $path = null;
        }
        if (! $path) {
            return $this->file($request, $media);
        }
        $response = response()->file(Storage::disk($media->disk)->path($path), [
            'Content-Type' => 'image/jpeg', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
        $response->setAutoLastModified();
        $response->isNotModified($request);
        return $response;
    }

    public function download(Media $media)
    {
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->download($media->path, $media->original_filename, [
            'Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Media $media)
    {
        DB::transaction(function () use ($media): void {
            // Keep completed upload keys cancelled so retries cannot recreate deleted media.
            UploadSession::where('media_id', $media->id)->update(['media_id' => null, 'cancelled' => true]);
            $media->delete();
            $paths = array_values(array_unique(array_filter([$media->path, $media->poster_path, MediaThumbnail::path($media)])));
            abort_unless(Storage::disk($media->disk)->delete($paths), 500, 'Could not delete the media file.');
        });

        return response()->noContent();
    }
}
