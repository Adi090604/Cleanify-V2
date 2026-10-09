@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
  @if(session('success'))
    <x-alert type="success" dismissible class="mb-4">{{ session('success') }}</x-alert>
  @endif
  @if(session('error'))
    <x-alert type="error" dismissible class="mb-4">{{ session('error') }}</x-alert>
  @endif

  <div class="bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-wrap gap-3 items-center justify-between">
    <div>
      <h4 class="font-semibold text-gray-800 flex items-center gap-3">
        <i class="fas fa-bell text-green-600"></i>Notifications
        @if($unreadCount)
          <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-semibold">{{ $unreadCount }} unread</span>
        @endif
      </h4>
      <p class="text-sm text-gray-500">Stay on top of schedule updates, report statuses, and reminders.</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="{{ route('notifications', ['filter' => 'all', 'category' => $categoryFilter, 'include_muted' => $includeMuted]) }}" class="px-3 py-1.5 rounded-full text-sm {{ $filter === 'all' ? 'bg-green-100 text-green-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">All</a>
      <a href="{{ route('notifications', ['filter' => 'unread', 'category' => $categoryFilter, 'include_muted' => $includeMuted]) }}" class="px-3 py-1.5 rounded-full text-sm {{ $filter === 'unread' ? 'bg-green-100 text-green-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">Unread</a>
      <form method="POST" action="{{ route('notifications.mark-all-read') }}" class="ml-2">
        @csrf
        <button type="submit" class="px-4 py-2 border border-green-600 text-green-600 rounded-lg hover:bg-green-600 hover:text-white transition-colors duration-300 text-sm" {{ $unreadCount ? '' : 'disabled' }}>
          <i class="fas fa-check-double mr-2"></i>Mark all as read
        </button>
      </form>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
    <form method="GET" action="{{ route('notifications') }}" class="flex items-center gap-3 flex-wrap">
      <input type="hidden" name="filter" value="{{ $filter }}">
      <label class="text-sm text-gray-600 flex items-center gap-2">
        <span>Category</span>
        <select name="category" class="border border-gray-300 rounded-full px-3 py-1.5 text-sm focus:ring-green-500">
          <option value="all" {{ $categoryFilter === 'all' ? 'selected' : '' }}>All categories</option>
          @foreach($categories as $key => $label)
            <option value="{{ $key }}" {{ $categoryFilter === $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label class="inline-flex items-center text-sm text-gray-600 gap-2">
        <input type="checkbox" name="include_muted" value="1" {{ $includeMuted ? 'checked' : '' }} class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
        <span>Include muted categories</span>
      </label>
      <button type="submit" class="px-4 py-1.5 bg-green-600 text-white rounded-full text-sm hover:bg-green-700">Apply</button>
    </form>
  </div>

  <div class="bg-white rounded-xl shadow-sm p-6">
    @if($notifications->count())
      <div class="space-y-4">
        @foreach($notifications as $notification)
          @php
            $isUnread = is_null($notification->read_at);
            $data = $notification->data ?? [];
            $title = $data['title'] ?? 'Notification';
            $message = $data['message'] ?? ($notification->type ?? '');
            $icon = $data['icon'] ?? 'fa-bell';
            $color = $data['color'] ?? 'bg-green-600';
            $category = $data['category'] ?? null;
            
            // Generate action URL based on category (this ensures correct routes)
            $actionUrl = null;
            if ($category === 'schedule') {
              $actionUrl = route('garbage-schedule');
            } elseif ($category === 'reports') {
              $actionUrl = route('community-reports');
            } elseif ($category === 'tracker') {
              $actionUrl = route('tracker');
            } elseif (isset($data['url'])) {
              // Fallback to stored URL, but normalize it
              $storedUrl = $data['url'];
              if (substr($storedUrl, 0, 1) === '/') {
                // Relative URL - use as is
                $actionUrl = $storedUrl;
              } elseif (substr($storedUrl, 0, 7) === 'http://' || substr($storedUrl, 0, 8) === 'https://') {
                // Full URL - extract path
                $parsed = parse_url($storedUrl);
                $actionUrl = isset($parsed['path']) ? $parsed['path'] : $storedUrl;
              } else {
                $actionUrl = $storedUrl;
              }
            }
          @endphp
          <div class="flex items-start p-4 border border-gray-100 rounded-xl {{ $isUnread ? 'bg-green-50' : 'bg-white' }}">
            <div class="w-12 h-12 {{ $color }} text-white rounded-full flex items-center justify-center mr-4 flex-shrink-0">
              <i class="fas {{ $icon }} text-lg"></i>
            </div>
            <div class="flex-1">
              <p class="text-gray-800 font-semibold">{{ $title }}</p>
              <p class="text-gray-600 text-sm">{{ $message }}</p>
              <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                <span><i class="fas fa-clock mr-1"></i>{{ $notification->created_at->diffForHumans() }}</span>
                @if($isUnread)
                  <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium">Unread</span>
                @endif
              </div>
            </div>
            <div class="flex flex-col gap-2 ml-4">
              @if($actionUrl)
                <a href="{{ $actionUrl }}" class="px-3 py-1.5 text-sm text-blue-600 hover:underline">View</a>
              @endif
              <form method="POST" action="{{ route('notifications.mark-read', $notification->id) }}">
                @csrf
                <button type="submit" class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50">
                  <i class="fas fa-check mr-1 text-green-600"></i>{{ $isUnread ? 'Mark read' : 'Read' }}
                </button>
              </form>
              <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}" class="notification-dismiss-form">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50">
                  <i class="fas fa-times mr-1 text-red-500"></i>Dismiss
                </button>
              </form>
            </div>
          </div>
        @endforeach
      </div>

      <div class="mt-6">
        {{ $notifications->links() }}
      </div>
    @else
      <div class="text-center py-16">
        <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
        <p class="text-gray-600 text-lg">You’re all caught up! No notifications right now.</p>
      </div>
    @endif
  </div>

  <div class="grid md:grid-cols-2 gap-4 mt-6">
    <div class="bg-white rounded-xl shadow-sm p-5 border border-green-100">
      <h5 class="text-gray-800 font-semibold mb-3 flex items-center gap-2">
        <i class="fas fa-filter text-green-600"></i>Category Preferences
      </h5>
      <p class="text-sm text-gray-500 mb-4">Toggle which alerts land in your inbox.</p>
      <form method="POST" action="{{ route('notifications.preferences') }}" class="space-y-3">
        @csrf
        <div class="grid grid-cols-1 gap-2">
          @foreach($categories as $key => $label)
            <label class="flex items-center justify-between border border-gray-100 rounded-lg px-3 py-2 text-sm text-gray-700">
              <div>
                <p class="font-medium">{{ $label }}</p>
                <p class="text-xs text-gray-500">{{ ucfirst($key) }} alerts</p>
              </div>
              <input type="hidden" name="preferences[{{ $key }}]" value="0">
              <input type="checkbox" name="preferences[{{ $key }}]" value="1" {{ $preferences[$key] ? 'checked' : '' }} class="w-5 h-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
            </label>
          @endforeach
        </div>
        <button type="submit" class="w-full mt-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">Save preferences</button>
      </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5 border border-green-100">
      <h5 class="text-gray-800 font-semibold mb-3 flex items-center gap-2">
        <i class="fas fa-lightbulb text-yellow-500"></i>Tips
      </h5>
      <ul class="space-y-3 text-sm text-gray-600">
        <li class="flex items-start gap-2"><i class="fas fa-bell text-green-600 mt-1"></i>Mark important alerts with the “View” button to jump right to the related page.</li>
        <li class="flex items-start gap-2"><i class="fas fa-envelope text-green-600 mt-1"></i>Keep email reminders enabled in Settings to receive critical updates even when logged out.</li>
        <li class="flex items-start gap-2"><i class="fas fa-sliders-h text-green-600 mt-1"></i>Mute categories you don’t need to cut down on noise.</li>
      </ul>
    </div>
  </div>
@endsection

@push('modals')
  <x-modal id="dismissNotificationModal" title="Dismiss Notification" icon="fas fa-times-circle" color="red" variant="confirmation">
    <p>Are you sure you want to dismiss this notification?</p>
    <div class="admin-confirm-warning">
      <i class="fas fa-exclamation-circle mt-0.5" aria-hidden="true"></i>
      <p>The notification will be removed from your inbox.</p>
    </div>

    @slot('footer')
      <div class="admin-confirm-actions">
        <button type="button" onclick="closeModal('dismissNotificationModal')" class="admin-btn-secondary">Cancel</button>
        <button type="button" id="confirmNotificationDismiss" class="admin-btn-danger">
          <i class="fas fa-times mr-2" aria-hidden="true"></i>Dismiss
        </button>
      </div>
    @endslot
  </x-modal>
@endpush

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      
      // Handle mark all as read
      const markAllReadForm = document.querySelector('form[action*="mark-all-read"]');
      if (markAllReadForm) {
        markAllReadForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          
          fetch(this.action, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
          })
          .then(response => {
            if (response.redirected) {
              window.location.href = response.url;
              return;
            }
            return response.json();
          })
          .then(data => {
            if (data && data.success) {
              if (typeof showToast === 'function') {
                showToast('success', 'All notifications marked as read');
              }
              setTimeout(() => location.reload(), 500);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
              showToast('error', 'Failed to mark all as read. Please try again.');
            } else {
              this.submit(); // Fallback to normal form submission
            }
          });
        });
      }
      
      // Handle individual notification mark as read
      document.querySelectorAll('form[action*="mark-read"]').forEach(form => {
        form.addEventListener('submit', function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          const notificationCard = this.closest('.border');
          
          fetch(this.action, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
          })
          .then(response => {
            if (response.redirected) {
              window.location.href = response.url;
              return;
            }
            return response.json();
          })
          .then(data => {
            if (data && data.success) {
              if (typeof showToast === 'function') {
                showToast('success', 'Notification marked as read');
              }
              // Update UI
              if (notificationCard) {
                notificationCard.classList.remove('bg-green-50');
                notificationCard.classList.add('bg-white');
                const unreadBadge = notificationCard.querySelector('.bg-red-100');
                if (unreadBadge) {
                  unreadBadge.remove();
                }
                const markReadBtn = this.querySelector('button');
                if (markReadBtn) {
                  markReadBtn.innerHTML = '<i class="fas fa-check mr-1 text-green-600"></i>Read';
                }
              }
              setTimeout(() => location.reload(), 500);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
              showToast('error', 'Failed to mark as read. Please try again.');
            } else {
              this.submit(); // Fallback to normal form submission
            }
          });
        });
      });
      
      // Handle notification delete/dismiss with the shared confirmation modal.
      let pendingDismissForm = null;

      document.querySelectorAll('.notification-dismiss-form').forEach(form => {
        form.addEventListener('submit', function(e) {
          e.preventDefault();
          pendingDismissForm = this;
          openModal('dismissNotificationModal');
        });
      });

      document.getElementById('confirmNotificationDismiss')?.addEventListener('click', function() {
        if (!pendingDismissForm) return;

        const form = pendingDismissForm;
        const formData = new FormData(form);
        const notificationCard = form.closest('.border');
        const confirmButton = this;
        pendingDismissForm = null;
        confirmButton.disabled = true;
        confirmButton.classList.add('cursor-wait', 'opacity-60');
        closeModal('dismissNotificationModal');

        fetch(form.action, {
            method: 'DELETE',
            headers: {
              'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
          })
          .then(response => {
            if (response.redirected) {
              window.location.href = response.url;
              return;
            }
            return response.json();
          })
          .then(data => {
            if (data && data.success) {
              showToast('success', 'Notification dismissed');
              // Remove notification from UI with animation
              if (notificationCard) {
                notificationCard.style.transition = 'opacity 0.3s, transform 0.3s';
                notificationCard.style.opacity = '0';
                notificationCard.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                  notificationCard.remove();
                  // Check if no notifications left
                  const notificationsContainer = document.querySelector('.space-y-4');
                  if (notificationsContainer && notificationsContainer.children.length === 0) {
                    location.reload();
                  }
                }, 300);
              } else {
                setTimeout(() => location.reload(), 500);
              }
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Failed to dismiss notification. Please try again.');
          })
          .finally(() => {
            confirmButton.disabled = false;
            confirmButton.classList.remove('cursor-wait', 'opacity-60');
          });
      });
      
      // Handle preferences form
      const preferencesForm = document.querySelector('form[action*="preferences"]');
      if (preferencesForm) {
        preferencesForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          
          fetch(this.action, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
          })
          .then(response => {
            if (response.redirected) {
              window.location.href = response.url;
              return;
            }
            return response.json();
          })
          .then(data => {
            if (data && data.success) {
              if (typeof showToast === 'function') {
                showToast('success', 'Notification preferences updated');
              }
              setTimeout(() => location.reload(), 500);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
              showToast('error', 'Failed to update preferences. Please try again.');
            } else {
              this.submit(); // Fallback to normal form submission
            }
          });
        });
      }
      
      // Handle filter form submission
      const filterForm = document.querySelector('form[action*="notifications"]:not([action*="mark"]):not([action*="preferences"]):not([action*="destroy"])');
      if (filterForm) {
        const categorySelect = filterForm.querySelector('select[name="category"]');
        const includeMutedCheckbox = filterForm.querySelector('input[name="include_muted"]');
        
        if (categorySelect) {
          categorySelect.addEventListener('change', function() {
            filterForm.submit();
          });
        }
        
        if (includeMutedCheckbox) {
          includeMutedCheckbox.addEventListener('change', function() {
            filterForm.submit();
          });
        }
      }
    });
  </script>
@endpush
