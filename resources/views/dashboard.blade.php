@extends('layouts.app')

@section('title', 'Home')

@section('content')
  @if(session('success'))
    <x-alert type="success" dismissible class="mb-4">
      {{ session('success') }}
    </x-alert>
  @endif

  <!-- Topbar -->
  <div class="dashboard-toolbar bg-white rounded-xl shadow-sm p-4 mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
      <h4 class="font-semibold text-gray-800">Welcome back, <strong>{{ $user->name }}</strong> 👋</h4>
      <p class="text-gray-500 text-sm">See the latest updates from your community.</p>
    </div>
    <div class="flex items-center gap-2">
      <input type="text" id="searchInput" class="w-72 px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Search community reports...">
      <button onclick="searchReports()" class="px-4 py-2 border border-green-600 text-green-600 rounded-lg hover:bg-green-600 hover:text-white transition-colors duration-300">
        <i class="fas fa-search"></i>
      </button>
    </div>
  </div>

  <!-- Feeds -->
  <div class="space-y-4">
    @forelse($recentReports as $report)
      @php
        $reportUser = $report->user;
        $avatarClasses = $reportUser?->getAvatarBgClasses() ?? 'bg-gray-400';
        $avatarInitial = $reportUser?->getAvatarInitial() ?? '?';
        $authorName = $reportUser->name ?? 'Cleanify User';
        $userLiked = $report->likes->contains('user_id', $user->id);
      @endphp
      <article class="dashboard-card bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center mb-4">
          <div class="w-12 h-12 rounded-full {{ $avatarClasses }} flex items-center justify-center text-white font-bold mr-4">
            {{ $avatarInitial }}
          </div>
          <div>
            <h6 class="font-semibold text-gray-800 mb-0">{{ $authorName }}</h6>
            <small class="text-gray-500">{{ $report->created_at?->diffForHumans() }}</small>
          </div>
          <div class="ml-auto">
            <span class="text-sm font-medium px-3 py-1 rounded-full {{ $report->getStatusBadgeBgClass() }}">
              {{ ucfirst($report->status) }}
            </span>
          </div>
        </div>
        <p class="text-gray-700 mb-4">{{ \Illuminate\Support\Str::limit($report->description, 240) }}</p>
        @if($report->image_path)
          <img src="{{ asset('storage/' . $report->image_path) }}" alt="Report Image" class="w-full rounded-lg mb-4" onerror="this.style.display='none'">
        @endif
        <div class="flex items-center mt-4 text-sm text-gray-500 gap-4">
          <button type="button" class="flex items-center gap-2 like-button {{ $userLiked ? 'text-green-600' : 'hover:text-green-600' }} transition-colors duration-300" data-report-id="{{ $report->id }}">
            <i class="fas fa-heart"></i>
            <span class="like-label">{{ $userLiked ? 'Liked' : 'Like' }}</span>
            <span class="like-count text-gray-600">({{ $report->likes_count }})</span>
          </button>
          <button type="button" class="flex items-center gap-2 text-gray-500 hover:text-blue-500 transition-colors duration-300" onclick="window.location.href='{{ route('community-reports') }}?highlight={{ $report->id }}';">
            <i class="fas fa-eye"></i> View details
          </button>
        </div>

        <div class="mt-4 space-y-3" id="comments-list-{{ $report->id }}">
          @foreach($report->comments as $comment)
            @php
              $commentUser = $comment->user;
              $commentClasses = $commentUser?->getAvatarBgClasses() ?? 'bg-gray-400';
              $commentInitial = $commentUser?->getAvatarInitial() ?? '?';
            @endphp
            <div class="flex items-start gap-3">
              <div class="w-9 h-9 rounded-full {{ $commentClasses }} flex items-center justify-center text-white font-semibold text-sm">
                {{ $commentInitial }}
              </div>
              <div class="flex-1 bg-gray-50 rounded-2xl px-4 py-2">
                <div class="flex items-center justify-between">
                  <p class="text-sm font-semibold text-gray-800">{{ $commentUser->name ?? 'Cleanify User' }}</p>
                  <span class="text-xs text-gray-500">{{ $comment->created_at?->diffForHumans() }}</span>
                </div>
                <p class="text-sm text-gray-700">{{ $comment->comment }}</p>
              </div>
            </div>
          @endforeach
        </div>

        <form class="mt-4 comment-form" data-report-id="{{ $report->id }}">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full {{ $user->getAvatarBgClasses() }} flex items-center justify-center text-white font-bold text-sm">
              {{ $user->getAvatarInitial() }}
            </div>
            <div class="flex-1 flex gap-2">
              <input type="text" name="comment" class="flex-1 border border-gray-200 rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 text-sm" placeholder="Write a comment..." required>
              <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-full text-sm hover:bg-green-700 transition-colors duration-300">
                <i class="fas fa-paper-plane"></i>
              </button>
            </div>
          </div>
        </form>
      </article>
    @empty
      <div class="bg-white rounded-xl shadow-sm p-12 text-center">
        <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
        <p class="text-gray-600 text-lg">No community updates yet. Be the first to report something! 🌿</p>
      </div>
    @endforelse
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      // Search functionality
      function searchReports() {
        const query = document.getElementById('searchInput')?.value;
        if (query && query.trim()) {
          window.location.href = '{{ route("community-reports") }}?search=' + encodeURIComponent(query.trim());
        }
      }

      // Make searchReports available globally for onclick handler
      window.searchReports = searchReports;

      const searchInput = document.getElementById('searchInput');
      if (searchInput) {
        searchInput.addEventListener('keypress', function(event) {
          if (event.key === 'Enter') {
            searchReports();
          }
        });
      }

      // Like button functionality
      document.querySelectorAll('.like-button').forEach(button => {
        button.addEventListener('click', function () {
          const reportId = this.dataset.reportId;
          if (!reportId || !csrfToken) return;

          // Disable button during request
          this.disabled = true;
          const originalHTML = this.innerHTML;

          fetch(`{{ url('/reports') }}/${reportId}/like`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json',
            },
          })
            .then(response => {
              if (!response.ok) {
                return response.json().then(err => Promise.reject(err));
              }
              return response.json();
            })
            .then(data => {
              if (data.likes_count !== undefined) {
                const likeCount = this.querySelector('.like-count');
                const likeLabel = this.querySelector('.like-label');
                
                if (likeCount) {
                  likeCount.textContent = `(${data.likes_count})`;
                }
                if (likeLabel) {
                  likeLabel.textContent = data.liked ? 'Liked' : 'Like';
                }
                
                // Update button styling
                if (data.liked) {
                  this.classList.add('text-green-600');
                  this.classList.remove('hover:text-green-600');
                } else {
                  this.classList.remove('text-green-600');
                  this.classList.add('hover:text-green-600');
                }
              }
            })
            .catch((error) => {
              console.error('Like error:', error);
              if (typeof showToast === 'function') {
                showToast('error', error.message || 'Unable to update like right now.');
              }
            })
            .finally(() => {
              this.disabled = false;
            });
        });
      });

      // Comment form functionality
      document.querySelectorAll('.comment-form').forEach(form => {
        form.addEventListener('submit', function (event) {
          event.preventDefault();
          const reportId = this.dataset.reportId;
          const input = this.querySelector('input[name="comment"]');
          const submitButton = this.querySelector('button[type="submit"]');
          const comment = input?.value.trim();
          
          if (!comment || !reportId || !csrfToken) return;

          // Disable form during submission
          if (submitButton) submitButton.disabled = true;
          if (input) input.disabled = true;

          fetch(`{{ url('/reports') }}/${reportId}/comment`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json',
            },
            body: JSON.stringify({ comment }),
          })
            .then(response => {
              if (!response.ok) {
                return response.json().then(err => Promise.reject(err));
              }
              return response.json();
            })
            .then(data => {
              if (data.comment) {
                const list = document.getElementById(`comments-list-${reportId}`);
                if (list) {
                  const wrapper = document.createElement('div');
                  wrapper.className = 'flex items-start gap-3';
                  wrapper.innerHTML = `
                    <div class="w-9 h-9 rounded-full ${data.comment.avatar ?? 'bg-gray-400'} flex items-center justify-center text-white font-semibold text-sm">
                      ${data.comment.initial ?? '?'}
                    </div>
                    <div class="flex-1 bg-gray-50 rounded-2xl px-4 py-2">
                      <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-800">${data.comment.author ?? 'You'}</p>
                        <span class="text-xs text-gray-500">${data.comment.timestamp ?? 'Just now'}</span>
                      </div>
                      <p class="text-sm text-gray-700">${data.comment.comment}</p>
                    </div>
                  `;
                  list.prepend(wrapper);
                }
                if (input) input.value = '';
                
                if (typeof showToast === 'function') {
                  showToast('success', 'Comment posted successfully!');
                }
              }
            })
            .catch((error) => {
              console.error('Comment error:', error);
              if (typeof showToast === 'function') {
                const errorMessage = error.message || (error.errors?.comment?.[0]) || 'Unable to post comment right now.';
                showToast('error', errorMessage);
              }
            })
            .finally(() => {
              if (submitButton) submitButton.disabled = false;
              if (input) input.disabled = false;
            });
        });
      });
    });
  </script>
@endpush
