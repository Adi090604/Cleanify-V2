@extends('layouts.app')

@section('title', 'Garbage Schedule')

@php use Illuminate\Support\Str; @endphp

@section('content')
  <div class="space-y-6">
    <!-- Personalized schedule + Upcoming timeline -->
    <div class="grid items-start gap-6 lg:grid-cols-2">
      <div class="bg-white rounded-2xl shadow p-6 border border-green-100">
        <div class="flex flex-col items-start gap-4 sm:flex-row sm:justify-between">
          <div>
            <p class="text-sm uppercase tracking-wide text-gray-500 mb-1">Your zone this week</p>
            <h3 class="text-2xl font-semibold text-gray-800">
              {{ $userArea ?? 'Select your area' }}
            </h3>
            @if($nextPickup)
              <p class="text-gray-600 mt-2 flex items-center gap-2">
                <i class="fas fa-calendar-day text-green-600"></i>
                {{ $nextPickup['date_display'] }} · {{ $nextPickup['time_display'] }}
              </p>
              <p class="text-gray-600 mt-1 flex items-center gap-2">
                <i class="fas fa-truck text-green-600"></i>
                Truck {{ $nextPickup['truck'] }} · {{ $nextPickup['waste_type'] }}
              </p>
            @else
              <p class="text-gray-600 mt-2">Choose your area to see the next pickup reminder.</p>
            @endif
          </div>
          <div class="w-full sm:w-40">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Service area</label>
            <select id="serviceAreaSelect" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500">
              <option value="">Select area</option>
              @foreach($availableAreas as $area)
                <option value="{{ $area }}" {{ $userArea === $area ? 'selected' : '' }}>{{ $area }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="mt-4 flex items-start gap-3 rounded-lg border px-3 py-2.5 text-sm {{ $scheduleRemindersEnabled ? 'border-green-100 bg-green-50 text-gray-700' : 'border-gray-200 bg-gray-50 text-gray-600' }}">
          <i class="fas fa-bell mt-0.5 {{ $scheduleRemindersEnabled ? 'text-green-600' : 'text-gray-400' }}" aria-hidden="true"></i>
          <div>
            @if($scheduleRemindersEnabled && $emailNotificationsEnabled)
              <p>We'll remind you by email 1 day before your scheduled collection.</p>
              <a href="{{ route('settings') }}" class="mt-1 inline-block text-xs font-medium text-green-700 hover:text-green-800 hover:underline">Manage reminder options in Settings</a>
            @elseif(!$scheduleRemindersEnabled)
              <p>Schedule reminders are currently turned off.</p>
              <a href="{{ route('settings') }}" class="mt-1 inline-block text-xs font-medium text-green-700 hover:text-green-800 hover:underline">Manage reminder options in Settings</a>
            @else
              <p>Schedule reminders are enabled. <a href="{{ route('settings') }}" class="font-medium text-green-700 hover:text-green-800 hover:underline">Manage your reminder options in Settings.</a></p>
            @endif
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow p-6 border border-green-100">
        <div class="flex items-center justify-between mb-4">
          <div>
            <p class="text-sm uppercase tracking-wide text-gray-500">Upcoming pickups</p>
            <h4 class="text-xl font-semibold text-gray-800">Next 5 collections</h4>
          </div>
        </div>
        <div class="space-y-4 max-h-60 overflow-y-auto pr-1">
          @forelse($upcomingPickups as $pickup)
            @php
              $pickupZoneColor = $zoneColors[$pickup['area']] ?? ['bg' => '#10B981', 'text' => '#FFFFFF', 'border' => '#059669'];
            @endphp
            <div class="flex items-center gap-4 border-l-4 rounded pl-3 py-2" style="border-left-color: {{ $pickupZoneColor['border'] }};">
              <div class="text-center w-16">
                <p class="text-lg font-bold" style="color: {{ $pickupZoneColor['bg'] }};">{{ $pickup['date_display'] }}</p>
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ $pickup['day_label'] }}</p>
              </div>
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <div class="w-3 h-3 rounded-full" style="background-color: {{ $pickupZoneColor['bg'] }};"></div>
                  <p class="text-sm font-semibold text-gray-800">{{ $pickup['area'] }}</p>
                </div>
                <p class="text-sm text-gray-500">{{ $pickup['time_range'] }} · Truck {{ $pickup['truck'] }}</p>
              </div>
              <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $pickup['badge'] }}">{{ $pickup['waste_type'] }}</span>
            </div>
          @empty
            <p class="text-gray-600">No upcoming pickups scheduled.</p>
          @endforelse
        </div>
      </div>
    </div>

    <!-- Schedule filters -->
    <div class="sticky top-3 z-10 flex flex-col gap-4 rounded-xl border border-green-100 bg-white p-4 shadow-sm sm:flex-row sm:items-center">
      <div class="flex w-full items-center gap-2 sm:w-auto">
        <label for="areaFilter" class="whitespace-nowrap text-sm font-medium text-gray-600">Filter by zone</label>
        <select id="areaFilter" class="min-w-0 flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-green-500 sm:flex-none">
          <option value="all">All zones</option>
          @foreach($availableAreas as $area)
            <option value="{{ $area }}">{{ $area }}</option>
          @endforeach
        </select>
      </div>
      <div class="flex w-full min-w-0 flex-1 items-center gap-2">
        <i class="fas fa-search text-gray-400"></i>
        <input type="text" id="scheduleSearch" class="min-w-0 flex-1 border-0 text-sm focus:ring-0" placeholder="Search zone, truck, or day...">
      </div>
    </div>

    <!-- Main content grid -->
    <div class="grid lg:grid-cols-3 gap-6 items-start">
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-xl p-6 border border-green-100">
        <div class="flex justify-between items-center mb-6">
          <button id="prevMonth" class="w-10 h-10 border border-green-600 text-green-600 rounded-full hover:bg-green-600 hover:text-white transition-colors duration-300 flex items-center justify-center">
            <i class="fas fa-chevron-left"></i>
          </button>
          <h3 id="monthYear" class="text-2xl font-bold text-green-600"></h3>
          <button id="nextMonth" class="w-10 h-10 border border-green-600 text-green-600 rounded-full hover:bg-green-600 hover:text-white transition-colors duration-300 flex items-center justify-center">
            <i class="fas fa-chevron-right"></i>
          </button>
        </div>
        <div class="grid grid-cols-7 gap-3" id="calendar"></div>
        <p class="text-xs text-gray-500 mt-4">
          <i class="fas fa-info-circle mr-1"></i>Tip: Each zone has a unique color. Hover/tap a date to see all pickups scheduled for that day.
        </p>
      </div>

      <div class="space-y-6">
        <div class="bg-white rounded-2xl shadow p-6 border border-green-100">
          <h5 class="text-green-700 font-semibold text-lg mb-4 flex items-center gap-2">
            <i class="fas fa-map-marker-alt"></i>Zone schedule directory
          </h5>
          @if($schedules->count())
            <div id="scheduleList" class="divide-y divide-gray-100 max-h-[480px] overflow-y-auto pr-1">
              @foreach($schedules as $schedule)
                @php
                  $zoneColor = $zoneColors[$schedule->area] ?? ['bg' => '#10B981', 'text' => '#FFFFFF', 'border' => '#059669'];
                @endphp
                <div class="py-4 schedule-item border-l-4" style="border-left-color: {{ $zoneColor['border'] }};" data-area="{{ Str::lower($schedule->area) }}" data-search="{{ Str::lower($schedule->area . ' ' . ($schedule->schedule_type === 'specific_date' ? $schedule->specific_date?->format('Y-m-d') : ($schedule->days ?? '')) . ' ' . $schedule->truck) }}" data-zone-color="{{ $zoneColor['bg'] }}" data-zone-text="{{ $zoneColor['text'] }}">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                      <div class="w-4 h-4 rounded-full" style="background-color: {{ $zoneColor['bg'] }};"></div>
                      <h6 class="font-semibold text-gray-800">{{ $schedule->area }}</h6>
                    </div>
                    <span class="text-xs font-medium px-2.5 py-0.5 rounded-full bg-green-100 text-green-800">{{ ucfirst($schedule->status) }}</span>
                  </div>
                  @if($schedule->schedule_type === 'specific_date')
                    <p class="text-sm text-gray-600 mt-1">
                      <strong>Date:</strong> 
                      <span class="inline-flex items-center gap-1">
                        <i class="fas fa-calendar-day text-blue-600"></i>
                        {{ $schedule->specific_date?->format('M d, Y') }}
                      </span>
                    </p>
                  @else
                    <p class="text-sm text-gray-600 mt-1"><strong>Days:</strong> {{ $schedule->days }}</p>
                  @endif
                  <p class="text-sm text-gray-600"><strong>Time:</strong> {{ $schedule->time_range }}</p>
                  <p class="text-sm text-gray-600"><strong>Truck:</strong> {{ $schedule->truck }}</p>
                </div>
              @endforeach
            </div>
          @else
            <p class="text-gray-600">No active schedules available.</p>
          @endif
        </div>

        <div class="bg-white rounded-2xl shadow p-6 border border-green-100">
          <h5 class="text-green-700 font-semibold text-lg mb-4 flex items-center gap-2">
            <i class="fas fa-palette"></i>Zone Color Legend
          </h5>
          <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
            @foreach($availableAreas as $area)
              @php
                $zoneColor = $zoneColors[$area] ?? ['bg' => '#10B981', 'text' => '#FFFFFF', 'border' => '#059669'];
              @endphp
              <div class="flex items-center gap-2 text-sm">
                <div class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $zoneColor['bg'] }};"></div>
                <span class="text-gray-700 truncate">{{ $area }}</span>
              </div>
            @endforeach
          </div>
        </div>

      </div>
    </div>
  </div>
