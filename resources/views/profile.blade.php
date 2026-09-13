@extends('layouts.app')

@section('title', 'Profile')

@section('content')
  <!-- Profile Card -->
  <div class="bg-white rounded-xl shadow-sm p-6 mb-6 flex flex-col md:flex-row items-center md:items-start gap-6">
    @if($user->profile_photo_url)
      <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}'s profile photo" class="w-28 h-28 rounded-full object-cover">
    @else
      <div class="w-28 h-28 rounded-full {{ $user->getAvatarBgClasses() }} flex items-center justify-center text-white font-bold text-4xl">
        {{ $user->getAvatarInitial() }}
      </div>
    @endif
    <div class="flex-1 text-center md:text-left">
      <h5 class="text-green-600 font-bold text-xl mb-2">{{ $user->name }}</h5>
      <p class="text-gray-600 mb-1">
        <i class="fas fa-envelope mr-2 text-green-500"></i>{{ $user->email }}
      </p>
      @if($user->phone)
        <p class="text-gray-600 mb-1">
          <i class="fas fa-phone mr-2 text-green-500"></i>{{ $user->phone }}
        </p>
      @endif
      <p class="text-gray-600">
        <i class="fas fa-map-marker-alt mr-2 text-green-500"></i>{{ $user->address ?? $user->service_area ?? 'No address set' }}
      </p>
    </div>
    <button onclick="openModal('editProfileModal')" class="px-6 py-2 border border-green-600 text-green-600 rounded-lg hover:bg-green-600 hover:text-white transition-colors duration-300">
      <i class="fas fa-edit mr-2"></i>Edit Profile
    </button>
  </div>

  <!-- My Posts Section -->
  <h5 class="mb-4 text-green-600 font-semibold text-lg">
    <i class="fas fa-images mr-2"></i>My Posts ({{ $userReports->count() }})
  </h5>

  @forelse($userReports as $report)
    @php
      $userLiked = $report->likes->contains('user_id', $user->id);
    @endphp
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 relative" id="report-{{ $report->id }}">
      <!-- Post Options Dropdown -->
      <div class="absolute top-4 right-4">
        <button onclick="toggleDropdown('postDropdown{{ $report->id }}')" class="text-gray-500 hover:text-green-600 transition-colors duration-300">
          <i class="fas fa-ellipsis-v"></i>
        </button>
        <div id="postDropdown{{ $report->id }}" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
          <a href="#" onclick="openEditPostModal({{ $report->id }})" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <i class="fas fa-edit mr-2"></i>Edit
          </a>
          <a href="#" onclick="openDeletePostModal({{ $report->id }})" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
            <i class="fas fa-trash mr-2"></i>Delete
          </a>
        </div>
      </div>

      <!-- Post Header -->
      <div class="flex items-center mb-4">
        @if($user->profile_photo_url)
          <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}'s profile photo" class="w-12 h-12 rounded-full object-cover mr-4">
        @else
          <div class="w-12 h-12 rounded-full {{ $user->getAvatarBgClasses() }} flex items-center justify-center text-white font-bold mr-4">
            {{ $user->getAvatarInitial() }}
          </div>
        @endif
        <div class="flex-1">
          <h6 class="font-semibold text-green-600 mb-0">{{ $user->name }}</h6>
          <small class="text-gray-500">{{ $report->created_at?->diffForHumans() ?? 'Recently' }}</small>
        </div>
        <div>
          <span class="text-sm font-medium px-3 py-1 rounded-full {{ $report->getStatusBadgeBgClass() }}">
            {{ ucfirst($report->status) }}
          </span>
        </div>
      </div>

      <!-- Post Content -->
      <p class="text-gray-700 mb-4" id="report-description-{{ $report->id }}">{{ $report->description }}</p>
      <p class="text-sm text-gray-500 mb-4">
        <i class="fas fa-map-marker-alt mr-2"></i>{{ $report->location }}
      </p>
      @if($report->image_path)
        <img src="{{ asset('storage/' . $report->image_path) }}" alt="Post Image" class="w-full rounded-lg mb-4" id="report-image-{{ $report->id }}" onerror="this.style.display='none'">
      @endif

      <!-- Post Actions -->
      <div class="flex items-center mt-4">
        <button type="button" class="flex items-center text-gray-600 hover:text-red-500 transition-colors duration-300 mr-6 like-button {{ $userLiked ? 'text-red-500' : '' }}" data-report-id="{{ $report->id }}">
          <i class="fas fa-heart mr-2"></i>
          <span class="like-label">{{ $userLiked ? 'Liked' : 'Like' }}</span>
          <span class="like-count text-gray-600 ml-1">({{ $report->likes_count }})</span>
        </button>
        <button onclick="openCommentModal({{ $report->id }})" class="flex items-center text-gray-600 hover:text-blue-500 transition-colors duration-300">
          <i class="fas fa-comment mr-2"></i>
          <span class="text-sm">{{ $report->comments_count }} comments</span>
        </button>
      </div>
    </div>
  @empty
    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
      <i class="fas fa-images text-gray-300 text-6xl mb-4"></i>
      <p class="text-gray-500 text-lg mb-2">No posts yet</p>
      <p class="text-gray-400 text-sm">Start sharing updates from your community!</p>
      <a href="{{ route('dashboard') }}" class="inline-block mt-4 px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-plus mr-2"></i>Create Your First Post
      </a>
    </div>
  @endforelse
