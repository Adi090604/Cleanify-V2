@extends('layouts.admin')

@section('title', 'Reports')

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
  @if(session('success'))
    <x-alert type="success" dismissible class="mb-4">
      {{ session('success') }}
    </x-alert>
  @endif
  @if(session('error') || $errors->any())
    <x-alert type="error" dismissible class="mb-4">{{ session('error') ?? $errors->first() }}</x-alert>
  @endif

  <div class="admin-page-header flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
    <div>
      <h2 class="text-3xl font-bold text-gray-800">
        <i class="fas fa-flag text-green-600 mr-3"></i>Reports Management
      </h2>
      <p class="text-gray-600 mt-1">Manage and resolve community reports</p>
    </div>
    <div class="flex w-full lg:w-auto max-w-lg">
      <form action="{{ route('admin.reports') }}" method="GET" class="flex w-full gap-2">
        @if($priorityFilter)<input type="hidden" name="priority" value="{{ $priorityFilter }}">@endif
        @if($reporterFilter)<input type="hidden" name="reporter_id" value="{{ $reporterFilter }}">@endif
        @if($locationFilter)<input type="hidden" name="location" value="{{ $locationFilter }}">@endif
        <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-green-500" aria-label="Filter reports by status" onchange="this.form.submit()">
          <option value="">All statuses</option>
          <option value="pending" @selected($statusFilter === 'pending')>Pending</option>
          <option value="in_progress" @selected($statusFilter === 'in_progress')>In Progress</option>
          <option value="resolved" @selected($statusFilter === 'resolved')>Resolved</option>
          <option value="rejected" @selected($statusFilter === 'rejected')>Rejected</option>
        </select>
        <div class="flex min-w-0 flex-1">
        <input id="reportSearchInput" type="text" name="search" value="{{ $search }}" class="min-w-0 flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Search reports...">
        <button type="submit" class="px-4 bg-green-600 text-white rounded-r-lg hover:bg-green-700 transition-colors duration-300">
          <i class="fas fa-search"></i>
        </button>
        </div>
      </form>
    </div>
  </div>

  <div class="admin-stat-grid grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5 mb-5">
    <x-admin.stat-card icon="fas fa-clock" title="Pending Reports" value="{{ $pendingReports }}" borderClass="border-l-4 border-yellow-500" iconWrapperClass="bg-yellow-100" iconColorClass="text-yellow-600" />
    <x-admin.stat-card icon="fas fa-spinner" title="In Progress" value="{{ $inProgressReports }}" borderClass="border-l-4 border-blue-500" iconWrapperClass="bg-blue-100" iconColorClass="text-blue-600" />
    <x-admin.stat-card icon="fas fa-check-circle" title="Resolved" value="{{ $resolvedReports }}" borderClass="border-l-4 border-green-500" iconWrapperClass="bg-green-100" iconColorClass="text-green-600" />
    <x-admin.stat-card icon="fas fa-times-circle" title="Rejected" value="{{ $rejectedReports }}" borderClass="border-l-4 border-red-500" iconWrapperClass="bg-red-100" iconColorClass="text-red-600" />
    <x-admin.stat-card icon="fas fa-chart-line" title="This Month" value="{{ $thisMonthReports }}" borderClass="border-l-4 border-blue-500" iconWrapperClass="bg-blue-100" iconColorClass="text-blue-600" />
  </div>

  <section class="mb-5" aria-labelledby="reporting-insights-title">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <div>
        <h3 id="reporting-insights-title" class="text-xl font-bold text-gray-800">Reporting Insights</h3>
        <p class="text-sm text-gray-500">Reporter activity and recurring free-text report locations.</p>
      </div>
      @if($reporterFilter || $locationFilter || $search || $statusFilter || $priorityFilter)
        <a href="{{ route('admin.reports') }}" class="text-sm font-semibold text-green-700 hover:text-green-800">Clear All Filters</a>
      @endif
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
      <article class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-4 py-3">
          <h4 class="font-semibold text-gray-800"><i class="fas fa-users mr-2 text-green-600"></i>Most Active Reporters</h4>
        </div>
        <div class="divide-y divide-gray-100">
          @forelse($topReporters as $reporter)
            @php
              $topLocation = $mostReportedLocations->get($reporter->id);
            @endphp
            <a href="{{ route('admin.reports', array_merge(request()->except(['page', 'report', 'reporter_id']), ['reporter_id' => $reporter->id])) }}" class="block px-4 py-3 transition hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-green-500">
              <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                <div class="min-w-0">
                  <p class="truncate font-semibold text-gray-900">{{ $reporter->name }}</p>
                  <p class="text-xs text-gray-500">Current Service Area: {{ $reporter->service_area ?: 'Not set' }}</p>
                  <p class="mt-1 text-xs text-gray-500">Most Reported Location: {{ $topLocation?->location_name ?: 'No location data' }}</p>
                </div>
                <div class="shrink-0 text-left sm:text-right">
                  <p class="font-semibold text-green-700">{{ $reporter->reports_count }} {{ Str::plural('report', $reporter->reports_count) }}</p>
                  <p class="text-xs text-gray-500">Latest {{ $reporter->latest_report_at ? \Carbon\Carbon::parse($reporter->latest_report_at)->format('M d, Y') : '—' }}</p>
                </div>
              </div>
              <div class="mt-2 flex flex-wrap gap-1.5 text-xs">
                <span class="rounded-full bg-yellow-50 px-2 py-1 text-yellow-700">Pending {{ $reporter->pending_reports_count }}</span>
                <span class="rounded-full bg-blue-50 px-2 py-1 text-blue-700">In Progress {{ $reporter->in_progress_reports_count }}</span>
                <span class="rounded-full bg-green-50 px-2 py-1 text-green-700">Resolved {{ $reporter->resolved_reports_count }}</span>
                <span class="rounded-full bg-red-50 px-2 py-1 text-red-700">Rejected {{ $reporter->rejected_reports_count }}</span>
              </div>
            </a>
          @empty
            <p class="px-4 py-8 text-center text-sm text-gray-500">No reporter activity yet.</p>
          @endforelse
        </div>
        <div class="border-t border-gray-100 px-4 py-3 text-right">
          <a href="{{ route('admin.reports.reporters') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-green-700 hover:text-green-900">
            View All Reporters <i class="fas fa-arrow-right text-xs"></i>
          </a>
        </div>
      </article>

      <article class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-4 py-3">
          <h4 class="font-semibold text-gray-800"><i class="fas fa-map-marker-alt mr-2 text-green-600"></i>Frequently Reported Locations</h4>
        </div>
        <div class="divide-y divide-gray-100">
          @forelse($frequentLocations as $locationInsight)
            <a href="{{ route('admin.reports', array_merge(request()->except(['page', 'report', 'location']), ['location' => $locationInsight->location_key])) }}" class="block px-4 py-3 transition hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-green-500">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate font-semibold text-gray-900">{{ $locationInsight->location_name }}</p>
                  <p class="text-xs text-gray-500">{{ $locationInsight->unique_reporters }} unique {{ Str::plural('reporter', $locationInsight->unique_reporters) }}</p>
                </div>
                <div class="shrink-0 text-right">
                  <p class="font-semibold text-green-700">{{ $locationInsight->total_reports }} {{ Str::plural('report', $locationInsight->total_reports) }}</p>
                  <p class="text-xs text-gray-500">Latest {{ \Carbon\Carbon::parse($locationInsight->latest_report_at)->format('M d, Y') }}</p>
                </div>
              </div>
              <div class="mt-2 flex flex-wrap gap-1.5 text-xs">
                <span class="rounded-full bg-yellow-50 px-2 py-1 text-yellow-700">Pending {{ $locationInsight->pending_reports }}</span>
                <span class="rounded-full bg-blue-50 px-2 py-1 text-blue-700">In Progress {{ $locationInsight->in_progress_reports }}</span>
                <span class="rounded-full bg-green-50 px-2 py-1 text-green-700">Resolved {{ $locationInsight->resolved_reports }}</span>
                <span class="rounded-full bg-red-50 px-2 py-1 text-red-700">Rejected {{ $locationInsight->rejected_reports }}</span>
              </div>
            </a>
          @empty
            <p class="px-4 py-8 text-center text-sm text-gray-500">No reported locations yet.</p>
          @endforelse
        </div>
        <div class="border-t border-gray-100 px-4 py-3 text-right">
          <a href="{{ route('admin.reports.locations') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-green-700 hover:text-green-900">
            View All Locations <i class="fas fa-arrow-right text-xs"></i>
          </a>
        </div>
      </article>
    </div>
  </section>

  @if($activeReporter || $activeLocationSummary)
    <section class="mb-4 grid grid-cols-1 gap-3 {{ $activeReporter && $activeLocationSummary ? 'xl:grid-cols-2' : '' }}" aria-label="Active report filters">
      @if($activeReporter)
        @php
          $reporterTopLocation = $mostReportedLocations->get($activeReporter->id);
        @endphp
        <article class="rounded-xl border border-green-200 bg-green-50 p-4">
          <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
            <div>
              <p class="text-xs font-bold uppercase tracking-wide text-green-700">Reporter History</p>
              <h3 class="mt-1 text-lg font-bold text-gray-900">{{ $activeReporter->name }}</h3>
              <p class="text-sm text-gray-600">{{ $activeReporter->reports_count }} reports submitted</p>
            </div>
            <a href="{{ route('admin.reports', request()->except(['page', 'report', 'reporter_id'])) }}" class="text-sm font-semibold text-green-700 hover:text-green-900">Clear Reporter Filter</a>
          </div>
          <dl class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Current Service Area</dt><dd class="font-medium text-gray-800">{{ $activeReporter->service_area ?: 'Not set' }}</dd></div>
            <div><dt class="text-gray-500">Most Reported Location</dt><dd class="font-medium text-gray-800">{{ $reporterTopLocation?->location_name ?: 'No location data' }}</dd></div>
            <div><dt class="text-gray-500">Status</dt><dd class="font-medium text-gray-800">{{ $activeReporter->pending_reports_count }} pending · {{ $activeReporter->in_progress_reports_count }} in progress · {{ $activeReporter->resolved_reports_count }} resolved · {{ $activeReporter->rejected_reports_count }} rejected</dd></div>
            <div><dt class="text-gray-500">Last Report</dt><dd class="font-medium text-gray-800">{{ $activeReporter->latest_report_at ? \Carbon\Carbon::parse($activeReporter->latest_report_at)->format('M d, Y') : '—' }}</dd></div>
          </dl>
        </article>
      @endif

      @if($activeLocationSummary)
        <article class="rounded-xl border border-blue-200 bg-blue-50 p-4">
          <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
            <div>
              <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Reported Location</p>
              <h3 class="mt-1 text-lg font-bold text-gray-900">{{ $activeLocationSummary->location_name }}</h3>
              <p class="text-sm text-gray-600">{{ $activeLocationSummary->total_reports }} reports · {{ $activeLocationSummary->unique_reporters }} unique reporters</p>
            </div>
            <a href="{{ route('admin.reports', request()->except(['page', 'report', 'location'])) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Clear Location Filter</a>
          </div>
          <dl class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Status</dt><dd class="font-medium text-gray-800">{{ $activeLocationSummary->pending_reports }} pending · {{ $activeLocationSummary->in_progress_reports }} in progress · {{ $activeLocationSummary->resolved_reports }} resolved · {{ $activeLocationSummary->rejected_reports }} rejected</dd></div>
            <div><dt class="text-gray-500">Latest Report</dt><dd class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($activeLocationSummary->latest_report_at)->format('M d, Y') }}</dd></div>
          </dl>
        </article>
      @endif
    </section>
  @endif

  <div class="admin-table-card bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full" id="reportsTable">
        <thead>
          <tr class="admin-table-header">
            @foreach (['#','Reporter','Location','Description','Image','Status','Date Reported','Actions'] as $heading)
              <th class="px-4 py-3 text-left text-sm font-semibold">{{ $heading }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200" id="reportsTableBody">
          @forelse ($reports as $report)
            <tr class="hover:bg-gray-50 transition-colors duration-200" data-report="{{ json_encode([
              'id' => $report->id,
              'reporter' => $report->user?->name ?? 'Unknown User',
              'email' => $report->user?->email ?? 'N/A',
              'location' => $report->location,
              'latitude' => $report->latitude,
              'longitude' => $report->longitude,
              'description' => $report->description,
              'image_path' => $report->image_path,
              'status' => $report->status,
              'admin_notes' => $report->admin_notes,
              'rejection_reason' => $report->rejection_reason,
              'created_at' => $report->created_at->format('M d, Y'),
              'resolved_at' => $report->resolved_at ? $report->resolved_at->format('M d, Y') : null,
            ]) }}">
              <td class="px-4 py-3">{{ $report->id }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  @if($report->user)
                    <div class="w-10 h-10 rounded-full {{ $report->user->getAvatarBgClasses() }} flex items-center justify-center text-white font-semibold text-sm">
                      {{ $report->user->getAvatarInitial() }}
                    </div>
                    <div>
                      <div class="font-medium text-gray-900">{{ $report->user->name }}</div>
                      <div class="text-xs text-gray-500">{{ $report->user->email }}</div>
                    </div>
                  @else
                    <div class="w-10 h-10 rounded-full bg-gray-400 flex items-center justify-center text-white font-semibold text-sm">
                      ?
                    </div>
                    <div>
                      <div class="font-medium text-gray-900">Unknown User</div>
                      <div class="text-xs text-gray-500">User not found</div>
                    </div>
                  @endif
                </div>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ $report->location }}</td>
              <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $report->description }}</td>
              <td class="px-4 py-3">
                @if($report->image_path)
                  <img src="{{ asset('storage/' . $report->image_path) }}" class="w-12 h-12 rounded object-cover" alt="Report image" onerror="this.src='https://via.placeholder.com/60?text=No+Image'">
                @else
                  <div class="w-12 h-12 rounded bg-gray-200 flex items-center justify-center">
                    <i class="fas fa-image text-gray-400"></i>
                  </div>
                @endif
              </td>
              <td class="px-4 py-3">
                @php
                  $statusBadgeClass = match($report->status) {
                    'pending' => 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
                    'in_progress' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
                    'resolved' => 'bg-green-50 text-green-700 ring-green-600/20',
                    'rejected' => 'bg-red-50 text-red-700 ring-red-600/20',
                    default => 'bg-gray-100 text-gray-700 ring-gray-500/20',
                  };
                @endphp
                <span class="admin-status-badge ring-1 ring-inset {{ $statusBadgeClass }}">
                  <i class="fas fa-circle text-xs mr-1"></i>{{ $report->status_label }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ $report->created_at->format('M d, Y') }}</td>
              <td class="px-4 py-3">
                <div class="flex space-x-2">
                  @if($report->status === 'pending')
                    <button onclick="openInProgressModal({{ $report->id }})" class="admin-icon-button bg-blue-500 text-white hover:bg-blue-600" title="Mark In Progress">
                      <i class="fas fa-spinner text-xs"></i>
                    </button>
                    <button onclick="openRejectModal({{ $report->id }})" class="admin-icon-button bg-red-500 text-white hover:bg-red-600" title="Reject">
                      <i class="fas fa-times text-xs"></i>
                    </button>
                  @elseif($report->status === 'in_progress')
                    <button onclick="openResolveModal({{ $report->id }})" class="admin-icon-button bg-green-500 text-white hover:bg-green-600" title="Resolve">
                      <i class="fas fa-check text-xs"></i>
                    </button>
                    <button onclick="openRejectModal({{ $report->id }})" class="admin-icon-button bg-red-500 text-white hover:bg-red-600" title="Reject">
                      <i class="fas fa-times text-xs"></i>
                    </button>
                  @endif
                  <button onclick="openReportView({{ $report->id }})" class="admin-icon-button bg-blue-500 text-white hover:bg-blue-600" title="View">
                    <i class="fas fa-eye text-xs"></i>
                  </button>
                  <button type="button" data-delete-report-url="{{ route('admin.reports.destroy', $report) }}" onclick="openDeleteReportModal(this.dataset.deleteReportUrl)" class="admin-icon-button bg-red-500 text-white hover:bg-red-600" title="Delete permanently">
                    <i class="fas fa-trash-alt text-xs"></i>
                  </button>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                <i class="fas fa-flag text-4xl mb-2 block"></i>
                No reports found
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
  <x-modal id="viewReportModal" title="Report Details" icon="fas fa-eye" color="green">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <a id="viewReportImageLink" target="_blank" class="hidden"><img id="viewReportImage" src="" class="w-full h-64 object-cover rounded-lg" alt="Report preview"></a>
        <p id="viewReportImageEmpty" class="h-64 flex items-center justify-center bg-gray-50 rounded-lg text-gray-500">No photo attached</p>
      </div>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Reporter</label>
          <p id="viewReportReporter" class="mt-1 text-lg font-semibold"></p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Location</label>
          <p id="viewReportLocation" class="mt-1 text-gray-900"></p>
        </div>
        <div><label class="block text-sm font-medium text-gray-700">Coordinates</label><p id="viewReportCoordinates" class="mt-1 text-gray-900"></p></div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Status</label>
          <span id="viewReportStatus" class="mt-1 font-semibold"></span>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Date Reported</label>
          <p id="viewReportDate" class="mt-1 text-gray-900"></p>
        </div>
      </div>
    </div>
    <div class="mt-6">
      <label class="block text-sm font-medium text-gray-700">Description</label>
      <p id="viewReportDescription" class="mt-2 text-gray-900 bg-gray-50 p-4 rounded-lg"></p>
    </div>
    <div id="viewReportMapSection" class="mt-6 hidden"><label class="block text-sm font-medium text-gray-700 mb-2">Reported map location</label><div id="viewReportMap" class="h-56 rounded-lg border border-gray-200"></div></div>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('viewReportModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Close
        </button>
        <button id="viewReportInProgressButton" onclick="openInProgressModalFromView()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-300">
          <i class="fas fa-spinner mr-2"></i>Mark In Progress
        </button>
        <button id="viewReportResolveButton" onclick="openResolveModalFromView()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
          <i class="fas fa-check mr-2"></i>Mark as Resolved
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="inProgressReportModal" title="Mark Report In Progress" icon="fas fa-spinner" color="blue" variant="confirmation">
    <p>This confirms that the reported garbage issue has been acknowledged and is currently being handled.</p>
    <form id="inProgressReportForm" method="POST">@csrf</form>
    @slot('footer')
      <div class="admin-confirm-actions">
        <button type="button" onclick="closeModal('inProgressReportModal')" class="admin-btn-secondary">Cancel</button>
        <button type="submit" form="inProgressReportForm" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
          <i class="fas fa-spinner mr-2"></i>Mark In Progress
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="resolveReportModal" title="Resolve Report" icon="fas fa-check-circle" color="green">
    <form id="resolveReportForm" method="POST">
      @csrf
      <div class="text-center mb-4">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-check text-green-600 text-2xl"></i>
        </div>
        <h4 class="text-xl font-semibold text-gray-800 mb-2">Confirm Resolution</h4>
        <p class="text-gray-600">Are you sure you want to mark this report as resolved?</p>
      </div>
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-comment mr-2 text-green-600"></i>Resolution Notes (Optional)
        </label>
        <textarea name="admin_notes" id="resolveNotes" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none" rows="3" placeholder="Add any notes about how this report was resolved..."></textarea>
      </div>
      <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4 flex">
        <i class="fas fa-info-circle text-blue-400 mr-3 mt-1"></i>
        <p class="text-sm text-blue-700">This will notify the reporter that their issue has been resolved.</p>
      </div>
    </form>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('resolveReportModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Cancel
        </button>
        <button type="submit" form="resolveReportForm" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
          <i class="fas fa-check mr-2"></i>Mark as Resolved
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="rejectReportModal" title="Reject Report" icon="fas fa-times-circle" color="red" variant="confirmation">
    <form id="rejectReportForm" method="POST">
      @csrf
      <p class="mb-4">Are you sure you want to reject this report?</p>
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-comment mr-2 text-red-600"></i>Rejection Reason (Required)
        </label>
        <textarea name="rejection_reason" id="rejectReason" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none" rows="3" minlength="10" placeholder="Please provide a reason for rejecting this report..." required></textarea>
        @error('rejection_reason', 'rejectReport')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div class="admin-confirm-warning">
        <i class="fas fa-exclamation-circle mt-0.5" aria-hidden="true"></i>
        <p>This action cannot be undone. The reporter will be notified about the rejection.</p>
      </div>
    </form>
    @slot('footer')
      <div class="admin-confirm-actions">
        <button type="button" onclick="closeModal('rejectReportModal')" class="admin-btn-secondary">
          Cancel
        </button>
        <button type="submit" form="rejectReportForm" class="admin-btn-danger">
          <i class="fas fa-times mr-2"></i>Reject Report
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="deleteReportModal" title="Delete Report" icon="fas fa-trash-alt" color="red" variant="confirmation">
    <p>Are you sure you want to permanently delete this report? This action cannot be undone.</p>
    <div class="admin-confirm-warning">
      <i class="fas fa-exclamation-circle mt-0.5" aria-hidden="true"></i>
      <p>The report and its related data will be permanently removed.</p>
    </div>
    <form id="deleteReportForm" method="POST">
      @csrf
      @method('DELETE')
    </form>
    @slot('footer')
      <div class="admin-confirm-actions">
        <button type="button" onclick="closeModal('deleteReportModal')" class="admin-btn-secondary">
          Cancel
        </button>
        <button type="submit" form="deleteReportForm" id="confirmDeleteReportButton" class="admin-btn-danger" disabled>
          <i class="fas fa-trash mr-2"></i>Delete Report
        </button>
      </div>
    @endslot
  </x-modal>
@endpush

@push('scripts')
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    const adminReports = @json($reports->items());
    let currentReportId = null;
    let viewReportMap;
    let viewReportMarker;
    const selectedReportId = @json($selectedReportId ?? null);

    function getReportFromTable(id) {
      const row = document.querySelector(`tr[data-report*='"id":${id}']`);
      if (row) {
        return JSON.parse(row.dataset.report);
      }
      return adminReports.find(r => r.id === Number(id));
    }

    function openReportView(id) {
      const report = getReportFromTable(id);
      if (!report) {
        if (typeof showToast === 'function') {
          showToast('error', 'Report not found. Please try again.');
        }
        return;
      }
      currentReportId = id;
      
      const imageElement = document.getElementById('viewReportImage');
      const imageLink = document.getElementById('viewReportImageLink');
      const imageEmpty = document.getElementById('viewReportImageEmpty');
      if (report.image_path) {
        const imageUrl = `/storage/${report.image_path}`;
        imageElement.src = imageUrl;
        imageLink.href = imageUrl; imageLink.classList.remove('hidden'); imageEmpty.classList.add('hidden');
        imageElement.onerror = () => { imageLink.classList.add('hidden'); imageEmpty.textContent = 'Photo unavailable'; imageEmpty.classList.remove('hidden'); };
      } else { imageLink.classList.add('hidden'); imageEmpty.textContent = 'No photo attached'; imageEmpty.classList.remove('hidden'); }
      
      document.getElementById('viewReportReporter').textContent = `${report.reporter} (${report.email})`;
      document.getElementById('viewReportLocation').textContent = report.location;
      const hasCoordinates = report.latitude !== null && report.latitude !== undefined && report.longitude !== null && report.longitude !== undefined;
      document.getElementById('viewReportCoordinates').textContent = hasCoordinates ? `${Number(report.latitude).toFixed(8)}, ${Number(report.longitude).toFixed(8)}` : 'Location not available';
      
      const statusElement = document.getElementById('viewReportStatus');
      const statusClass = report.status === 'pending' ? 'text-yellow-600' :
                         report.status === 'in_progress' ? 'text-blue-600' :
                         report.status === 'resolved' ? 'text-green-600' : 'text-red-600';
      statusElement.className = `mt-1 font-semibold ${statusClass}`;
      statusElement.innerHTML = `<i class="fas fa-circle text-xs mr-1"></i>${report.status.replace('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())}`;
      
      document.getElementById('viewReportDate').textContent = report.created_at;
      document.getElementById('viewReportDescription').textContent = report.description;
      
      document.getElementById('viewReportInProgressButton').style.display = report.status === 'pending' ? 'inline-block' : 'none';
      document.getElementById('viewReportResolveButton').style.display = report.status === 'in_progress' ? 'inline-block' : 'none';
      
      openModal('viewReportModal');
      const mapSection = document.getElementById('viewReportMapSection');
      if (hasCoordinates) {
        mapSection.classList.remove('hidden');
        setTimeout(() => renderAdminReportMap(report.latitude, report.longitude), 150);
      } else mapSection.classList.add('hidden');
    }

    function renderAdminReportMap(latitude, longitude) {
      const point = [Number(latitude), Number(longitude)];
      if (!viewReportMap) {
        viewReportMap = L.map('viewReportMap');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(viewReportMap);
      }
      if (viewReportMarker) viewReportMarker.setLatLng(point);
      else viewReportMarker = L.marker(point).addTo(viewReportMap);
      viewReportMap.setView(point, 15); viewReportMap.invalidateSize();
    }

    function openResolveModal(id) {
      currentReportId = id;
      const form = document.getElementById('resolveReportForm');
      form.action = `/admin/reports/${id}/resolve`;
      document.getElementById('resolveNotes').value = '';
      openModal('resolveReportModal');
    }

    function openInProgressModal(id) {
      currentReportId = id;
      document.getElementById('inProgressReportForm').action = `/admin/reports/${id}/in-progress`;
      openModal('inProgressReportModal');
    }

    function openRejectModal(id) {
      currentReportId = id;
      const form = document.getElementById('rejectReportForm');
      form.action = `/admin/reports/${id}/reject`;
      document.getElementById('rejectReason').value = '';
      openModal('rejectReportModal');
    }

    function openDeleteReportModal(deleteUrl) {
      const form = document.getElementById('deleteReportForm');
      const confirmButton = document.getElementById('confirmDeleteReportButton');
      if (!form || !deleteUrl) return;

      form.setAttribute('action', deleteUrl);
      form.dataset.ready = 'true';
      form.dataset.submitting = 'false';
      if (confirmButton) {
        confirmButton.disabled = false;
        confirmButton.innerHTML = '<i class="fas fa-trash mr-2"></i>Delete Report';
      }
      openModal('deleteReportModal');
    }

    document.getElementById('deleteReportForm')?.addEventListener('submit', function(event) {
      if (this.dataset.ready !== 'true' || !this.getAttribute('action') || this.dataset.submitting === 'true') {
        event.preventDefault();
        return;
      }

      this.dataset.submitting = 'true';
      const confirmButton = document.getElementById('confirmDeleteReportButton');
      if (confirmButton) {
        confirmButton.disabled = true;
        confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';
      }
    });

    function openResolveModalFromView() {
      closeModal('viewReportModal');
      setTimeout(() => openResolveModal(currentReportId), 300);
    }

    function openInProgressModalFromView() {
      closeModal('viewReportModal');
      setTimeout(() => openInProgressModal(currentReportId), 300);
    }

    if (selectedReportId) {
      const openSelectedReport = () => openReportView(selectedReportId);
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openSelectedReport, { once: true });
      } else {
        openSelectedReport();
      }
    }

    // Search functionality (now handled by controller, but keeping for client-side if needed)
    document.getElementById('reportSearchInput')?.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        this.closest('form')?.submit();
      }
    });
  </script>
@endpush
