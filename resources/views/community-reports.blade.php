@extends('layouts.app')

@php use Illuminate\Support\Str; @endphp

@section('title', 'Community Reports')

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@section('content')
  @if(session('success'))
    <x-alert type="success" dismissible class="mb-4">{{ session('success') }}</x-alert>
  @endif
  @if($errors->any())
    <x-alert type="error" dismissible class="mb-4">Please fix the errors below.</x-alert>
  @endif

  <div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h4 class="text-2xl font-semibold text-gray-800 flex items-center gap-2">
          <i class="fas fa-comment-dots text-green-600"></i>Community Reports
        </h4>
        <p class="text-sm text-gray-500">Share what’s happening in your area and stay updated.</p>
      </div>
      <div class="flex gap-3">
        @php
          $total = \App\Models\Report::count();
          $pending = \App\Models\Report::where('status', 'pending')->count();
          $resolved = \App\Models\Report::where('status', 'resolved')->count();
        @endphp
        <div class="bg-white rounded-xl shadow-sm p-3 text-center border border-gray-100">
          <p class="text-xs uppercase text-gray-500">Total</p>
          <p class="text-xl font-semibold text-gray-800">{{ $total }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-3 text-center border border-gray-100">
          <p class="text-xs uppercase text-gray-500">Pending</p>
          <p class="text-xl font-semibold text-yellow-600">{{ $pending }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-3 text-center border border-gray-100">
          <p class="text-xs uppercase text-gray-500">Resolved</p>
          <p class="text-xl font-semibold text-green-600">{{ $resolved }}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
      <form method="POST" action="{{ route('reports.store') }}" enctype="multipart/form-data" class="space-y-4" id="reportCreateForm">
        @csrf
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-full bg-green-100 text-green-700 flex items-center justify-center font-semibold">
            {{ strtoupper(substr(auth()->user()->name ?? 'C', 0, 1)) }}
          </div>
          <div class="flex-1 space-y-3">
            <div>
              <p class="text-sm font-medium text-gray-700 mb-2">Report location</p>
              <div id="reportLocationMap" class="h-56 w-full rounded-lg border border-gray-200"></div>
              <p class="text-xs text-gray-500 mt-2">Click the map to select where the issue is located. You can drag the marker to refine it.</p>
              <input type="hidden" name="latitude" id="reportLatitude" value="{{ old('latitude') }}">
              <input type="hidden" name="longitude" id="reportLongitude" value="{{ old('longitude') }}">
              @error('latitude') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
              @error('longitude') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2" for="reportLocationText">Location / Barangay <span class="text-gray-400 font-normal">(optional)</span></label>
              <input id="reportLocationText" type="text" name="location" value="{{ old('location') }}" class="w-full border border-gray-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 @error('location') border-red-500 @enderror" placeholder="e.g. Barangay Luna">
              @error('location') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <textarea name="description" rows="3" class="w-full border border-gray-200 rounded-2xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 resize-none @error('description') border-red-500 @enderror" placeholder="Describe the issue..." required>{{ old('description') }}</textarea>
            @error('description') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <label class="inline-flex items-center px-4 py-2 border border-green-600 text-green-600 rounded-lg hover:bg-green-600 hover:text-white transition-colors duration-300 cursor-pointer text-sm">
              <i class="fas fa-image mr-2"></i>
              <span id="reportImageLabel">Add Photo</span>
              <input type="file" name="image" accept="image/*" class="hidden" id="reportImageInput">
            </label>
            @error('image') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
          </div>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">Submit Report</button>
        </div>
      </form>
    </div>

    <div class="space-y-4" id="reportsFeed">
      @forelse($reports as $report)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 report-card" data-report-id="{{ $report->id }}">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 rounded-full {{ $report->user?->getAvatarBgClasses() ?? 'bg-green-100' }} text-white flex items-center justify-center font-semibold">
              {{ $report->user?->getAvatarInitial() ?? 'C' }}
            </div>
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <p class="font-semibold text-gray-800">{{ $report->user->name ?? 'Cleanify User' }}</p>
                @if($report->user && $report->user->id !== auth()->id())
                  <button onclick="openReportUserModal({{ $report->user->id }}, '{{ $report->user->name }}')" class="text-xs text-red-600 hover:text-red-700 hover:underline" title="Report this user">
                    <i class="fas fa-flag"></i>
                  </button>
                @endif
              </div>
              <p class="text-sm text-gray-500 flex items-center gap-1"><i class="fas fa-map-marker-alt"></i>{{ $report->location }}</p>
            </div>
            <span class="text-xs font-semibold px-3 py-1 rounded-full {{ $report->getStatusBadgeBgClass() }}">{{ ucfirst($report->status) }}</span>
          </div>
          <p class="text-gray-700 mb-3">{{ Str::limit($report->description, 160) }}</p>
          @if($report->image_path)
            <img src="{{ asset('storage/' . $report->image_path) }}" class="w-full rounded-lg mb-3" alt="Report image">
          @endif
          <div class="flex items-center justify-between text-sm text-gray-500">
            <div class="flex items-center gap-4">
              <span><i class="fas fa-heart text-red-500 mr-1"></i>{{ $report->likes_count }}</span>
              <span><i class="fas fa-comment text-blue-500 mr-1"></i>{{ $report->comments_count }}</span>
              <span><i class="fas fa-eye text-green-500 mr-1"></i>{{ $report->followers_count }}</span>
            </div>
            <button class="text-green-600 hover:underline view-report-btn" data-report-id="{{ $report->id }}">View details →</button>
          </div>
        </div>
      @empty
        <div class="bg-white rounded-xl shadow-sm p-12 text-center text-gray-600">
          <i class="fas fa-inbox text-gray-300 text-5xl mb-3"></i>
          <p>No reports yet. Be the first to share!</p>
        </div>
      @endforelse
    </div>

    <div>
      {{ $reports->links() }}
    </div>
  </div>
@endsection

@push('modals')
  <x-modal id="reportUserModal" title="Report User" icon="fas fa-flag" color="red">
    <form id="reportUserForm" class="space-y-4">
      @csrf
      <input type="hidden" id="reportedUserId" name="reported_user_id">
      <div>
        <p class="text-sm text-gray-600 mb-2">You are reporting: <strong id="reportedUserName"></strong></p>
        <p class="text-xs text-gray-500">Please provide details about why you're reporting this user. False reports may result in action against your account.</p>
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-exclamation-triangle mr-2 text-red-600"></i>Reason</label>
        <select name="reason" id="reportReason" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" required>
          <option value="">Select a reason</option>
          <option value="spam">Spam</option>
          <option value="harassment">Harassment</option>
          <option value="inappropriate_content">Inappropriate Content</option>
          <option value="fake_account">Fake Account</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-comment mr-2 text-red-600"></i>Additional Details (Optional)</label>
        <textarea name="description" id="reportDescription" rows="4" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-red-500 resize-none" placeholder="Provide any additional information that might help us review this report..."></textarea>
      </div>
    </form>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('reportUserModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Cancel
        </button>
        <button type="submit" form="reportUserForm" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300">
          <i class="fas fa-flag mr-2"></i>Submit Report
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="reportDetailModal" title="Report Details" icon="fas fa-clipboard-list" color="green" size="xl">
    <div class="space-y-4 text-gray-700">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center font-bold text-green-700" id="detailAuthorAvatar">C</div>
        <div>
          <p class="font-semibold text-gray-800" id="detailAuthorName">Cleanify User</p>
          <p class="text-sm text-gray-500 flex items-center gap-1">
            <i class="fas fa-map-marker-alt"></i>
            <span id="detailLocation">Location</span>
          </p>
        </div>
        <span class="ml-auto text-xs font-semibold px-3 py-1 rounded-full bg-gray-100 text-gray-700" id="detailStatusBadge">Pending</span>
      </div>

      <p id="detailDescription" class="text-gray-700"></p>
      <img id="detailImage" src="" class="w-full rounded-lg hidden" alt="Report image">

      <div id="detailAdminNotes" class="bg-green-50 border-l-4 border-green-600 px-4 py-3 rounded hidden"></div>
      <div id="detailRejection" class="bg-red-50 border-l-4 border-red-600 px-4 py-3 rounded hidden"></div>

      <div id="detailMapContainer" class="hidden">
        <p class="text-sm text-gray-500 mb-2">Approximate location</p>
        <div id="detailMap" class="w-full h-56 rounded-lg border border-gray-200"></div>
      </div>

      <div>
        <h6 class="font-semibold text-gray-800 mb-2">Status timeline</h6>
        <ol id="detailTimeline" class="space-y-2 text-sm text-gray-600"></ol>
      </div>

      <div>
        <h6 class="font-semibold text-gray-800 mb-2">Comments</h6>
        <div id="detailComments" class="space-y-2 text-sm text-gray-700"></div>
      </div>

      <div>
        <h6 class="font-semibold text-gray-800 mb-2">Related reports</h6>
        <div id="detailRelated" class="space-y-2 text-sm text-gray-600"></div>
      </div>
    </div>

    @slot('footer')
      <div class="flex flex-wrap items-center gap-3 justify-between w-full text-sm text-gray-600">
        <div>
          <span id="detailLikeCount"><i class="fas fa-heart text-red-500 mr-1"></i>0</span>
          <span id="detailCommentCount" class="ml-3"><i class="fas fa-comment text-blue-500 mr-1"></i>0</span>
          <span id="detailFollowerCount" class="ml-3"><i class="fas fa-eye text-green-500 mr-1"></i>0</span>
        </div>
        <div class="flex items-center gap-3">
          <button id="detailFollowBtn" class="px-4 py-2 border border-green-600 text-green-600 rounded-lg hover:bg-green-600 hover:text-white transition">
            Follow updates
          </button>
          <button onclick="closeModal('reportDetailModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">Close</button>
        </div>
      </div>
    @endslot
  </x-modal>
@endpush

@push('scripts')
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    const reportDetailUrlTemplate = '{{ route('reports.show', ['report' => '__ID__']) }}';
    const reportFollowUrlTemplate = '{{ route('reports.follow', ['report' => '__ID__']) }}';
    const reportUserUrlTemplate = '{{ route('users.report', ['user' => '__ID__']) }}';
    let detailMap;
    let detailMarker;
    let activeReportId = null;
    let reportLocationMap;
    let reportLocationMarker;

    function initializeReportLocationMap() {
      if (reportLocationMap || typeof L === 'undefined') return;
      const latitudeInput = document.getElementById('reportLatitude');
      const longitudeInput = document.getElementById('reportLongitude');
      reportLocationMap = L.map('reportLocationMap').setView([9.7870, 125.4928], 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
      }).addTo(reportLocationMap);
      const setLocation = (lat, lng, center = true) => {
        latitudeInput.value = Number(lat).toFixed(8);
        longitudeInput.value = Number(lng).toFixed(8);
        if (reportLocationMarker) reportLocationMarker.setLatLng([lat, lng]);
        else {
          reportLocationMarker = L.marker([lat, lng], { draggable: true }).addTo(reportLocationMap);
          reportLocationMarker.on('dragend', event => {
            const point = event.target.getLatLng();
            setLocation(point.lat, point.lng, false);
          });
        }
        if (center) reportLocationMap.setView([lat, lng], 15);
      };
      reportLocationMap.on('click', event => setLocation(event.latlng.lat, event.latlng.lng, false));
      if (latitudeInput.value && longitudeInput.value) setLocation(latitudeInput.value, longitudeInput.value);
    }

    initializeReportLocationMap();

    function openReportUserModal(userId, userName) {
      document.getElementById('reportedUserId').value = userId;
      document.getElementById('reportedUserName').textContent = userName;
      document.getElementById('reportReason').value = '';
      document.getElementById('reportDescription').value = '';
      openModal('reportUserModal');
    }

    document.getElementById('reportUserForm')?.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      const userId = formData.get('reported_user_id');
      const reason = formData.get('reason');
      
      // Validate reason is selected
      if (!reason) {
        if (typeof showToast === 'function') {
          showToast('error', 'Please select a reason for reporting this user.');
        } else {
          alert('Please select a reason for reporting this user.');
        }
        return;
      }
      
      fetch(reportUserUrlTemplate.replace('__ID__', userId), {
        method: 'POST',
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
          if (typeof showToast === 'function') {
            showToast('success', data.message || 'User reported successfully. Our team will review this report.');
          }
          closeModal('reportUserModal');
          // Reset form
          document.getElementById('reportReason').value = '';
          document.getElementById('reportDescription').value = '';
        } else if (data.error) {
          if (typeof showToast === 'function') {
            showToast('error', data.error);
          } else {
            alert(data.error);
          }
        }
      })
      .catch(error => {
        console.error('Error:', error);
        let errorMessage = 'Failed to submit report. Please try again.';
        
        if (error.error) {
          // Handle Laravel validation errors (format: { message: "...", errors: { field: ["error"] } })
          if (error.error.errors) {
            const errors = error.error.errors;
            const firstErrorKey = Object.keys(errors)[0];
            const firstError = errors[firstErrorKey];
            errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
          } 
          // Handle custom error messages
          else if (error.error.error) {
            errorMessage = error.error.error;
          } 
          // Handle message field
          else if (error.error.message && error.error.message !== 'The given data was invalid.') {
            errorMessage = error.error.message;
          }
        }
        
        if (typeof showToast === 'function') {
          showToast('error', errorMessage);
        } else {
          alert(errorMessage);
        }
      });
    });

    document.getElementById('reportImageInput')?.addEventListener('change', function () {
      const label = document.getElementById('reportImageLabel');
      label.textContent = this.files.length ? this.files[0].name : 'Attach photo (optional)';
    });

    document.querySelectorAll('.view-report-btn').forEach(button => {
      button.addEventListener('click', () => {
        const reportId = button.dataset.reportId;
        fetch(reportDetailUrlTemplate.replace('__ID__', reportId))
          .then(res => res.json())
          .then(data => {
            activeReportId = data.id;
            populateReportDetail(data);
            openModal('reportDetailModal');
          })
          .catch(() => showToast?.('error', 'Unable to load report details right now.'));
      });
    });

    document.getElementById('detailFollowBtn').addEventListener('click', () => {
      if (!activeReportId) return;
      fetch(reportFollowUrlTemplate.replace('__ID__', activeReportId), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json',
        },
      })
        .then(res => res.json())
        .then(data => {
          updateFollowButton(data.following);
          document.getElementById('detailFollowerCount').innerHTML = `<i class="fas fa-eye text-green-500 mr-1"></i>${data.followers_count}`;
        })
        .catch(() => showToast?.('error', 'Unable to update follow state.'));
    });

    function populateReportDetail(data) {
      document.getElementById('detailAuthorAvatar').textContent = data.author.initial ?? 'C';
      document.getElementById('detailAuthorAvatar').className = `w-12 h-12 rounded-full flex items-center justify-center font-bold ${data.author.avatar_bg ?? 'bg-green-100 text-green-700'}`;
      document.getElementById('detailAuthorName').textContent = data.author.name;
      document.getElementById('detailLocation').textContent = data.location;
      document.getElementById('detailDescription').textContent = data.description;
      document.getElementById('detailStatusBadge').textContent = data.status;
      document.getElementById('detailStatusBadge').className = `text-xs font-semibold px-3 py-1 rounded-full ${data.status_badge}`;
      document.getElementById('detailLikeCount').innerHTML = `<i class="fas fa-heart text-red-500 mr-1"></i>${data.likes_count}`;
      document.getElementById('detailCommentCount').innerHTML = `<i class="fas fa-comment text-blue-500 mr-1"></i>${data.comments_count}`;
      document.getElementById('detailFollowerCount').innerHTML = `<i class="fas fa-eye text-green-500 mr-1"></i>${data.followers_count}`;

      const image = document.getElementById('detailImage');
      if (data.image) {
        image.src = data.image;
        image.classList.remove('hidden');
      } else {
        image.classList.add('hidden');
      }

      const adminNotes = document.getElementById('detailAdminNotes');
      if (data.admin_notes) {
        adminNotes.innerHTML = `<p class="font-semibold text-green-800 mb-1"><i class="fas fa-clipboard-check mr-1"></i>Admin notes</p>${data.admin_notes}`;
        adminNotes.classList.remove('hidden');
      } else {
        adminNotes.classList.add('hidden');
      }

      const rejection = document.getElementById('detailRejection');
      if (data.rejection_reason) {
        rejection.innerHTML = `<p class="font-semibold text-red-800 mb-1"><i class="fas fa-times-circle mr-1"></i>Reason provided</p>${data.rejection_reason}`;
        rejection.classList.remove('hidden');
      } else {
        rejection.classList.add('hidden');
      }

      const timeline = document.getElementById('detailTimeline');
      timeline.innerHTML = data.timeline.map(item => `
        <li class="flex items-center gap-3">
          <span class="w-2 h-2 rounded-full ${item.status === 'resolved' ? 'bg-green-600' : (item.status === 'rejected' ? 'bg-red-600' : 'bg-yellow-500')}"></span>
          <div>
            <p class="font-semibold text-gray-800">${item.label}</p>
            <p class="text-xs text-gray-500">${item.meta ?? ''}</p>
          </div>
        </li>
      `).join('');

      const comments = document.getElementById('detailComments');
      if (data.comments.length) {
        comments.innerHTML = data.comments.map(comment => `
          <div class="border border-gray-100 rounded-lg px-3 py-2">
            <p class="font-semibold text-gray-800">${comment.author}</p>
            <p class="text-sm text-gray-700">${comment.comment}</p>
            <p class="text-xs text-gray-400 mt-1">${comment.timestamp}</p>
          </div>
        `).join('');
      } else {
        comments.innerHTML = '<p class="text-sm text-gray-500">No comments yet.</p>';
      }

      const related = document.getElementById('detailRelated');
      if (data.related && data.related.length) {
        related.innerHTML = data.related.map(item => `
          <div class="flex items-center justify-between border border-gray-100 rounded-lg px-3 py-2">
            <div>
              <p class="font-semibold text-gray-800">${item.location}</p>
              <p class="text-xs text-gray-500">${item.status} · ${item.timestamp}</p>
            </div>
            <button class="text-green-600 text-xs hover:underline" onclick="openReportDetail(${item.id})">Open</button>
          </div>
        `).join('');
      } else {
        related.innerHTML = '<p class="text-sm text-gray-500">No related reports.</p>';
      }

      updateFollowButton(data.is_following);

      if (data.coordinate) {
        document.getElementById('detailMapContainer').classList.remove('hidden');
        setTimeout(() => renderDetailMap(data.coordinate), 200);
      } else {
        document.getElementById('detailMapContainer').classList.add('hidden');
      }
    }

    function updateFollowButton(isFollowing) {
      const btn = document.getElementById('detailFollowBtn');
      if (isFollowing) {
        btn.textContent = 'Following';
        btn.classList.add('bg-green-600', 'text-white');
        btn.classList.remove('text-green-600', 'border-green-600');
      } else {
        btn.textContent = 'Follow updates';
        btn.classList.remove('bg-green-600', 'text-white');
        btn.classList.add('text-green-600', 'border-green-600');
      }
    }

    function renderDetailMap(coordinate) {
      if (!detailMap) {
        detailMap = L.map('detailMap');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors',
        }).addTo(detailMap);
      }
      if (detailMarker) {
        detailMap.removeLayer(detailMarker);
      }
      detailMap.setView([coordinate.lat, coordinate.lng], 15);
      detailMarker = L.marker([coordinate.lat, coordinate.lng]).addTo(detailMap);
    }
  </script>
@endpush
