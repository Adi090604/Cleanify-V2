@extends('layouts.admin')

@section('title', 'Service Area Requests')

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    .service-area-review-map { height: 240px; border: 1px solid #d1d5db; border-radius: .5rem; }
  </style>
@endpush

@section('content')
  <div class="admin-page-header mb-6 flex flex-col items-start justify-between gap-4 lg:flex-row lg:items-center">
    <div>
      <h2 class="text-3xl font-bold text-gray-800">
        <i class="fas fa-map-marked-alt mr-3 text-green-600"></i>Service Area Requests
      </h2>
      <p class="mt-1 text-gray-600">Review resident requests for areas not currently available in Cleanify.</p>
    </div>
    <form method="GET" action="{{ route('admin.service-area-requests') }}" class="flex w-full max-w-lg lg:w-auto">
      <input type="hidden" name="status" value="{{ $statusFilter }}">
      <input name="search" type="search" value="{{ $search }}" class="flex-1 rounded-l-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Search resident or area...">
      <button type="submit" class="rounded-r-lg bg-green-600 px-4 text-white transition-colors duration-300 hover:bg-green-700" aria-label="Search service area requests">
        <i class="fas fa-search"></i>
      </button>
      @if($search !== '')
        <a href="{{ route('admin.service-area-requests', ['status' => $statusFilter]) }}" class="ml-2 rounded-lg bg-gray-500 px-4 py-2 text-white hover:bg-gray-600" aria-label="Clear search"><i class="fas fa-times"></i></a>
      @endif
    </form>
  </div>

  @if(session('success'))
    <x-alert type="success" dismissible class="mb-4">{{ session('success') }}</x-alert>
  @endif
  @if($errors->any())
    <x-alert type="error" dismissible class="mb-4">
      <ul class="list-inside list-disc text-sm">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </x-alert>
  @endif

  <div class="admin-stat-grid mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <x-admin.stat-card icon="fas fa-inbox" title="All Requests" :value="$totalRequests" />
    <x-admin.stat-card icon="fas fa-clock" title="Pending" :value="$pendingRequests" borderClass="border-l-4 border-yellow-500" iconWrapperClass="bg-yellow-100" iconColorClass="text-yellow-600" />
    <x-admin.stat-card icon="fas fa-check-circle" title="Approved" :value="$approvedRequests" borderClass="border-l-4 border-green-500" iconWrapperClass="bg-green-100" iconColorClass="text-green-600" />
    <x-admin.stat-card icon="fas fa-times-circle" title="Rejected" :value="$rejectedRequests" borderClass="border-l-4 border-red-500" iconWrapperClass="bg-red-100" iconColorClass="text-red-600" />
  </div>

  <div class="admin-toolbar-card mb-4 flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 shadow-sm">
    <form method="GET" action="{{ route('admin.service-area-requests') }}" class="flex flex-wrap items-center gap-3">
      <input type="hidden" name="search" value="{{ $search }}">
      <label class="flex items-center gap-2 text-sm text-gray-600">
        <span>Status</span>
        <select name="status" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:ring-green-500" onchange="this.form.submit()">
          <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All</option>
          <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
          <option value="approved" {{ $statusFilter === 'approved' ? 'selected' : '' }}>Approved</option>
          <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Rejected</option>
        </select>
      </label>
    </form>
  </div>

  <div class="admin-table-card overflow-hidden rounded-xl bg-white shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="admin-table-header">
            <th class="px-4 py-3 text-left text-sm font-semibold">Resident</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Requested Area</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Submitted</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
            <th class="px-4 py-3 text-left text-sm font-semibold">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          @forelse($requests as $serviceAreaRequest)
            <tr class="transition-colors duration-200 hover:bg-gray-50">
              <td class="px-4 py-3">
                <p class="font-medium text-gray-900">{{ $serviceAreaRequest->user?->name ?? 'Deleted resident' }}</p>
                <p class="text-xs text-gray-500">{{ $serviceAreaRequest->user?->email }}</p>
              </td>
              <td class="px-4 py-3">
                <p class="font-medium text-gray-900">{{ $serviceAreaRequest->area_name }}</p>
                <p class="text-xs text-gray-500">{{ $serviceAreaRequest->barangay ?: 'No barangay provided' }}</p>
                @if($serviceAreaRequest->latitude !== null && $serviceAreaRequest->longitude !== null)
                  <span class="mt-1 inline-flex items-center gap-1 text-xs text-green-700"><i class="fas fa-map-marker-alt"></i>Location shared</span>
                @endif
                @if($serviceAreaRequest->serviceZone)
                  <span class="mt-1 block text-xs font-medium text-blue-700">Linked: {{ $serviceAreaRequest->serviceZone->display_name }}</span>
                @endif
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ $serviceAreaRequest->created_at->format('M d, Y') }}
                <br><span class="text-xs text-gray-400">{{ $serviceAreaRequest->created_at->format('g:i A') }}</span>
              </td>
              <td class="px-4 py-3">
                <span class="admin-status-badge {{ $serviceAreaRequest->getStatusBadgeClass() }}">{{ $serviceAreaRequest->status_label }}</span>
              </td>
              <td class="px-4 py-3">
                <button type="button" onclick="openServiceAreaRequestDetail({{ $serviceAreaRequest->id }}, @js($serviceAreaRequest->latitude), @js($serviceAreaRequest->longitude))" class="admin-icon-button bg-blue-500 text-white hover:bg-blue-600" title="View / Review">
                  <i class="fas fa-eye text-xs"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-map-marked-alt mb-2 block text-4xl"></i>No service area requests found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="border-t border-gray-200 px-6 py-4">
      <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
        <p class="text-sm text-gray-600">
          @if($requests->total() > 0)
            Showing {{ $requests->firstItem() }} to {{ $requests->lastItem() }} of {{ $requests->total() }} requests
          @else
            No requests found
          @endif
        </p>
        {{ $requests->links('pagination::tailwind') }}
      </div>
    </div>
  </div>
