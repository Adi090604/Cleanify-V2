@props(['active' => 'home', 'unreadCount' => 0])

<div class="user-sidebar user-sidebar--desktop cleanify-sidebar hidden lg:flex w-64 bg-green-600 text-white flex-col justify-between fixed h-full">
  <div>
    <!-- Logo Section -->
    <div class="text-center py-5 border-b border-green-500">
      <img src="/assets/icons/cleanifyicon.png" alt="Cleanify Logo" class="w-16 mx-auto mb-2">
      <h5 class="font-bold text-lg">Cleanify</h5>
    </div>

    <!-- Navigation -->
    <nav class="p-4 space-y-2">
      <a href="{{ route('dashboard') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'home' ? 'bg-green-700' : 'hover:bg-green-700' }}">
        <i class="fas fa-home mr-3 text-lg"></i>
        <span>Home</span>
      </a>
      
      <a href="{{ route('garbage-schedule') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'schedule' ? 'bg-green-700' : 'hover:bg-green-700' }}">
        <i class="fas fa-calendar-alt mr-3 text-lg"></i>
        <span>Garbage Schedule</span>
      </a>
      
      <a href="{{ route('tracker') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'tracker' ? 'bg-green-700' : 'hover:bg-green-700' }}">
        <i class="fas fa-truck mr-3 text-lg"></i>
        <span>Truck Tracker</span>
      </a>
      
      <a href="{{ route('notifications') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'notifications' ? 'bg-green-700' : 'hover:bg-green-700' }}">
        <i class="fas fa-bell mr-3 text-lg"></i>
        <span>Notifications</span>
        @if($unreadCount > 0)
          <span class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold leading-none text-white" aria-label="{{ $unreadCount }} unread notifications">
            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
          </span>
        @endif
      </a>
      
      <a href="{{ route('community-reports') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'reports' ? 'bg-green-700' : 'hover:bg-green-700' }}">
        <i class="fas fa-comment-dots mr-3 text-lg"></i>
        <span>Community Reports</span>
      </a>
      
      <a href="{{ route('profile') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'profile' ? 'bg-green-700' : 'hover:bg-green-700' }}">
        <i class="fas fa-user-circle mr-3 text-lg"></i>
        <span>Profile</span>
      </a>
      
      <a href="{{ route('settings') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'settings' ? 'bg-green-700' : 'hover:bg-green-700' }}">
        <i class="fas fa-cog mr-3 text-lg"></i>
        <span>Settings</span>
      </a>
    </nav>
  </div>

  <!-- Footer -->
  <div class="p-3 border-t border-green-500">
    <button onclick="openModal('logoutModal')" class="w-full flex items-center justify-center px-4 py-3 rounded-lg text-white bg-red-600 hover:bg-red-700 transition-colors duration-300 mb-3">
      <i class="fas fa-sign-out-alt mr-2"></i>
      <span>Logout</span>
    </button>
    <div class="text-center">
      <small class="text-gray-200">&copy; 2025 Cleanify</small>
    </div>
  </div>
</div>
