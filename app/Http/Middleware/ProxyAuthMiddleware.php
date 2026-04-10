<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProxyAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->header('X-Proxy-Secret') !== env('PROXY_SECRET')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}
