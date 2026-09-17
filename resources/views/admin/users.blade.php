@extends('layouts.admin')

@section('title', 'Users')

@section('content')
  <div class="admin-page-header flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
    <div>
      <h2 class="text-3xl font-bold text-gray-800">
        <i class="fas fa-users text-green-600 mr-3"></i>User Management
      </h2>
      <p class="text-gray-600 mt-1">Manage user accounts, roles, and permissions</p>
    </div>
    <form method="GET" action="{{ route('admin.users') }}" class="flex w-full lg:w-auto max-w-lg">
      <input 
        id="userSearchInput" 
        name="search" 
        type="text" 
        value="{{ $search }}"
        class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" 
        placeholder="Search users..."
      >
      <button type="submit" class="px-4 bg-green-600 text-white rounded-r-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-search"></i>
      </button>
      @if($search)
        <a href="{{ route('admin.users') }}" class="ml-2 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-300">
          <i class="fas fa-times"></i>
        </a>
      @endif
    </form>
  </div>

  @if(session('success'))
    <x-alert type="success" dismissible class="mb-4">
      {{ session('success') }}
    </x-alert>
  @endif

  @if($errors->any())
    <x-alert type="error" dismissible class="mb-4">
      <ul class="list-disc list-inside text-sm">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </x-alert>
  @endif

  <div class="admin-stat-grid grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 mb-5">
    <x-admin.stat-card icon="fas fa-users" title="Total Users" :value="$totalUsers" />
    <x-admin.stat-card icon="fas fa-user-shield" title="Admins" :value="$totalAdmins" borderClass="border-l-4 border-blue-500" iconWrapperClass="bg-blue-100" iconColorClass="text-blue-600" />
    <x-admin.stat-card icon="fas fa-user-tie" title="Regular Users" :value="$totalRegular" borderClass="border-l-4 border-yellow-500" iconWrapperClass="bg-yellow-100" iconColorClass="text-yellow-600" />
    <x-admin.stat-card icon="fas fa-user-slash" title="Banned Users" :value="$bannedUsers" borderClass="border-l-4 border-red-500" iconWrapperClass="bg-red-100" iconColorClass="text-red-600" />
  </div>

  @php
    $roleBadge = [
      'User' => 'bg-blue-100 text-blue-800',
      'Admin' => 'bg-green-100 text-green-800',
      'Moderator' => 'bg-yellow-100 text-yellow-800',
    ];
  @endphp

  <div class="admin-table-card bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full" id="userTable">
        <thead>
          <tr class="admin-table-header">
            @foreach (['#','Avatar','Name','Email','Role','Status','Date Joined','Actions'] as $heading)
              <th class="px-4 py-3 text-left text-sm font-semibold">{{ $heading }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody id="userTableBody" class="divide-y divide-gray-200">
          @forelse ($users as $user)
            <tr class="hover:bg-gray-50 transition-colors duration-200" data-user="{{ json_encode([
              'id' => $user->id,
              'name' => $user->name,
              'email' => $user->email,
              'is_admin' => $user->is_admin,
              'created_at' => $user->created_at->format('M d, Y'),
              'updated_at' => $user->updated_at->format('M d, Y'),
              'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->format('M d, Y') : null,
            ]) }}">
              <td class="px-4 py-3">{{ $user->id }}</td>
              <td class="px-4 py-3">
                <div class="w-10 h-10 rounded-full {{ $user->getAvatarBgClasses() }} flex items-center justify-center text-white font-semibold text-sm">
                  {{ $user->getAvatarInitial() }}
                </div>
              </td>
              <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
              <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
              <td class="px-4 py-3">
                <span class="admin-status-badge {{ $user->is_admin ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                  <i class="fas {{ $user->is_admin ? 'fa-user-shield' : 'fa-user' }} mr-1"></i>{{ $user->is_admin ? 'Admin' : 'User' }}
                </span>
              </td>
              <td class="px-4 py-3">
                @if($user->isBanned())
                  <span class="text-red-600 font-semibold">
                    <i class="fas fa-ban text-xs mr-1"></i>Banned
                  </span>
                @else
                  <span class="text-green-600 font-semibold">
                    <i class="fas fa-circle text-xs mr-1"></i>Active
                  </span>
                @endif
              </td>
              <td class="px-4 py-3 text-gray-600">{{ $user->created_at->format('M d, Y') }}</td>
              <td class="px-4 py-3">
                <div class="flex space-x-2">
                  <button onclick="openUserView({{ $user->id }})" class="admin-icon-button bg-purple-500 text-white hover:bg-purple-600">
                    <i class="fas fa-eye text-xs"></i>
                  </button>
                  <button onclick="openUserEdit({{ $user->id }})" class="admin-icon-button bg-blue-500 text-white hover:bg-blue-600">
                    <i class="fas fa-edit text-xs"></i>
                  </button>
                  @if(auth()->id() != $user->id && !$user->isAdmin())
                    @if($user->isBanned())
                      <button onclick="openUserUnban({{ $user->id }})" class="admin-icon-button bg-green-500 text-white hover:bg-green-600" title="Unban User">
                        <i class="fas fa-unlock text-sm"></i>
                      </button>
                    @else
                      <button onclick="openUserBan({{ $user->id }})" class="admin-icon-button bg-orange-500 text-white hover:bg-orange-600" title="Ban User">
                        <i class="fas fa-ban text-sm"></i>
                      </button>
                    @endif
                  @endif
                  @if(auth()->id() != $user->id)
                    <button onclick="openUserDelete({{ $user->id }})" class="admin-icon-button bg-red-500 text-white hover:bg-red-600">
                      <i class="fas fa-trash text-xs"></i>
                    </button>
                  @else
                    <button disabled class="admin-icon-button bg-gray-300 text-gray-500 cursor-not-allowed" title="Cannot delete your own account">
                      <i class="fas fa-trash text-xs"></i>
                    </button>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                <i class="fas fa-users text-4xl mb-2 block"></i>
                No users found
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-200">
      <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
        <div class="text-sm text-gray-600">
          @if($users->total() > 0)
            Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} entries
          @else
            No entries found
          @endif
        </div>
        <div class="flex items-center">
          {{ $users->links('pagination::tailwind') }}
        </div>
      </div>
    </div>
  </div>