@endsection

@push('scripts')
  @php
    $scheduleData = $schedules->map(function ($schedule) use ($zoneColors) {
        $text = strtolower(($schedule->days ?? '') . ' ' . $schedule->area);
        $wasteType = str_contains($text, 'biodegradable') ? 'Biodegradable'
            : (str_contains($text, 'non') ? 'Non-biodegradable'
            : (str_contains($text, 'recycl') ? 'Recyclables' : 'General'));

        $zoneColor = $zoneColors[$schedule->area] ?? ['bg' => '#10B981', 'text' => '#FFFFFF', 'border' => '#059669'];

        $data = [
            'area' => $schedule->area,
            'schedule_type' => $schedule->schedule_type ?? 'recurring',
            'time_range' => $schedule->time_range,
            'truck' => $schedule->truck,
            'waste_type' => $wasteType,
            'zone_color' => $zoneColor,
        ];

        if ($schedule->schedule_type === 'specific_date' && $schedule->specific_date) {
            $data['specific_date'] = $schedule->specific_date->format('Y-m-d');
            $data['days_list'] = [];
            $data['days_label'] = $schedule->specific_date->format('M d, Y');
        } else {
            $days = collect(explode(',', $schedule->days ?? ''))
                ->map(function ($day) {
                    return strtolower(trim($day));
                })
                ->filter()
                ->values();
            $data['days_list'] = $days;
            $data['days_label'] = $schedule->days ?? '';
        }

        return $data;
    });
  @endphp
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const calendar = document.getElementById("calendar");
      const monthYear = document.getElementById("monthYear");
      const prevMonth = document.getElementById("prevMonth");
      const nextMonth = document.getElementById("nextMonth");
      const areaFilter = document.getElementById("areaFilter");
      const scheduleSearch = document.getElementById("scheduleSearch");
      const scheduleItems = document.querySelectorAll(".schedule-item");
      const serviceAreaSelect = document.getElementById('serviceAreaSelect');
      const schedules = @json($scheduleData);
      const zoneColorsMap = @json($zoneColors);

      // Validate required elements exist
      if (!calendar || !monthYear || !prevMonth || !nextMonth || !areaFilter || !scheduleSearch) {
        console.error('Required calendar elements not found');
        return;
      }

      let today = new Date();
      let currentMonth = today.getMonth();
      let currentYear = today.getFullYear();
      let activeAreaFilter = 'all';

    const scheduleDayMap = { 0: [], 1: [], 2: [], 3: [], 4: [], 5: [], 6: [] };
    const specificDateSchedules = [];
    const dayMap = { sunday: 0, monday: 1, tuesday: 2, wednesday: 3, thursday: 4, friday: 5, saturday: 6 };

    if (Array.isArray(schedules)) {
        schedules.forEach(schedule => {
          if (schedule.schedule_type === 'specific_date' && schedule.specific_date) {
            // Store specific date schedules separately
            specificDateSchedules.push(schedule);
          } else if (schedule.days_list && Array.isArray(schedule.days_list)) {
            // Handle recurring schedules
            schedule.days_list.forEach(day => {
              if (dayMap[day] !== undefined) {
                scheduleDayMap[dayMap[day]].push(schedule);
              }
            });
          }
        });
      }

      const renderCalendar = (month, year) => {
        if (!calendar || !monthYear) return;
        
        calendar.innerHTML = "";
        const firstDay = new Date(year, month).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

        weekdays.forEach(day => {
          const div = document.createElement("div");
          div.classList.add("p-3", "text-center", "font-bold", "bg-green-600", "text-white", "rounded-lg");
          div.textContent = day;
          calendar.appendChild(div);
        });

        for (let i = 0; i < firstDay; i++) {
          const empty = document.createElement("div");
          calendar.appendChild(empty);
        }

      for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(year, month, d);
        const div = document.createElement("div");
        const dayOfWeek = date.getDay();
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        
        // Get recurring schedules for this day of week
        const daySchedules = scheduleDayMap[dayOfWeek] || [];
        
        // Get specific date schedules for this exact date
        const specificDateMatches = specificDateSchedules.filter(s => {
          if (s.specific_date === dateStr) {
            return activeAreaFilter === 'all' || s.area === activeAreaFilter;
          }
          return false;
        });
        
        // Combine both types
        const allDaySchedules = [...daySchedules, ...specificDateMatches];
        const filteredSchedules = allDaySchedules.filter(s => activeAreaFilter === 'all' || s.area === activeAreaFilter);
        const summarySource = filteredSchedules.length ? filteredSchedules : allDaySchedules;

          div.className = "p-4 text-center rounded-lg cursor-pointer transition-all duration-300 font-medium";
          div.textContent = d;

          if (summarySource.length) {
            const summary = summarySource.map(item => `${item.area || 'Unknown'} (${item.time_range || 'N/A'})`).join(" • ");
            div.title = summary;
          }

          if (filteredSchedules.length) {
            // Use zone color for the first schedule on this day
            const firstSchedule = filteredSchedules[0];
            const zoneColor = firstSchedule.zone_color || { bg: '#10B981', text: '#FFFFFF', border: '#059669' };
            
            // If multiple zones on same day, use gradient or first zone color
            if (filteredSchedules.length > 1) {
              // Multiple zones - use a gradient or the first zone's color with indicator
              div.style.backgroundColor = zoneColor.bg;
              div.style.color = zoneColor.text;
              div.style.borderColor = zoneColor.border;
              div.style.borderWidth = '2px';
              div.style.borderStyle = 'solid';
              div.classList.add('font-semibold', 'shadow-md');
              div.title = `${filteredSchedules.length} zones scheduled`;
            } else {
              // Single zone - use its color
              div.style.backgroundColor = zoneColor.bg;
              div.style.color = zoneColor.text;
              div.style.borderColor = zoneColor.border;
              div.style.borderWidth = '2px';
              div.style.borderStyle = 'solid';
              div.classList.add('font-semibold', 'shadow-md');
            }
          } else if (daySchedules.length) {
            // Has schedules but filtered out - show muted
            div.classList.add("bg-gray-100", "text-gray-600", "border", "border-gray-200");
          } else {
            // No schedules
            div.classList.add("bg-gray-100", "hover:bg-gray-200", "hover:scale-105", "text-gray-800");
          }

          const isToday = d === today.getDate() && month === today.getMonth() && year === today.getFullYear();
          if (isToday) {
            div.classList.add("ring-2", "ring-green-500");
          }

          if (summarySource.length) {
            div.addEventListener('click', () => {
              const dayDetailsBody = document.getElementById('dayDetailsBody');
              const dayDetailsModal = document.getElementById('dayDetailsModal');
              
              if (!dayDetailsBody || !dayDetailsModal) {
                if (typeof showToast === 'function') {
                  showToast('error', 'Unable to display day details.');
                }
                return;
              }

              const listItems = summarySource.map(item => {
                const zoneColor = item.zone_color || { bg: '#10B981', border: '#059669' };
                return `
                  <div class="border-l-4 rounded-lg p-3 mb-2 bg-gray-50" style="border-left-color: ${zoneColor.border};">
                    <div class="flex items-center gap-2 mb-2">
                      <div class="w-3 h-3 rounded-full" style="background-color: ${zoneColor.bg};"></div>
                      <p class="font-semibold text-gray-800">${item.area || 'Unknown'}</p>
                    </div>
                    <p class="text-sm text-gray-600">Days: ${item.days_label || 'N/A'}</p>
                    <p class="text-sm text-gray-600">Time: ${item.time_range || 'N/A'}</p>
                    <p class="text-sm text-gray-600">Truck: ${item.truck || 'N/A'}</p>
                  </div>
                `;
              }).join('');

              dayDetailsBody.innerHTML = listItems;
              
              // Update modal title
              const modalTitle = dayDetailsModal.querySelector('h5');
              if (modalTitle) {
                const icon = modalTitle.querySelector('i');
                modalTitle.innerHTML = '';
                if (icon) {
                  modalTitle.appendChild(icon);
                }
                modalTitle.appendChild(document.createTextNode(`Pickups on ${date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })}`));
              }
              
              if (typeof openModal === 'function') {
                openModal('dayDetailsModal');
              }
            });
          }

          calendar.appendChild(div);
        }

        const monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];
        monthYear.textContent = `${monthNames[month]} ${year}`;
      };

      prevMonth.addEventListener("click", () => {
        currentMonth--;
        if (currentMonth < 0) {
          currentMonth = 11;
          currentYear--;
        }
        renderCalendar(currentMonth, currentYear);
      });

      nextMonth.addEventListener("click", () => {
        currentMonth++;
        if (currentMonth > 11) {
          currentMonth = 0;
          currentYear++;
        }
        renderCalendar(currentMonth, currentYear);
      });

      const applyScheduleFilters = () => {
        if (!areaFilter || !scheduleSearch) return;
        
        const areaValue = areaFilter.value.toLowerCase();
        const searchValue = scheduleSearch.value.trim().toLowerCase();

        scheduleItems.forEach(item => {
          const matchesArea = areaValue === 'all' || item.dataset.area === areaValue.toLowerCase();
          const matchesSearch = !searchValue || (item.dataset.search && item.dataset.search.includes(searchValue));
          item.style.display = matchesArea && matchesSearch ? 'block' : 'none';
        });
      };

      areaFilter.addEventListener('change', () => {
        activeAreaFilter = areaFilter.value === 'all' ? 'all' : areaFilter.value;
        applyScheduleFilters();
        renderCalendar(currentMonth, currentYear);
      });

      scheduleSearch.addEventListener('input', applyScheduleFilters);

      if (serviceAreaSelect && csrfToken) {
        serviceAreaSelect.addEventListener('change', () => {
          const selectedArea = serviceAreaSelect.value || null;
          
          fetch('{{ route('garbage-schedule.service-area') }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json',
            },
            body: JSON.stringify({ service_area: selectedArea }),
          })
            .then(response => {
              if (!response.ok) {
                return response.json().then(err => Promise.reject(err));
              }
              return response.json();
            })
            .then(() => {
              // Reload to show updated next pickup info
              window.location.reload();
            })
            .catch((error) => {
              console.error('Service area update error:', error);
              if (typeof showToast === 'function') {
                const errorMessage = error.message || 'Unable to update your zone. Please try again.';
                showToast('error', errorMessage);
              }
            });
        });
      }

      applyScheduleFilters();
      renderCalendar(currentMonth, currentYear);
    });
  </script>
@endpush

@push('modals')
  <x-modal id="dayDetailsModal" title="Day Schedule" icon="fas fa-calendar" color="green">
    <div id="dayDetailsBody" class="space-y-3 text-sm text-gray-700">
    </div>

    @slot('footer')
      <div class="flex justify-end">
        <button onclick="closeModal('dayDetailsModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">Close</button>
      </div>
    @endslot
  </x-modal>
@endpush
