<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DuplicateUserReportException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserReportSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserReportController extends Controller
{
    public function store(
        Request $request,
        User $user,
        UserReportSubmissionService $submissionService
    ): JsonResponse {
        $reporter = $request->user();

        if ($reporter->isBanned()) {
            return response()->json([
                'message' => 'Your account has been banned. Please contact an administrator.',
            ], 403);
        }

        if ($reporter->isAdmin()) {
            return response()->json([
                'message' => 'Admin accounts can only sign in through the web Admin Portal.',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => ['required', Rule::in(['spam', 'harassment', 'inappropriate_content', 'fake_account', 'other'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'report_id' => [
                'nullable',
                'integer',
                Rule::exists('reports', 'id')->where(fn ($query) => $query->where('user_id', $user->id)),
            ],
        ]);

        if ($reporter->id === $user->id) {
            return response()->json([
                'message' => 'You cannot report yourself.',
            ], 422);
        }

        try {
            $submissionService->submit($reporter, $user, $validated);
        } catch (DuplicateUserReportException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'status' => $exception->userReport->status,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'User reported successfully. Our team will review this report.',
        ]);
    }
}
