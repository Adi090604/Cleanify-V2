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
      
      <!-- Global Notification Toggles -->
      <div class="mb-6">
        <h6 class="text-gray-700 font-semibold mb-3">Global Settings</h6>
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <label for="email_notifications" class="text-gray-700 cursor-pointer">Email Notifications</label>
              <p class="text-sm text-gray-500">Receive notifications via email</p>
            </div>
            <div class="relative inline-block w-12 h-6">
              <input type="checkbox" name="email_notifications" id="email_notifications" value="1" class="sr-only peer" {{ $user->email_notifications ? 'checked' : '' }}>
              <div class="w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></div>
              <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></div>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div>
              <label for="sms_notifications" class="text-gray-700 cursor-pointer">SMS Notifications</label>
              <p class="text-sm text-gray-500">Receive notifications via SMS</p>
            </div>
            <div class="relative inline-block w-12 h-6">
              <input type="checkbox" name="sms_notifications" id="sms_notifications" value="1" class="sr-only peer" {{ $user->sms_notifications ? 'checked' : '' }}>
              <div class="w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></div>
              <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></div>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div>
              <label for="push_notifications" class="text-gray-700 cursor-pointer">Push Notifications</label>
              <p class="text-sm text-gray-500">Receive browser push notifications</p>
            </div>
            <div class="relative inline-block w-12 h-6">
              <input type="checkbox" name="push_notifications" id="push_notifications" value="1" class="sr-only peer" {{ $user->push_notifications ? 'checked' : '' }}>
              <div class="w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></div>
              <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></div>
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
            <div class="relative inline-block w-12 h-6">
              <input type="checkbox" name="preferences[report_updates]" id="pref_report_updates" value="1" class="sr-only peer" {{ $notificationPrefs['report_updates'] ?? true ? 'checked' : '' }}>
              <div class="w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></div>
              <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></div>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div>
              <label for="pref_schedule_reminders" class="text-gray-700 cursor-pointer">Schedule Reminders</label>
              <p class="text-sm text-gray-500">Garbage pickup reminders for your area</p>
            </div>
            <div class="relative inline-block w-12 h-6">
              <input type="checkbox" name="preferences[schedule_reminders]" id="pref_schedule_reminders" value="1" class="sr-only peer" {{ $notificationPrefs['schedule_reminders'] ?? true ? 'checked' : '' }}>
              <div class="w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></div>
              <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></div>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div>
              <label for="pref_community_posts" class="text-gray-700 cursor-pointer">Community Posts</label>
              <p class="text-sm text-gray-500">New posts and updates from your community</p>
            </div>
            <div class="relative inline-block w-12 h-6">
              <input type="checkbox" name="preferences[community_posts]" id="pref_community_posts" value="1" class="sr-only peer" {{ $notificationPrefs['community_posts'] ?? true ? 'checked' : '' }}>
              <div class="w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></div>
              <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></div>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div>
              <label for="pref_truck_tracking" class="text-gray-700 cursor-pointer">Truck Tracking</label>
              <p class="text-sm text-gray-500">ETA updates and route changes</p>
            </div>
            <div class="relative inline-block w-12 h-6">
              <input type="checkbox" name="preferences[truck_tracking]" id="pref_truck_tracking" value="1" class="sr-only peer" {{ $notificationPrefs['truck_tracking'] ?? true ? 'checked' : '' }}>
              <div class="w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></div>
              <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></div>
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-save mr-2"></i>Save Preferences
      </button>
    </form>
  </div>

  <!-- Privacy Settings Section -->
  <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h5 class="text-green-600 font-semibold text-lg mb-4 border-b-2 border-green-600 pb-2">
      <i class="fas fa-shield-alt mr-2"></i>Privacy Settings
    </h5>
    
    <form id="privacyForm" method="POST" action="{{ route('settings.privacy') }}">
      @csrf
      @method('PATCH')
      
      <div class="space-y-4 mb-6">
        <div class="flex items-center justify-between">
          <div>
            <label for="show_email" class="text-gray-700 cursor-pointer">Show my email to community members</label>
            <p class="text-sm text-gray-500">Allow other users to see your email address</p>
          </div>
          <div class="relative inline-block w-12 h-6">
            <input type="checkbox" name="show_email" id="show_email" value="1" class="sr-only peer" {{ $user->show_email ?? false ? 'checked' : '' }}>
            <div class="w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></div>
            <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></div>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <div>
            <label for="location_sharing" class="text-gray-700 cursor-pointer">Share my location for truck tracking</label>
            <p class="text-sm text-gray-500">Enable location sharing to get accurate truck ETAs</p>
          </div>
          <div class="relative inline-block w-12 h-6">
            <input type="checkbox" name="location_sharing" id="location_sharing" value="1" class="sr-only peer" {{ $user->location_sharing ?? true ? 'checked' : '' }}>
            <div class="w-12 h-6 bg-gray-300 peer-checked:bg-green-600 rounded-full transition-colors duration-300"></div>
            <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300 peer-checked:translate-x-6"></div>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <div>
            <label for="profile_visibility" class="text-gray-700 cursor-pointer">Profile Visibility</label>
            <p class="text-sm text-gray-500">Control who can see your profile</p>
          </div>
          <select name="profile_visibility" id="profile_visibility" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
            <option value="public" {{ ($user->profile_visibility ?? 'public') === 'public' ? 'selected' : '' }}>Public</option>
            <option value="private" {{ ($user->profile_visibility ?? 'public') === 'private' ? 'selected' : '' }}>Private</option>
          </select>
        </div>
      </div>

      <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-save mr-2"></i>Save Privacy Settings
      </button>
    </form>
  </div>

  <!-- Application Preferences Section -->
  <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h5 class="text-green-600 font-semibold text-lg mb-4 border-b-2 border-green-600 pb-2">
      <i class="fas fa-sliders-h mr-2"></i>Application Preferences
    </h5>
    
    <form id="preferencesForm" method="POST" action="{{ route('settings.preferences') }}">
      @csrf
      @method('PATCH')
      
      <div class="space-y-4 mb-6">
        <div>
          <label class="block text-gray-700 mb-2">Tracker Auto-Refresh Interval</label>
          <select name="tracker_refresh_interval" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
            <option value="15" {{ ($user->tracker_refresh_interval ?? 30) == 15 ? 'selected' : '' }}>15 seconds</option>
            <option value="30" {{ ($user->tracker_refresh_interval ?? 30) == 30 ? 'selected' : '' }}>30 seconds (Default)</option>
            <option value="60" {{ ($user->tracker_refresh_interval ?? 30) == 60 ? 'selected' : '' }}>1 minute</option>
            <option value="300" {{ ($user->tracker_refresh_interval ?? 30) == 300 ? 'selected' : '' }}>5 minutes</option>
          </select>
          <p class="text-sm text-gray-500 mt-1">How often the truck tracker should refresh automatically</p>
        </div>

        <div>
          <label class="block text-gray-700 mb-2">Language</label>
          <select name="language" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
            <option value="en" {{ ($user->language ?? 'en') === 'en' ? 'selected' : '' }}>English</option>
            <option value="fil" {{ ($user->language ?? 'en') === 'fil' ? 'selected' : '' }}>Filipino</option>
          </select>
          <p class="text-sm text-gray-500 mt-1">Interface language preference</p>
        </div>
      </div>

      <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-save mr-2"></i>Save Preferences
      </button>
    </form>
  </div>

  <!-- Security & Data Section -->
  <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <h5 class="text-green-600 font-semibold text-lg mb-4 border-b-2 border-green-600 pb-2">
      <i class="fas fa-shield-alt mr-2"></i>Security & Data
    </h5>
    
    <!-- Active Sessions -->
    <div class="mb-6">
      <h6 class="text-gray-700 font-semibold mb-3">Active Sessions</h6>
      @if($activeSessions->count() > 0)
        <div class="space-y-3">
          @foreach($activeSessions as $session)
            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg {{ $session['is_current'] ? 'bg-green-50 border-green-300' : '' }}">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-gray-800">{{ $session['ip_address'] }}</span>
                  @if($session['is_current'])
                    <span class="text-xs px-2 py-0.5 bg-green-100 text-green-800 rounded">Current Session</span>
                  @endif
                </div>
                <p class="text-xs text-gray-500 mt-1">{{ \Illuminate\Support\Str::limit($session['user_agent'], 60) }}</p>
                <p class="text-xs text-gray-400 mt-1">Last activity: {{ $session['last_activity'] }}</p>
              </div>
              @if(!$session['is_current'])
                <button onclick="openRevokeModal('{{ $session['id'] }}', '{{ $session['ip_address'] }}')" class="ml-4 px-3 py-1 text-sm text-red-600 border border-red-300 rounded hover:bg-red-50 transition-colors duration-300">
                  <i class="fas fa-times mr-1"></i>Revoke
                </button>
              @endif
            </div>
          @endforeach
        </div>
      @else
        <p class="text-gray-500 text-sm">No active sessions found.</p>
      @endif
    </div>

    <!-- Download Data -->
    <div class="mb-6">
      <h6 class="text-gray-700 font-semibold mb-3">Download Your Data</h6>
      <p class="text-gray-600 text-sm mb-3">Download a copy of all your data stored in Cleanify (GDPR compliance).</p>
      <a href="{{ route('settings.download-data') }}" class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-300">
        <i class="fas fa-download mr-2"></i>Download My Data
      </a>
    </div>

    <!-- Login History -->
    <div>
      <h6 class="text-gray-700 font-semibold mb-3">Login History</h6>
      <p class="text-gray-600 text-sm mb-2">Last login: {{ $user->last_login_at ? $user->last_login_at->format('F j, Y g:i A') : 'Never' }}</p>
      <p class="text-xs text-gray-500">Detailed login history coming soon.</p>
    </div>
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

  <!-- Revoke Session Confirmation Modal -->
  <x-modal id="revokeSessionModal" title="Revoke Session" icon="fas fa-sign-out-alt" color="orange">
    <p class="text-gray-700 mb-4">Are you sure you want to revoke this session? The user will be logged out from that device.</p>
    <p class="text-sm text-gray-500 mb-4" id="revokeSessionInfo"></p>
    
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('revokeSessionModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">Cancel</button>
        <button onclick="confirmRevokeSession()" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Revoke Session
        </button>
      </div>
    @endslot
  </x-modal>

  <!-- Delete Confirmation Modal -->
  <x-modal id="deleteModal" title="Confirm Account Deletion" icon="fas fa-exclamation-circle" color="red">
    <form id="deleteAccountForm" method="POST" action="{{ route('settings.delete-account') }}">
      @csrf
      @method('DELETE')
      <p class="text-gray-700 mb-4">Are you absolutely sure you want to delete your Cleanify account? This action cannot be undone.</p>
      
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
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('deleteModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">Cancel</button>
        <button onclick="submitDeleteAccount()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300">
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

  document.getElementById('privacyForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    submitForm(this, 'Privacy settings updated successfully');
  });

  document.getElementById('preferencesForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    submitForm(this, 'Application preferences updated successfully');
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

  let currentRevokeSessionId = null;

  function openRevokeModal(sessionId, ipAddress) {
    currentRevokeSessionId = sessionId;
    document.getElementById('revokeSessionInfo').textContent = `IP Address: ${ipAddress}`;
    openModal('revokeSessionModal');
  }

  function confirmRevokeSession() {
    if (!currentRevokeSessionId) {
      showToast('error', 'No session selected');
      return;
    }

    // URL encode the session ID to handle special characters
    const encodedSessionId = encodeURIComponent(currentRevokeSessionId);

    fetch(`/settings/sessions/${encodedSessionId}/revoke`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json',
      },
    })
    .then(response => {
      if (!response.ok) {
        return response.json().then(data => {
          throw new Error(data.message || 'Failed to revoke session');
        });
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        showToast('success', 'Session revoked successfully');
        closeModal('revokeSessionModal');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast('error', data.message || 'Failed to revoke session');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showToast('error', error.message || 'An error occurred. Please try again.');
    });
  }
</script>
@endpush
