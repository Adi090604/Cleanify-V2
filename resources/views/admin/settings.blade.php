@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
  @if(session('success'))
    <x-alert type="success" dismissible class="mb-4">
      {{ session('success') }}
    </x-alert>
  @endif

  @if(session('error'))
    <x-alert type="error" dismissible class="mb-4">
      {{ session('error') }}
    </x-alert>
  @endif

  <div class="admin-page-header mb-6">
    <h2 class="text-3xl font-bold text-gray-800">
      <i class="fas fa-cog text-green-600 mr-3"></i>Admin Settings ⚙️
    </h2>
    <p class="text-gray-600 mt-1">Manage your account preferences and system settings</p>
  </div>

  <div class="admin-section-card bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex items-center mb-4">
      <i class="fas fa-user-circle text-green-600 text-xl mr-3"></i>
      <h3 class="text-xl font-semibold text-gray-800">Profile Settings</h3>
    </div>
    <form method="POST" action="{{ route('admin.settings.profile') }}" class="space-y-4" enctype="multipart/form-data">
      @csrf
      <div class="flex items-center gap-4">
        @if($user->profile_photo_url)
          <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}'s profile photo" class="w-16 h-16 rounded-full object-cover">
        @else
          <div class="w-16 h-16 rounded-full {{ $user->getAvatarBgClasses() }} flex items-center justify-center text-white text-xl font-bold">
            {{ $user->getAvatarInitial() }}
          </div>
        @endif
        <div class="flex-1">
          <label class="block text-gray-700 mb-2 font-medium">Profile Photo</label>
          <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent @error('profile_photo') border-red-500 @enderror">
          <p class="mt-1 text-sm text-gray-500">JPG, PNG, or WebP up to 4 MB.</p>
          @error('profile_photo')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
          @enderror
          @if($user->profile_photo_url)
            <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" name="remove_profile_photo" value="1" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
              Remove current profile photo
            </label>
          @endif
        </div>
      </div>
      <div>
        <label class="block text-gray-700 mb-2 font-medium">
          <i class="fas fa-user mr-2 text-green-600"></i>Admin Name
        </label>
        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent @error('name') border-red-500 @enderror" required>
        @error('name')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2 font-medium">
          <i class="fas fa-envelope mr-2 text-green-600"></i>Email Address
        </label>
        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent @error('email') border-red-500 @enderror" required>
        @error('email')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-save mr-2"></i>Save Changes
      </button>
    </form>
  </div>

  <div class="admin-section-card bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex items-center mb-4">
      <i class="fas fa-lock text-green-600 text-xl mr-3"></i>
      <h3 class="text-xl font-semibold text-gray-800">Update Password</h3>
    </div>
    <form method="POST" action="{{ route('admin.settings.password') }}" class="space-y-4">
      @csrf
      <div>
        <label class="block text-gray-700 mb-2 font-medium">
          <i class="fas fa-key mr-2 text-green-600"></i>Current Password
        </label>
        <input type="password" name="current_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent @error('current_password') border-red-500 @enderror" placeholder="Enter current password" required>
        @error('current_password')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2 font-medium">
          <i class="fas fa-key mr-2 text-green-600"></i>New Password
        </label>
        <input type="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent @error('password') border-red-500 @enderror" placeholder="Enter new password" required>
        @error('password')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2 font-medium">
          <i class="fas fa-key mr-2 text-green-600"></i>Confirm New Password
        </label>
        <input type="password" name="password_confirmation" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Confirm new password" required>
      </div>
      <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-key mr-2"></i>Update Password
      </button>
    </form>
  </div>

  <div class="admin-section-card bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex items-center mb-4">
      <i class="fas fa-bell text-green-600 text-xl mr-3"></i>
      <h3 class="text-xl font-semibold text-gray-800">Notification Preferences</h3>
    </div>
    <form method="POST" action="{{ route('admin.settings.notifications') }}" class="space-y-3">
      @csrf
      
      <!-- Email Notifications -->
      <div class="flex items-center p-3 border border-gray-200 rounded-lg">
        <input type="checkbox" id="emailNotif" name="email_notifications" value="1" class="w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 focus:ring-2" {{ old('email_notifications', $user->email_notifications ?? true) ? 'checked' : '' }}>
        <label for="emailNotif" class="ml-2 text-gray-700">
          <i class="fas fa-envelope mr-2 text-green-600"></i>Email Notifications
        </label>
      </div>
      
      <!-- SMS Notifications -->
      <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg bg-gray-50">
        <div class="flex items-center">
          <input type="checkbox" id="smsNotif" name="sms_notifications" value="1" class="w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 focus:ring-2" {{ old('sms_notifications', $user->sms_notifications ?? false) ? 'checked' : '' }} disabled>
          <label for="smsNotif" class="ml-2 text-gray-500">
            <i class="fas fa-comment-alt mr-2 text-gray-400"></i>SMS Notifications
          </label>
        </div>
        <span class="px-3 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-lg">
          <i class="fas fa-clock mr-1"></i>Coming Soon
        </span>
      </div>
      
      <!-- Push Notifications -->
      <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg bg-gray-50">
        <div class="flex items-center">
          <input type="checkbox" id="pushNotif" name="push_notifications" value="1" class="w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 focus:ring-2" {{ old('push_notifications', $user->push_notifications ?? true) ? 'checked' : '' }} disabled>
          <label for="pushNotif" class="ml-2 text-gray-500">
            <i class="fas fa-bell mr-2 text-gray-400"></i>Push Notifications
          </label>
        </div>
        <span class="px-3 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-lg">
          <i class="fas fa-clock mr-1"></i>Coming Soon
        </span>
      </div>
      
      <button type="submit" class="mt-4 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-bell mr-2"></i>Update Preferences
      </button>
    </form>
  </div>

  <div class="admin-table-card bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-6">
      <div class="flex items-center mb-4">
        <i class="fas fa-link text-green-600 text-xl mr-3"></i>
        <h3 class="text-xl font-semibold text-gray-800">Connected Accounts</h3>
      </div>
      @php
        $accounts = [
          ['service' => 'Google', 'icon' => 'fab fa-google text-red-500', 'status' => 'Connected', 'connected' => true, 'date' => 'Jan 12, 2025'],
          ['service' => 'Facebook', 'icon' => 'fab fa-facebook text-blue-600', 'status' => 'Disconnected', 'connected' => false, 'date' => 'Feb 20, 2025'],
          ['service' => 'Twitter', 'icon' => 'fab fa-twitter text-blue-400', 'status' => 'Connected', 'connected' => true, 'date' => 'Mar 05, 2025'],
        ];
      @endphp
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="bg-green-600 text-white">
              @foreach (['#','Service','Status','Connected Since','Action'] as $heading)
                <th class="px-4 py-3 text-left text-sm font-semibold">{{ $heading }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200" id="connectedAccountsBody">
            @foreach ($accounts as $index => $account)
              <tr class="hover:bg-gray-50 transition-colors duration-200" data-account="{{ json_encode($account) }}">
                <td class="px-4 py-3">{{ $index + 1 }}</td>
                <td class="px-4 py-3 font-medium text-gray-900">
                  <div class="flex items-center">
                    <i class="{{ $account['icon'] }} mr-2"></i>
                    <span>{{ $account['service'] }}</span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span class="text-xs font-medium px-2.5 py-0.5 rounded-full {{ $account['connected'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                    <i class="fas fa-circle text-xs mr-1"></i>{{ $account['status'] }}
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $account['date'] }}</td>
                <td class="px-4 py-3">
                  <button class="px-3 py-1 text-white text-sm rounded-lg transition-colors duration-300 {{ $account['connected'] ? 'bg-red-500 hover:bg-red-600' : 'bg-green-600 hover:bg-green-700' }}" onclick="toggleAccountConnection(this)">
                    <i class="fas {{ $account['connected'] ? 'fa-times-circle' : 'fa-check-circle' }} mr-1"></i>
                    {{ $account['connected'] ? 'Disconnect' : 'Connect' }}
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    function toggleAccountConnection(button) {
      const row = button.closest('tr');
      const isConnected = button.textContent.trim().includes('Disconnect');
      const statusCell = row.querySelector('td:nth-child(3) span');
      if (isConnected) {
        button.classList.remove('bg-red-500', 'hover:bg-red-600');
        button.classList.add('bg-green-600', 'hover:bg-green-700');
        button.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Connect';
        statusCell.className = 'text-xs font-medium px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-800';
        statusCell.innerHTML = '<i class="fas fa-circle text-xs mr-1"></i>Disconnected';
        if (typeof showToast === 'function') {
          showToast('info', 'Successfully disconnected account.');
        }
      } else {
        button.classList.remove('bg-green-600', 'hover:bg-green-700');
        button.classList.add('bg-red-500', 'hover:bg-red-600');
        button.innerHTML = '<i class="fas fa-times-circle mr-1"></i>Disconnect';
        statusCell.className = 'text-xs font-medium px-2.5 py-0.5 rounded-full bg-green-100 text-green-800';
        statusCell.innerHTML = '<i class="fas fa-circle text-xs mr-1"></i>Connected';
        if (typeof showToast === 'function') {
          showToast('success', 'Successfully connected account.');
        }
      }
    }
  </script>
@endpush
