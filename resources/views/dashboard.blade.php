@extends('layouts.app')

@section('title', 'Home')

@section('content')
  <div class="resident-home">
    @if(session('success'))
      <x-alert type="success" dismissible class="mb-4">
        {{ session('success') }}
      </x-alert>
    @endif

    <!-- Home heading and report search -->
    <header class="dashboard-toolbar resident-home-header mb-6 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
      <div class="resident-home-heading">
        <p class="resident-eyebrow">Community feed</p>
        <h1 class="resident-page-title">Welcome back, {{ $user->name }}</h1>
        <p class="resident-page-subtitle">See the latest updates from your community.</p>
      </div>
      <div class="resident-home-search flex w-full items-center gap-2 lg:w-auto">
        <label for="searchInput" class="sr-only">Search community reports</label>
        <input type="text" id="searchInput" class="resident-search min-w-0 flex-1 lg:w-80" placeholder="Search community reports...">
        <button type="button" onclick="searchReports()" class="resident-btn-primary resident-home-search-button" aria-label="Search community reports">
          <i class="fas fa-search" aria-hidden="true"></i>
        </button>
      </div>
    </header>

    <div class="resident-home-section-heading">
      <div>
        <h2 class="resident-section-title">Community updates</h2>
        <p class="resident-page-subtitle">Recent reports and conversations from Cleanify residents.</p>
      </div>
    </div>

    <!-- Feeds -->
    <div class="resident-home-feed grid items-start gap-4 xl:grid-cols-2">
    @forelse($recentReports as $report)
      @php
        $reportUser = $report->user;
        $avatarClasses = $reportUser?->getAvatarBgClasses() ?? 'bg-gray-400';
        $avatarInitial = $reportUser?->getAvatarInitial() ?? '?';
        $profilePhotoUrl = $reportUser?->profile_photo_url;
        $authorName = $reportUser->name ?? 'Cleanify User';
        $userLiked = $report->likes->contains('user_id', $user->id);
      @endphp
      <article class="dashboard-card resident-card resident-home-report-card">
        <div class="resident-home-report-header flex items-center">
          @if($profilePhotoUrl)
            <img src="{{ $profilePhotoUrl }}" alt="{{ $authorName }}'s profile photo" class="resident-home-report-avatar object-cover">
          @else
            <div class="resident-home-report-avatar {{ $avatarClasses }} flex items-center justify-center text-white font-bold">
              {{ $avatarInitial }}
            </div>
          @endif
          <div class="resident-home-report-author-wrap">
            <h3 class="resident-home-report-author">{{ $authorName }}</h3>
            <p class="resident-home-report-time">{{ $report->created_at?->diffForHumans() }}</p>
          </div>
          <div class="ml-auto">
            <span class="resident-home-status {{ $report->getStatusBadgeBgClass() }}" data-status="{{ $report->status }}">
              {{ $report->status_label }}
            </span>
          </div>
        </div>
        <p class="resident-home-report-description">{{ \Illuminate\Support\Str::limit($report->description, 240) }}</p>
        @if($report->image_path)
          <img src="{{ asset('storage/' . $report->image_path) }}" alt="Report Image" class="resident-report-media resident-home-report-image" onerror="this.style.display='none'">
        @endif
        <div class="resident-home-report-actions flex items-center gap-2">
          <button type="button" class="resident-home-action like-button {{ $userLiked ? 'text-green-600' : 'hover:text-green-600' }}" data-report-id="{{ $report->id }}">
            <i class="fas fa-heart" aria-hidden="true"></i>
            <span class="like-label">{{ $userLiked ? 'Liked' : 'Like' }}</span>
            <span class="like-count">({{ $report->likes_count }})</span>
          </button>
          <button type="button" class="resident-home-action" onclick="window.location.href='{{ route('community-reports') }}?highlight={{ $report->id }}';">
            <i class="fas fa-eye" aria-hidden="true"></i>
            <span>View details</span>
          </button>
        </div>

        <div class="resident-home-comments" id="comments-list-{{ $report->id }}">
          @foreach($report->comments as $comment)
            @php
              $commentUser = $comment->user;
              $commentClasses = $commentUser?->getAvatarBgClasses() ?? 'bg-gray-400';
              $commentInitial = $commentUser?->getAvatarInitial() ?? '?';
            @endphp
            <div class="resident-home-comment flex items-start gap-3">
              <div class="resident-home-comment-avatar {{ $commentClasses }} flex items-center justify-center text-white font-semibold text-sm">
                {{ $commentInitial }}
              </div>
              <div class="resident-home-comment-bubble flex-1">
                <div class="flex items-start justify-between gap-3">
                  <p class="resident-home-comment-author">{{ $commentUser->name ?? 'Cleanify User' }}</p>
                  <span class="resident-home-comment-time">{{ $comment->created_at?->diffForHumans() }}</span>
                </div>
                <p class="resident-home-comment-copy">{{ $comment->comment }}</p>
              </div>
            </div>
          @endforeach
        </div>

        <form class="comment-form resident-home-comment-form" data-report-id="{{ $report->id }}">
          <div class="flex items-center gap-2">
            <div class="resident-home-comment-avatar resident-home-comment-avatar--current {{ $user->getAvatarBgClasses() }} flex items-center justify-center text-white font-bold text-sm">
              {{ $user->getAvatarInitial() }}
            </div>
            <div class="resident-home-comment-composer flex min-w-0 flex-1 gap-2">
              <input type="text" name="comment" class="resident-home-comment-input min-w-0 flex-1" placeholder="Write a comment..." required>
              <button type="submit" class="resident-home-comment-submit" aria-label="Post comment">
                <i class="fas fa-paper-plane" aria-hidden="true"></i>
              </button>
            </div>
          </div>
        </form>
      </article>
    @empty
      <div class="resident-empty-state resident-home-empty xl:col-span-2">
        <span class="resident-home-empty-icon"><i class="fas fa-inbox" aria-hidden="true"></i></span>
        <h3>No community updates yet</h3>
        <p>Be the first to share a report with your community.</p>
      </div>
    @endforelse
    </div>
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
                  wrapper.className = 'resident-home-comment flex items-start gap-3';
                  wrapper.innerHTML = `
                    <div class="resident-home-comment-avatar ${data.comment.avatar ?? 'bg-gray-400'} flex items-center justify-center text-white font-semibold text-sm">
                      ${data.comment.initial ?? '?'}
                    </div>
                    <div class="resident-home-comment-bubble flex-1">
                      <div class="flex items-start justify-between gap-3">
                        <p class="resident-home-comment-author">${data.comment.author ?? 'You'}</p>
                        <span class="resident-home-comment-time">${data.comment.timestamp ?? 'Just now'}</span>
                      </div>
                      <p class="resident-home-comment-copy">${data.comment.comment}</p>
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
