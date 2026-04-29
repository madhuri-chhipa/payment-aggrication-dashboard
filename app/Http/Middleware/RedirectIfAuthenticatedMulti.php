<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticatedMulti
{
    public function handle(Request $request, Closure $next)
    {
        // Admin logged in
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        // User logged in
        if (Auth::guard('user')->check()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
