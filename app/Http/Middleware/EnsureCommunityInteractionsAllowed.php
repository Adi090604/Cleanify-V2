<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCommunityInteractionsAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $restriction = $request->user()?->activeCommunityRestriction();

        if (! $restriction) {
            return $next($request);
        }

        $expiration = $restriction->expires_at?->format('M j, Y g:i A') ?? 'further notice';

        return new JsonResponse([
            'message' => 'Your Community Reports access is temporarily read-only until '.$expiration.'. You can still view reports and use other Cleanify services.',
        ], 403);
    }
}
