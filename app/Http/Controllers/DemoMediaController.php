<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

final class DemoMediaController extends Controller
{
    public function upload(): View
    {
        return view('pages.upload');
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

        return view('pages.gallery', ['items' => $state === 'empty' ? [] : $this->items($sort), 'state' => $state, 'sort' => $sort]);
    }

    public function feed(Request $request): JsonResponse
    {
        $sort = $request->query('sort', 'none');

        return response()->json(['items' => $this->items(is_string($sort) ? $sort : 'none'), 'updated_at' => now()->toIso8601String()]);
    }

    private function items(string $sort): array
    {
        $query = Media::latest('created_at')->orderByDesc('id');
        if (in_array($sort, ['today', 'yesterday'], true)) {
            $day = $sort === 'today' ? today() : today()->subDay();
            $query->whereBetween('created_at', [$day, $day->copy()->endOfDay()]);
        }

        return $query->get()->map(fn (Media $media) => $media->galleryItem())->all();
    }

    public function guest(string $id): View
    {
        $media = Media::where('public_id', $id)->firstOrFail();

        return view('pages.guest', ['item' => $media->galleryItem()]);
    }

    public function file(Media $media)
    {
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return response()->file(Storage::disk($media->disk)->path($media->path), [
            'Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(Media $media)
    {
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->download($media->path, $media->original_filename, [
            'Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