@endsection

@push('modals')
  <!-- Edit Profile Modal -->
  <x-modal id="editProfileModal" title="Edit Profile" icon="fas fa-edit" color="green">
    <form id="editProfileForm" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
      @csrf
      @method('PATCH')
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Full Name</label>
        <input type="text" name="name" id="editProfileName" value="{{ $user->name }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
        <div id="error-name" class="text-red-500 text-sm mt-1 hidden"></div>
      </div>
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Email</label>
        <input type="email" name="email" id="editProfileEmail" value="{{ $user->email }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
        <div id="error-email" class="text-red-500 text-sm mt-1 hidden"></div>
      </div>
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Phone</label>
        <input type="text" name="phone" id="editProfilePhone" value="{{ $user->phone ?? '' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="+63 912 345 6789">
        <div id="error-phone" class="text-red-500 text-sm mt-1 hidden"></div>
      </div>
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Address</label>
        <input type="text" name="address" id="editProfileAddress" value="{{ $user->address ?? '' }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Barangay, City">
        <div id="error-address" class="text-red-500 text-sm mt-1 hidden"></div>
      </div>
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Profile Photo</label>
        <input type="file" name="profile_photo" id="editProfilePhoto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
        <p class="mt-1 text-sm text-gray-500">JPG, PNG, or WebP up to 4 MB.</p>
        <div id="error-profile_photo" class="text-red-500 text-sm mt-1 hidden"></div>
      </div>
      @if($user->profile_photo_url)
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input type="checkbox" name="remove_profile_photo" value="1" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
          Remove current profile photo
        </label>
      @endif
    </form>
    
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('editProfileModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">Cancel</button>
        <button type="button" onclick="submitProfileForm()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">Save Changes</button>
      </div>
    @endslot
  </x-modal>

  <!-- Edit Post Modal -->
  <x-modal id="editPostModal" title="Edit Post" icon="fas fa-edit" color="green">
    <form id="editPostForm" method="POST" enctype="multipart/form-data">
      @csrf
      @method('PATCH')
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Location</label>
        <input type="text" name="location" id="editPostLocation" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
      </div>
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Description</label>
        <textarea name="description" id="editPostDescription" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none" required></textarea>
      </div>
      <div class="mb-4">
        <label class="block text-gray-700 mb-2">Image (optional - leave empty to keep current)</label>
        <input type="file" name="image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
        <p class="text-sm text-gray-500 mt-1">Current image will be replaced if a new one is uploaded.</p>
      </div>
    </form>
    
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('editPostModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">Cancel</button>
        <button onclick="submitEditPost()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">Save Changes</button>
      </div>
    @endslot
  </x-modal>

  <!-- Delete Confirmation Modal -->
  <x-modal id="deletePostModal" title="Delete Post" icon="fas fa-trash" color="red">
    <p class="text-gray-700 text-center">Are you sure you want to delete this post? This action cannot be undone.</p>
    
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('deletePostModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">Cancel</button>
        <button onclick="confirmDeletePost()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300">Delete</button>
      </div>
    @endslot
  </x-modal>

  <!-- Comment Modal -->
  <x-modal id="commentModal" title="Comments" icon="fas fa-comment-dots" color="green">
    <div id="commentsContainer" class="max-h-96 overflow-y-auto space-y-4 mb-4">
      <!-- Comments will be loaded here -->
    </div>
    
    @slot('footer')
      <div class="flex">
        <textarea id="commentInput" class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none" placeholder="Add a comment..." rows="2"></textarea>
        <button onclick="submitComment()" class="ml-3 px-4 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300 self-end">
          <i class="fas fa-paper-plane"></i>
        </button>
      </div>
    @endslot
  </x-modal>
@endpush

@push('scripts')
<script>
  let currentReportId = null;
  let currentDeleteReportId = null;

  // Edit Post Modal
  function openEditPostModal(reportId) {
    currentReportId = reportId;
    const report = @json($userReports->keyBy('id'));
    
    // Close dropdown
    document.getElementById(`postDropdown${reportId}`)?.classList.add('hidden');
    
    if (report[reportId]) {
      document.getElementById('editPostLocation').value = report[reportId].location;
      document.getElementById('editPostDescription').value = report[reportId].description;
      document.getElementById('editPostForm').action = `/profile/reports/${reportId}`;
      openModal('editPostModal');
    }
  }

  function submitEditPost() {
    const form = document.getElementById('editPostForm');
    const formData = new FormData(form);

    fetch(form.action, {
      method: 'PATCH',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json',
      },
      body: formData,
    })
    .then(async response => {
      const data = await response.json();
      if (!response.ok) {
        throw { response, error: data };
      }
      return data;
    })
    .then(data => {
      if (data.success) {
        showToast('success', 'Post updated successfully');
        closeModal('editPostModal');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast('error', data.message || 'Failed to update post');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      let errorMessage = 'Failed to update post. Please try again.';
      
      if (error.error) {
        if (error.error.message) {
          errorMessage = error.error.message;
        } else if (error.error.errors) {
          const firstError = Object.values(error.error.errors)[0];
          errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
        }
      }
      
      showToast('error', errorMessage);
    });
  }

  // Delete Post Modal
  function openDeletePostModal(reportId) {
    currentDeleteReportId = reportId;
    // Close dropdown
    document.getElementById(`postDropdown${reportId}`)?.classList.add('hidden');
    openModal('deletePostModal');
  }

  function confirmDeletePost() {
    if (!currentDeleteReportId) return;

    fetch(`/profile/reports/${currentDeleteReportId}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json',
      },
    })
    .then(async response => {
      const data = await response.json();
      if (!response.ok) {
        throw { response, error: data };
      }
      return data;
    })
    .then(data => {
      if (data.success) {
        showToast('success', 'Post deleted successfully');
        const reportElement = document.getElementById(`report-${currentDeleteReportId}`);
        if (reportElement) {
          reportElement.style.transition = 'opacity 0.3s, transform 0.3s';
          reportElement.style.opacity = '0';
          reportElement.style.transform = 'translateX(-20px)';
          setTimeout(() => {
            reportElement.remove();
            // Reload if no posts left
            if (document.querySelectorAll('[id^="report-"]').length === 0) {
              setTimeout(() => location.reload(), 500);
            }
          }, 300);
        }
        closeModal('deletePostModal');
        currentDeleteReportId = null;
      } else {
        showToast('error', data.message || 'Failed to delete post');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      let errorMessage = 'Failed to delete post. Please try again.';
      
      if (error.error) {
        if (error.error.message) {
          errorMessage = error.error.message;
        }
      }
      
      showToast('error', errorMessage);
    });
  }

  // Comment Modal
  function openCommentModal(reportId) {
    currentReportId = reportId;
    const commentsContainer = document.getElementById('commentsContainer');
    const commentInput = document.getElementById('commentInput');
    commentsContainer.innerHTML = '<p class="text-center text-gray-500">Loading comments...</p>';
    if (commentInput) {
      commentInput.value = '';
    }

    fetch(`/reports/${reportId}/comments`, {
      headers: {
        'Accept': 'application/json',
      },
    })
      .then(async response => {
        if (!response.ok) {
          throw new Error('Failed to load comments');
        }
        return response.json();
      })
      .then(data => {
        commentsContainer.innerHTML = '';
        if (data.comments && data.comments.length > 0) {
          data.comments.forEach(comment => {
            const commentDiv = document.createElement('div');
            commentDiv.className = 'flex mb-4';
            commentDiv.innerHTML = `
              <div class="w-10 h-10 rounded-full ${comment.avatar || 'bg-gray-400'} flex items-center justify-center text-white font-bold mr-3">
                ${comment.initial || '?'}
              </div>
              <div class="flex-1">
                <div class="flex items-center mb-1">
                  <strong class="text-gray-800">${comment.author || 'User'}</strong>
                  <small class="text-gray-500 ml-2">• ${comment.timestamp || 'Just now'}</small>
                </div>
                <p class="text-gray-700">${comment.comment}</p>
              </div>
            `;
            commentsContainer.appendChild(commentDiv);
          });
        } else {
          commentsContainer.innerHTML = '<p class="text-center text-gray-500">No comments yet. Be the first to comment!</p>';
        }
      })
      .catch(error => {
        console.error('Error loading comments:', error);
        commentsContainer.innerHTML = '<p class="text-center text-red-500">Failed to load comments. Please try again.</p>';
      });

    openModal('commentModal');
    
    // Focus comment input
    if (commentInput) {
      setTimeout(() => commentInput.focus(), 100);
    }
  }
  
  // Handle Enter key in comment input
  document.addEventListener('DOMContentLoaded', function() {
    const commentInput = document.getElementById('commentInput');
    if (commentInput) {
      commentInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          submitComment();
        }
      });
    }
  });

  function submitComment() {
    if (!currentReportId) return;

    const commentInput = document.getElementById('commentInput');
    const comment = commentInput.value.trim();

    if (!comment) {
      showToast('error', 'Please enter a comment');
      return;
    }

    fetch(`/reports/${currentReportId}/comment`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json',
      },
      body: JSON.stringify({ comment }),
    })
    .then(async response => {
      const data = await response.json();
      if (!response.ok) {
        throw { response, error: data };
      }
      return data;
    })
    .then(data => {
      if (data.comment) {
        commentInput.value = '';
        const commentsContainer = document.getElementById('commentsContainer');
        if (commentsContainer.innerHTML.includes('No comments')) {
          commentsContainer.innerHTML = '';
        }
        const commentDiv = document.createElement('div');
        commentDiv.className = 'flex mb-4';
        commentDiv.innerHTML = `
          <div class="w-10 h-10 rounded-full ${data.comment.avatar || 'bg-gray-400'} flex items-center justify-center text-white font-bold mr-3">
            ${data.comment.initial || '?'}
          </div>
          <div class="flex-1">
            <div class="flex items-center mb-1">
              <strong class="text-gray-800">${data.comment.author || 'You'}</strong>
              <small class="text-gray-500 ml-2">• ${data.comment.timestamp || 'Just now'}</small>
            </div>
            <p class="text-gray-700">${data.comment.comment}</p>
          </div>
        `;
        commentsContainer.appendChild(commentDiv);
        showToast('success', 'Comment added');
        
        // Update comment count in the post
        const commentButton = document.querySelector(`[onclick="openCommentModal(${currentReportId})"]`);
        if (commentButton) {
          const countSpan = commentButton.querySelector('span');
          if (countSpan && data.comments_count !== undefined) {
            countSpan.textContent = `${data.comments_count} comments`;
          }
        }
      } else {
        showToast('error', 'Failed to add comment');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      let errorMessage = 'Failed to add comment. Please try again.';
      
      if (error.error) {
        if (error.error.message) {
          errorMessage = error.error.message;
        } else if (error.error.errors) {
          const firstError = Object.values(error.error.errors)[0];
          errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
        }
      }
      
      showToast('error', errorMessage);
    });
  }

  // Like functionality
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.like-button').forEach(button => {
      button.addEventListener('click', function() {
        const reportId = this.getAttribute('data-report-id');
        const likeLabel = this.querySelector('.like-label');
        const likeCount = this.querySelector('.like-count');

        fetch(`/reports/${reportId}/like`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
        })
        .then(async response => {
          const data = await response.json();
          if (!response.ok) {
            throw { response, error: data };
          }
          return data;
        })
        .then(data => {
          if (likeLabel) {
            likeLabel.textContent = data.liked ? 'Liked' : 'Like';
          }
          if (likeCount) {
            likeCount.textContent = `(${data.likes_count})`;
          }
          this.classList.toggle('text-red-500', data.liked);
          this.classList.toggle('text-gray-600', !data.liked);
        })
        .catch(error => {
          console.error('Error:', error);
          showToast('error', 'Failed to like post. Please try again.');
        });
      });
    });
  });

  // Handle profile form submission - make it globally accessible
  window.submitProfileForm = function() {
    const form = document.getElementById('editProfileForm');
    if (!form) {
      showToast('error', 'Form not found');
      return;
    }
    
    // Get input values explicitly to ensure they're captured
    const nameInput = document.getElementById('editProfileName');
    const emailInput = document.getElementById('editProfileEmail');
    const phoneInput = document.getElementById('editProfilePhone');
    const addressInput = document.getElementById('editProfileAddress');
    
    // Validate required fields
    if (!nameInput || !nameInput.value || nameInput.value.trim() === '') {
      showToast('error', 'Name field is required');
      nameInput?.focus();
      return;
    }
    
    if (!emailInput || !emailInput.value || emailInput.value.trim() === '') {
      showToast('error', 'Email field is required');
      emailInput?.focus();
      return;
    }
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                     form.querySelector('input[name="_token"]')?.value;
    
    const formData = new FormData(form);
    formData.set('name', nameInput.value.trim());
    formData.set('email', emailInput.value.trim());
    formData.set('phone', phoneInput && phoneInput.value ? phoneInput.value.trim() : '');
    formData.set('address', addressInput && addressInput.value ? addressInput.value.trim() : '');
    
    // Debug: Log form data (remove in production)
    console.log('Submitting profile form');

    fetch(form.action, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken || '',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: formData,
      redirect: 'manual', // Prevent automatic redirect following
    })
    .then(async response => {
      // Handle redirects - should not happen with AJAX
      if (response.type === 'opaqueredirect' || response.status === 0) {
        throw new Error('Unexpected redirect detected. Please check your request headers.');
      }
      
      // Check if response is JSON
      const contentType = response.headers.get('content-type') || '';
      if (!contentType.includes('application/json')) {
        const text = await response.text();
        console.error('Non-JSON response:', text);
        throw new Error('Server returned non-JSON response. Please try again.');
      }
      
      const data = await response.json();
      if (!response.ok) {
        // Log the full error for debugging
        console.error('Server error response:', {
          status: response.status,
          statusText: response.statusText,
          data: data,
          errors: data.errors,
        });
        throw { response, error: data };
      }
      return data;
    })
    .then(data => {
      if (data.success) {
        // Clear any error messages
        document.querySelectorAll('[id^="error-"]').forEach(el => {
          el.classList.add('hidden');
          el.textContent = '';
        });
        
        // Remove error styling from inputs
        document.querySelectorAll('#editProfileForm input').forEach(input => {
          input.classList.remove('border-red-500');
          input.classList.add('border-gray-300');
        });
        
        showToast('success', 'Profile updated successfully');
        closeModal('editProfileModal');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast('error', data.message || 'Failed to update profile');
      }
    })
    .catch(error => {
      console.error('Full error object:', error);
      console.error('Error.error:', error.error);
      console.error('Error.error.errors:', error.error?.errors);
      
      // Clear previous field errors
      document.querySelectorAll('[id^="error-"]').forEach(el => {
        el.classList.add('hidden');
        el.textContent = '';
      });
      
      // Remove error styling from inputs
      document.querySelectorAll('#editProfileForm input').forEach(input => {
        input.classList.remove('border-red-500');
        input.classList.add('border-gray-300');
      });
      
      let errorMessage = 'Failed to update profile. Please try again.';
      let hasFieldErrors = false;
      
      // Handle Laravel validation errors
      if (error.error) {
        // Check for validation errors object
        if (error.error.errors) {
          const errors = error.error.errors;
          console.log('Processing validation errors:', errors);
          
          // Display field-specific errors
          Object.keys(errors).forEach(field => {
            const errorElement = document.getElementById(`error-${field}`);
            // Handle field name mapping (e.g., 'name' -> 'editProfileName')
            let inputElement = null;
            if (field === 'name') {
              inputElement = document.getElementById('editProfileName');
            } else if (field === 'email') {
              inputElement = document.getElementById('editProfileEmail');
            } else if (field === 'phone') {
              inputElement = document.getElementById('editProfilePhone');
            } else if (field === 'address') {
              inputElement = document.getElementById('editProfileAddress');
            }
            
            if (errorElement && errors[field]) {
              const errorText = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
              errorElement.textContent = errorText;
              errorElement.classList.remove('hidden');
              hasFieldErrors = true;
              console.log(`Displaying error for ${field}:`, errorText);
            }
            
            if (inputElement) {
              inputElement.classList.remove('border-gray-300');
              inputElement.classList.add('border-red-500');
            }
          });
          
          // Get first error for toast
          const firstErrorKey = Object.keys(errors)[0];
          const firstError = errors[firstErrorKey];
          errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
        } 
        // Check for message field
        else if (error.error.message && error.error.message !== 'The given data was invalid.') {
          errorMessage = error.error.message;
        }
      } 
      // Handle generic error messages
      else if (error.message) {
        errorMessage = error.message;
      }
      
      // Show toast with error message
      showToast('error', errorMessage);
    });
  };

  document.getElementById('editProfileForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    if (typeof window.submitProfileForm === 'function') {
      window.submitProfileForm();
    } else {
      console.error('submitProfileForm function not found');
      showToast('error', 'Form submission error. Please refresh the page.');
    }
  });

  // Clear errors when user starts typing
  ['name', 'email', 'phone', 'address'].forEach(field => {
    const input = document.getElementById(`editProfile${field.charAt(0).toUpperCase() + field.slice(1)}`);
    const errorElement = document.getElementById(`error-${field}`);
    
    if (input && errorElement) {
      input.addEventListener('input', function() {
        errorElement.classList.add('hidden');
        errorElement.textContent = '';
        input.classList.remove('border-red-500');
        input.classList.add('border-gray-300');
      });
    }
  });
</script>
@endpush
