<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReportSubmissionAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $communityRestriction = $request->user()?->activeCommunityRestriction();

        if ($communityRestriction) {
            $expiration = $communityRestriction->expires_at?->format('M j, Y g:i A') ?? 'further notice';
            $message = 'Your Community Reports access is temporarily read-only until '.$expiration.'. You can still view reports and use other Cleanify services.';

            if ($request->expectsJson() || $request->is('api/*')) {
                return new JsonResponse(['message' => $message], 403);
            }

            return back()->with('error', $message);
        }

        $restriction = $request->user()?->activeReportSubmissionRestriction();

        if (! $restriction) {
            return $next($request);
        }

        $expiration = $restriction->expires_at?->format('M j, Y g:i A') ?? 'further notice';
        $message = 'New cleanliness report submission is temporarily restricted until '.$expiration.'.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return new JsonResponse(['message' => $message], 403);
        }

        return back()->with('error', $message);
    }
}
