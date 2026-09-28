<?php

namespace App\Http\Controllers;

use App\Support\DemoMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return view('pages.gallery', ['items' => $state === 'empty' ? [] : DemoMedia::sorted($sort), 'state' => $state, 'sort' => $sort]);
    }

    public function feed(Request $request): JsonResponse
    {
        $sort = $request->query('sort', 'none');
        if (! in_array($sort, ['none', 'today', 'yesterday'], true)) {
            $sort = 'none';
        }

        return response()->json(['items' => DemoMedia::sorted($sort), 'updated_at' => now()->toIso8601String()]);
    }

    public function guest(string $id): View
    {
        $item = DemoMedia::find($id);
        abort_if($item === null, 404);

        return view('pages.guest', ['item' => $item]);
    }
}
