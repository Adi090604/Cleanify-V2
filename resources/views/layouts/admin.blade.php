<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Admin') | Cleanify Admin</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @stack('styles')
</head>
<body class="admin-page bg-gray-50 overflow-x-hidden {{ ($activePage ?? null) === 'dashboard' ? 'admin-dashboard-page' : '' }}">
  <!-- Mobile Menu -->
  <x-admin.mobile-menu :active="$activePage ?? 'dashboard'" :pending-report-count="$pendingReportCount" />
  
  <div class="flex h-screen">
    <x-admin.sidebar :active="$activePage ?? 'dashboard'" :pending-report-count="$pendingReportCount" />

    <div class="cleanify-main lg:ml-64 flex-1 overflow-y-auto">
      <main class="p-3 sm:p-4 lg:p-4">
        <div class="admin-content-shell">
          @yield('content')
        </div>
      </main>
    </div>
  </div>

  @stack('modals')
  
  <!-- Admin Logout Confirmation Modal -->
  <x-modal id="adminLogoutModal" title="Confirm Logout" icon="fas fa-sign-out-alt" color="red" variant="confirmation">
    <p>Are you sure you want to logout? You will need to login again to access the admin panel.</p>
    
    <x-slot name="footer">
      <div class="admin-confirm-actions">
        <button type="button" onclick="closeModal('adminLogoutModal')" class="admin-btn-secondary">
          Cancel
        </button>
        <form method="POST" action="{{ route('logout') }}" class="inline">
          @csrf
          <button type="submit" class="admin-btn-danger gap-2">
            <i class="fas fa-sign-out-alt"></i>
            Logout
          </button>
        </form>
      </div>
    </x-slot>
  </x-modal>
  
  @stack('scripts')
  @vite(['resources/js/app.js'])
</body>
</html>
