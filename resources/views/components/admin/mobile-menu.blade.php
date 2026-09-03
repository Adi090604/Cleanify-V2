@props(['active' => 'dashboard'])

<!-- Mobile Menu Button -->
<button id="adminMobileMenuButton" class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-green-600 text-white rounded-lg shadow-lg hover:bg-green-700 transition-colors duration-300">
  <i class="fas fa-bars text-xl"></i>
</button>

<!-- Mobile Menu Overlay -->
<div id="adminMobileMenuOverlay" class="lg:hidden fixed inset-0 bg-black bg-opacity-50 z-40 hidden transition-opacity duration-300"></div>

<!-- Mobile Menu Sidebar -->
<div id="adminMobileMenu" class="cleanify-sidebar lg:hidden fixed top-0 left-0 h-full w-64 bg-green-600 text-white z-50 transform -translate-x-full transition-transform duration-300 overflow-y-auto">
  <div class="p-4 border-b border-green-500 flex items-center justify-between">
    <div class="flex items-center">
      <img src="/assets/icons/cleanifyicon.png" alt="Cleanify Logo" class="w-10 h-10 mr-3">
      <h5 class="font-semibold text-lg">Cleanify Admin</h5>
    </div>
    <button id="closeAdminMobileMenu" class="p-2 hover:bg-green-700 rounded-lg transition-colors duration-300">
      <i class="fas fa-times text-xl"></i>
    </button>
  </div>

  <nav class="flex-1 p-4 space-y-2">
    <a href="{{ route('admin.dashboard') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'dashboard' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeAdminMobileMenu()">
      <i class="fas fa-tachometer-alt mr-3 text-lg"></i>
      <span>Dashboard</span>
    </a>
    
    <a href="{{ route('admin.users') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'users' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeAdminMobileMenu()">
      <i class="fas fa-users mr-3 text-lg"></i>
      <span>Users</span>
    </a>
    
    <a href="{{ route('admin.reports') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'reports' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeAdminMobileMenu()">
      <i class="fas fa-flag mr-3 text-lg"></i>
      <span>Reports</span>
    </a>
    
    <a href="{{ route('admin.user-reports') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'user-reports' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeAdminMobileMenu()">
      <i class="fas fa-user-shield mr-3 text-lg"></i>
      <span>User Reports</span>
    </a>
    
    <a href="{{ route('admin.schedule') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'schedule' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeAdminMobileMenu()">
      <i class="fas fa-calendar-alt mr-3 text-lg"></i>
      <span>Schedule</span>
    </a>
    
    <a href="{{ route('admin.tracker') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'tracker' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeAdminMobileMenu()">
      <i class="fas fa-map-marker-alt mr-3 text-lg"></i>
      <span>Tracker</span>
    </a>
    <a href="{{ route('admin.service-zones') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'service-zones' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeAdminMobileMenu()">
      <i class="fas fa-draw-polygon mr-3 text-lg"></i>
      <span>Service Zones</span>
    </a>
    
    <a href="{{ route('admin.settings') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'settings' ? 'bg-green-700' : 'hover:bg-green-700' }}" onclick="closeAdminMobileMenu()">
      <i class="fas fa-cog mr-3 text-lg"></i>
      <span>Settings</span>
    </a>
  </nav>

  <div class="p-4 border-t border-green-500">
    <button onclick="openModal('adminLogoutModal'); closeAdminMobileMenu();" class="w-full flex items-center justify-center px-4 py-3 rounded-lg text-white bg-red-600 hover:bg-red-700 transition-colors duration-300">
      <i class="fas fa-sign-out-alt mr-2"></i>
      <span>Logout</span>
    </button>
  </div>
</div>
