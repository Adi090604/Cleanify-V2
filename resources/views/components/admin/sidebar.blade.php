@props(['active' => 'dashboard', 'pendingReportCount' => 0])

<div class="cleanify-sidebar hidden lg:block w-64 bg-green-600 text-white flex flex-col fixed h-full">
  <div class="p-4 border-b border-green-500 text-center">
    <img src="/assets/icons/cleanifyicon.png" alt="Cleanify Logo" class="w-16 h-16 mx-auto mb-2">
    <h5 class="font-semibold text-lg">Cleanify Admin</h5>
  </div>

  <nav class="flex-1 p-4 space-y-2">
    <a href="{{ route('admin.dashboard') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'dashboard' ? 'bg-green-700' : 'hover:bg-green-700' }}">
      <i class="fas fa-tachometer-alt mr-3 text-lg"></i>
      <span>Dashboard</span>
    </a>
    <a href="{{ route('admin.users') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'users' ? 'bg-green-700' : 'hover:bg-green-700' }}">
      <i class="fas fa-users mr-3 text-lg"></i>
      <span>Users</span>
    </a>
    <a href="{{ route('admin.reports') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'reports' ? 'bg-green-700' : 'hover:bg-green-700' }}">
      <i class="fas fa-flag mr-3 text-lg"></i>
      <span>Reports</span>
      @if($pendingReportCount > 0)
        <span class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold leading-none text-white" aria-label="{{ $pendingReportCount }} pending reports">
          {{ $pendingReportCount > 99 ? '99+' : $pendingReportCount }}
        </span>
      @endif
    </a>
    <a href="{{ route('admin.user-reports') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'user-reports' ? 'bg-green-700' : 'hover:bg-green-700' }}">
      <i class="fas fa-user-shield mr-3 text-lg"></i>
      <span>User Reports</span>
    </a>
    <a href="{{ route('admin.schedule') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'schedule' ? 'bg-green-700' : 'hover:bg-green-700' }}">
      <i class="fas fa-calendar-alt mr-3 text-lg"></i>
      <span>Schedule</span>
    </a>
    <a href="{{ route('admin.tracker') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'tracker' ? 'bg-green-700' : 'hover:bg-green-700' }}">
      <i class="fas fa-map-marker-alt mr-3 text-lg"></i>
      <span>Tracker</span>
    </a>
    <a href="{{ route('admin.service-zones') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'service-zones' ? 'bg-green-700' : 'hover:bg-green-700' }}">
      <i class="fas fa-draw-polygon mr-3 text-lg"></i>
      <span>Service Zones</span>
    </a>
    <a href="{{ route('admin.settings') }}" class="flex items-center px-4 py-3 rounded-lg text-white transition-colors duration-300 {{ $active === 'settings' ? 'bg-green-700' : 'hover:bg-green-700' }}">
      <i class="fas fa-cog mr-3 text-lg"></i>
      <span>Settings</span>
    </a>
  </nav>

  <div class="p-4 border-t border-green-500">
    <button onclick="openModal('adminLogoutModal')" class="w-full flex items-center justify-center px-4 py-3 rounded-lg text-white bg-red-600 hover:bg-red-700 transition-colors duration-300">
      <i class="fas fa-sign-out-alt mr-2"></i>
      <span>Logout</span>
    </button>
  </div>
</div>
