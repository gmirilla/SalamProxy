<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuditLogAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (
            $request->getUser() !== env('AUDIT_LOG_USERNAME')
            || $request->getPassword() !== env('AUDIT_LOG_PASSWORD')
        ) {
            return response('Unauthorized', 401)
                ->header('WWW-Authenticate', 'Basic realm="Elite Update Log"');
        }

        return $next($request);
    }
}