@endsection

@push('modals')
  <x-modal id="viewUserModal" title="User Details" icon="fas fa-eye" color="green">
      <div class="flex flex-col md:flex-row items-center md:items-start gap-6 mb-6">
      <div class="flex-shrink-0">
        <div id="viewUserAvatar" class="w-32 h-32 rounded-full flex items-center justify-center text-white font-bold text-4xl border-4 border-purple-100"></div>
      </div>
      <div class="flex-1 text-center md:text-left">
        <h3 id="viewUserName" class="text-2xl font-bold text-gray-800 mb-2"></h3>
        <div id="viewUserBadges" class="flex flex-wrap gap-2 justify-center md:justify-start mb-4"></div>
        <p class="text-gray-600"><i class="fas fa-envelope mr-2 text-purple-600"></i><span id="viewUserEmail"></span></p>
      </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
      <div class="bg-gray-50 rounded-lg p-4" id="viewUserInfo"></div>
      <div class="bg-gray-50 rounded-lg p-4" id="viewUserStats"></div>
    </div>
    <div class="bg-gray-50 rounded-lg p-4" id="viewUserExtra"></div>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('viewUserModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Close
        </button>
        <button onclick="openEditFromView()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-300">
          <i class="fas fa-edit mr-2"></i>Edit User
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="editUserModal" title="Edit User" icon="fas fa-user-edit" color="green">
    <form id="editUserForm" method="POST" action="" class="space-y-4">
      @csrf
      @method('PUT')
      <input type="hidden" name="id" id="editUserId">
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-user mr-2 text-green-600"></i>Full Name</label>
        <input type="text" name="name" id="editUserName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
        @error('name')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-envelope mr-2 text-green-600"></i>Email</label>
        <input type="email" name="email" id="editUserEmail" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
        @error('email')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-user-tag mr-2 text-green-600"></i>Role</label>
        <select name="is_admin" id="editUserIsAdmin" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
          <option value="0">User</option>
          <option value="1">Admin</option>
        </select>
        @error('is_admin')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
    </form>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('editUserModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Cancel
        </button>
        <button type="submit" form="editUserForm" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
          <i class="fas fa-save mr-2"></i>Save Changes
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="deleteUserModal" title="Delete User" icon="fas fa-trash-alt" color="red" variant="confirmation">
    <p>Are you sure you want to delete this user? This action cannot be undone and all user data will be permanently removed.</p>
    <div class="admin-confirm-warning">
      <i class="fas fa-exclamation-circle mt-0.5" aria-hidden="true"></i>
      <p>This will delete the user account and all associated data including posts and reports.</p>
    </div>
    @slot('footer')
      <div class="admin-confirm-actions">
        <button type="button" onclick="closeModal('deleteUserModal')" class="admin-btn-secondary">
          Cancel
        </button>
        <form id="deleteUserForm" method="POST" action="" class="inline">
          @csrf
          @method('DELETE')
          <button type="submit" class="admin-btn-danger">
            <i class="fas fa-trash mr-2"></i>Delete User
          </button>
        </form>
      </div>
    @endslot
  </x-modal>

  <x-modal id="banUserModal" title="Ban User" icon="fas fa-ban" color="orange" variant="confirmation">
    <p>Are you sure you want to ban this user? Banned users will not be able to log in or access the application.</p>
    <div class="admin-confirm-warning">
      <i class="fas fa-exclamation-circle mt-0.5" aria-hidden="true"></i>
      <p>The user will be immediately logged out if currently active and will not be able to access their account.</p>
    </div>
    @slot('footer')
      <div class="admin-confirm-actions">
        <button type="button" onclick="closeModal('banUserModal')" class="admin-btn-secondary">
          Cancel
        </button>
        <form id="banUserForm" method="POST" action="" class="inline">
          @csrf
          <button type="submit" class="admin-btn-warning">
            <i class="fas fa-ban mr-2"></i>Ban User
          </button>
        </form>
      </div>
    @endslot
  </x-modal>

  <x-modal id="unbanUserModal" title="Unban User" icon="fas fa-unlock" color="green" variant="confirmation">
    <p>Are you sure you want to unban this user? The user will be able to log in and access the application again.</p>
    @slot('footer')
      <div class="admin-confirm-actions">
        <button type="button" onclick="closeModal('unbanUserModal')" class="admin-btn-secondary">
          Cancel
        </button>
        <form id="unbanUserForm" method="POST" action="" class="inline">
          @csrf
          <button type="submit" class="admin-btn-success">
            <i class="fas fa-unlock mr-2"></i>Unban User
          </button>
        </form>
      </div>
    @endslot
  </x-modal>
@endpush

@push('scripts')
  <script>
    let currentUserId = null;

    function getUserFromTable(id) {
      const rows = document.querySelectorAll('#userTableBody tr[data-user]');
      for (let row of rows) {
        const userData = JSON.parse(row.dataset.user);
        if (userData.id === id) {
          return userData;
        }
      }
      return null;
    }

    function openUserView(id) {
      const user = getUserFromTable(id);
      if (!user) {
        if (typeof showToast === 'function') {
          showToast('error', 'User not found. Please try again.');
        }
        return;
      }
      
      currentUserId = id;
      
      // Generate avatar with first letter and consistent color
      const firstLetter = user.name.charAt(0).toUpperCase();
      const colors = [
        ['bg-red-500', 'bg-red-600'],
        ['bg-orange-500', 'bg-orange-600'],
        ['bg-amber-500', 'bg-amber-600'],
        ['bg-yellow-500', 'bg-yellow-600'],
        ['bg-lime-500', 'bg-lime-600'],
        ['bg-green-500', 'bg-green-600'],
        ['bg-emerald-500', 'bg-emerald-600'],
        ['bg-teal-500', 'bg-teal-600'],
        ['bg-cyan-500', 'bg-cyan-600'],
        ['bg-sky-500', 'bg-sky-600'],
        ['bg-blue-500', 'bg-blue-600'],
        ['bg-indigo-500', 'bg-indigo-600'],
        ['bg-violet-500', 'bg-violet-600'],
        ['bg-purple-500', 'bg-purple-600'],
        ['bg-fuchsia-500', 'bg-fuchsia-600'],
        ['bg-pink-500', 'bg-pink-600'],
        ['bg-rose-500', 'bg-rose-600'],
      ];
      const charCode = firstLetter.charCodeAt(0);
      const colorIndex = (charCode - 65) % colors.length;
      const avatarBg = `bg-gradient-to-br ${colors[colorIndex][0]} ${colors[colorIndex][1]}`;
      
      const avatarElement = document.getElementById('viewUserAvatar');
      avatarElement.className = `w-32 h-32 rounded-full flex items-center justify-center text-white font-bold text-4xl border-4 border-purple-100 ${avatarBg}`;
      avatarElement.textContent = firstLetter;
      document.getElementById('viewUserName').textContent = user.name;
      document.getElementById('viewUserEmail').textContent = user.email;
      document.getElementById('viewUserBadges').innerHTML = `
        <span class="${user.is_admin ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'} text-sm font-medium px-3 py-1 rounded-full">
          <i class="fas ${user.is_admin ? 'fa-user-shield' : 'fa-user'} mr-1"></i>${user.is_admin ? 'Admin' : 'User'}
        </span>
        ${user.is_banned ? 
          '<span class="bg-red-100 text-red-800 text-sm font-medium px-3 py-1 rounded-full"><i class="fas fa-ban text-xs mr-1"></i>Banned</span>' :
          '<span class="bg-green-100 text-green-800 text-sm font-medium px-3 py-1 rounded-full"><i class="fas fa-circle text-xs mr-1"></i>Active</span>'
        }`;
      document.getElementById('viewUserInfo').innerHTML = `
        <h4 class="font-semibold text-gray-800 mb-3"><i class="fas fa-info-circle mr-2 text-purple-600"></i>Basic Information</h4>
        <div class="space-y-2">
          <div class="flex justify-between"><span class="text-gray-600">Member Since:</span><span class="font-medium">${user.created_at}</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Email Verified:</span><span class="${user.email_verified_at ? 'text-green-600' : 'text-yellow-600'} font-medium"><i class="fas ${user.email_verified_at ? 'fa-check-circle' : 'fa-times-circle'} mr-1"></i>${user.email_verified_at ? 'Yes' : 'No'}</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Account Status:</span><span class="${user.is_banned ? 'text-red-600' : 'text-green-600'} font-medium"><i class="fas ${user.is_banned ? 'fa-ban' : 'fa-check-circle'} mr-1"></i>${user.is_banned ? 'Banned' : 'Active'}</span></div>
          ${user.banned_at ? `<div class="flex justify-between"><span class="text-gray-600">Banned Since:</span><span class="font-medium">${user.banned_at}</span></div>` : ''}
        </div>`;
      document.getElementById('viewUserStats').innerHTML = `
        <h4 class="font-semibold text-gray-800 mb-3"><i class="fas fa-chart-bar mr-2 text-purple-600"></i>Account Details</h4>
        <div class="space-y-2">
          <div class="flex justify-between"><span class="text-gray-600">User ID:</span><span class="font-medium">#${user.id}</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Account Type:</span><span class="font-medium">${user.is_admin ? 'Administrator' : 'Regular User'}</span></div>
        </div>`;
      document.getElementById('viewUserExtra').innerHTML = `
        <h4 class="font-semibold text-gray-800 mb-3"><i class="fas fa-sticky-note mr-2 text-purple-600"></i>Additional Information</h4>
        <div class="space-y-2">
          <div class="flex justify-between"><span class="text-gray-600">Last Updated:</span><span class="font-medium">${user.updated_at}</span></div>
        </div>`;
      openModal('viewUserModal');
    }

    function openUserEdit(id) {
      const user = getUserFromTable(id);
      if (!user) {
        if (typeof showToast === 'function') {
          showToast('error', 'User not found. Please try again.');
        }
        return;
      }
      
      currentUserId = id;
      const form = document.getElementById('editUserForm');
      form.action = `/admin/users/${id}`;
      document.getElementById('editUserId').value = user.id;
      document.getElementById('editUserName').value = user.name;
      document.getElementById('editUserEmail').value = user.email;
      document.getElementById('editUserIsAdmin').value = user.is_admin ? '1' : '0';
      openModal('editUserModal');
    }

    function openEditFromView() {
      closeModal('viewUserModal');
      setTimeout(() => openUserEdit(currentUserId), 300);
    }

    function openUserDelete(id) {
      currentUserId = id;
      const form = document.getElementById('deleteUserForm');
      form.action = `/admin/users/${id}`;
      openModal('deleteUserModal');
    }

    window.openUserBan = function(id) {
      console.log('openUserBan called with id:', id);
      currentUserId = id;
      
      // Check if modal exists
      const modal = document.getElementById('banUserModal');
      if (!modal) {
        console.error('Ban modal not found in DOM');
        if (typeof showToast === 'function') {
          showToast('error', 'Ban modal not found. Please refresh the page.');
        }
        return;
      }
      
      const form = document.getElementById('banUserForm');
      if (!form) {
        console.error('Ban form not found');
        if (typeof showToast === 'function') {
          showToast('error', 'Unable to open ban modal. Please refresh the page.');
        }
        return;
      }
      
      form.action = `/admin/users/${id}/ban`;
      console.log('Opening ban modal, form action:', form.action);
      
      // Try to open modal
      if (typeof openModal === 'function') {
        openModal('banUserModal');
        // Verify modal is visible
        setTimeout(() => {
          if (modal.classList.contains('hidden')) {
            console.error('Modal still hidden after openModal call');
            modal.classList.remove('hidden');
          }
        }, 100);
      } else {
        console.error('openModal function not found, trying direct approach');
        modal.classList.remove('hidden');
      }
    };

    window.openUserUnban = function(id) {
      console.log('openUserUnban called with id:', id);
      currentUserId = id;
      
      // Check if modal exists
      const modal = document.getElementById('unbanUserModal');
      if (!modal) {
        console.error('Unban modal not found in DOM');
        if (typeof showToast === 'function') {
          showToast('error', 'Unban modal not found. Please refresh the page.');
        }
        return;
      }
      
      const form = document.getElementById('unbanUserForm');
      if (!form) {
        console.error('Unban form not found');
        if (typeof showToast === 'function') {
          showToast('error', 'Unable to open unban modal. Please refresh the page.');
        }
        return;
      }
      
      form.action = `/admin/users/${id}/unban`;
      console.log('Opening unban modal, form action:', form.action);
      
      // Try to open modal
      if (typeof openModal === 'function') {
        openModal('unbanUserModal');
        // Verify modal is visible
        setTimeout(() => {
          if (modal.classList.contains('hidden')) {
            console.error('Modal still hidden after openModal call');
            modal.classList.remove('hidden');
          }
        }, 100);
      } else {
        console.error('openModal function not found, trying direct approach');
        modal.classList.remove('hidden');
      }
    };

    // Real-time search (optional - can also use form submission)
    document.getElementById('userSearchInput')?.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        this.closest('form').submit();
      }
    });
  </script>
@endpush
