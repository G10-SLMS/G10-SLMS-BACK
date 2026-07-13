<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $allowed = false;

        for ($i = 0; $i < count($roles); $i++) {
            if ($user->role === $roles[$i]) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden. Insufficient role.'], 403);
        }

        return $next($request);
    }
     public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!in_array($request->user()->role, $roles)) {
            return response()->json(['message' => 'Unauthorized. Insufficient role.'], 403);
        }

        return $next($request);
    }
}
