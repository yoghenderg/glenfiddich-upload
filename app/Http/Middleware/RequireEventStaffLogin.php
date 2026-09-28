<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireEventStaffLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) $request->session()->get('event_staff.authenticated', false)) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
