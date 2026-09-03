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
<body class="bg-gray-50 overflow-hidden">
  <!-- Mobile Menu -->
  <x-mobile-menu :active="$activePage ?? 'home'" />
  
  <div class="flex h-screen">
    <!-- Sidebar -->
    <x-sidebar :active="$activePage ?? 'home'" />
    
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
  <x-modal id="logoutModal" title="Confirm Logout" icon="fa-sign-out-alt" color="red">
    <p class="text-gray-700 mb-4">Are you sure you want to logout? You will need to login again to access your account.</p>
    
    <x-slot name="footer">
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('logoutModal')" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg transition-colors duration-300">
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
  
  <!-- Scripts -->
  @stack('scripts')
  @vite(['resources/js/app.js'])
</body>
</html>
