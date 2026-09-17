@extends('layouts.admin')

@section('title', 'Service Zones')

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    .service-zone-map { height: 260px; border: 2px solid #e5e7eb; border-radius: .5rem; }
  </style>
@endpush

@section('content')
  @if(session('success'))
    <x-alert type="success" dismissible class="mb-4">{{ session('success') }}</x-alert>
  @endif
  @if(session('error'))
    <x-alert type="error" dismissible class="mb-4">{{ session('error') }}</x-alert>
  @endif

  <div class="admin-page-header flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <div>
      <h2 class="text-3xl font-bold text-gray-800"><i class="fas fa-map-marked-alt text-green-600 mr-3"></i>Service Zones</h2>
      <p class="text-gray-600 mt-1">Manage service-zone names and map locations.</p>
    </div>
    <button onclick="openServiceZoneCreate()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
      <i class="fas fa-plus-circle mr-2"></i>Add Service Zone
    </button>
  </div>

  <div class="admin-table-card bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="border-b border-gray-100 px-4 py-3.5 sm:px-5">
      <div class="admin-card-heading">
        <i class="fas fa-map-location-dot" aria-hidden="true"></i>
        <div>
          <h3>Service Zone Directory</h3>
          <p class="mt-0.5 text-xs text-gray-500">Configured zones and their mapped coordinates</p>
        </div>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead><tr class="admin-table-header">
          <th class="px-4 py-3 text-left text-sm font-semibold">Zone</th>
          <th class="px-4 py-3 text-left text-sm font-semibold">Barangay</th>
          <th class="px-4 py-3 text-left text-sm font-semibold">Coordinates</th>
          <th class="px-4 py-3 text-left text-sm font-semibold">Status</th>
          <th class="px-4 py-3 text-left text-sm font-semibold">Actions</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-200">
          @forelse($zones as $zone)
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-900">{{ $zone->name }}</td>
              <td class="px-4 py-3 text-gray-600">{{ $zone->barangay ?? '—' }}</td>
              <td class="px-4 py-3 text-gray-600">
                @if($zone->hasCoordinates())
                  {{ $zone->latitude }}, {{ $zone->longitude }}
                @else
                  <span class="text-gray-400">No location selected</span>
                @endif
              </td>
              <td class="px-4 py-3"><span class="admin-status-badge {{ $zone->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ ucfirst($zone->status) }}</span></td>
              <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                <button type="button"
                  data-zone-edit
                  data-zone-id="{{ $zone->id }}"
                  data-zone-name="{{ $zone->name }}"
                  data-zone-barangay="{{ $zone->barangay }}"
                  data-zone-status="{{ $zone->status }}"
                  data-zone-latitude="{{ $zone->latitude }}"
                  data-zone-longitude="{{ $zone->longitude }}"
                  class="admin-icon-button bg-blue-500 text-white hover:bg-blue-600" title="Edit"><i class="fas fa-edit text-xs"></i></button>
                <button type="button" data-zone-delete-id="{{ $zone->id }}" data-zone-delete-name="{{ $zone->name }}" class="admin-icon-button bg-red-500 text-white hover:bg-red-600" title="Delete"><i class="fas fa-trash text-xs"></i></button>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-map-marker-alt text-4xl mb-2 block"></i>No service zones found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection

@push('modals')
  <x-modal id="createServiceZoneModal" title="Add Service Zone" icon="fas fa-map-marker-alt" color="green">
    <form id="createServiceZoneForm" method="POST" action="{{ route('admin.service-zones.store') }}" class="space-y-4">
      @csrf
      <div>
        <label class="block text-gray-700 mb-2">Zone name</label>
        <input id="createServiceZoneName" name="name" value="{{ old('name', $suggestedZoneName) }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500" placeholder="e.g. Zone 16">
      </div>
      <div><label class="block text-gray-700 mb-2">Location / Barangay</label><input name="barangay" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500" placeholder="e.g. Barangay Example"></div>
      <div>
        <label class="block text-gray-700 mb-2">Location <span class="text-sm text-gray-500">(optional)</span></label>
        <div id="createServiceZoneMap" class="service-zone-map"></div>
        <p class="text-xs text-gray-500 mt-2"><i class="fas fa-info-circle mr-1"></i>Enter coordinates manually or click the map.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
          <div>
            <label for="createServiceZoneLatitude" class="block text-xs font-medium text-gray-600 mb-1">Latitude</label>
            <input name="latitude" id="createServiceZoneLatitude" type="number" step="any" min="-90" max="90" inputmode="decimal" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500" placeholder="9.76740151" aria-describedby="createServiceZoneCoordinateError">
          </div>
          <div>
            <label for="createServiceZoneLongitude" class="block text-xs font-medium text-gray-600 mb-1">Longitude</label>
            <input name="longitude" id="createServiceZoneLongitude" type="number" step="any" min="-180" max="180" inputmode="decimal" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500" placeholder="125.45395093" aria-describedby="createServiceZoneCoordinateError">
          </div>
        </div>
        <p id="createServiceZoneCoordinateError" class="hidden mt-2 text-xs font-medium text-red-600" role="alert"></p>
      </div>
      <div><label class="block text-gray-700 mb-2">Status</label><select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    </form>
    @slot('footer')<div class="flex justify-end space-x-3"><button onclick="closeModal('createServiceZoneModal')" class="px-4 py-2 border rounded-lg">Cancel</button><button type="submit" form="createServiceZoneForm" class="px-4 py-2 bg-green-600 text-white rounded-lg">Save Zone</button></div>@endslot
  </x-modal>

  <x-modal id="editServiceZoneModal" title="Edit Service Zone" icon="fas fa-map-marker-alt" color="blue">
    <form id="editServiceZoneForm" method="POST" class="space-y-4">
      @csrf @method('PUT')
      <div><label class="block text-gray-700 mb-2">Zone name</label><input id="editServiceZoneName" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></div>
      <div><label class="block text-gray-700 mb-2">Location / Barangay</label><input id="editServiceZoneBarangay" name="barangay" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></div>
      <div>
        <label class="block text-gray-700 mb-2">Location <span class="text-sm text-gray-500">(optional)</span></label>
        <div id="editServiceZoneMap" class="service-zone-map"></div>
        <p class="text-xs text-gray-500 mt-2"><i class="fas fa-info-circle mr-1"></i>Enter coordinates manually or click the map.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
          <div>
            <label for="editServiceZoneLatitude" class="block text-xs font-medium text-gray-600 mb-1">Latitude</label>
            <input name="latitude" id="editServiceZoneLatitude" type="number" step="any" min="-90" max="90" inputmode="decimal" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="9.76740151" aria-describedby="editServiceZoneCoordinateError">
          </div>
          <div>
            <label for="editServiceZoneLongitude" class="block text-xs font-medium text-gray-600 mb-1">Longitude</label>
            <input name="longitude" id="editServiceZoneLongitude" type="number" step="any" min="-180" max="180" inputmode="decimal" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="125.45395093" aria-describedby="editServiceZoneCoordinateError">
          </div>
        </div>
        <p id="editServiceZoneCoordinateError" class="hidden mt-2 text-xs font-medium text-red-600" role="alert"></p>
      </div>
      <div><label class="block text-gray-700 mb-2">Status</label><select id="editServiceZoneStatus" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    </form>
    @slot('footer')<div class="flex justify-end space-x-3"><button onclick="closeModal('editServiceZoneModal')" class="px-4 py-2 border rounded-lg">Cancel</button><button type="submit" form="editServiceZoneForm" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Save Changes</button></div>@endslot
  </x-modal>

  <x-modal id="deleteServiceZoneModal" title="Delete Service Zone" icon="fas fa-trash-alt" color="red" variant="confirmation">
    <p>Delete <strong id="deleteServiceZoneName" class="font-semibold text-gray-800"></strong>? Existing schedules are not changed.</p>
    <div class="admin-confirm-warning">
      <i class="fas fa-exclamation-circle mt-0.5" aria-hidden="true"></i>
      <p>This action permanently removes the service zone.</p>
    </div>
    <form id="deleteServiceZoneForm" method="POST">@csrf @method('DELETE')</form>
    @slot('footer')
      <div class="admin-confirm-actions">
        <button type="button" onclick="closeModal('deleteServiceZoneModal')" class="admin-btn-secondary">Cancel</button>
        <button type="submit" form="deleteServiceZoneForm" class="admin-btn-danger"><i class="fas fa-trash mr-2"></i>Delete Zone</button>
      </div>
    @endslot
  </x-modal>
@endpush

@push('scripts')
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    // Mirrors the Truck Management picker: Leaflet click -> marker move -> latitude/longitude fields.
    const zonePickerDefaultCenter = [9.7870, 125.4928];
    const zonePickers = {};
    const serviceZoneBaseUrl = @json(url('/admin/service-zones'));

    function getZonePicker(key) {
      if (zonePickers[key]) return zonePickers[key];
      const latInput = document.getElementById(`${key}ServiceZoneLatitude`);
      const lngInput = document.getElementById(`${key}ServiceZoneLongitude`);
      const coordinateError = document.getElementById(`${key}ServiceZoneCoordinateError`);
      const form = document.getElementById(`${key}ServiceZoneForm`);
      const map = L.map(`${key}ServiceZoneMap`).setView(zonePickerDefaultCenter, 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
      let marker = null;

      const setCoordinateError = (message = '', field = null) => {
        coordinateError.textContent = message;
        coordinateError.classList.toggle('hidden', message === '');
        latInput.setCustomValidity(field === 'latitude' || field === 'pair' ? message : '');
        lngInput.setCustomValidity(field === 'longitude' || field === 'pair' ? message : '');
      };

      const writeCoordinates = (lat, lng) => {
        latInput.value = Number(lat).toFixed(8);
        lngInput.value = Number(lng).toFixed(8);
        setCoordinateError();
      };

      const updateMarkerPopup = (lat, lng) => {
        marker.bindPopup(`Selected Location<br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`);
      };

      const setLocation = (lat, lng, center = true) => {
        lat = Number(lat); lng = Number(lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) return false;
        writeCoordinates(lat, lng);
        if (marker) marker.setLatLng([lat, lng]);
        else {
          marker = L.marker([lat, lng], { draggable: true }).addTo(map);
          marker.on('dragend', event => {
            const position = event.target.getLatLng();
            writeCoordinates(position.lat, position.lng);
            updateMarkerPopup(position.lat, position.lng);
          });
        }
        updateMarkerPopup(lat, lng);
        if (center) map.setView([lat, lng], 15);
        return true;
      };

      const removeMarker = () => {
        if (!marker) return;
        map.removeLayer(marker);
        marker = null;
      };

      const syncInputsToMap = (showIncompleteError = false) => {
        const latitude = latInput.value.trim();
        const longitude = lngInput.value.trim();
        const hasLatitude = latitude !== '';
        const hasLongitude = longitude !== '';

        if (latInput.validity.badInput) {
          setCoordinateError('Latitude must be a number between -90 and 90.', 'latitude');
          return false;
        }

        if (lngInput.validity.badInput) {
          setCoordinateError('Longitude must be a number between -180 and 180.', 'longitude');
          return false;
        }

        if (!hasLatitude && !hasLongitude) {
          setCoordinateError();
          removeMarker();
          return true;
        }

        if (!hasLatitude || !hasLongitude) {
          if (showIncompleteError) setCoordinateError('Enter both latitude and longitude, or leave both blank.', 'pair');
          else setCoordinateError();
          return false;
        }

        const lat = Number(latitude);
        const lng = Number(longitude);

        if (!Number.isFinite(lat) || lat < -90 || lat > 90) {
          setCoordinateError('Latitude must be a number between -90 and 90.', 'latitude');
          return false;
        }

        if (!Number.isFinite(lng) || lng < -180 || lng > 180) {
          setCoordinateError('Longitude must be a number between -180 and 180.', 'longitude');
          return false;
        }

        setCoordinateError();
        return setLocation(lat, lng);
      };

      [latInput, lngInput].forEach(input => {
        input.addEventListener('change', () => syncInputsToMap(false));
        input.addEventListener('blur', () => syncInputsToMap(false));
      });

      form.addEventListener('submit', event => {
        if (syncInputsToMap(true)) return;
        event.preventDefault();
        (latInput.validationMessage ? latInput : lngInput).reportValidity();
      });

      map.on('click', event => setLocation(event.latlng.lat, event.latlng.lng, false));
      return zonePickers[key] = {
        map,
        setLocation,
        syncInputsToMap,
        clear() {
          latInput.value = '';
          lngInput.value = '';
          setCoordinateError();
          removeMarker();
        }
      };
    }

    window.openServiceZoneCreate = function() {
      document.getElementById('createServiceZoneForm').reset();
      const picker = getZonePicker('create'); picker.clear();
      openModal('createServiceZoneModal');
      setTimeout(() => picker.map.invalidateSize(), 150);
    };

    window.openServiceZoneEdit = function(zone) {
      const form = document.getElementById('editServiceZoneForm');
      form.action = `${serviceZoneBaseUrl}/${zone.id}`;
      document.getElementById('editServiceZoneName').value = zone.name;
      document.getElementById('editServiceZoneBarangay').value = zone.barangay || '';
      document.getElementById('editServiceZoneStatus').value = zone.status || 'active';

      // The shared modal component reveals this existing DOM element by removing `hidden`.
      // Open it before Leaflet initializes so the map has a visible container to measure.
      openModal('editServiceZoneModal');
      const picker = getZonePicker('edit'); picker.clear();
      if (zone.latitude !== null && zone.longitude !== null) picker.setLocation(zone.latitude, zone.longitude);
      setTimeout(() => picker.map.invalidateSize(), 150);
    };

    window.openServiceZoneDelete = function(id, name) {
      document.getElementById('deleteServiceZoneName').textContent = name;
      document.getElementById('deleteServiceZoneForm').action = `${serviceZoneBaseUrl}/${id}`;
      openModal('deleteServiceZoneModal');
    };

    function bindServiceZoneDeleteButtons() {
      document.querySelectorAll('[data-zone-delete-id]').forEach(button => {
        if (button.dataset.deleteBound) return;
        button.dataset.deleteBound = 'true';
        button.addEventListener('click', () => window.openServiceZoneDelete(button.dataset.zoneDeleteId, button.dataset.zoneDeleteName));
      });
    }

    // Delegation keeps Edit functional even when its table rows are rendered or refreshed later.
    document.addEventListener('click', event => {
      const button = event.target.closest('[data-zone-edit]');
      if (!button) return;

      const latitude = button.dataset.zoneLatitude;
      const longitude = button.dataset.zoneLongitude;
      window.openServiceZoneEdit({
        id: button.dataset.zoneId,
        name: button.dataset.zoneName,
        barangay: button.dataset.zoneBarangay,
        status: button.dataset.zoneStatus,
        latitude: latitude === '' ? null : Number(latitude),
        longitude: longitude === '' ? null : Number(longitude),
      });
    });

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', bindServiceZoneDeleteButtons, { once: true });
    } else {
      bindServiceZoneDeleteButtons();
    }
  </script>
@endpush
