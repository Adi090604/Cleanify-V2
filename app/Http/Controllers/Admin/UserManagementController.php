<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\ModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = User::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Pagination
        $users = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        // Statistics
        $totalUsers = User::count();
        $totalAdmins = User::where('is_admin', true)->count();
        $totalRegular = User::where('is_admin', false)->count();
        $bannedUsers = User::whereNotNull('banned_at')->count();

        return view('admin.users', [
            'activePage' => 'users',
            'users' => $users,
            'totalUsers' => $totalUsers,
            'totalAdmins' => $totalAdmins,
            'totalRegular' => $totalRegular,
            'bannedUsers' => $bannedUsers,
            'search' => $request->search ?? '',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Prevent admin from removing their own admin status
        if (auth()->id() == $user->id && ! $request->is_admin && $user->is_admin) {
            return back()->withErrors(['is_admin' => 'You cannot remove your own admin privileges.']);
        }

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
        ];

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'is_admin' => $request->is_admin ?? false,
        ]);

        // Log activity
        $changes = array_diff_assoc($user->getAttributes(), $oldData);
        if (! empty($changes)) {
            ActivityLogService::logUserUpdated($user, $changes);
        }

        return redirect()->route('admin.users')
            ->with('success', 'User updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Prevent admin from deleting themselves
        if (auth()->id() == $user->id) {
            return back()->withErrors(['delete' => 'You cannot delete your own account.']);
        }

        // Log activity before deletion
        ActivityLogService::logUserDeleted($user);

        $user->delete();

        return redirect()->route('admin.users')
            ->with('success', 'User deleted successfully!');
    }

    /**
     * Ban a user.
     */
    public function ban(string $id, ModerationService $moderation): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Prevent admin from banning themselves
        if (auth()->id() == $user->id) {
            return back()->withErrors(['ban' => 'You cannot suspend your own account.']);
        }

        // Prevent banning other admins
        if ($user->isAdmin()) {
            return back()->withErrors(['ban' => 'Cannot suspend administrator accounts.']);
        }

        $moderation->suspendUser($user, request()->user());

        // Log activity
        ActivityLogService::logUserBanned($user);

        return redirect()->route('admin.users')
            ->with('success', 'User has been suspended successfully!');
    }

    /**
     * Unban a user.
     */
    public function unban(string $id, ModerationService $moderation): RedirectResponse
    {
        $user = User::findOrFail($id);

        $moderation->unsuspendUser($user, request()->user());

        // Log activity
        ActivityLogService::logUserUnbanned($user);

        return redirect()->route('admin.users')
            ->with('success', 'User has been unsuspended successfully!');
    }
}
