<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoryteller
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isStoryteller()) {
            abort(403, 'Только рассказчик.');
        }

        return $next($request);
    }
}
