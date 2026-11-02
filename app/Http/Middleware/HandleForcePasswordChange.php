<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->force_password_change && ! $request->is('profile', 'profile/*', 'logout')) {
            return redirect('/profile');
        }

        return $next($request);
    }
}
