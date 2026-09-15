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

  <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
    <div>
      <h2 class="text-3xl font-bold text-gray-800">
        <i class="fas fa-flag text-green-600 mr-3"></i>Reports Management
      </h2>
      <p class="text-gray-600 mt-1">Manage and resolve community reports</p>
    </div>
    <div class="flex w-full lg:w-auto max-w-lg">
      <form action="{{ route('admin.reports') }}" method="GET" class="flex w-full">
        <input id="reportSearchInput" type="text" name="search" value="{{ $search }}" class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Search reports...">
        <button type="submit" class="px-4 bg-green-600 text-white rounded-r-lg hover:bg-green-700 transition-colors duration-300">
          <i class="fas fa-search"></i>
        </button>
      </form>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <x-admin.stat-card icon="fas fa-clock" title="Pending Reports" value="{{ $pendingReports }}" borderClass="border-l-4 border-yellow-500" iconWrapperClass="bg-yellow-100" iconColorClass="text-yellow-600" />
    <x-admin.stat-card icon="fas fa-check-circle" title="Resolved" value="{{ $resolvedReports }}" borderClass="border-l-4 border-green-500" iconWrapperClass="bg-green-100" iconColorClass="text-green-600" />
    <x-admin.stat-card icon="fas fa-times-circle" title="Rejected" value="{{ $rejectedReports }}" borderClass="border-l-4 border-red-500" iconWrapperClass="bg-red-100" iconColorClass="text-red-600" />
    <x-admin.stat-card icon="fas fa-chart-line" title="This Month" value="{{ $thisMonthReports }}" borderClass="border-l-4 border-blue-500" iconWrapperClass="bg-blue-100" iconColorClass="text-blue-600" />
  </div>

  <div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full" id="reportsTable">
        <thead>
          <tr class="bg-green-600 text-white">
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
                <span class="{{ $report->getStatusBadgeClass() }} font-semibold">
                  <i class="fas fa-circle text-xs mr-1"></i>{{ ucfirst($report->status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ $report->created_at->format('M d, Y') }}</td>
              <td class="px-4 py-3">
                <div class="flex space-x-2">
                  @if($report->status === 'pending')
                    <button onclick="openResolveModal({{ $report->id }})" class="w-8 h-8 bg-green-500 text-white rounded hover:bg-green-600 transition-colors duration-300" title="Resolve">
                      <i class="fas fa-check text-xs"></i>
                    </button>
                    <button onclick="openRejectModal({{ $report->id }})" class="w-8 h-8 bg-red-500 text-white rounded hover:bg-red-600 transition-colors duration-300" title="Reject">
                      <i class="fas fa-times text-xs"></i>
                    </button>
                  @endif
                  <button onclick="openReportView({{ $report->id }})" class="w-8 h-8 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors duration-300" title="View">
                    <i class="fas fa-eye text-xs"></i>
                  </button>
                  <form method="POST" action="{{ route('admin.reports.destroy', $report) }}" onsubmit="return confirm('Permanently delete this report? This action cannot be undone.');">
                    @csrf
                    @method('DELETE')
                                        <button type="submit" class="w-8 h-8 bg-red-500 text-white rounded hover:bg-red-600 transition-colors duration-300" title="Delete permanently">
                      <i class="fas fa-trash-alt text-xs"></i>
                    </button>
                  </form>
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
          {{ $reports->appends(['search' => $search, 'status' => $statusFilter])->links('pagination::tailwind') }}
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
        <button onclick="openResolveModalFromView()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
          <i class="fas fa-check mr-2"></i>Mark as Resolved
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

  <x-modal id="rejectReportModal" title="Reject Report" icon="fas fa-times-circle" color="red">
    <form id="rejectReportForm" method="POST">
      @csrf
      <div class="text-center mb-4">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-exclamation text-red-600 text-2xl"></i>
        </div>
        <h4 class="text-xl font-semibold text-gray-800 mb-2">Reject Report</h4>
        <p class="text-gray-600">Are you sure you want to reject this report?</p>
      </div>
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          <i class="fas fa-comment mr-2 text-red-600"></i>Rejection Reason (Required)
        </label>
        <textarea name="rejection_reason" id="rejectReason" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none" rows="3" minlength="10" placeholder="Please provide a reason for rejecting this report..." required></textarea>
        @error('rejection_reason', 'rejectReport')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4 flex">
        <i class="fas fa-exclamation-triangle text-yellow-400 mr-3 mt-1"></i>
        <p class="text-sm text-yellow-700">
          This action cannot be undone. The reporter will be notified about the rejection.
        </p>
      </div>
    </form>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('rejectReportModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Cancel
        </button>
        <button type="submit" form="rejectReportForm" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Reject Report
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
                         report.status === 'resolved' ? 'text-green-600' : 'text-red-600';
      statusElement.className = `mt-1 font-semibold ${statusClass}`;
      statusElement.innerHTML = `<i class="fas fa-circle text-xs mr-1"></i>${report.status.charAt(0).toUpperCase() + report.status.slice(1)}`;
      
      document.getElementById('viewReportDate').textContent = report.created_at;
      document.getElementById('viewReportDescription').textContent = report.description;
      
      // Show/hide resolve button based on status
      const resolveBtn = document.querySelector('#viewReportModal [onclick="openResolveModalFromView()"]');
      if (resolveBtn) {
        resolveBtn.style.display = report.status === 'pending' ? 'inline-block' : 'none';
      }
      
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

    function openRejectModal(id) {
      currentReportId = id;
      const form = document.getElementById('rejectReportForm');
      form.action = `/admin/reports/${id}/reject`;
      document.getElementById('rejectReason').value = '';
      openModal('rejectReportModal');
    }

    function openResolveModalFromView() {
      closeModal('viewReportModal');
      setTimeout(() => openResolveModal(currentReportId), 300);
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
