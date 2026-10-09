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
      <i class="fas fa-cog text-green-600 mr-3"></i>Admin Settings
    </h2>
    <p class="text-gray-600 mt-1">Manage your account preferences and system settings</p>
  </div>

  <div class="grid grid-cols-1 gap-5 xl:grid-cols-2 mb-5">
  <div class="admin-section-card admin-settings-section bg-white rounded-xl shadow-sm p-6 mb-0">
    <div class="admin-card-heading mb-5">
      <i class="fas fa-user-circle" aria-hidden="true"></i>
      <div>
        <h3>Profile Settings</h3>
        <p class="mt-0.5 text-xs text-gray-500">Update your admin identity and profile photo</p>
      </div>
    </div>
    <form method="POST" action="{{ route('admin.settings.profile') }}" class="space-y-4" enctype="multipart/form-data">
      @csrf
      <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:flex-row sm:items-center">
        @if($user->profile_photo_url)
          <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}'s profile photo" class="w-16 h-16 rounded-full object-cover">
        @else
          <div class="w-16 h-16 rounded-full {{ $user->getAvatarBgClasses() }} flex items-center justify-center text-white text-xl font-bold">
            {{ $user->getAvatarInitial() }}
          </div>
        @endif
        <div class="flex-1">
          <label class="block text-gray-700 mb-2 font-medium">Profile Photo</label>
          <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="w-full rounded-lg border border-gray-300 bg-white text-sm text-gray-600 file:mr-3 file:border-0 file:border-r file:border-gray-200 file:bg-green-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-green-700 hover:file:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500 @error('profile_photo') border-red-500 @enderror">
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

  <div class="admin-section-card admin-settings-section bg-white rounded-xl shadow-sm p-6 mb-0">
    <div class="admin-card-heading mb-5">
      <i class="fas fa-lock" aria-hidden="true"></i>
      <div>
        <h3>Update Password</h3>
        <p class="mt-0.5 text-xs text-gray-500">Keep your administrator account secure</p>
      </div>
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
  </div>

  <div class="admin-section-card admin-settings-section bg-white rounded-xl shadow-sm p-6">
    <div class="admin-card-heading mb-5">
      <i class="fas fa-bell" aria-hidden="true"></i>
      <div>
        <h3>Notification Preferences</h3>
        <p class="mt-0.5 text-xs text-gray-500">Choose how administrative updates reach you</p>
      </div>
    </div>
    <form method="POST" action="{{ route('admin.settings.notifications') }}" class="space-y-3">
      @csrf
      
      <!-- Email Notifications -->
      <div class="admin-settings-option flex items-center p-3">
        <input type="checkbox" id="emailNotif" name="email_notifications" value="1" class="w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 focus:ring-2" {{ old('email_notifications', $user->email_notifications ?? true) ? 'checked' : '' }}>
        <label for="emailNotif" class="ml-2 text-gray-700">
          <i class="fas fa-envelope mr-2 text-green-600"></i>Email Notifications
        </label>
      </div>
      
      <!-- SMS Notifications -->
      <div class="admin-settings-option flex items-center justify-between p-3">
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
      <div class="admin-settings-option flex items-center justify-between p-3">
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

@endsection
