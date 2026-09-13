@extends('layouts.app')

@section('title', 'Settings')

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
      </div>
      <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-check-circle mr-2"></i>Save Changes
      </button>
    </form>
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
<script>
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
