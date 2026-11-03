@extends('layouts.admin')

@php
  use Illuminate\Support\Facades\Storage;
  use Illuminate\Support\Str;
@endphp

@section('title', 'User Reports')

@section('content')
  <div class="admin-page-header flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
    <div>
      <h2 class="text-3xl font-bold text-gray-800">
        <i class="fas fa-user-shield text-green-600 mr-3"></i>User Reports
      </h2>
      <p class="text-gray-600 mt-1">Review and manage reports submitted by users about other users</p>
    </div>
    <form method="GET" action="{{ route('admin.user-reports') }}" class="flex w-full lg:w-auto max-w-lg">
      <input 
        id="userReportSearchInput" 
        name="search" 
        type="text" 
        value="{{ $search }}"
        class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
        placeholder="Search by reporter or reported user..."
      >
      <button type="submit" class="px-4 bg-green-600 text-white rounded-r-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-search"></i>
      </button>
      @if($search)
        <a href="{{ route('admin.user-reports') }}" class="ml-2 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-300">
          <i class="fas fa-times"></i>
        </a>
      @endif
    </form>
  </div>

  <div class="admin-stat-grid grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 mb-5">
    <x-admin.stat-card icon="fas fa-flag" title="Total Reports" :value="$totalReports" />
    <x-admin.stat-card icon="fas fa-clock" title="Pending" :value="$pendingReports" borderClass="border-l-4 border-yellow-500" iconWrapperClass="bg-yellow-100" iconColorClass="text-yellow-600" />
    <x-admin.stat-card icon="fas fa-times-circle" title="Dismissed" :value="$dismissedReports" borderClass="border-l-4 border-gray-500" iconWrapperClass="bg-gray-100" iconColorClass="text-gray-600" />
    <x-admin.stat-card icon="fas fa-ban" title="Action Taken" :value="$actionTakenReports" borderClass="border-l-4 border-red-500" iconWrapperClass="bg-red-100" iconColorClass="text-red-600" />
  </div>

  <div class="admin-toolbar-card bg-white rounded-xl shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
    <form method="GET" action="{{ route('admin.user-reports') }}" class="flex items-center gap-3 flex-wrap">
      <input type="hidden" name="search" value="{{ $search }}">
      <label class="text-sm text-gray-600 flex items-center gap-2">
        <span>Status</span>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-green-500" onchange="this.form.submit()">
          <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All statuses</option>
          <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
          <option value="dismissed" {{ $statusFilter === 'dismissed' ? 'selected' : '' }}>Dismissed</option>
          <option value="action_taken" {{ $statusFilter === 'action_taken' ? 'selected' : '' }}>Action Taken</option>
        </select>
      </label>
      <label class="text-sm text-gray-600 flex items-center gap-2">
        <span>Reason</span>
        <select name="reason" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-green-500" onchange="this.form.submit()">
          <option value="all" {{ $reasonFilter === 'all' ? 'selected' : '' }}>All reasons</option>
          <option value="spam" {{ $reasonFilter === 'spam' ? 'selected' : '' }}>Spam</option>
          <option value="harassment" {{ $reasonFilter === 'harassment' ? 'selected' : '' }}>Harassment</option>
          <option value="inappropriate_content" {{ $reasonFilter === 'inappropriate_content' ? 'selected' : '' }}>Inappropriate Content</option>
          <option value="fake_account" {{ $reasonFilter === 'fake_account' ? 'selected' : '' }}>Fake Account</option>
          <option value="other" {{ $reasonFilter === 'other' ? 'selected' : '' }}>Other</option>
        </select>
      </label>
    </form>
  </div>

  <div class="admin-table-card bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="admin-table-header">
            <th class="px-4 py-3 text-left text-sm font-semibold">#</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Reporter</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Reported User</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Reason</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Confirmed</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Moderation</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Date</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          @forelse($reports as $report)
            <tr class="hover:bg-gray-50 transition-colors duration-200">
              <td class="px-4 py-3">{{ $report->id }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-full {{ $report->reporter?->getAvatarBgClasses() ?? 'bg-blue-100' }} flex items-center justify-center text-white font-semibold text-xs">
                    {{ $report->reporter?->getAvatarInitial() ?? '?' }}
                  </div>
                  <div>
                    <p class="font-medium text-gray-900">{{ $report->reporter->name ?? 'Unknown' }}</p>
                    <p class="text-xs text-gray-500">{{ $report->reporter->email ?? '' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-full {{ $report->reportedUser?->getAvatarBgClasses() ?? 'bg-red-100' }} flex items-center justify-center text-white font-semibold text-xs">
                    {{ $report->reportedUser?->getAvatarInitial() ?? '?' }}
                  </div>
                  <div>
                    <p class="font-medium text-gray-900">{{ $report->reportedUser->name ?? 'Unknown' }}</p>
                    <p class="text-xs text-gray-500">{{ $report->reportedUser->email ?? '' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="admin-status-badge bg-gray-100 text-gray-700">
                  {{ $report->getReasonLabel() }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">
                {{ $report->reportedUser?->confirmedViolationCount($report->id) ?? 0 }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">
                {{ $report->reportedUser?->moderationStatusLabel() ?? 'Unknown' }}
              </td>
              <td class="px-4 py-3">
                <span class="admin-status-badge {{ $report->getStatusBadgeClass() }}">
                  {{ ucfirst(str_replace('_', ' ', $report->status)) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ $report->created_at->format('M d, Y') }}
                <br>
                <span class="text-xs text-gray-400">{{ $report->created_at->diffForHumans() }}</span>
              </td>
              <td class="px-4 py-3">
                <button onclick="openUserReportView({{ $report->id }})" class="admin-icon-button bg-blue-500 text-white hover:bg-blue-600" title="View Details">
                  <i class="fas fa-eye text-xs"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                <i class="fas fa-flag text-4xl mb-2 block"></i>
                No user reports found
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-200">
      <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
        <div class="text-sm text-gray-600">
          @if($reports->total() > 0)
            Showing {{ $reports->firstItem() }} to {{ $reports->lastItem() }} of {{ $reports->total() }} entries
          @else
            No entries found
          @endif
        </div>
        <div class="flex items-center">
          {{ $reports->links('pagination::tailwind') }}
        </div>
      </div>
    </div>
  </div>
@endsection

@push('modals')
  <x-modal id="viewUserReportModal" title="User Report Details" icon="fas fa-flag" color="red">
    <div id="userReportDetails" class="space-y-4">
      <!-- Content will be populated by JavaScript -->
    </div>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('viewUserReportModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Close
        </button>
        <button id="reviewUserReportButton" onclick="openUserReportEdit()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300">
          <i class="fas fa-gavel mr-2"></i>Review
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="editUserReportModal" title="Review User Report" icon="fas fa-user-shield" color="red">
    <form
      id="editUserReportForm"
      method="POST"
      action=""
      class="space-y-4"
      data-admin-moderation-confirmation
      data-confirm-title="Dismiss this user report?"
      data-confirm-text="No moderation action will be applied."
      data-confirm-button="Dismiss Report"
    >
      @csrf
      @method('PUT')
      <input type="hidden" name="id" id="editUserReportId">
      <div>
        <p id="moderationRecommendation" class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"></p>
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-gavel mr-2 text-red-600"></i>Decision</label>
        <select name="decision" id="editUserReportDecision" class="w-full px-4 py-2 border @error('decision') border-red-500 @else border-gray-300 @enderror rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent" required onchange="updateModerationFields()">
          <option value="dismiss" @selected(old('decision') === 'dismiss')>Dismiss Report</option>
          <option value="warning" @selected(old('decision') === 'warning')>Issue Warning</option>
          <option value="community_restriction" @selected(old('decision') === 'community_restriction')>Community Restriction</option>
          <option value="suspension" @selected(old('decision') === 'suspension')>Suspend Account</option>
        </select>
        @error('decision')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      </div>
      <div id="moderationDurationFields" class="hidden space-y-3">
        <div>
          <label class="block text-gray-700 mb-2">Duration</label>
          <select name="duration" id="editUserReportDuration" class="w-full px-4 py-2 border @error('duration') border-red-500 @else border-gray-300 @enderror rounded-lg" onchange="updateModerationFields()">
            <option value="3" @selected(old('duration') === '3')>3 Days</option>
            <option value="7" @selected(old('duration') === '7')>7 Days</option>
            <option value="custom" @selected(old('duration') === 'custom')>Custom</option>
          </select>
          @error('duration')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div id="moderationCustomUntilField" class="hidden">
          <label class="block text-gray-700 mb-2">Restriction Ends</label>
          <input type="datetime-local" name="custom_until" id="editUserReportCustomUntil" value="{{ old('custom_until') }}" class="w-full px-4 py-2 border @error('custom_until') border-red-500 @else border-gray-300 @enderror rounded-lg">
          @error('custom_until')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-comment-alt mr-2 text-green-600"></i>Resident Safe Reason</label>
        <textarea name="resident_reason" id="editUserReportResidentReason" rows="3" class="w-full px-4 py-2 border @error('resident_reason') border-red-500 @else border-gray-300 @enderror rounded-lg resize-none" placeholder="Optional information that may be shown to the resident">{{ old('resident_reason') }}</textarea>
        <p class="mt-1 text-xs text-gray-500">Do not put private investigation notes here.</p>
        @error('resident_reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-lock mr-2 text-red-600"></i>Internal Admin Notes</label>
        <textarea name="admin_notes" id="editUserReportNotes" rows="4" class="w-full px-4 py-2 border @error('admin_notes') border-red-500 @else border-gray-300 @enderror rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none" placeholder="Private notes for administrators only">{{ old('admin_notes') }}</textarea>
        <p class="mt-1 text-xs text-red-600">Never shown in resident notifications.</p>
        @error('admin_notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      </div>
    </form>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('editUserReportModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Cancel
        </button>
        <button type="submit" form="editUserReportForm" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300">
          <i class="fas fa-check mr-2"></i>Confirm Decision
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="deleteCommunityReportModal" title="Delete Community Report" icon="fas fa-trash-alt" color="red">
    <p class="text-gray-700">
      Are you sure you want to permanently delete this Community Report? This action cannot be undone.
    </p>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('deleteCommunityReportModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          Cancel
        </button>
        <form id="deleteCommunityReportForm" method="POST" action="">
          @csrf
          @method('DELETE')
          <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300">
            <i class="fas fa-trash-alt mr-2"></i>Delete Report
          </button>
        </form>
      </div>
    @endslot
  </x-modal>
@endpush

@push('scripts')
  <script>
    let currentUserReportId = null;
    @php
      $userReportsData = $reports->map(function($report) {
        $recommendation = $report->reportedUser
          ? app(\App\Services\ModerationService::class)->recommendedAction($report->reportedUser)
          : ['action' => 'warning', 'label' => 'Warning', 'days' => null];

        return [
          'id' => $report->id,
          'reporter' => [
            'id' => $report->reporter_id,
            'name' => $report->reporter->name ?? 'Unknown',
            'email' => $report->reporter->email ?? '',
          ],
          'reported_user' => [
            'id' => $report->reported_user_id,
            'name' => $report->reportedUser->name ?? 'Unknown',
            'email' => $report->reportedUser->email ?? '',
            'is_suspended' => $report->reportedUser ? $report->reportedUser->isSuspended() : false,
            'confirmed_violations' => $report->reportedUser?->confirmedViolationCount($report->id) ?? 0,
            'moderation_status' => $report->reportedUser?->moderationStatusLabel() ?? 'Unknown',
            'recommendation' => $recommendation,
            'moderation_history' => $report->reportedUser?->moderationActions
              ->sortByDesc('starts_at')
              ->map(fn ($action) => [
                'id' => $action->id,
                'label' => $action->label(),
                'status' => $action->statusLabel(),
                'applied_by' => $action->administrator?->name ?? 'Unknown Admin',
                'starts_at' => $action->starts_at?->format('M d, Y g:i A'),
                'expires_at' => $action->expires_at?->format('M d, Y g:i A'),
                'can_lift' => !$action->lifted_at
                  && in_array($action->action_type, ['community_restriction', 'report_submission_restriction', 'suspension'], true)
                  && (!$action->expires_at || $action->expires_at->isFuture()),
                'lift_url' => route('admin.moderation-actions.lift', $action),
              ])->values()->all() ?? [],
          ],
          'reason' => $report->reason,
          'reason_label' => $report->getReasonLabel(),
          'description' => $report->description,
          'status' => $report->status,
          'admin_notes' => $report->admin_notes,
          'created_at' => $report->created_at->format('M d, Y'),
          'created_at_human' => $report->created_at->diffForHumans(),
          'reviewed_at' => $report->reviewed_at ? $report->reviewed_at->format('M d, Y') : null,
          'reviewer' => $report->reviewer ? $report->reviewer->name : null,
          'originating_report' => $report->report ? [
            'id' => $report->report->id,
            'user_name' => $report->report->user->name ?? 'Unknown',
            'description' => $report->report->description,
            'location' => $report->report->location,
            'status' => $report->report->status,
            'submitted_at' => $report->report->created_at->format('M d, Y g:i A'),
            'image_url' => $report->report->image_path && Storage::disk('public')->exists($report->report->image_path)
              ? asset('storage/' . $report->report->image_path)
              : null,
            'delete_url' => route('admin.user-reports.report.destroy', $report),
          ] : null,
        ];
      })->keyBy('id');
    @endphp
    const userReportsData = @json($userReportsData);

    function escapeHtml(value) {
      const element = document.createElement('div');
      element.textContent = value ?? '';
      return element.innerHTML;
    }

    function openUserReportView(id) {
      const report = userReportsData[id];
      if (!report) {
        if (typeof showToast === 'function') {
          showToast('error', 'Report not found. Please refresh the page.');
        }
        return;
      }

      currentUserReportId = id;
      
      const detailsHtml = `
        <div class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-user mr-2 text-blue-600"></i>Reporter</h4>
              <p class="text-gray-900 font-medium">${report.reporter.name}</p>
              <p class="text-sm text-gray-600">${report.reporter.email}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-user-slash mr-2 text-red-600"></i>Reported User</h4>
              <p class="text-gray-900 font-medium">${escapeHtml(report.reported_user.name)}</p>
              <p class="text-sm text-gray-600">${escapeHtml(report.reported_user.email)}</p>
              <p class="mt-1 text-xs font-semibold text-red-600">${escapeHtml(report.reported_user.moderation_status)}</p>
            </div>
          </div>
          
          <div>
            <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-exclamation-triangle mr-2 text-red-600"></i>Reason</h4>
            <span class="text-sm font-medium px-3 py-1 rounded-full bg-gray-100 text-gray-800">${report.reason_label}</span>
          </div>
          
          <div>
            <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-comment mr-2 text-red-600"></i>Description</h4>
            <p class="text-gray-700 bg-gray-50 rounded-lg p-3">${escapeHtml(report.description || 'No description provided')}</p>
          </div>

          <div>
            <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-file-alt mr-2 text-red-600"></i>Reported Content</h4>
            ${report.originating_report ? `
              <div class="border border-gray-200 rounded-lg p-4 space-y-3 bg-gray-50">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                  <p><strong>Community Report ID:</strong> #${report.originating_report.id}</p>
                  <p><strong>Reported user:</strong> ${escapeHtml(report.originating_report.user_name)}</p>
                  <p><strong>Location:</strong> ${escapeHtml(report.originating_report.location || 'Not provided')}</p>
                  <p><strong>Status:</strong> ${escapeHtml(report.originating_report.status)}</p>
                  <p class="md:col-span-2"><strong>Submitted:</strong> ${escapeHtml(report.originating_report.submitted_at)}</p>
                </div>
                <div>
                  <p class="font-semibold text-sm text-gray-700 mb-1">Community Report description</p>
                  <p class="text-gray-700 bg-white rounded p-3">${escapeHtml(report.originating_report.description)}</p>
                </div>
                ${report.originating_report.image_url ? `
                  <img src="${escapeHtml(report.originating_report.image_url)}" class="w-full max-h-72 object-contain rounded-lg border border-gray-200 bg-white" alt="Reported Community Report image">
                ` : '<p class="text-sm text-gray-500">No uploaded image is available for this Community Report.</p>'}
                <div class="flex justify-end">
                  <button type="button" onclick="openDeleteCommunityReportModal(${report.id})" class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                    <i class="fas fa-trash-alt mr-2"></i>Delete Community Report
                  </button>
                </div>
              </div>
            ` : `
              <p class="text-gray-600 bg-gray-50 border border-gray-200 rounded-lg p-4">
                Original Community Report is no longer available.
              </p>
            `}
          </div>
          
          <div>
            <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-info-circle mr-2 text-red-600"></i>Status</h4>
            <span class="text-sm font-semibold px-3 py-1 rounded-full ${getStatusBadgeClass(report.status)}">${report.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-lg bg-gray-50 p-4">
              <p class="text-sm text-gray-500">Previous Confirmed Violations</p>
              <p class="text-xl font-bold text-gray-900">${report.reported_user.confirmed_violations}</p>
            </div>
            <div class="rounded-lg bg-blue-50 p-4">
              <p class="text-sm text-blue-600">Recommended Action</p>
              <p class="font-semibold text-blue-900">${escapeHtml(report.reported_user.recommendation.label)}</p>
              <p class="text-xs text-blue-700">Guidance only. Admin judgment remains required.</p>
            </div>
          </div>

          <div>
            <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-history mr-2 text-red-600"></i>Moderation History</h4>
            ${report.reported_user.moderation_history.length ? report.reported_user.moderation_history.map(action => `
              <div class="mb-2 rounded-lg border border-gray-200 p-3 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <strong>${escapeHtml(action.label)}</strong>
                  <span class="text-xs font-semibold text-gray-600">${escapeHtml(action.status)}</span>
                </div>
                <p class="text-gray-600">Applied by ${escapeHtml(action.applied_by)} on ${escapeHtml(action.starts_at)}</p>
                ${action.expires_at ? `<p class="text-gray-600">Expires: ${escapeHtml(action.expires_at)}</p>` : ''}
                ${action.can_lift ? `
                  <form
                    method="POST"
                    action="${escapeHtml(action.lift_url)}"
                    class="mt-2"
                    data-admin-moderation-confirmation
                    data-confirm-title="${action.label === 'Suspension' ? 'Unsuspend this account?' : 'Lift this restriction?'}"
                    data-confirm-text="${action.label === 'Suspension' ? 'The resident will be able to access Cleanify again.' : 'The resident will immediately regain access to the restricted feature.'}"
                    data-confirm-button="${action.label === 'Suspension' ? 'Unsuspend Account' : 'Lift Restriction'}"
                  >
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <button type="submit" class="rounded bg-gray-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-800">
                      ${action.label === 'Suspension' ? 'Unsuspend' : 'Lift Restriction'}
                    </button>
                  </form>
                ` : ''}
              </div>
            `).join('') : '<p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-500">No confirmed moderation history.</p>'}
          </div>
          
          ${report.admin_notes ? `
            <div>
              <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-sticky-note mr-2 text-red-600"></i>Admin Notes</h4>
              <p class="text-gray-700 bg-red-50 border-l-4 border-red-600 rounded-lg p-3">${escapeHtml(report.admin_notes)}</p>
            </div>
          ` : ''}
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
            <div>
              <p><strong>Reported on:</strong> ${report.created_at} (${report.created_at_human})</p>
            </div>
            ${report.reviewed_at ? `
              <div>
                <p><strong>Reviewed on:</strong> ${report.reviewed_at}</p>
                ${report.reviewer ? `<p><strong>Reviewed by:</strong> ${report.reviewer}</p>` : ''}
              </div>
            ` : ''}
          </div>
        </div>
      `;
      
      document.getElementById('userReportDetails').innerHTML = detailsHtml;
      document.getElementById('reviewUserReportButton').classList.toggle('hidden', report.status !== 'pending');
      openModal('viewUserReportModal');
    }

    function openUserReportEdit(preserveOldInput = false) {
      if (!currentUserReportId) return;
      
      const report = userReportsData[currentUserReportId];
      if (!report) return;
      if (report.status !== 'pending') {
        showToast('error', 'This report already has a final review decision.');
        return;
      }

      const form = document.getElementById('editUserReportForm');
      form.action = `/admin/user-reports/${currentUserReportId}`;
      document.getElementById('editUserReportId').value = report.id;
      if (!preserveOldInput) {
        document.getElementById('editUserReportDecision').value = report.reported_user.recommendation.action;
        if (report.reported_user.recommendation.days) {
          document.getElementById('editUserReportDuration').value = String(report.reported_user.recommendation.days);
        }
        document.getElementById('editUserReportNotes').value = report.admin_notes || '';
        document.getElementById('editUserReportResidentReason').value = '';
      }
      document.getElementById('moderationRecommendation').textContent = `Recommended Action: ${report.reported_user.recommendation.label}. This is guidance only.`;
      updateModerationFields();
      
      closeModal('viewUserReportModal');
      setTimeout(() => openModal('editUserReportModal'), 300);
    }

    function updateModerationFields() {
      const decision = document.getElementById('editUserReportDecision').value;
      const duration = document.getElementById('editUserReportDuration').value;
      const temporary = decision === 'community_restriction';
      const durationFields = document.getElementById('moderationDurationFields');
      const customField = document.getElementById('moderationCustomUntilField');
      const customInput = document.getElementById('editUserReportCustomUntil');

      durationFields.classList.toggle('hidden', !temporary);
      customField.classList.toggle('hidden', !temporary || duration !== 'custom');
      customInput.required = temporary && duration === 'custom';

      const confirmations = {
        dismiss: ['Dismiss this user report?', 'No moderation action will be applied.', 'Dismiss Report'],
        warning: ['Issue warning?', 'This action will be recorded in moderation history.', 'Issue Warning'],
        community_restriction: ['Apply community restriction?', 'Temporarily restrict this resident from posting or interacting in Community Reports. They can still view reports and access other Cleanify services.', 'Apply Restriction'],
        suspension: ['Suspend this account?', 'The resident will be signed out and unable to access Cleanify until unsuspended.', 'Suspend Account'],
      };
      const [title, text, button] = confirmations[decision] || confirmations.dismiss;
      const form = document.getElementById('editUserReportForm');
      form.dataset.confirmTitle = title;
      form.dataset.confirmText = text;
      form.dataset.confirmButton = button;
    }

    function openDeleteCommunityReportModal(userReportId) {
      const report = userReportsData[userReportId];
      if (!report?.originating_report) return;

      document.getElementById('deleteCommunityReportForm').action = report.originating_report.delete_url;
      closeModal('viewUserReportModal');
      setTimeout(() => openModal('deleteCommunityReportModal'), 300);
    }

    function getStatusBadgeClass(status) {
      const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'reviewed': 'bg-blue-100 text-blue-800',
        'dismissed': 'bg-gray-100 text-gray-800',
        'action_taken': 'bg-green-100 text-green-800',
      };
      return classes[status] || 'bg-gray-100 text-gray-800';
    }

    // Real-time search
    document.getElementById('userReportSearchInput')?.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        this.closest('form').submit();
      }
    });

    @if($errors->any() && old('id'))
      currentUserReportId = @json((int) old('id'));
      setTimeout(() => openUserReportEdit(true), 0);
    @endif
  </script>
@endpush
