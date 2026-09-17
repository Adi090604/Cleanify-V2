@extends('layouts.admin')

@php use Illuminate\Support\Str; @endphp

@section('title', 'User Reports')

@section('content')
  <div class="admin-page-header flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
    <div>
      <h2 class="text-3xl font-bold text-gray-800">
        <i class="fas fa-user-shield text-red-600 mr-3"></i>User Reports
      </h2>
      <p class="text-gray-600 mt-1">Review and manage reports submitted by users about other users</p>
    </div>
    <form method="GET" action="{{ route('admin.user-reports') }}" class="flex w-full lg:w-auto max-w-lg">
      <input 
        id="userReportSearchInput" 
        name="search" 
        type="text" 
        value="{{ $search }}"
        class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent" 
        placeholder="Search by reporter or reported user..."
      >
      <button type="submit" class="px-4 bg-red-600 text-white rounded-r-lg hover:bg-red-700 transition-colors duration-300">
        <i class="fas fa-search"></i>
      </button>
      @if($search)
        <a href="{{ route('admin.user-reports') }}" class="ml-2 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-300">
          <i class="fas fa-times"></i>
        </a>
      @endif
    </form>
  </div>

  @if(session('success'))
    <x-alert type="success" dismissible class="mb-4">
      {{ session('success') }}
    </x-alert>
  @endif

  @if($errors->any())
    <x-alert type="error" dismissible class="mb-4">
      <ul class="list-disc list-inside text-sm">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </x-alert>
  @endif

  <div class="admin-stat-grid grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 mb-5">
    <x-admin.stat-card icon="fas fa-flag" title="Total Reports" :value="$totalReports" />
    <x-admin.stat-card icon="fas fa-clock" title="Pending" :value="$pendingReports" borderClass="border-l-4 border-yellow-500" iconWrapperClass="bg-yellow-100" iconColorClass="text-yellow-600" />
    <x-admin.stat-card icon="fas fa-check-circle" title="Reviewed" :value="$reviewedReports" borderClass="border-l-4 border-blue-500" iconWrapperClass="bg-blue-100" iconColorClass="text-blue-600" />
    <x-admin.stat-card icon="fas fa-ban" title="Action Taken" :value="$actionTakenReports" borderClass="border-l-4 border-red-500" iconWrapperClass="bg-red-100" iconColorClass="text-red-600" />
  </div>

  <div class="admin-toolbar-card bg-white rounded-xl shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
    <form method="GET" action="{{ route('admin.user-reports') }}" class="flex items-center gap-3 flex-wrap">
      <input type="hidden" name="search" value="{{ $search }}">
      <label class="text-sm text-gray-600 flex items-center gap-2">
        <span>Status</span>
        <select name="status" class="border border-gray-300 rounded-full px-3 py-1.5 text-sm focus:ring-red-500" onchange="this.form.submit()">
          <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All statuses</option>
          <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
          <option value="reviewed" {{ $statusFilter === 'reviewed' ? 'selected' : '' }}>Reviewed</option>
          <option value="dismissed" {{ $statusFilter === 'dismissed' ? 'selected' : '' }}>Dismissed</option>
          <option value="action_taken" {{ $statusFilter === 'action_taken' ? 'selected' : '' }}>Action Taken</option>
        </select>
      </label>
      <label class="text-sm text-gray-600 flex items-center gap-2">
        <span>Reason</span>
        <select name="reason" class="border border-gray-300 rounded-full px-3 py-1.5 text-sm focus:ring-red-500" onchange="this.form.submit()">
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
          <tr class="bg-red-600 text-white">
            <th class="px-4 py-3 text-left text-sm font-semibold">#</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Reporter</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Reported User</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Reason</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Description</th>
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
                    @if($report->reportedUser && $report->reportedUser->isBanned())
                      <span class="text-xs text-red-600 font-semibold">(Banned)</span>
                    @endif
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="text-xs font-medium px-2 py-1 rounded-full bg-gray-100 text-gray-800">
                  {{ $report->getReasonLabel() }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 max-w-xs">
                {{ Str::limit($report->description ?? 'No description provided', 50) }}
              </td>
              <td class="px-4 py-3">
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full {{ $report->getStatusBadgeClass() }}">
                  {{ ucfirst(str_replace('_', ' ', $report->status)) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ $report->created_at->format('M d, Y') }}
                <br>
                <span class="text-xs text-gray-400">{{ $report->created_at->diffForHumans() }}</span>
              </td>
              <td class="px-4 py-3">
                <button onclick="openUserReportView({{ $report->id }})" class="w-8 h-8 bg-purple-500 text-white rounded hover:bg-purple-600 transition-colors duration-300" title="View Details">
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
        <button onclick="openUserReportEdit()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300">
          <i class="fas fa-edit mr-2"></i>Update Status
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="editUserReportModal" title="Update User Report Status" icon="fas fa-edit" color="red">
    <form id="editUserReportForm" method="POST" action="" class="space-y-4">
      @csrf
      @method('PUT')
      <input type="hidden" name="id" id="editUserReportId">
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-tag mr-2 text-red-600"></i>Status</label>
        <select name="status" id="editUserReportStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent" required>
          <option value="pending">Pending</option>
          <option value="reviewed">Reviewed</option>
          <option value="dismissed">Dismissed</option>
          <option value="action_taken">Action Taken</option>
        </select>
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-comment mr-2 text-red-600"></i>Admin Notes</label>
        <textarea name="admin_notes" id="editUserReportNotes" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none" placeholder="Add notes about this report..."></textarea>
      </div>
    </form>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('editUserReportModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Cancel
        </button>
        <button type="submit" form="editUserReportForm" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300">
          <i class="fas fa-save mr-2"></i>Save Changes
        </button>
      </div>
    @endslot
  </x-modal>
@endpush

@push('scripts')
  <script>
    let currentUserReportId = null;
    @php
      $userReportsData = $reports->map(function($report) {
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
            'is_banned' => $report->reportedUser ? $report->reportedUser->isBanned() : false,
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
        ];
      })->keyBy('id');
    @endphp
    const userReportsData = @json($userReportsData);

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
              <p class="text-gray-900 font-medium">${report.reported_user.name} ${report.reported_user.is_banned ? '<span class="text-xs text-red-600 font-semibold">(Banned)</span>' : ''}</p>
              <p class="text-sm text-gray-600">${report.reported_user.email}</p>
            </div>
          </div>
          
          <div>
            <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-exclamation-triangle mr-2 text-red-600"></i>Reason</h4>
            <span class="text-sm font-medium px-3 py-1 rounded-full bg-gray-100 text-gray-800">${report.reason_label}</span>
          </div>
          
          <div>
            <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-comment mr-2 text-red-600"></i>Description</h4>
            <p class="text-gray-700 bg-gray-50 rounded-lg p-3">${report.description || 'No description provided'}</p>
          </div>
          
          <div>
            <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-info-circle mr-2 text-red-600"></i>Status</h4>
            <span class="text-sm font-semibold px-3 py-1 rounded-full ${getStatusBadgeClass(report.status)}">${report.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
          </div>
          
          ${report.admin_notes ? `
            <div>
              <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-sticky-note mr-2 text-red-600"></i>Admin Notes</h4>
              <p class="text-gray-700 bg-red-50 border-l-4 border-red-600 rounded-lg p-3">${report.admin_notes}</p>
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
      openModal('viewUserReportModal');
    }

    function openUserReportEdit() {
      if (!currentUserReportId) return;
      
      const report = userReportsData[currentUserReportId];
      if (!report) return;

      const form = document.getElementById('editUserReportForm');
      form.action = `/admin/user-reports/${currentUserReportId}`;
      document.getElementById('editUserReportId').value = report.id;
      document.getElementById('editUserReportStatus').value = report.status;
      document.getElementById('editUserReportNotes').value = report.admin_notes || '';
      
      closeModal('viewUserReportModal');
      setTimeout(() => openModal('editUserReportModal'), 300);
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
  </script>
@endpush
