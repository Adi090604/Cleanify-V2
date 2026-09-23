<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\DuplicateUserReportException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserReportSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserReportController extends Controller
{
    /**
     * Store a new user report.
     */
    public function store(
        Request $request,
        User $user,
        UserReportSubmissionService $submissionService
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', Rule::in(['spam', 'harassment', 'inappropriate_content', 'fake_account', 'other'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'report_id' => [
                'nullable',
                'integer',
                Rule::exists('reports', 'id')->where(fn ($query) => $query->where('user_id', $user->id)),
            ],
        ]);

        $reporter = $request->user();
        $reportedUser = $user;

        // Prevent self-reporting
        if ($reporter->id === $reportedUser->id) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'You cannot report yourself.'], 422);
            }

            return back()->withErrors(['error' => 'You cannot report yourself.']);
        }

        try {
            $submissionService->submit($reporter, $reportedUser, $validated);
        } catch (DuplicateUserReportException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $exception->getMessage(),
                    'status' => $exception->userReport->status,
                ], 422);
            }

            return back()->withErrors(['error' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'User reported successfully. Our team will review this report.',
            ]);
        }

        return back()->with('success', 'User reported successfully. Our team will review this report.');
    }
}
