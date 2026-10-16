@props(['active' => 'home', 'unreadCount' => 0])

<!-- Mobile Menu Button -->
<button id="mobileMenuButton" class="resident-menu-trigger lg:hidden fixed top-4 left-4 z-50 p-2 bg-green-600 text-white rounded-lg shadow-lg hover:bg-green-700 transition-colors duration-300" aria-label="Open navigation menu" aria-controls="mobileMenu">
  <i class="fas fa-bars text-xl"></i>
</button>

<!-- Mobile Menu Overlay -->
<div id="mobileMenuOverlay" class="resident-menu-overlay lg:hidden fixed inset-0 bg-black bg-opacity-50 z-40 hidden transition-opacity duration-300"></div>

<!-- Mobile Menu Sidebar -->
<div id="mobileMenu" class="user-sidebar user-sidebar--mobile resident-sidebar cleanify-sidebar flex flex-col lg:hidden fixed top-0 left-0 h-full w-64 bg-green-600 text-white z-50 transform -translate-x-full transition-transform duration-300 overflow-y-auto">
  <div class="resident-sidebar__brand p-4 border-b border-green-500 flex items-center justify-between">
    <div class="flex items-center">
      <img src="/assets/icons/cleanifyicon.png" alt="Cleanify Logo" class="w-10 h-10 mr-3">
      <h5 class="font-bold text-lg">Cleanify</h5>
    </div>
    <button id="closeMobileMenu" class="resident-menu-close p-2 hover:bg-green-700 rounded-lg transition-colors duration-300" aria-label="Close navigation menu">
      <i class="fas fa-times text-xl"></i>
    </button>
  </div>

  <nav class="resident-sidebar__nav flex-1 p-4 space-y-2">
    <a href="{{ route('dashboard') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'home' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()" @if($active === 'home') aria-current="page" @endif>
      <i class="fas fa-home mr-3 text-lg"></i>
      <span>Home</span>
    </a>
    
    <a href="{{ route('garbage-schedule') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'schedule' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()" @if($active === 'schedule') aria-current="page" @endif>
      <i class="fas fa-calendar-alt mr-3 text-lg"></i>
      <span>Garbage Schedule</span>
    </a>
    
    <a href="{{ route('tracker') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'tracker' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()" @if($active === 'tracker') aria-current="page" @endif>
      <i class="fas fa-truck mr-3 text-lg"></i>
      <span>Truck Tracker</span>
    </a>
    
    <a href="{{ route('notifications') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'notifications' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()" @if($active === 'notifications') aria-current="page" @endif>
      <i class="fas fa-bell mr-3 text-lg"></i>
      <span>Notifications</span>
      @if($unreadCount > 0)
        <span class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold leading-none text-white" aria-label="{{ $unreadCount }} unread notifications">
          {{ $unreadCount > 99 ? '99+' : $unreadCount }}
        </span>
      @endif
    </a>
    
    <a href="{{ route('community-reports') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'reports' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()" @if($active === 'reports') aria-current="page" @endif>
      <i class="fas fa-comment-dots mr-3 text-lg"></i>
      <span>Community Reports</span>
    </a>
    
    <a href="{{ route('profile') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'profile' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()" @if($active === 'profile') aria-current="page" @endif>
      <i class="fas fa-user-circle mr-3 text-lg"></i>
      <span>Profile</span>
    </a>
    
    <a href="{{ route('settings') }}" class="resident-nav-link flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'settings' ? 'is-active bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()" @if($active === 'settings') aria-current="page" @endif>
      <i class="fas fa-cog mr-3 text-lg"></i>
      <span>Settings</span>
    </a>
  </nav>

  <!-- Mobile Menu Footer -->
  <div class="resident-sidebar__footer p-4 border-t border-green-500">
    <button onclick="openModal('logoutModal'); closeMobileMenu();" class="resident-sidebar__logout w-full flex items-center justify-center px-4 py-3 rounded-lg text-white bg-red-600 hover:bg-red-700 transition-colors duration-300">
      <i class="fas fa-sign-out-alt mr-2"></i>
      <span>Logout</span>
    </button>
  </div>
</div>