@endsection

@push('modals')
  @foreach($requests as $serviceAreaRequest)
    @php
      $hasLinkedServiceZone = $serviceAreaRequest->serviceZone !== null;
      $hasZoneSetupHistory = (bool) $serviceAreaRequest->has_zone_setup_history;
      $createZoneLabel = $hasZoneSetupHistory ? 'Create Service Zone Again' : 'Create Service Zone';
    @endphp
    <x-modal id="serviceAreaRequestDetail{{ $serviceAreaRequest->id }}" title="Service Area Request Details" icon="fas fa-map-marked-alt" color="green">
      <div class="space-y-5">
        <section>
          <h6 class="mb-2 font-semibold text-gray-800"><i class="fas fa-user mr-2 text-green-600"></i>Resident Information</h6>
          <dl class="grid grid-cols-1 gap-2 rounded-lg bg-gray-50 p-3 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Name</dt><dd class="font-medium text-gray-800">{{ $serviceAreaRequest->user?->name ?? 'Deleted resident' }}</dd></div>
            <div><dt class="text-gray-500">Email</dt><dd class="break-all font-medium text-gray-800">{{ $serviceAreaRequest->user?->email ?? 'Unavailable' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-gray-500">Current Service Area</dt><dd class="font-medium text-gray-800">{{ $serviceAreaRequest->user?->service_area ?: 'None selected' }}</dd></div>
          </dl>
        </section>

        <section>
          <h6 class="mb-2 font-semibold text-gray-800"><i class="fas fa-map-signs mr-2 text-green-600"></i>Requested Area</h6>
          <dl class="space-y-2 rounded-lg border border-gray-200 p-3 text-sm">
            <div><dt class="text-gray-500">Area / Purok</dt><dd class="font-medium text-gray-800">{{ $serviceAreaRequest->area_name }}</dd></div>
            <div><dt class="text-gray-500">Barangay</dt><dd class="text-gray-800">{{ $serviceAreaRequest->barangay ?: 'Not provided' }}</dd></div>
            <div><dt class="text-gray-500">Address / Landmark</dt><dd class="text-gray-800">{{ $serviceAreaRequest->address ?: 'Not provided' }}</dd></div>
            <div><dt class="text-gray-500">Additional Details</dt><dd class="whitespace-pre-line text-gray-800">{{ $serviceAreaRequest->details ?: 'No additional details provided.' }}</dd></div>
          </dl>
        </section>

        <section>
          <h6 class="mb-2 font-semibold text-gray-800"><i class="fas fa-location-dot mr-2 text-green-600"></i>Location</h6>
          @if($serviceAreaRequest->latitude !== null && $serviceAreaRequest->longitude !== null)
            <p class="mb-2 text-xs text-gray-500">{{ number_format((float) $serviceAreaRequest->latitude, 6) }}, {{ number_format((float) $serviceAreaRequest->longitude, 6) }}</p>
            <div id="serviceAreaRequestMap{{ $serviceAreaRequest->id }}" class="service-area-review-map w-full" aria-label="Submitted request location map"></div>
          @else
            <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-600">No location was shared with this request.</div>
          @endif
        </section>

        <section>
          <h6 class="mb-2 font-semibold text-gray-800"><i class="fas fa-clipboard-check mr-2 text-green-600"></i>Request Information</h6>
          <dl class="grid grid-cols-1 gap-2 rounded-lg bg-gray-50 p-3 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Status</dt><dd><span class="admin-status-badge {{ $serviceAreaRequest->getStatusBadgeClass() }}">{{ $serviceAreaRequest->status_label }}</span></dd></div>
            <div><dt class="text-gray-500">Submitted</dt><dd class="text-gray-800">{{ $serviceAreaRequest->created_at->format('M d, Y g:i A') }}</dd></div>
            @if($serviceAreaRequest->status !== 'pending')
              <div><dt class="text-gray-500">Reviewed By</dt><dd class="text-gray-800">{{ $serviceAreaRequest->reviewer?->name ?? 'Former administrator' }}</dd></div>
              <div><dt class="text-gray-500">Reviewed At</dt><dd class="text-gray-800">{{ $serviceAreaRequest->reviewed_at?->format('M d, Y g:i A') ?? 'Unavailable' }}</dd></div>
              <div class="sm:col-span-2"><dt class="text-gray-500">Admin Notes</dt><dd class="whitespace-pre-line text-gray-800">{{ $serviceAreaRequest->admin_notes ?: 'No notes provided.' }}</dd></div>
            @endif
          </dl>
        </section>

        @if($hasLinkedServiceZone)
          @php
            $requesterHasLinkedZone = $serviceAreaRequest->user?->service_area === $serviceAreaRequest->serviceZone->display_name;
          @endphp
          <section>
            <h6 class="mb-2 font-semibold text-gray-800"><i class="fas fa-draw-polygon mr-2 text-green-600"></i>Linked Service Zone</h6>
            <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm">
              <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p class="font-semibold text-gray-900">{{ $serviceAreaRequest->serviceZone->display_name }}</p>
                  <p class="text-xs text-gray-600">Zone status: {{ ucfirst($serviceAreaRequest->serviceZone->status) }}</p>
                </div>
                <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $requesterHasLinkedZone ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                  Resident Assignment: {{ $requesterHasLinkedZone ? 'Assigned' : 'Not Assigned' }}
                </span>
              </div>
              <a href="{{ route('admin.schedule') }}" class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-green-700 hover:text-green-800">
                <i class="fas fa-calendar-alt"></i>Manage Collection Schedule
              </a>
            </div>
          </section>
        @elseif($serviceAreaRequest->status === 'approved')
          <section>
            <h6 class="mb-2 font-semibold text-gray-800"><i class="fas fa-draw-polygon mr-2 text-amber-600"></i>Linked Service Zone</h6>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
              <p class="font-semibold">Service Zone Setup Needed</p>
              <p class="mt-1"><span class="font-medium">Linked Service Zone:</span> None</p>
              <p class="mt-1">No service zone currently linked.</p>
              <p class="mt-1 text-xs text-amber-800">This request was previously approved and needs a Service Zone to be created. It does not need to be approved again.</p>
            </div>
          </section>
        @endif
      </div>

      @slot('footer')
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <button type="button" onclick="closeModal('serviceAreaRequestDetail{{ $serviceAreaRequest->id }}')" class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50">Close</button>
          @if($serviceAreaRequest->status === 'pending')
            <button type="button" onclick="closeModal('serviceAreaRequestDetail{{ $serviceAreaRequest->id }}'); openModal('rejectServiceAreaRequest{{ $serviceAreaRequest->id }}')" class="rounded-lg bg-red-600 px-4 py-2 text-white hover:bg-red-700"><i class="fas fa-times mr-2"></i>Reject Request</button>
            <button type="button" onclick="closeModal('serviceAreaRequestDetail{{ $serviceAreaRequest->id }}'); openModal('approveServiceAreaRequest{{ $serviceAreaRequest->id }}')" class="rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700"><i class="fas fa-check mr-2"></i>Approve Request</button>
          @elseif($serviceAreaRequest->status === 'approved' && ! $hasLinkedServiceZone)
            <button type="button" onclick="closeModal('serviceAreaRequestDetail{{ $serviceAreaRequest->id }}'); openModal('setupServiceAreaRequest{{ $serviceAreaRequest->id }}')" class="rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700"><i class="fas fa-plus mr-2"></i>{{ $createZoneLabel }}</button>
          @endif
        </div>
      @endslot
    </x-modal>

    @if($serviceAreaRequest->status === 'pending')
      <x-modal id="approveServiceAreaRequest{{ $serviceAreaRequest->id }}" title="Approve Service Area Request" icon="fas fa-check-circle" color="green" variant="confirmation">
        <p>Approve <strong>{{ $serviceAreaRequest->area_name }}</strong> for service-area consideration?</p>
        <p class="mt-2 text-sm text-gray-600">Approval does not create a Service Zone or collection schedule.</p>
        <form id="approveServiceAreaRequestForm{{ $serviceAreaRequest->id }}" method="POST" action="{{ route('admin.service-area-requests.approve', $serviceAreaRequest) }}" class="mt-4">
          @csrf
          <label class="mb-1 block text-sm font-medium text-gray-700" for="approveNotes{{ $serviceAreaRequest->id }}">Admin Notes <span class="font-normal text-gray-400">(optional)</span></label>
          <textarea id="approveNotes{{ $serviceAreaRequest->id }}" name="admin_notes" maxlength="1000" rows="3" class="w-full resize-none rounded-lg border border-gray-300 px-3 py-2 focus:ring-green-500"></textarea>
        </form>
        @slot('footer')
          <div class="admin-confirm-actions">
            <button type="button" onclick="closeModal('approveServiceAreaRequest{{ $serviceAreaRequest->id }}')" class="admin-btn-secondary">Cancel</button>
            <button type="submit" form="approveServiceAreaRequestForm{{ $serviceAreaRequest->id }}" class="rounded-lg bg-green-600 px-4 py-2 font-medium text-white hover:bg-green-700">Approve Request</button>
          </div>
        @endslot
      </x-modal>

      <x-modal id="rejectServiceAreaRequest{{ $serviceAreaRequest->id }}" title="Reject Service Area Request" icon="fas fa-times-circle" color="red" variant="confirmation">
        <p>Reject <strong>{{ $serviceAreaRequest->area_name }}</strong>? The request will remain in the administrative record.</p>
        <form id="rejectServiceAreaRequestForm{{ $serviceAreaRequest->id }}" method="POST" action="{{ route('admin.service-area-requests.reject', $serviceAreaRequest) }}" class="mt-4">
          @csrf
          <label class="mb-1 block text-sm font-medium text-gray-700" for="rejectNotes{{ $serviceAreaRequest->id }}">Reason for Rejection <span class="text-red-600">*</span></label>
          <textarea id="rejectNotes{{ $serviceAreaRequest->id }}" name="admin_notes" minlength="10" maxlength="1000" rows="3" required class="w-full resize-none rounded-lg border border-gray-300 px-3 py-2 focus:ring-red-500" placeholder="Explain why this request was rejected."></textarea>
        </form>
        @slot('footer')
          <div class="admin-confirm-actions">
            <button type="button" onclick="closeModal('rejectServiceAreaRequest{{ $serviceAreaRequest->id }}')" class="admin-btn-secondary">Cancel</button>
            <button type="submit" form="rejectServiceAreaRequestForm{{ $serviceAreaRequest->id }}" class="admin-btn-danger">Reject Request</button>
          </div>
        @endslot
      </x-modal>
    @endif

    @if($serviceAreaRequest->status === 'approved' && ! $hasLinkedServiceZone)
      <x-modal id="setupServiceAreaRequest{{ $serviceAreaRequest->id }}" title="{{ $createZoneLabel }}" icon="fas fa-plus-circle" color="green">
        @php
          $recoveryRequestId = (int) old('service_area_request_id');
          $isCreateRecoveryError = $errors->any()
            && $recoveryRequestId === $serviceAreaRequest->id
            && old('recovery_action') === 'create';
        @endphp
        <p class="mb-4 text-sm text-gray-600">Create an official Service Zone using the resident's submitted details as editable defaults.</p>

        <section class="rounded-lg border border-gray-200 p-4">
          <h6 class="font-semibold text-gray-800">{{ $createZoneLabel }}</h6>
          <div class="mt-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-xs text-yellow-800">
            Zone names must be unique. Review existing Service Zones before creating another one.
          </div>
          <form id="createServiceZoneFromRequestForm{{ $serviceAreaRequest->id }}" method="POST" action="{{ route('admin.service-area-requests.create-zone', $serviceAreaRequest) }}" class="mt-3 space-y-3">
            @csrf
            <input type="hidden" name="recovery_action" value="create">
            <input type="hidden" name="service_area_request_id" value="{{ $serviceAreaRequest->id }}">
            @if($isCreateRecoveryError)
              <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
                <ul class="list-inside list-disc">
                  @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                  @endforeach
                </ul>
              </div>
            @endif
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label for="newServiceZoneName{{ $serviceAreaRequest->id }}" class="mb-1 block text-sm font-medium text-gray-700">Zone Name</label>
                <input id="newServiceZoneName{{ $serviceAreaRequest->id }}" name="name" value="{{ $isCreateRecoveryError ? old('name') : $serviceAreaRequest->area_name }}" maxlength="255" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-green-500">
              </div>
              <div>
                <label for="newServiceZoneBarangay{{ $serviceAreaRequest->id }}" class="mb-1 block text-sm font-medium text-gray-700">Barangay</label>
                <input id="newServiceZoneBarangay{{ $serviceAreaRequest->id }}" name="barangay" value="{{ $isCreateRecoveryError ? old('barangay') : $serviceAreaRequest->barangay }}" maxlength="255" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-green-500">
              </div>
              <div>
                <label for="newServiceZoneLatitude{{ $serviceAreaRequest->id }}" class="mb-1 block text-sm font-medium text-gray-700">Latitude <span class="font-normal text-gray-400">(optional)</span></label>
                <input id="newServiceZoneLatitude{{ $serviceAreaRequest->id }}" name="latitude" value="{{ $isCreateRecoveryError ? old('latitude') : $serviceAreaRequest->latitude }}" type="number" step="any" min="-90" max="90" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-green-500">
              </div>
              <div>
                <label for="newServiceZoneLongitude{{ $serviceAreaRequest->id }}" class="mb-1 block text-sm font-medium text-gray-700">Longitude <span class="font-normal text-gray-400">(optional)</span></label>
                <input id="newServiceZoneLongitude{{ $serviceAreaRequest->id }}" name="longitude" value="{{ $isCreateRecoveryError ? old('longitude') : $serviceAreaRequest->longitude }}" type="number" step="any" min="-180" max="180" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-green-500">
              </div>
            </div>
            <div>
              <label for="newServiceZoneStatus{{ $serviceAreaRequest->id }}" class="mb-1 block text-sm font-medium text-gray-700">Zone Status</label>
              <select id="newServiceZoneStatus{{ $serviceAreaRequest->id }}" name="status" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-green-500">
                <option value="active" @selected(! $isCreateRecoveryError || old('status') === 'active')>Active</option>
                <option value="inactive" {{ $isCreateRecoveryError && old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>
            <label class="flex items-start gap-2 text-sm text-gray-700">
              <input type="checkbox" name="assign_to_requester" value="1" @checked(! $isCreateRecoveryError || old('assign_to_requester')) class="mt-0.5 rounded border-gray-300 text-green-600 focus:ring-green-500">
              <span>Assign this Service Zone to the requesting resident using its official display name.</span>
            </label>
            <button type="submit" form="createServiceZoneFromRequestForm{{ $serviceAreaRequest->id }}" class="w-full rounded-lg bg-green-600 px-4 py-2 font-medium text-white hover:bg-green-700">{{ $createZoneLabel }}</button>
          </form>
        </section>

        @slot('footer')
          <div class="flex justify-end">
            <button type="button" onclick="closeModal('setupServiceAreaRequest{{ $serviceAreaRequest->id }}')" class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50">Cancel</button>
          </div>
        @endslot
      </x-modal>
    @endif

  @endforeach
@endpush

@push('scripts')
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    const serviceAreaRequestMaps = {};

    window.openServiceAreaRequestDetail = function(id, latitude, longitude) {
      openModal(`serviceAreaRequestDetail${id}`);
      if (latitude === null || longitude === null || typeof L === 'undefined') return;

      setTimeout(() => {
        if (!serviceAreaRequestMaps[id]) {
          const map = L.map(`serviceAreaRequestMap${id}`, { scrollWheelZoom: false }).setView([Number(latitude), Number(longitude)], 16);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
          }).addTo(map);
          L.marker([Number(latitude), Number(longitude)]).addTo(map);
          serviceAreaRequestMaps[id] = map;
        }
        serviceAreaRequestMaps[id].invalidateSize();
      }, 100);
    };

    @if($errors->any() && old('service_area_request_id') && old('recovery_action') === 'create')
      document.addEventListener('DOMContentLoaded', function() {
        openModal('setupServiceAreaRequest{{ (int) old('service_area_request_id') }}');
      });
    @endif
  </script>
@endpush
