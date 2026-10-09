@extends('layouts.app')

@section('title', 'Settings')

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    .service-area-request-map { height: 230px; border: 1px solid #d1d5db; border-radius: .5rem; }
  </style>
@endpush

@section('content')
  @if(session('success'))
    <x-alert type="success" dismissible class="mb-4">
      {{ session('success') }}
    </x-alert>
  @endif

  <h3 class="mb-6 text-gray-800 font-semibold">
    <i class="fas fa-cog text-green-600 mr-3"></i>Account Settings
  </h3>

  <!-- Account Information Section -->
  <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h5 class="text-green-600 font-semibold text-lg mb-4 border-b-2 border-green-600 pb-2">
      <i class="fas fa-user-lines mr-2"></i>Account Information
    </h5>
    <form id="accountForm" method="POST" action="{{ route('settings.account') }}">
      @csrf
      @method('PATCH')
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Email Address</label>
        <input type="email" name="email" value="{{ $user->email }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent @error('email') border-red-500 @enderror" required>
        @error('email')
          <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
      </div>
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Phone Number</label>
        <input type="text" name="phone" value="{{ $user->phone ?? '' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="+63 912 345 6789">
      </div>
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Service Area</label>
        <select name="service_area" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
          <option value="">Select your area</option>
          @foreach($availableAreas as $area)
            <option value="{{ $area }}" {{ $user->service_area === $area ? 'selected' : '' }}>{{ $area }}</option>
          @endforeach
        </select>
        <p class="text-sm text-gray-500 mt-1">This helps us show you relevant schedules and updates.</p>
        <div class="mt-3 flex flex-col gap-2 rounded-lg border border-green-200 bg-green-50 p-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-sm font-medium text-gray-800">Can't find your area?</p>
            <p class="text-xs text-gray-600">Send your area details for administrator review.</p>
          </div>
          <button type="button" id="openServiceAreaRequestButton" onclick="openServiceAreaRequestModal()" class="inline-flex items-center justify-center gap-2 rounded-lg border border-green-600 px-4 py-2 text-sm font-medium text-green-700 transition-colors duration-300 hover:bg-green-600 hover:text-white">
            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
            My area is not listed
          </button>
        </div>
      </div>
      <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-check-circle mr-2"></i>Save Changes
      </button>
    </form>

    @if($latestServiceAreaRequest)
      @php
        $requestStatus = match ($latestServiceAreaRequest->status) {
            'approved' => ['label' => 'Approved', 'classes' => 'bg-green-100 text-green-800', 'message' => 'Your requested area has been approved. Check the Garbage Schedule page for any available collection schedule.'],
            'rejected' => ['label' => 'Rejected', 'classes' => 'bg-red-100 text-red-800', 'message' => 'Your request was not approved. You may submit a new request if your area details have changed.'],
            default => ['label' => 'Pending Review', 'classes' => 'bg-yellow-100 text-yellow-800', 'message' => 'Your request has been submitted and is waiting for administrator review.'],
        };
      @endphp
      <section aria-labelledby="serviceAreaRequestStatusHeading" class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h6 id="serviceAreaRequestStatusHeading" class="font-semibold text-gray-800">Service Area Request</h6>
            <p class="mt-1 text-sm text-gray-700">
              {{ $latestServiceAreaRequest->area_name }}@if($latestServiceAreaRequest->barangay), {{ $latestServiceAreaRequest->barangay }}@endif
            </p>
          </div>
          <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $requestStatus['classes'] }}">
            {{ $requestStatus['label'] }}
          </span>
        </div>
        <p class="mt-3 text-sm text-gray-600">{{ $requestStatus['message'] }}</p>
        <p class="mt-2 text-xs text-gray-500">Submitting a request does not automatically create a collection schedule. The area will first be reviewed by the administrator.</p>
      </section>
    @endif
  </div>

  <!-- Password Change Section -->
  <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h5 class="text-green-600 font-semibold text-lg mb-4 border-b-2 border-green-600 pb-2">
      <i class="fas fa-lock mr-2"></i>Change Password
    </h5>
    <form id="passwordForm" method="POST" action="{{ route('settings.password') }}">
      @csrf
      @method('PATCH')
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Current Password</label>
        <input type="password" name="current_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent @error('current_password') border-red-500 @enderror" required>
        @error('current_password')
          <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
      </div>
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">New Password</label>
        <input type="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent @error('password') border-red-500 @enderror" required>
        @error('password')
          <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
        <p class="text-sm text-gray-500 mt-1">Must be at least 8 characters long.</p>
      </div>
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Confirm New Password</label>
        <input type="password" name="password_confirmation" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
      </div>
      <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-key mr-2"></i>Update Password
      </button>
    </form>
  </div>

  <!-- Notification Preferences Section -->
  <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h5 class="text-green-600 font-semibold text-lg mb-4 border-b-2 border-green-600 pb-2">
      <i class="fas fa-bell mr-2"></i>Notification Preferences
    </h5>
    
    <form id="notificationsForm" method="POST" action="{{ route('settings.notifications') }}">
      @csrf
      @method('PATCH')

      @if($errors->hasAny(['email_notifications', 'sms_notifications', 'preferences', 'preferences.report_updates', 'preferences.schedule_reminders']))
        <x-alert type="error" class="mb-4">
          Please review the notification preference errors and try again.
        </x-alert>
      @endif
      
      <!-- Global Notification Toggles -->
      <div class="mb-6">
        <h6 class="text-gray-700 font-semibold mb-3">Global Settings</h6>
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <label for="email_notifications" class="text-gray-700 cursor-pointer">Email Notifications</label>
              <p class="text-sm text-gray-500">Receive notifications via email</p>
            </div>
            <div>
              <input type="hidden" name="email_notifications" value="0">
              <label for="email_notifications" class="relative inline-block w-12 h-6 cursor-pointer">
                <input type="checkbox" name="email_notifications" id="email_notifications" value="1" class="sr-only peer" {{ old('email_notifications', $user->email_notifications) ? 'checked' : '' }}>
                <span class="block w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></span>
                <span class="pointer-events-none absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></span>
              </label>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div>
              <label for="sms_notifications" class="text-gray-700 cursor-pointer">SMS Notifications</label>
              @if(config('sms.driver') === 'twilio')
                <p class="text-sm text-gray-500">Receive garbage pickup reminders via SMS</p>
              @else
                <p class="text-sm text-gray-500">SMS delivery is currently in simulation mode. Your preference will apply when SMS delivery is configured.</p>
              @endif
            </div>
            <div>
              <input type="hidden" name="sms_notifications" value="0">
              <label for="sms_notifications" class="relative inline-block w-12 h-6 cursor-pointer">
                <input type="checkbox" name="sms_notifications" id="sms_notifications" value="1" class="sr-only peer" {{ old('sms_notifications', $user->sms_notifications) ? 'checked' : '' }}>
                <span class="block w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></span>
                <span class="pointer-events-none absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></span>
              </label>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div>
              <div class="flex items-center gap-2">
                <span class="text-gray-500">Push Notifications</span>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Unavailable</span>
              </div>
              <p class="text-sm text-gray-500">Browser push notifications are not currently supported</p>
            </div>
            <div class="relative inline-block w-12 h-6">
              <input type="checkbox" aria-label="Push Notifications unavailable" class="sr-only peer" disabled>
              <div class="w-12 h-6 bg-gray-200 rounded-full cursor-not-allowed"></div>
              <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Category-Specific Preferences -->
      <div class="mb-6">
        <h6 class="text-gray-700 font-semibold mb-3">Notification Categories</h6>
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <label for="pref_report_updates" class="text-gray-700 cursor-pointer">Report Updates</label>
              <p class="text-sm text-gray-500">When your reports are resolved or rejected</p>
            </div>
            <div>
              <input type="hidden" name="preferences[report_updates]" value="0">
              <label for="pref_report_updates" class="relative inline-block w-12 h-6 cursor-pointer">
                <input type="checkbox" name="preferences[report_updates]" id="pref_report_updates" value="1" class="sr-only peer" {{ old('preferences.report_updates', $notificationPrefs['report_updates'] ?? true) ? 'checked' : '' }}>
                <span class="block w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></span>
                <span class="pointer-events-none absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></span>
              </label>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div>
              <label for="pref_schedule_reminders" class="text-gray-700 cursor-pointer">Schedule Reminders</label>
              <p class="text-sm text-gray-500">Garbage pickup reminders for your area</p>
            </div>
            <div>
              <input type="hidden" name="preferences[schedule_reminders]" value="0">
              <label for="pref_schedule_reminders" class="relative inline-block w-12 h-6 cursor-pointer">
                <input type="checkbox" name="preferences[schedule_reminders]" id="pref_schedule_reminders" value="1" class="sr-only peer" {{ old('preferences.schedule_reminders', $notificationPrefs['schedule_reminders'] ?? true) ? 'checked' : '' }}>
                <span class="block w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></span>
                <span class="pointer-events-none absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-save mr-2"></i>Save Preferences
      </button>
    </form>
  </div>

  <!-- Danger Zone Section -->
  <div class="bg-white rounded-xl shadow-sm p-6 border border-red-200">
    <h5 class="text-red-600 font-semibold text-lg mb-4 border-b-2 border-red-600 pb-2">
      <i class="fas fa-exclamation-triangle mr-2"></i>Danger Zone
    </h5>
    <div class="mb-4">
      <p class="text-gray-600 mb-2">Deleting your account is permanent and cannot be undone. All your data will be lost:</p>
      <ul class="list-disc list-inside text-gray-600 space-y-1 mb-4">
        <li><strong>{{ $userStats['reports_count'] }}</strong> reports you've submitted</li>
        <li><strong>{{ $userStats['likes_count'] }}</strong> likes you've given</li>
        <li><strong>{{ $userStats['comments_count'] }}</strong> comments you've made</li>
        <li>All your account information and preferences</li>
      </ul>
    </div>
    <button onclick="openModal('deleteModal')" class="px-6 py-2 border border-red-600 text-red-600 rounded-lg hover:bg-red-600 hover:text-white transition-colors duration-300">
      <i class="fas fa-trash mr-2"></i>Delete My Account
    </button>
  </div>
