<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileResident
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->isBanned()) {
            return new JsonResponse([
                'message' => 'Your account has been banned. Please contact an administrator.',
            ], 403);
        }

        if ($request->user()->isAdmin()) {
            return new JsonResponse([
                'message' => 'Admin accounts can only sign in through the web Admin Portal.',
            ], 403);
        }

        return $next($request);
    }
}
