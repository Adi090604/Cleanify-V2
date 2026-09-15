@props(['active' => 'home', 'unreadCount' => 0])

<!-- Mobile Menu Button -->
<button id="mobileMenuButton" class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-green-600 text-white rounded-lg shadow-lg hover:bg-green-700 transition-colors duration-300">
  <i class="fas fa-bars text-xl"></i>
</button>

<!-- Mobile Menu Overlay -->
<div id="mobileMenuOverlay" class="lg:hidden fixed inset-0 bg-black bg-opacity-50 z-40 hidden transition-opacity duration-300"></div>

<!-- Mobile Menu Sidebar -->
<div id="mobileMenu" class="cleanify-sidebar lg:hidden fixed top-0 left-0 h-full w-64 bg-green-600 text-white z-50 transform -translate-x-full transition-transform duration-300 overflow-y-auto">
  <div class="p-4 border-b border-green-500 flex items-center justify-between">
    <div class="flex items-center">
      <img src="/assets/icons/cleanifyicon.png" alt="Cleanify Logo" class="w-10 h-10 mr-3">
      <h5 class="font-bold text-lg">Cleanify</h5>
    </div>
    <button id="closeMobileMenu" class="p-2 hover:bg-green-700 rounded-lg transition-colors duration-300">
      <i class="fas fa-times text-xl"></i>
    </button>
  </div>

  <nav class="p-4 space-y-2">
    <a href="{{ route('dashboard') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'home' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()">
      <i class="fas fa-home mr-3 text-lg"></i>
      <span>Home</span>
    </a>
    
    <a href="{{ route('garbage-schedule') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'schedule' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()">
      <i class="fas fa-calendar-alt mr-3 text-lg"></i>
      <span>Garbage Schedule</span>
    </a>
    
    <a href="{{ route('tracker') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'tracker' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()">
      <i class="fas fa-truck mr-3 text-lg"></i>
      <span>Truck Tracker</span>
    </a>
    
    <a href="{{ route('notifications') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'notifications' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()">
      <i class="fas fa-bell mr-3 text-lg"></i>
      <span>Notifications</span>
      @if($unreadCount > 0)
        <span class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold leading-none text-white" aria-label="{{ $unreadCount }} unread notifications">
          {{ $unreadCount > 99 ? '99+' : $unreadCount }}
        </span>
      @endif
    </a>
    
    <a href="{{ route('community-reports') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'reports' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()">
      <i class="fas fa-comment-dots mr-3 text-lg"></i>
      <span>Community Reports</span>
    </a>
    
    <a href="{{ route('profile') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'profile' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()">
      <i class="fas fa-user-circle mr-3 text-lg"></i>
      <span>Profile</span>
    </a>
    
    <a href="{{ route('settings') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'settings' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeMobileMenu()">
      <i class="fas fa-cog mr-3 text-lg"></i>
      <span>Settings</span>
    </a>
  </nav>

  <!-- Mobile Menu Footer -->
  <div class="p-4 border-t border-green-500">
    <button onclick="openModal('logoutModal'); closeMobileMenu();" class="w-full flex items-center justify-center px-4 py-3 rounded-lg text-white bg-red-600 hover:bg-red-700 transition-colors duration-300">
      <i class="fas fa-sign-out-alt mr-2"></i>
      <span>Logout</span>
    </button>
  </div>
</div>
