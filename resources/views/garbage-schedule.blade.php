@extends('layouts.app')

@section('title', 'Garbage Schedule')

@section('content')
  <div class="resident-schedule-page">
    <header class="resident-page-header resident-schedule-header">
      <p class="resident-eyebrow">Collection services</p>
      <h1 class="resident-page-title">Garbage Schedule</h1>
      <p class="resident-page-subtitle">Your official collection days, based on the service area saved to your account.</p>
    </header>

    @if(!$userArea)
      <section class="resident-card resident-schedule-empty" aria-labelledby="schedule-area-empty-title">
        <span class="resident-schedule-empty-icon" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>
        <h2 id="schedule-area-empty-title">No official service area assigned</h2>
        <p>Add or update your service area in Settings to see the collection schedule for your neighborhood.</p>
        <a href="{{ route('settings') }}" class="resident-btn-primary">
          <i class="fas fa-cog" aria-hidden="true"></i>
          Open Settings
        </a>
      </section>
    @else
      <div class="resident-schedule-summary-grid">
        <section class="resident-schedule-featured" aria-labelledby="next-collection-title">
          <div class="resident-schedule-area-label">
            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
            Official service area
          </div>
          <p class="resident-schedule-area">{{ $userArea }}</p>

          <div class="resident-schedule-next">
            <div>
              <p class="resident-schedule-kicker">Next collection</p>
              <h2 id="next-collection-title">
                {{ $nextPickup ? $nextPickup['date_display'] : 'No collection scheduled' }}
              </h2>
              @if($nextPickup)
                <p class="resident-schedule-next-meta">{{ $nextPickup['time_display'] }} · {{ $nextPickup['waste_type'] }}</p>
              @else
                <p class="resident-schedule-next-meta">There are no upcoming pickups published for your area yet.</p>
              @endif
            </div>
            @if($nextPickup)
              <span class="resident-schedule-time-badge">
                <i class="far fa-clock" aria-hidden="true"></i>
                {{ $nextPickup['time_range'] }}
              </span>
            @endif
          </div>

          @if($nextPickup)
            <div class="resident-schedule-detail-row">
              <span class="resident-schedule-detail-icon" aria-hidden="true"><i class="fas fa-truck"></i></span>
              <div>
                <span>Assigned vehicle</span>
                <strong>Truck {{ $nextPickup['truck'] }}</strong>
              </div>
            </div>
          @endif

          <div class="resident-schedule-reminder">
            <i class="fas fa-bell" aria-hidden="true"></i>
            <div>
              @if($scheduleRemindersEnabled && $emailNotificationsEnabled)
                <p>We'll remind you by email 1 day before your scheduled collection.</p>
                <a href="{{ route('settings') }}">Manage reminder options in Settings</a>
              @elseif(!$scheduleRemindersEnabled)
                <p>Schedule reminders are currently turned off.</p>
                <a href="{{ route('settings') }}">Manage reminder options in Settings</a>
              @else
                <p>Schedule reminders are enabled.</p>
                <a href="{{ route('settings') }}">Manage your reminder options in Settings.</a>
              @endif
            </div>
          </div>
        </section>

        <section class="resident-card resident-schedule-upcoming" aria-labelledby="upcoming-pickups-title">
          <div class="resident-schedule-section-heading">
            <div>
              <p class="resident-schedule-kicker">Plan ahead</p>
              <h2 id="upcoming-pickups-title">Upcoming pickups</h2>
            </div>
            <span>Next 5</span>
          </div>

          <div class="resident-schedule-upcoming-list">
            @forelse($upcomingPickups as $pickup)
              <article class="resident-schedule-pickup">
                <div class="resident-schedule-date-box">
                  <strong>{{ $pickup['date_display'] }}</strong>
                  <span>{{ $pickup['day_label'] }}</span>
                </div>
                <div class="resident-schedule-pickup-copy">
                  <strong>{{ $pickup['time_range'] }}</strong>
                  <span>Truck {{ $pickup['truck'] }} · {{ $pickup['waste_type'] }}</span>
                </div>
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
              </article>
            @empty
              <div class="resident-schedule-list-empty">
                <i class="far fa-calendar" aria-hidden="true"></i>
                <p>No upcoming pickups have been published for your area.</p>
              </div>
            @endforelse
          </div>
        </section>
      </div>

      @if($schedules->isNotEmpty())
        <section class="resident-card resident-schedule-calendar-card" aria-labelledby="collection-calendar-title">
          <div class="resident-schedule-calendar-header">
            <div>
              <p class="resident-schedule-kicker">Monthly view</p>
              <h2 id="collection-calendar-title">Collection calendar</h2>
            </div>
            <div class="resident-schedule-month-controls">
              <button id="prevMonth" type="button" aria-label="Previous month">
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
              </button>
              <h3 id="monthYear" aria-live="polite"></h3>
              <button id="nextMonth" type="button" aria-label="Next month">
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
              </button>
            </div>
          </div>
          <div class="resident-schedule-calendar" id="calendar"></div>
          <p class="resident-schedule-calendar-tip">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            Green dates are collection days. Select one to view pickup details.
          </p>
        </section>
      @else
        <section class="resident-card resident-schedule-empty resident-schedule-empty--compact" aria-labelledby="schedule-empty-title">
          <span class="resident-schedule-empty-icon" aria-hidden="true"><i class="far fa-calendar-times"></i></span>
          <h2 id="schedule-empty-title">No schedule published yet</h2>
          <p>Your official area is set to {{ $userArea }}, but it does not have an active collection schedule right now.</p>
        </section>
      @endif
    @endif
  </div>
