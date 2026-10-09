<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Cleanify') | Cleanify</title>
  
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  
  @stack('styles')
</head>
<body class="user-page bg-gray-50 overflow-hidden">
  @php
    $navigationUnreadCount = auth()->check()
      ? auth()->user()->unreadNotifications()->count()
      : 0;
  @endphp

  <!-- Mobile Menu -->
  <x-mobile-menu :active="$activePage ?? 'home'" :unread-count="$navigationUnreadCount" />
  
  <div class="flex h-screen">
    <!-- Sidebar -->
    <x-sidebar :active="$activePage ?? 'home'" :unread-count="$navigationUnreadCount" />
    
    <!-- Main Content -->
    <div class="cleanify-main lg:ml-64 flex-1 overflow-y-auto">
      <main class="p-3 sm:p-4 lg:p-4">
        @yield('content')
      </main>
    </div>
  </div>
  
  <!-- Modals -->
  @stack('modals')
  
  <!-- Logout Confirmation Modal -->
  <x-modal id="logoutModal" title="Confirm Logout" icon="fas fa-sign-out-alt" color="red" variant="confirmation">
    <p>Are you sure you want to logout? You will need to login again to access your account.</p>
    
    <x-slot name="footer">
      <div class="admin-confirm-actions">
        <button type="button" onclick="closeModal('logoutModal')" class="admin-btn-secondary">
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
  
  <!-- Scripts -->
  @stack('scripts')
  @vite(['resources/js/app.js'])
</body>
</html>
