<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProxyAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $expected = config('proxy.secret');
        $provided = $request->header('X-Proxy-Secret');

        if (! is_string($expected) || $expected === '' || ! is_string($provided) || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
