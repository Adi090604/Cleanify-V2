@extends('layouts.admin')

@section('title', 'Schedule')

@section('content')
  @if(session('success'))
    <x-alert type="success" dismissible class="mb-4">
      {{ session('success') }}
    </x-alert>
  @endif

  <div class="admin-page-header flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
    <div>
      <h2 class="text-3xl font-bold text-gray-800">
        <i class="fas fa-calendar-alt text-green-600 mr-3"></i>Garbage Collection Schedule
      </h2>
      <p class="text-gray-600 mt-1">Manage garbage collection schedules for different areas</p>
    </div>
    <div class="flex w-full lg:w-auto max-w-lg">
      <form action="{{ route('admin.schedule') }}" method="GET" class="flex w-full">
        <input id="scheduleSearchInput" type="text" name="search" value="{{ $search }}" class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Search schedule...">
        <button type="submit" class="px-4 bg-green-600 text-white rounded-r-lg hover:bg-green-700 transition-colors duration-300">
          <i class="fas fa-search"></i>
        </button>
      </form>
    </div>
  </div>

  <div class="admin-stat-grid grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 mb-5">
    <x-admin.stat-card icon="fas fa-calendar-check" title="Active Schedules" value="{{ $activeSchedules }}" />
    <x-admin.stat-card icon="fas fa-truck" title="Trucks Assigned" value="{{ $trucksAssigned }}" borderClass="border-l-4 border-blue-500" iconWrapperClass="bg-blue-100" iconColorClass="text-blue-600" />
    <x-admin.stat-card icon="fas fa-clock" title="Pending Schedules" value="{{ $pendingSchedules }}" borderClass="border-l-4 border-yellow-500" iconWrapperClass="bg-yellow-100" iconColorClass="text-yellow-600" />
    <x-admin.stat-card icon="fas fa-calendar-times" title="Inactive Schedules" value="{{ $inactiveSchedules }}" borderClass="border-l-4 border-red-500" iconWrapperClass="bg-red-100" iconColorClass="text-red-600" />
  </div>

  <div class="admin-table-card bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-4 flex justify-between items-center">
      <h5 class="font-semibold text-lg text-gray-800">Community Garbage Schedules</h5>
      <button onclick="openAddScheduleModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-plus-circle mr-2"></i>Add Schedule
      </button>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full" id="scheduleTable">
        <thead>
          <tr class="admin-table-header">
            @foreach (['#','Barangay / Zone','Collection Day','Time','Truck Assigned','Status','Actions'] as $heading)
              <th class="px-4 py-3 text-left text-sm font-semibold">{{ $heading }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody id="scheduleTableBody" class="divide-y divide-gray-200">
          @forelse ($schedules as $schedule)
            <tr class="hover:bg-gray-50 transition-colors duration-200" data-schedule="{{ json_encode([
              'id' => $schedule->id,
              'area' => $schedule->area,
              'schedule_type' => $schedule->schedule_type,
              'specific_date' => $schedule->specific_date?->format('Y-m-d'),
              'days' => $schedule->days,
              'time_start' => $schedule->time_start->format('H:i'),
              'time_end' => $schedule->time_end->format('H:i'),
              'truck' => $schedule->truck,
              'status' => $schedule->status,
            ]) }}">
              <td class="px-4 py-3">{{ $schedule->id }}</td>
              <td class="px-4 py-3 font-medium text-gray-900">{{ $schedule->area }}</td>
              <td class="px-4 py-3 text-gray-600">
                @if($schedule->schedule_type === 'specific_date')
                  <span class="inline-flex items-center gap-1">
                    <i class="fas fa-calendar-day text-blue-600"></i>
                    {{ $schedule->specific_date?->format('M d, Y') }}
                  </span>
                @else
                  {{ $schedule->days }}
                @endif
              </td>
              <td class="px-4 py-3 text-gray-600">{{ $schedule->time_range }}</td>
              @php
                // Find the matching truck by its code so we can show code + driver
                $assignedTruck = $trucks->firstWhere('code', $schedule->truck);
              @endphp
              <td class="px-4 py-3 text-gray-600">
                @if($assignedTruck)
                  {{ $assignedTruck->code }}@if($assignedTruck->driver) - {{ $assignedTruck->driver }}@endif
                @else
                  {{ $schedule->truck }}
                @endif
              </td>
              <td class="px-4 py-3">
                <span class="admin-status-badge {{ $schedule->getStatusBadgeClass() }}">
                  <i class="fas fa-circle text-xs mr-1"></i>{{ $schedule->formatted_status }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex space-x-2">
                  <button onclick="openEditScheduleModal({{ $schedule->id }})" class="admin-icon-button bg-blue-500 text-white hover:bg-blue-600" title="Edit">
                    <i class="fas fa-edit text-xs"></i>
                  </button>
                  <button onclick="openDeleteScheduleModal({{ $schedule->id }})" class="admin-icon-button bg-red-500 text-white hover:bg-red-600" title="Delete">
                    <i class="fas fa-trash text-xs"></i>
                  </button>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                <i class="fas fa-calendar-alt text-4xl mb-2 block"></i>
                No schedules found
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="px-6 py-4 border-t border-gray-200">
      <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
        <div class="text-sm text-gray-600">
          @if($schedules->total() > 0)
            Showing {{ $schedules->firstItem() }} to {{ $schedules->lastItem() }} of {{ $schedules->total() }} entries
          @else
            No entries found
          @endif
        </div>
        <div class="flex items-center">
          {{ $schedules->appends(['search' => $search, 'status' => $statusFilter])->links('pagination::tailwind') }}
        </div>
      </div>
    </div>
  </div>
@endsection

@push('modals')
  <x-modal id="addScheduleModal" title="Add Schedule" icon="fas fa-plus-circle" color="green">
    <form id="addScheduleForm" method="POST" action="{{ route('admin.schedule.store') }}" class="space-y-4">
      @csrf
      <div>
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-map-marker-alt mr-2 text-green-600"></i>Barangay / Zone
        </label>
        <select name="area" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
          <option value="">Select a zone</option>
          @foreach($zones as $zone)
            <option value="{{ $zone }}">{{ $zone }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-calendar-check mr-2 text-green-600"></i>Schedule Type
        </label>
        <select name="schedule_type" id="addScheduleType" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
          <option value="recurring">Recurring (By Day of Week)</option>
          <option value="specific_date">Specific Date</option>
        </select>
      </div>
      <div id="addRecurringDays" class="schedule-type-field">
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-calendar-day mr-2 text-green-600"></i>Collection Days
        </label>
        <select name="days" id="addScheduleDays" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
          @foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday','Monday & Thursday','Tuesday & Friday'] as $option)
            <option value="{{ $option }}">{{ $option }}</option>
          @endforeach
        </select>
      </div>
      <div id="addSpecificDate" class="schedule-type-field hidden">
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-calendar-alt mr-2 text-green-600"></i>Specific Date
        </label>
        <input type="date" name="specific_date" id="addScheduleSpecificDate" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" min="{{ date('Y-m-d') }}">
      </div>
      <div>
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-clock mr-2 text-green-600"></i>Collection Time
        </label>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
          @foreach ([
            ['id' => 'addScheduleTimeStart', 'name' => 'time_start', 'value' => old('time_start', '06:00'), 'label' => 'Start time'],
            ['id' => 'addScheduleTimeEnd', 'name' => 'time_end', 'value' => old('time_end', '09:00'), 'label' => 'End time'],
          ] as $timePicker)
            @if (!$loop->first)
              <span class="self-center text-sm text-gray-500">to</span>
            @endif
            <div class="relative min-w-0 flex-1" data-time-picker>
              <input type="hidden" name="{{ $timePicker['name'] }}" id="{{ $timePicker['id'] }}" value="{{ $timePicker['value'] }}" data-time-value>
              <button type="button" class="flex h-10 w-full items-center justify-between rounded-xl border border-gray-300 bg-white px-4 text-left text-gray-800 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1" data-time-trigger aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $timePicker['id'] }}Popover" aria-label="Choose {{ strtolower($timePicker['label']) }}">
                <span data-time-display></span>
                <i class="far fa-clock ml-3 text-sm text-gray-400" aria-hidden="true"></i>
              </button>
              <div id="{{ $timePicker['id'] }}Popover" class="fixed z-[70] hidden max-w-[calc(100vw-1.5rem)] rounded-xl border border-gray-200 bg-white p-3 shadow-lg" data-time-popover role="dialog" aria-label="{{ $timePicker['label'] }} picker">
                <div class="grid grid-cols-[1fr_auto_1fr_1fr] items-start gap-2">
                  <label class="text-center">
                    <select class="w-full rounded-lg border-gray-200 bg-green-50 px-2 py-2 text-center font-semibold text-green-700 focus:border-green-500 focus:ring-green-500" data-time-hour aria-label="Hour"></select>
                    <span class="mt-1 block text-[11px] text-gray-500">hour</span>
                  </label>
                  <span class="pt-2 font-semibold text-gray-400" aria-hidden="true">:</span>
                  <label class="text-center">
                    <select class="w-full rounded-lg border-gray-200 bg-green-50 px-2 py-2 text-center font-semibold text-green-700 focus:border-green-500 focus:ring-green-500" data-time-minute aria-label="Minute"></select>
                    <span class="mt-1 block text-[11px] text-gray-500">minute</span>
                  </label>
                  <label class="text-center">
                    <select class="w-full rounded-lg border-gray-200 bg-green-50 px-2 py-2 text-center font-semibold text-green-700 focus:border-green-500 focus:ring-green-500" data-time-period aria-label="AM or PM">
                      <option value="AM">AM</option>
                      <option value="PM">PM</option>
                    </select>
                    <span class="mt-1 block text-[11px] text-gray-500">period</span>
                  </label>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
      <div>
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-truck mr-2 text-green-600"></i>Truck Assigned
        </label>
        <select name="truck" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
          <option value="">Select a truck</option>
          @foreach($trucks as $truck)
            <option value="{{ $truck->code }}">{{ $truck->code }} — {{ filled($truck->driver) ? $truck->driver : 'No driver assigned' }} — {{ filled($truck->route) ? $truck->route : 'No route assigned' }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-circle mr-2 text-green-600"></i>Status
        </label>
        <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
          @foreach (['active','pending','inactive'] as $status)
            <option value="{{ $status }}">{{ ucfirst($status) }}</option>
          @endforeach
        </select>
      </div>
    </form>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('addScheduleModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Cancel
        </button>
        <button type="submit" form="addScheduleForm" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
          <i class="fas fa-save mr-2"></i>Save Schedule
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="editScheduleModal" title="Edit Schedule" icon="fas fa-edit" color="blue">
    <form id="editScheduleForm" method="POST" class="space-y-4">
      @csrf
      @method('PUT')
      <input type="hidden" name="id" id="editScheduleId">
      <div>
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>Barangay / Zone
        </label>
        <select name="area" id="editScheduleArea" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
          <option value="">Select a zone</option>
          @foreach($zones as $zone)
            <option value="{{ $zone }}">{{ $zone }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-calendar-check mr-2 text-blue-600"></i>Schedule Type
        </label>
        <select name="schedule_type" id="editScheduleType" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
          <option value="recurring">Recurring (By Day of Week)</option>
          <option value="specific_date">Specific Date</option>
        </select>
      </div>
      <div id="editRecurringDays" class="schedule-type-field">
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-calendar-day mr-2 text-blue-600"></i>Collection Days
        </label>
        <select name="days" id="editScheduleDays" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
          @foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday','Monday & Thursday','Tuesday & Friday'] as $option)
            <option value="{{ $option }}">{{ $option }}</option>
          @endforeach
        </select>
      </div>
      <div id="editSpecificDate" class="schedule-type-field hidden">
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-calendar-alt mr-2 text-blue-600"></i>Specific Date
        </label>
        <input type="date" name="specific_date" id="editScheduleSpecificDate" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" min="{{ date('Y-m-d') }}">
      </div>
      <div>
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-clock mr-2 text-blue-600"></i>Collection Time
        </label>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
          @foreach ([
            ['id' => 'editScheduleTimeStart', 'name' => 'time_start', 'label' => 'Start time'],
            ['id' => 'editScheduleTimeEnd', 'name' => 'time_end', 'label' => 'End time'],
          ] as $timePicker)
            @if (!$loop->first)
              <span class="self-center text-sm text-gray-500">to</span>
            @endif
            <div class="relative min-w-0 flex-1" data-time-picker>
              <input type="hidden" name="{{ $timePicker['name'] }}" id="{{ $timePicker['id'] }}" data-time-value>
              <button type="button" class="flex h-10 w-full items-center justify-between rounded-xl border border-gray-300 bg-white px-4 text-left text-gray-800 transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1" data-time-trigger aria-haspopup="dialog" aria-expanded="false" aria-controls="{{ $timePicker['id'] }}Popover" aria-label="Choose {{ strtolower($timePicker['label']) }}">
                <span data-time-display></span>
                <i class="far fa-clock ml-3 text-sm text-gray-400" aria-hidden="true"></i>
              </button>
              <div id="{{ $timePicker['id'] }}Popover" class="fixed z-[70] hidden max-w-[calc(100vw-1.5rem)] rounded-xl border border-gray-200 bg-white p-3 shadow-lg" data-time-popover role="dialog" aria-label="{{ $timePicker['label'] }} picker">
                <div class="grid grid-cols-[1fr_auto_1fr_1fr] items-start gap-2">
                  <label class="text-center">
                    <select class="w-full rounded-lg border-gray-200 bg-green-50 px-2 py-2 text-center font-semibold text-green-700 focus:border-green-500 focus:ring-green-500" data-time-hour aria-label="Hour"></select>
                    <span class="mt-1 block text-[11px] text-gray-500">hour</span>
                  </label>
                  <span class="pt-2 font-semibold text-gray-400" aria-hidden="true">:</span>
                  <label class="text-center">
                    <select class="w-full rounded-lg border-gray-200 bg-green-50 px-2 py-2 text-center font-semibold text-green-700 focus:border-green-500 focus:ring-green-500" data-time-minute aria-label="Minute"></select>
                    <span class="mt-1 block text-[11px] text-gray-500">minute</span>
                  </label>
                  <label class="text-center">
                    <select class="w-full rounded-lg border-gray-200 bg-green-50 px-2 py-2 text-center font-semibold text-green-700 focus:border-green-500 focus:ring-green-500" data-time-period aria-label="AM or PM">
                      <option value="AM">AM</option>
                      <option value="PM">PM</option>
                    </select>
                    <span class="mt-1 block text-[11px] text-gray-500">period</span>
                  </label>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
      <div>
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-truck mr-2 text-blue-600"></i>Truck Assigned
        </label>
        <select name="truck" id="editScheduleTruck" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
          <option value="">Select a truck</option>
          @foreach($trucks as $truck)
            <option value="{{ $truck->code }}">{{ $truck->code }} — {{ filled($truck->driver) ? $truck->driver : 'No driver assigned' }} — {{ filled($truck->route) ? $truck->route : 'No route assigned' }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-circle mr-2 text-blue-600"></i>Status
        </label>
        <select name="status" id="editScheduleStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
          @foreach (['active','pending','inactive'] as $status)
            <option value="{{ $status }}">{{ ucfirst($status) }}</option>
          @endforeach
        </select>
      </div>
    </form>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('editScheduleModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Cancel
        </button>
        <button type="submit" form="editScheduleForm" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-300">
          <i class="fas fa-save mr-2"></i>Save Changes
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="deleteScheduleModal" title="Delete Schedule" icon="fas fa-trash-alt" color="red" variant="confirmation">
    <p>Are you sure you want to delete this schedule? This action cannot be undone.</p>
    <div class="admin-confirm-warning">
      <i class="fas fa-exclamation-circle mt-0.5" aria-hidden="true"></i>
      <p>This will permanently remove the schedule and may affect garbage collection in the area.</p>
    </div>
    @slot('footer')
      <div class="admin-confirm-actions">
        <button type="button" onclick="closeModal('deleteScheduleModal')" class="admin-btn-secondary">
          Cancel
        </button>
        <form id="deleteScheduleForm" method="POST" class="inline">
          @csrf
          @method('DELETE')
          <button type="submit" class="admin-btn-danger">
            <i class="fas fa-trash mr-2"></i>Delete Schedule
          </button>
        </form>
      </div>
    @endslot
  </x-modal>
@endpush

  @push('scripts')
  <script>
    let currentScheduleId = null;
    let activeTimePicker = null;

    function normalizeTime24(value) {
      const match = String(value || '').match(/^(\d{1,2}):(\d{2})/);
      if (!match) return null;

      const hour = Number(match[1]);
      const minute = Number(match[2]);
      if (!Number.isInteger(hour) || hour < 0 || hour > 23 || !Number.isInteger(minute) || minute < 0 || minute > 59) {
        return null;
      }

      return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
    }

    function time24ToPickerParts(value) {
      const normalized = normalizeTime24(value) || '00:00';
      const [hourValue, minute] = normalized.split(':');
      const hour24 = Number(hourValue);

      return {
        hour: String(hour24 % 12 || 12).padStart(2, '0'),
        minute,
        period: hour24 >= 12 ? 'PM' : 'AM',
      };
    }

    function pickerPartsToTime24(hourValue, minute, period) {
      const hour12 = Number(hourValue);
      let hour24 = hour12 % 12;
      if (period === 'PM') hour24 += 12;

      return `${String(hour24).padStart(2, '0')}:${minute}`;
    }

    function setTimePickerValue(picker, value) {
      if (!picker) return;

      const input = picker.querySelector('[data-time-value]');
      const display = picker.querySelector('[data-time-display]');
      const hourSelect = picker.querySelector('[data-time-hour]');
      const minuteSelect = picker.querySelector('[data-time-minute]');
      const periodSelect = picker.querySelector('[data-time-period]');
      const normalized = normalizeTime24(value) || '00:00';
      const parts = time24ToPickerParts(normalized);

      input.value = normalized;
      hourSelect.value = parts.hour;
      minuteSelect.value = parts.minute;
      periodSelect.value = parts.period;
      display.textContent = `${parts.hour}:${parts.minute} ${parts.period}`;
    }

    function updateTimePickerFromControls(picker) {
      const hour = picker.querySelector('[data-time-hour]').value;
      const minute = picker.querySelector('[data-time-minute]').value;
      const period = picker.querySelector('[data-time-period]').value;
      setTimePickerValue(picker, pickerPartsToTime24(hour, minute, period));
    }

    function positionTimePickerPopover(picker) {
      const trigger = picker.querySelector('[data-time-trigger]');
      const popover = picker.querySelector('[data-time-popover]');
      const triggerRect = trigger.getBoundingClientRect();
      const viewportPadding = 12;
      const gap = 6;
      const width = Math.min(264, window.innerWidth - (viewportPadding * 2));

      popover.style.width = `${width}px`;
      const left = Math.min(
        Math.max(viewportPadding, triggerRect.left),
        window.innerWidth - width - viewportPadding
      );
      let top = triggerRect.bottom + gap;
      const popoverHeight = popover.offsetHeight;

      if (top + popoverHeight > window.innerHeight - viewportPadding) {
        top = Math.max(viewportPadding, triggerRect.top - popoverHeight - gap);
      }

      popover.style.left = `${left}px`;
      popover.style.top = `${top}px`;
    }

    function closeTimePicker({ restoreFocus = false } = {}) {
      if (!activeTimePicker) return;

      const trigger = activeTimePicker.querySelector('[data-time-trigger]');
      activeTimePicker.querySelector('[data-time-popover]').classList.add('hidden');
      trigger.setAttribute('aria-expanded', 'false');
      activeTimePicker = null;

      if (restoreFocus) trigger.focus();
    }

    function openTimePicker(picker) {
      if (activeTimePicker && activeTimePicker !== picker) closeTimePicker();

      const trigger = picker.querySelector('[data-time-trigger]');
      const popover = picker.querySelector('[data-time-popover]');
      const isAlreadyOpen = activeTimePicker === picker;

      if (isAlreadyOpen) {
        closeTimePicker();
        return;
      }

      activeTimePicker = picker;
      popover.classList.remove('hidden');
      trigger.setAttribute('aria-expanded', 'true');
      positionTimePickerPopover(picker);
      requestAnimationFrame(() => picker.querySelector('[data-time-hour]').focus());
    }

    function initializeTimePickers() {
      document.querySelectorAll('[data-time-picker]').forEach(picker => {
        const hourSelect = picker.querySelector('[data-time-hour]');
        const minuteSelect = picker.querySelector('[data-time-minute]');
        const periodSelect = picker.querySelector('[data-time-period]');
        const input = picker.querySelector('[data-time-value]');

        hourSelect.innerHTML = Array.from({ length: 12 }, (_, index) => {
          const value = String(index + 1).padStart(2, '0');
          return `<option value="${value}">${value}</option>`;
        }).join('');
        minuteSelect.innerHTML = Array.from({ length: 60 }, (_, index) => {
          const value = String(index).padStart(2, '0');
          return `<option value="${value}">${value}</option>`;
        }).join('');

        setTimePickerValue(picker, input.value);
        picker.querySelector('[data-time-trigger]').addEventListener('click', () => openTimePicker(picker));
        [hourSelect, minuteSelect, periodSelect].forEach(select => {
          select.addEventListener('change', () => updateTimePickerFromControls(picker));
        });
      });

      document.addEventListener('click', event => {
        if (activeTimePicker && !activeTimePicker.contains(event.target)) closeTimePicker();
      });
      document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && activeTimePicker) {
          event.preventDefault();
          closeTimePicker({ restoreFocus: true });
        }
      });
      window.addEventListener('resize', () => {
        if (activeTimePicker) positionTimePickerPopover(activeTimePicker);
      });
      document.addEventListener('scroll', () => {
        if (activeTimePicker) positionTimePickerPopover(activeTimePicker);
      }, true);
    }

    function syncTimePickersInForm(form) {
      form.querySelectorAll('[data-time-picker]').forEach(picker => {
        setTimePickerValue(picker, picker.querySelector('[data-time-value]').value);
      });
    }

    function openAddScheduleModal() {
      const form = document.getElementById('addScheduleForm');
      form.reset();
      syncTimePickersInForm(form);
      closeTimePicker();
      openModal('addScheduleModal');
    }

    function openEditScheduleModal(id) {
      currentScheduleId = id;
      const schedule = getScheduleData(id);
      if (!schedule) {
        if (typeof showToast === 'function') {
          showToast('error', 'Schedule not found. Please try again.');
        }
        return;
      }
      
      const form = document.getElementById('editScheduleForm');
      form.action = `/admin/schedule/${id}`;
      document.getElementById('editScheduleId').value = schedule.id;
      document.getElementById('editScheduleType').value = schedule.schedule_type || 'recurring';
      setTimePickerValue(document.getElementById('editScheduleTimeStart').closest('[data-time-picker]'), schedule.time_start);
      setTimePickerValue(document.getElementById('editScheduleTimeEnd').closest('[data-time-picker]'), schedule.time_end);
      document.getElementById('editScheduleTruck').value = schedule.truck;
      document.getElementById('editScheduleStatus').value = schedule.status;
      const areaSelect = document.getElementById('editScheduleArea');
      areaSelect.querySelectorAll('[data-legacy-schedule-area]').forEach(option => option.remove());
      if (schedule.area && !Array.from(areaSelect.options).some(option => option.value === schedule.area)) {
        // Preserve a legacy schedule's stored area when editing without offering it for new schedules.
        const legacyOption = new Option(schedule.area, schedule.area, true, true);
        legacyOption.dataset.legacyScheduleArea = 'true';
        areaSelect.add(legacyOption);
      }
      areaSelect.value = schedule.area;
      
      // Handle schedule type fields
      toggleScheduleTypeFields('edit', schedule.schedule_type || 'recurring');
      
      if (schedule.schedule_type === 'specific_date') {
        document.getElementById('editScheduleSpecificDate').value = schedule.specific_date || '';
      } else {
        document.getElementById('editScheduleDays').value = schedule.days || '';
      }
      
      openModal('editScheduleModal');
    }
    
    function toggleScheduleTypeFields(prefix, scheduleType) {
      const recurringField = document.getElementById(`${prefix}RecurringDays`);
      const specificDateField = document.getElementById(`${prefix}SpecificDate`);
      const daysInput = document.getElementById(`${prefix}ScheduleDays`);
      const specificDateInput = document.getElementById(`${prefix}ScheduleSpecificDate`);
      
      if (scheduleType === 'specific_date') {
        recurringField.classList.add('hidden');
        specificDateField.classList.remove('hidden');
        if (daysInput) daysInput.removeAttribute('required');
        if (specificDateInput) specificDateInput.setAttribute('required', 'required');
      } else {
        recurringField.classList.remove('hidden');
        specificDateField.classList.add('hidden');
        if (daysInput) daysInput.setAttribute('required', 'required');
        if (specificDateInput) specificDateInput.removeAttribute('required');
      }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
      initializeTimePickers();
      const addScheduleType = document.getElementById('addScheduleType');
      const editScheduleType = document.getElementById('editScheduleType');
      
      if (addScheduleType) {
        addScheduleType.addEventListener('change', function() {
          toggleScheduleTypeFields('add', this.value);
        });
      }
      
      if (editScheduleType) {
        editScheduleType.addEventListener('change', function() {
          toggleScheduleTypeFields('edit', this.value);
        });
      }
    });

    function openDeleteScheduleModal(id) {
      currentScheduleId = id;
      const form = document.getElementById('deleteScheduleForm');
      form.action = `/admin/schedule/${id}`;
      openModal('deleteScheduleModal');
    }

    function getScheduleData(id) {
      const row = document.querySelector(`#scheduleTableBody tr[data-schedule*='"id":${id}']`);
      if (row) {
        return JSON.parse(row.dataset.schedule);
      }
      return null;
    }

    // Search functionality (now handled by controller, but keeping for client-side if needed)
    document.getElementById('scheduleSearchInput')?.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        this.closest('form')?.submit();
      }
    });
  </script>
@endpush