@endsection

@push('modals')
  @php
    $serviceAreaRequestHasErrors = $errors->hasAny([
        'area_name', 'barangay', 'address', 'latitude', 'longitude', 'details',
    ]);
  @endphp

  <x-modal id="serviceAreaRequestModal" title="Request Service for Your Area" icon="fas fa-map-marked-alt" color="green">
    <div class="mb-4">
      <p class="text-sm text-gray-700">Can't find your area in our service areas? Send your location and area details for review.</p>
      @if($pendingServiceAreaRequest)
        <div class="mt-3 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
          <i class="fas fa-clock mr-1" aria-hidden="true"></i>
          You already have a pending request for <strong>{{ $pendingServiceAreaRequest->area_name }}</strong>. You cannot submit the same area again while it is pending.
        </div>
      @endif
    </div>

    <form id="serviceAreaRequestForm" method="POST" action="{{ route('settings.service-area-requests.store') }}" class="space-y-4">
      @csrf
      <div>
        <label for="serviceAreaRequestName" class="mb-1 block text-sm font-medium text-gray-700">Area / Purok <span class="text-red-600">*</span></label>
        <input id="serviceAreaRequestName" name="area_name" value="{{ old('area_name') }}" maxlength="255" required class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-green-500 @error('area_name') border-red-500 @enderror" placeholder="e.g. Purok 8">
        @error('area_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        <p id="serviceAreaRequestDuplicateError" class="mt-1 hidden text-sm text-red-600" role="alert">You already have a pending request for this service area.</p>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label for="serviceAreaRequestBarangay" class="mb-1 block text-sm font-medium text-gray-700">Barangay <span class="font-normal text-gray-400">(optional)</span></label>
          <input id="serviceAreaRequestBarangay" name="barangay" value="{{ old('barangay') }}" maxlength="255" class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-green-500 @error('barangay') border-red-500 @enderror" placeholder="e.g. Barangay Washington">
          @error('barangay')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
          <label for="serviceAreaRequestAddress" class="mb-1 block text-sm font-medium text-gray-700">Address / Landmark <span class="font-normal text-gray-400">(optional)</span></label>
          <input id="serviceAreaRequestAddress" name="address" value="{{ old('address') }}" maxlength="255" class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-green-500 @error('address') border-red-500 @enderror" placeholder="e.g. Near the covered court">
          @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
      </div>

      <div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-sm font-medium text-gray-700">Location <span class="font-normal text-gray-400">(optional)</span></p>
            <p class="text-xs text-gray-500">Share your position or choose a point on the map.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" id="useServiceAreaCurrentLocation" class="inline-flex items-center gap-2 rounded-lg border border-green-600 px-3 py-2 text-xs font-medium text-green-700 hover:bg-green-50">
              <i class="fas fa-location-crosshairs" aria-hidden="true"></i>Use My Current Location
            </button>
            <button type="button" id="selectServiceAreaOnMap" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
              <i class="fas fa-map" aria-hidden="true"></i>Select on Map
            </button>
          </div>
        </div>
        <div id="serviceAreaRequestMapWrapper" class="mt-3 hidden">
          <div id="serviceAreaRequestMap" class="service-area-request-map w-full"></div>
          <p class="mt-2 text-xs text-gray-500">Click the map to place the marker, or drag it to refine the location.</p>
        </div>
        <p id="serviceAreaLocationStatus" class="mt-2 hidden text-xs" role="status" aria-live="polite"></p>
        <input type="hidden" name="latitude" id="serviceAreaRequestLatitude" value="{{ old('latitude') }}">
        <input type="hidden" name="longitude" id="serviceAreaRequestLongitude" value="{{ old('longitude') }}">
        @error('latitude')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        @error('longitude')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      </div>

      <div>
        <label for="serviceAreaRequestDetails" class="mb-1 block text-sm font-medium text-gray-700">Additional Details <span class="font-normal text-gray-400">(optional)</span></label>
        <textarea id="serviceAreaRequestDetails" name="details" maxlength="2000" rows="3" class="w-full resize-none rounded-lg border border-gray-300 px-4 py-2 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-green-500 @error('details') border-red-500 @enderror" placeholder="Tell us anything that may help identify or review the area.">{{ old('details') }}</textarea>
        @error('details')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      </div>

      <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
        <i class="fas fa-info-circle mr-1 text-green-600" aria-hidden="true"></i>
        Submitting a request does not automatically create a collection schedule. The area will first be reviewed by the administrator.
      </div>
    </form>

    @slot('footer')
      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" onclick="closeModal('serviceAreaRequestModal')" class="rounded-lg border border-gray-300 px-4 py-2 text-gray-700 transition-colors duration-300 hover:bg-gray-50">Cancel</button>
        <button type="submit" form="serviceAreaRequestForm" class="rounded-lg bg-green-600 px-4 py-2 font-medium text-white transition-colors duration-300 hover:bg-green-700">
          <i class="fas fa-paper-plane mr-2" aria-hidden="true"></i>Submit Service Area Request
        </button>
      </div>
    @endslot
  </x-modal>

  <!-- Delete Confirmation Modal -->
  <x-modal id="deleteModal" title="Confirm Account Deletion" icon="fas fa-exclamation-circle" color="red" variant="confirmation">
    <form id="deleteAccountForm" method="POST" action="{{ route('settings.delete-account') }}">
      @csrf
      @method('DELETE')
      <p class="mb-4">Are you absolutely sure you want to delete your Cleanify account? This action cannot be undone.</p>
      
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Enter your password to confirm</label>
        <input type="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent" required>
      </div>
      
      <div class="mb-4">
        <label class="flex items-center">
          <input type="checkbox" name="confirm_delete" value="1" class="mr-2" required>
          <span class="text-gray-700">I understand that this action cannot be undone</span>
        </label>
      </div>
    </form>
    
    @slot('footer')
      <div class="admin-confirm-actions">
        <button type="button" onclick="closeModal('deleteModal')" class="admin-btn-secondary">Cancel</button>
        <button type="button" onclick="submitDeleteAccount()" class="admin-btn-danger">
          <i class="fas fa-trash mr-2"></i>Delete Account
        </button>
      </div>
    @endslot
  </x-modal>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  let serviceAreaRequestMap;
  let serviceAreaRequestMarker;
  const pendingServiceAreaName = @json($pendingServiceAreaRequest?->area_name);
  const serviceAreaRequestHasErrors = @json($serviceAreaRequestHasErrors);

  function normalizeServiceAreaName(value) {
    return value.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
  }

  function showServiceAreaLocationStatus(message, isError = false) {
    const status = document.getElementById('serviceAreaLocationStatus');
    status.textContent = message;
    status.classList.remove('hidden', 'text-gray-600', 'text-red-600', 'text-green-700');
    status.classList.add(isError ? 'text-red-600' : 'text-green-700');
  }

  function setServiceAreaRequestLocation(lat, lng, center = true) {
    const latitude = Number(lat);
    const longitude = Number(lng);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)
      || latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) return false;

    document.getElementById('serviceAreaRequestLatitude').value = latitude.toFixed(8);
    document.getElementById('serviceAreaRequestLongitude').value = longitude.toFixed(8);

    if (serviceAreaRequestMarker) serviceAreaRequestMarker.setLatLng([latitude, longitude]);
    else {
      serviceAreaRequestMarker = L.marker([latitude, longitude], { draggable: true }).addTo(serviceAreaRequestMap);
      serviceAreaRequestMarker.on('dragend', event => {
        const point = event.target.getLatLng();
        setServiceAreaRequestLocation(point.lat, point.lng, false);
      });
    }

    if (center) serviceAreaRequestMap.setView([latitude, longitude], 16);
    showServiceAreaLocationStatus(`Selected location: ${latitude.toFixed(6)}, ${longitude.toFixed(6)}`);
    return true;
  }

  function initializeServiceAreaRequestMap() {
    if (serviceAreaRequestMap || typeof L === 'undefined') return;

    serviceAreaRequestMap = L.map('serviceAreaRequestMap').setView([9.7870, 125.4928], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(serviceAreaRequestMap);
    serviceAreaRequestMap.on('click', event => setServiceAreaRequestLocation(event.latlng.lat, event.latlng.lng, false));

    const latitude = document.getElementById('serviceAreaRequestLatitude').value;
    const longitude = document.getElementById('serviceAreaRequestLongitude').value;
    if (latitude !== '' && longitude !== '') setServiceAreaRequestLocation(latitude, longitude);
  }

  function showServiceAreaRequestMap() {
    document.getElementById('serviceAreaRequestMapWrapper').classList.remove('hidden');
    initializeServiceAreaRequestMap();
    setTimeout(() => serviceAreaRequestMap?.invalidateSize(), 100);
  }

  function openServiceAreaRequestModal() {
    openModal('serviceAreaRequestModal');
    if (document.getElementById('serviceAreaRequestLatitude').value !== '' || serviceAreaRequestHasErrors) {
      showServiceAreaRequestMap();
    }
  }

  document.getElementById('selectServiceAreaOnMap')?.addEventListener('click', showServiceAreaRequestMap);

  document.getElementById('useServiceAreaCurrentLocation')?.addEventListener('click', function() {
    showServiceAreaRequestMap();

    if (!navigator.geolocation) {
      showServiceAreaLocationStatus('Location lookup is unavailable in this browser. You can still select a point on the map.', true);
      return;
    }

    const button = this;
    button.disabled = true;
    button.classList.add('cursor-wait', 'opacity-60');
    showServiceAreaLocationStatus('Finding your current location...');

    navigator.geolocation.getCurrentPosition(
      position => {
        const updated = setServiceAreaRequestLocation(position.coords.latitude, position.coords.longitude);
        if (!updated) showServiceAreaLocationStatus('Your browser returned an invalid location. Please select a point on the map.', true);
        button.disabled = false;
        button.classList.remove('cursor-wait', 'opacity-60');
      },
      () => {
        showServiceAreaLocationStatus('We could not access your location. You can still select a point on the map.', true);
        button.disabled = false;
        button.classList.remove('cursor-wait', 'opacity-60');
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
    );
  });

  document.getElementById('serviceAreaRequestForm')?.addEventListener('submit', event => {
    const areaInput = document.getElementById('serviceAreaRequestName');
    const duplicateError = document.getElementById('serviceAreaRequestDuplicateError');
    const isDuplicate = pendingServiceAreaName
      && normalizeServiceAreaName(areaInput.value) === normalizeServiceAreaName(pendingServiceAreaName);

    duplicateError.classList.toggle('hidden', !isDuplicate);
    if (isDuplicate) {
      event.preventDefault();
      areaInput.focus();
    }
  });

  document.getElementById('serviceAreaRequestName')?.addEventListener('input', () => {
    document.getElementById('serviceAreaRequestDuplicateError').classList.add('hidden');
  });

  if (serviceAreaRequestHasErrors) {
    window.addEventListener('DOMContentLoaded', openServiceAreaRequestModal, { once: true });
  }

  // Handle form submissions with toast notifications
  document.getElementById('accountForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    submitForm(this, 'Account information updated successfully');
  });

  document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    submitForm(this, 'Password updated successfully');
  });

  document.getElementById('notificationsForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    submitForm(this, 'Notification preferences updated successfully');
  });

  function submitForm(form, successMessage) {
    const formData = new FormData(form);
    const method = form.method || 'POST';
    const action = form.action;

    fetch(action, {
      method: method,
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: formData,
    })
    .then(response => {
      if (response.redirected) {
        window.location.href = response.url;
        return;
      }
      return response.json();
    })
    .then(data => {
      if (data) {
        if (data.success) {
          showToast('success', successMessage);
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast('error', data.message || 'An error occurred');
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showToast('error', 'An error occurred. Please try again.');
    });
  }

  function submitDeleteAccount() {
    const form = document.getElementById('deleteAccountForm');
    const formData = new FormData(form);

    if (!formData.get('confirm_delete')) {
      showToast('error', 'Please confirm that you understand this action cannot be undone');
      return;
    }

    if (!formData.get('password')) {
      showToast('error', 'Please enter your password to confirm');
      return;
    }

    fetch(form.action, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: formData,
    })
    .then(response => {
      if (response.redirected) {
        window.location.href = response.url;
        return;
      }
      return response.json();
    })
    .then(data => {
      if (data) {
        if (data.success) {
          showToast('success', 'Account deleted successfully');
          setTimeout(() => {
            window.location.href = '/';
          }, 2000);
        } else {
          showToast('error', data.message || 'Failed to delete account');
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showToast('error', 'An error occurred. Please try again.');
    });
  }

</script>
@endpush
