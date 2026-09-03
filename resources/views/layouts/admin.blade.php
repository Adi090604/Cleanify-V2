<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Admin') | Cleanify Admin</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @stack('styles')
</head>
<body class="bg-gray-50 overflow-x-hidden">
  <!-- Mobile Menu -->
  <x-admin.mobile-menu :active="$activePage ?? 'dashboard'" />
  
  <div class="flex h-screen">
    <x-admin.sidebar :active="$activePage ?? 'dashboard'" />

    <div class="cleanify-main lg:ml-64 flex-1 overflow-y-auto">
      <main class="p-3 sm:p-4 lg:p-4">
        @yield('content')
      </main>
    </div>
  </div>

  @stack('modals')
  
  <!-- Admin Logout Confirmation Modal -->
  <x-modal id="adminLogoutModal" title="Confirm Logout" icon="fa-sign-out-alt" color="red">
    <p class="text-gray-700 mb-4">Are you sure you want to logout? You will need to login again to access the admin panel.</p>
    
    <x-slot name="footer">
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('adminLogoutModal')" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg transition-colors duration-300">
          Cancel
        </button>
        <form method="POST" action="{{ route('logout') }}" class="inline">
          @csrf
          <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors duration-300 inline-flex items-center gap-2">
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
