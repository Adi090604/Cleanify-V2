@props(['active' => 'home', 'unreadCount' => 0])

<div class="user-sidebar user-sidebar--desktop resident-sidebar cleanify-sidebar hidden lg:flex w-64 bg-green-600 text-white flex-col fixed h-full">
  <!-- Logo Section -->
  <div class="resident-sidebar__brand p-4 border-b border-green-500 text-center">
    <img src="/assets/icons/cleanifyicon.png" alt="Cleanify Logo" class="w-16 h-16 mx-auto mb-2">
    <h5 class="font-semibold text-lg">Cleanify</h5>
  </div>

  <!-- Navigation -->
  <nav class="resident-sidebar__nav flex-1 p-4 space-y-2">
      <a href="{{ route('dashboard') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'home' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" @if($active === 'home') aria-current="page" @endif>
        <i class="fas fa-home mr-3 text-lg"></i>
        <span>Home</span>
      </a>
      
      <a href="{{ route('garbage-schedule') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'schedule' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" @if($active === 'schedule') aria-current="page" @endif>
        <i class="fas fa-calendar-alt mr-3 text-lg"></i>
        <span>Garbage Schedule</span>
      </a>
      
      <a href="{{ route('tracker') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'tracker' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" @if($active === 'tracker') aria-current="page" @endif>
        <i class="fas fa-truck mr-3 text-lg"></i>
        <span>Truck Tracker</span>
      </a>
      
      <a href="{{ route('notifications') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'notifications' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" @if($active === 'notifications') aria-current="page" @endif>
        <i class="fas fa-bell mr-3 text-lg"></i>
        <span>Notifications</span>
        @if($unreadCount > 0)
          <span class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold leading-none text-white" aria-label="{{ $unreadCount }} unread notifications">
            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
          </span>
        @endif
      </a>
      
      <a href="{{ route('community-reports') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'reports' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" @if($active === 'reports') aria-current="page" @endif>
        <i class="fas fa-comment-dots mr-3 text-lg"></i>
        <span>Community Reports</span>
      </a>
      
      <a href="{{ route('profile') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'profile' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" @if($active === 'profile') aria-current="page" @endif>
        <i class="fas fa-user-circle mr-3 text-lg"></i>
        <span>Profile</span>
      </a>
      
      <a href="{{ route('settings') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'settings' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" @if($active === 'settings') aria-current="page" @endif>
        <i class="fas fa-cog mr-3 text-lg"></i>
        <span>Settings</span>
      </a>
  </nav>

  <!-- Footer -->
  <div class="resident-sidebar__footer p-4 border-t border-green-500">
    <button onclick="openModal('logoutModal')" class="resident-sidebar__logout w-full flex items-center justify-center px-4 py-3 rounded-lg text-white bg-red-600 hover:bg-red-700 transition-colors duration-300">
      <i class="fas fa-sign-out-alt mr-2"></i>
      <span>Logout</span>
    </button>
  </div>
</div>