@endsection

@if($userArea && $schedules->isNotEmpty())
  @push('scripts')
    @php
      $scheduleData = $schedules->map(function ($schedule) {
          $text = strtolower(($schedule->days ?? '').' '.$schedule->area);
          $wasteType = str_contains($text, 'biodegradable') ? 'Biodegradable'
              : (str_contains($text, 'non') ? 'Non-biodegradable'
              : (str_contains($text, 'recycl') ? 'Recyclables' : 'General'));

          $data = [
              'area' => $schedule->area,
              'schedule_type' => $schedule->schedule_type ?? 'recurring',
              'time_range' => $schedule->time_range,
              'truck' => $schedule->truck,
              'waste_type' => $wasteType,
              'recurrence_start_date' => $schedule->recurrence_start_date?->format('Y-m-d'),
              'recurrence_interval_days' => $schedule->recurrence_interval_days,
          ];

          if ($schedule->schedule_type === 'specific_date' && $schedule->specific_date) {
              $data['specific_date'] = $schedule->specific_date->format('Y-m-d');
              $data['days_list'] = [];
              $data['days_label'] = $schedule->specific_date->format('M d, Y');
          } elseif ($schedule->usesIntervalRecurrence()) {
              $data['days_list'] = [];
              $data['days_label'] = $schedule->collection_label;
          } else {
              $days = collect(preg_split('/\s*(?:,|&|and)\s*/i', $schedule->days ?? '') ?: [])
                  ->map(fn ($day) => strtolower(trim($day)))
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
        const calendar = document.getElementById('calendar');
        const monthYear = document.getElementById('monthYear');
        const prevMonth = document.getElementById('prevMonth');
        const nextMonth = document.getElementById('nextMonth');
        const schedules = @json($scheduleData);

        if (!calendar || !monthYear || !prevMonth || !nextMonth) {
          return;
        }

        const today = new Date();
        let currentMonth = today.getMonth();
        let currentYear = today.getFullYear();
        let selectedDate = null;
        const scheduleDayMap = { 0: [], 1: [], 2: [], 3: [], 4: [], 5: [], 6: [] };
        const specificDateSchedules = [];
        const intervalSchedules = [];
        const dayMap = { sunday: 0, monday: 1, tuesday: 2, wednesday: 3, thursday: 4, friday: 5, saturday: 6 };

        schedules.forEach(schedule => {
          if (schedule.schedule_type === 'specific_date' && schedule.specific_date) {
            specificDateSchedules.push(schedule);
            return;
          }

          if (schedule.recurrence_start_date && schedule.recurrence_interval_days) {
            intervalSchedules.push(schedule);
            return;
          }

          (schedule.days_list || []).forEach(day => {
            if (dayMap[day] !== undefined) {
              scheduleDayMap[dayMap[day]].push(schedule);
            }
          });
        });

        const escapeHtml = value => String(value ?? '')
          .replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;')
          .replaceAll('"', '&quot;')
          .replaceAll("'", '&#039;');

        const occursOnInterval = (schedule, dateKey) => {
          const start = Date.parse(`${schedule.recurrence_start_date}T00:00:00Z`);
          const candidate = Date.parse(`${dateKey}T00:00:00Z`);
          const elapsedDays = Math.round((candidate - start) / 86400000);

          return elapsedDays >= 0 && elapsedDays % Number(schedule.recurrence_interval_days) === 0;
        };

        const showDayDetails = (date, pickups) => {
          const body = document.getElementById('dayDetailsBody');
          const modal = document.getElementById('dayDetailsModal');

          if (!body || !modal) {
            return;
          }

          body.innerHTML = pickups.map(item => `
            <article class="resident-schedule-modal-pickup">
              <span class="resident-schedule-detail-icon" aria-hidden="true"><i class="fas fa-truck"></i></span>
              <div>
                <strong>${escapeHtml(item.waste_type || 'Collection')}</strong>
                <p>${escapeHtml(item.days_label || 'Scheduled date')} · ${escapeHtml(item.time_range || 'Time pending')}</p>
                <span>Truck ${escapeHtml(item.truck || 'To be assigned')}</span>
              </div>
            </article>
          `).join('');

          const title = modal.querySelector('h5');
          if (title) {
            title.textContent = `Pickups on ${date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })}`;
          }

          if (typeof openModal === 'function') {
            openModal('dayDetailsModal');
          }
        };

        const renderCalendar = (month, year) => {
          calendar.innerHTML = '';
          const firstDay = new Date(year, month).getDay();
          const daysInMonth = new Date(year, month + 1, 0).getDate();
          const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

          weekdays.forEach(day => {
            const heading = document.createElement('div');
            heading.className = 'resident-schedule-weekday';
            heading.textContent = day;
            calendar.appendChild(heading);
          });

          for (let i = 0; i < firstDay; i++) {
            const spacer = document.createElement('div');
            spacer.className = 'resident-schedule-calendar-spacer';
            spacer.setAttribute('aria-hidden', 'true');
            calendar.appendChild(spacer);
          }

          for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day);
            const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const pickups = [
              ...(scheduleDayMap[date.getDay()] || []),
              ...specificDateSchedules.filter(schedule => schedule.specific_date === dateKey),
              ...intervalSchedules.filter(schedule => occursOnInterval(schedule, dateKey)),
            ];
            const cell = document.createElement('button');
            cell.type = 'button';
            cell.className = 'resident-schedule-calendar-day';
            cell.textContent = day;

            if (pickups.length) {
              cell.classList.add('resident-schedule-calendar-day--collection');
              cell.title = pickups.map(item => `${item.time_range || 'Time pending'} · Truck ${item.truck || 'TBA'}`).join(', ');
              cell.addEventListener('click', () => {
                selectedDate = dateKey;
                renderCalendar(currentMonth, currentYear);
                showDayDetails(date, pickups);
              });
            } else {
              cell.disabled = true;
            }

            if (dateKey === selectedDate) {
              cell.classList.add('resident-schedule-calendar-day--selected');
            }

            if (
              day === today.getDate()
              && month === today.getMonth()
              && year === today.getFullYear()
            ) {
              cell.classList.add('resident-schedule-calendar-day--today');
              cell.setAttribute('aria-current', 'date');
            }

            calendar.appendChild(cell);
          }

          const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
          monthYear.textContent = `${monthNames[month]} ${year}`;
        };

        prevMonth.addEventListener('click', () => {
          currentMonth--;
          if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
          }
          selectedDate = null;
          renderCalendar(currentMonth, currentYear);
        });

        nextMonth.addEventListener('click', () => {
          currentMonth++;
          if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
          }
          selectedDate = null;
          renderCalendar(currentMonth, currentYear);
        });

        renderCalendar(currentMonth, currentYear);
      });
    </script>
  @endpush

  @push('modals')
    <x-modal id="dayDetailsModal" title="Day Schedule" icon="fas fa-calendar" color="green">
      <div id="dayDetailsBody" class="resident-schedule-modal-list"></div>

      @slot('footer')
        <div class="flex justify-end">
          <button type="button" onclick="closeModal('dayDetailsModal')" class="resident-btn-secondary">Close</button>
        </div>
      @endslot
    </x-modal>
  @endpush
@endif
