@extends('layouts.admin')

@section('title', 'Tracker')

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    #adminTruckMap {
      height: 400px;
      border-radius: 10px;
      z-index: 1;
    }
    #addTruckMap {
      height: 250px !important;
      width: 100% !important;
      min-height: 250px;
    }
    #addTruckMap .leaflet-container {
      height: 100% !important;
      width: 100% !important;
    }
  </style>
@endpush

@section('content')
  @if(session('success'))
    <x-alert type="success" dismissible class="mb-4">
      {{ session('success') }}
    </x-alert>
  @endif

  <div class="mb-6">
    <h2 class="text-3xl font-bold text-gray-800">
      <i class="fas fa-truck text-green-600 mr-3"></i>Garbage Truck Tracker 🚛
    </h2>
    <p class="text-gray-600 mt-1">Monitor garbage truck locations and routes in real-time</p>
  </div>

  <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
      <div class="flex items-center">
        <i class="fas fa-map-marked-alt text-green-600 text-xl mr-3"></i>
        <h3 class="text-xl font-semibold text-gray-800">Live Tracker</h3>
      </div>
      <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
        <select id="truckSelector" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
          <option value="">Select a truck to update...</option>
          @foreach($trucks as $truck)
            <option value="{{ $truck->id }}" data-lat="{{ $truck->latitude }}" data-lng="{{ $truck->longitude }}">
              {{ $truck->code }} - {{ $truck->driver }}
            </option>
          @endforeach
        </select>
        <button onclick="openUpdateLocationModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300 whitespace-nowrap">
          <i class="fas fa-location-arrow mr-2"></i>Update Location
        </button>
        <button onclick="refreshMap()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-300 whitespace-nowrap">
          <i class="fas fa-sync-alt mr-2"></i>Refresh Map
        </button>
        <div class="flex items-center gap-2 px-4 py-2 bg-green-50 border border-green-200 rounded-lg">
          <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse" id="autoRefreshIndicator"></div>
          <span class="text-sm text-gray-600">Auto-refresh: <span id="refreshCountdown">30</span>s</span>
        </div>
      </div>
    </div>
    <div id="adminTruckMap"></div>
    <p class="text-gray-500 mt-4 text-sm">
      <i class="fas fa-info-circle mr-2"></i>
      Click on the map to set a new location, or use the "Update Location" button to manually enter coordinates.
    </p>
  </div>


  <div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="p-4 flex justify-between items-center">
      <div class="flex items-center">
        <i class="fas fa-truck text-green-600 text-xl mr-3"></i>
        <h3 class="text-xl font-semibold text-gray-800">Active Garbage Trucks</h3>
      </div>
      <button onclick="openAddTruckModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
        <i class="fas fa-plus-circle mr-2"></i>Add Truck
      </button>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-green-600 text-white">
            @foreach (['#','Truck ID','Driver','Route','Status','Last Updated','Actions'] as $heading)
              <th class="px-4 py-3 text-left text-sm font-semibold">{{ $heading }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200" id="truckTableBody">
          @forelse ($trucks as $truck)
            <tr class="hover:bg-gray-50 transition-colors duration-200" data-truck="{{ json_encode([
              'id' => $truck->id,
              'code' => $truck->code,
              'driver' => $truck->driver,
              'route' => $truck->route,
              'status' => $truck->status,
              'latitude' => $truck->latitude,
              'longitude' => $truck->longitude,
              'last_updated' => $truck->last_updated ? $truck->last_updated->format('M d, Y H:i') : null,
              'last_updated_iso' => $truck->last_updated ? $truck->last_updated->toIso8601String() : null,
            ]) }}">
              <td class="px-4 py-3">{{ $truck->id }}</td>
              <td class="px-4 py-3 font-medium text-gray-900">{{ $truck->code }}</td>
              <td class="px-4 py-3 text-gray-600">{{ $truck->driver }}</td>
              <td class="px-4 py-3 text-gray-600">{{ $truck->route }}</td>
              <td class="px-4 py-3">
                <span class="{{ $truck->getStatusBadgeClass() }} text-xs font-medium px-2.5 py-0.5 rounded-full">
                  <i class="fas fa-circle text-xs mr-1"></i>{{ $truck->formatted_status }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-600">
                <span class="last-updated" data-timestamp="{{ $truck->last_updated ? $truck->last_updated->toIso8601String() : '' }}" data-truck-id="{{ $truck->id }}">
                  {{ $truck->last_updated_human }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex space-x-2">
                  <button onclick="openTruckView({{ $truck->id }})" class="w-8 h-8 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors duration-300" title="View">
                    <i class="fas fa-eye text-xs"></i>
                  </button>
                  <button onclick="editTruck({{ $truck->id }})" class="w-8 h-8 bg-yellow-500 text-white rounded hover:bg-yellow-600 transition-colors duration-300" title="Edit">
                    <i class="fas fa-edit text-xs"></i>
                  </button>
                  <button onclick="openTruckDelete({{ $truck->id }})" class="w-8 h-8 bg-red-500 text-white rounded hover:bg-red-600 transition-colors duration-300" title="Delete">
                    <i class="fas fa-trash text-xs"></i>
                  </button>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                <i class="fas fa-truck text-4xl mb-2 block"></i>
                No trucks found
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection

@push('modals')
  <x-modal id="viewTruckModal" title="Truck Details" icon="fas fa-truck" color="green">
    <div class="flex flex-col md:flex-row items-center md:items-start gap-6 mb-6">
      <div class="flex-shrink-0">
        <div class="w-32 h-32 bg-blue-100 rounded-full flex items-center justify-center">
          <i class="fas fa-truck text-blue-600 text-4xl"></i>
        </div>
      </div>
      <div class="flex-1 text-center md:text-left">
        <h3 id="viewTruckCode" class="text-2xl font-bold text-gray-800 mb-2"></h3>
        <div class="flex flex-wrap gap-2 justify-center md:justify-start mb-4" id="viewTruckBadges"></div>
        <p class="text-gray-600">
          <i class="fas fa-user mr-2 text-blue-600"></i><span id="viewTruckDriver"></span>
        </p>
      </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
      <div class="bg-gray-50 rounded-lg p-4" id="viewTruckInfo"></div>
      <div class="bg-gray-50 rounded-lg p-4" id="viewTruckRoute"></div>
    </div>
    <div class="bg-gray-50 rounded-lg p-4" id="viewTruckStats"></div>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('viewTruckModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Close
        </button>
        <button onclick="editTruckFromView()" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors duration-300">
          <i class="fas fa-edit mr-2"></i>Edit Truck
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="addTruckModal" title="Add New Truck" icon="fas fa-plus-circle" color="green">
    <form id="addTruckForm" method="POST" action="{{ route('admin.tracker.store') }}" class="space-y-4">
      @csrf
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-id-card mr-2 text-green-600"></i>Truck ID</label>
        <input type="text" name="code" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="TRK-001" required>
        @error('code', 'storeTruck')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-user mr-2 text-green-600"></i>Driver Name</label>
        <input type="text" name="driver" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Juan Dela Cruz" required>
        @error('driver', 'storeTruck')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-route mr-2 text-green-600"></i>Route</label>
        <select name="route" id="addTruckRoute" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
          @foreach ($routes ?? [] as $route)
            <option value="{{ $route }}">{{ $route }}</option>
          @endforeach
        </select>
        @error('route', 'storeTruck')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-circle mr-2 text-green-600"></i>Status</label>
        <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" required>
          @foreach (['active','on_break','offline','maintenance'] as $status)
            <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
          @endforeach
        </select>
        @error('status', 'storeTruck')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-map-marker-alt mr-2 text-green-600"></i>Initial Location (Optional)</label>
        <div class="mb-3">
          <div id="addTruckMap" style="height: 250px; width: 100%; border-radius: 8px; border: 2px solid #e5e7eb; background-color: #f3f4f6;"></div>
          <p class="text-xs text-gray-500 mt-2">
            <i class="fas fa-info-circle mr-1"></i>Click on the map to set the location automatically
          </p>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <input type="number" step="any" name="latitude" id="addTruckLatitude" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Latitude">
          <input type="number" step="any" name="longitude" id="addTruckLongitude" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Longitude">
        </div>
        <p class="text-xs text-gray-500 mt-1">Or manually enter coordinates</p>
      </div>
    </form>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('addTruckModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Cancel
        </button>
        <button type="submit" form="addTruckForm" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
          <i class="fas fa-save mr-2"></i>Save Truck
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="editTruckModal" title="Edit Truck" icon="fas fa-edit" color="yellow">
    <form id="editTruckForm" method="POST" class="space-y-4">
      @csrf
      @method('PUT')
      <input type="hidden" name="id" id="editTruckId">
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-id-card mr-2 text-yellow-600"></i>Truck ID</label>
        <input type="text" name="code" id="editTruckCode" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required>
        @error('code', 'updateTruck')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-user mr-2 text-yellow-600"></i>Driver Name</label>
        <input type="text" name="driver" id="editTruckDriver" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required>
        @error('driver', 'updateTruck')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-route mr-2 text-yellow-600"></i>Route</label>
        <select name="route" id="editTruckRoute" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required>
          @foreach ($routes ?? [] as $route)
            <option value="{{ $route }}">{{ $route }}</option>
          @endforeach
        </select>
        @error('route', 'updateTruck')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-circle mr-2 text-yellow-600"></i>Status</label>
        <select name="status" id="editTruckStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" required>
          @foreach (['active','on_break','offline','maintenance'] as $status)
            <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
          @endforeach
        </select>
        @error('status', 'updateTruck')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>
      <div>
        <label class="block text-gray-700 mb-2"><i class="fas fa-map-marker-alt mr-2 text-yellow-600"></i>Location (Optional)</label>
        <div class="grid grid-cols-2 gap-2">
          <input type="number" step="any" name="latitude" id="editTruckLatitude" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" placeholder="Latitude">
          <input type="number" step="any" name="longitude" id="editTruckLongitude" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" placeholder="Longitude">
        </div>
        <p class="text-xs text-gray-500 mt-1">Update location coordinates</p>
      </div>
    </form>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('editTruckModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Cancel
        </button>
        <button type="submit" form="editTruckForm" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors duration-300">
          <i class="fas fa-save mr-2"></i>Save Changes
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="updateLocationModal" title="Update Truck Location" icon="fas fa-map-marker-alt" color="green">
    <form id="updateLocationForm" method="POST" class="space-y-4">
      @csrf
      <div>
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-truck mr-2 text-green-600"></i>Selected Truck
        </label>
        <input type="text" id="updateLocationTruckDisplay" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" readonly>
        <input type="hidden" id="updateLocationTruckId" value="">
      </div>
      <div>
        <label class="block text-gray-700 mb-2">
          <i class="fas fa-map-marker-alt mr-2 text-green-600"></i>Location Coordinates
        </label>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <input type="number" step="any" name="latitude" id="updateLocationLatitude" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Latitude" required>
            <p class="text-xs text-gray-500 mt-1">Click on map to set</p>
          </div>
          <div>
            <input type="number" step="any" name="longitude" id="updateLocationLongitude" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Longitude" required>
            <p class="text-xs text-gray-500 mt-1">Click on map to set</p>
          </div>
        </div>
      </div>
      <div class="bg-blue-50 border-l-4 border-blue-400 p-4 flex">
        <i class="fas fa-info-circle text-blue-400 mr-3 mt-1"></i>
        <p class="text-sm text-blue-700">
          Click on the map above to automatically set coordinates, or manually enter them in the fields above.
        </p>
      </div>
    </form>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('updateLocationModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Cancel
        </button>
        <button type="submit" form="updateLocationForm" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-300">
          <i class="fas fa-save mr-2"></i>Update Location
        </button>
      </div>
    @endslot
  </x-modal>

  <x-modal id="deleteTruckModal" title="Delete Truck" icon="fas fa-exclamation-triangle" color="red">
    <div class="text-center">
      <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-exclamation text-red-600 text-2xl"></i>
      </div>
      <h4 class="text-xl font-semibold text-gray-800 mb-2">Confirm Deletion</h4>
      <p class="text-gray-600 mb-4">Are you sure you want to delete this truck? This action cannot be undone.</p>
      <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4 text-left flex">
        <i class="fas fa-exclamation-circle text-yellow-400 mr-3 mt-1"></i>
        <p class="text-sm text-yellow-700">
          This will permanently remove the truck from the system and all associated data.
        </p>
      </div>
    </div>
    @slot('footer')
      <div class="flex justify-end space-x-3">
        <button onclick="closeModal('deleteTruckModal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-300">
          <i class="fas fa-times mr-2"></i>Cancel
        </button>
        <form id="deleteTruckForm" method="POST" class="inline">
          @csrf
          @method('DELETE')
          <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-300">
            <i class="fas fa-trash mr-2"></i>Delete Truck
          </button>
        </form>
      </div>
    @endslot
  </x-modal>
@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
@endpush

@push('scripts')
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
  <script>
    const trackerDataUrl = '/admin/tracker/data';
    const serviceZoneCoordinates = @json($serviceZoneCoordinates ?? []);
    let truckData = @json($trucks);
    let currentTruckId = null;
    let map = null;
    let truckMarkers = [];
    let markerCluster = null;
    let routePolylines = {}; // Store route polylines by truck ID
    let autoRefreshInterval = null;
    let countdownInterval = null;
    let refreshCountdown = 30;

    let clickMarker = null;
    let selectedTruckMarker = null;

    // Initialize map
    if (document.getElementById('adminTruckMap')) {
      // Calculate center and bounds from truck locations
      const trucksWithLocation = truckData.filter(t => t.latitude && t.longitude);
      let mapCenter = [9.7870, 125.4928]; // Default: Surigao City coordinates
      let mapZoom = 13;
      
      if (trucksWithLocation.length > 0) {
        // Calculate bounds to fit all trucks
        const lats = trucksWithLocation.map(t => parseFloat(t.latitude));
        const lngs = trucksWithLocation.map(t => parseFloat(t.longitude));
        const minLat = Math.min(...lats);
        const maxLat = Math.max(...lats);
        const minLng = Math.min(...lngs);
        const maxLng = Math.max(...lngs);
        
        // Center point
        mapCenter = [(minLat + maxLat) / 2, (minLng + maxLng) / 2];
        
        // Calculate appropriate zoom (with padding)
        const latDiff = maxLat - minLat;
        const lngDiff = maxLng - minLng;
        const maxDiff = Math.max(latDiff, lngDiff);
        
        if (maxDiff > 0.1) mapZoom = 11;
        else if (maxDiff > 0.05) mapZoom = 12;
        else if (maxDiff > 0.01) mapZoom = 13;
        else mapZoom = 14;
      }
      
      map = L.map('adminTruckMap').setView(mapCenter, mapZoom);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
        minZoom: 3
      }).addTo(map);
      
      // Initialize marker cluster group
      markerCluster = L.markerClusterGroup({
        chunkedLoading: true,
        maxClusterRadius: 50
      });
    }
    
    // Function to add/update markers (defined outside if block so it can be called from fetchTruckData)
    function updateMarkers(trucks) {
      if (!map || !markerCluster) {
        return; // Map not initialized yet
      }
      
      // Clear existing markers
      markerCluster.clearLayers();
      truckMarkers = [];
      
      const bounds = [];
      trucks.forEach(truck => {
        if (truck.latitude && truck.longitude) {
          const lat = parseFloat(truck.latitude);
          const lng = parseFloat(truck.longitude);
          
          // Validate coordinates
          if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            return;
          }
          
          // Different icon colors based on status
          const statusColors = {
            'active': 'green',
            'on_break': 'yellow',
            'offline': 'red',
            'maintenance': 'blue',
          };
          
          const color = statusColors[truck.status] || 'gray';
          const truckIcon = L.icon({
            iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-${color}.png`,
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [0, -41],
          });
          
          const statusLabels = {
            'active': 'Active',
            'on_break': 'On Break',
            'offline': 'Offline',
            'maintenance': 'Maintenance',
          };
          
          const lastUpdated = truck.last_updated ? new Date(truck.last_updated).toLocaleString() : 'Never';
          const lastUpdatedIso = truck.last_updated_iso || truck.last_updated || '';
          const marker = L.marker([lat, lng], { icon: truckIcon })
            .bindPopup(`
              <div class="text-center">
                <b>${truck.code}</b><br>
                Driver: ${truck.driver}<br>
                Route: ${truck.route}<br>
                Status: <span class="font-semibold">${statusLabels[truck.status] || truck.status}</span><br>
                <small>Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}</small><br>
                <small>Last updated: <span class="last-updated-popup" data-timestamp="${lastUpdatedIso}">${lastUpdated}</span></small><br>
                <button onclick="openTruckView(${truck.id})" class="mt-2 px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
                  View Details
                </button>
                <button onclick="showRouteHistory(${truck.id})" class="mt-2 px-3 py-1 bg-green-500 text-white rounded text-sm hover:bg-green-600">
                  Show Route
                </button>
              </div>
            `);
          
          marker.truckId = truck.id;
          markerCluster.addLayer(marker);
          truckMarkers.push(marker);
          bounds.push([lat, lng]);
        }
      });
      
      // Add cluster group to map
      if (markerCluster && bounds.length > 0) {
        map.addLayer(markerCluster);
      }
      
      // Fit map to show all trucks if there are multiple (only on initial load)
      if (bounds.length > 1 && !map._initialBoundsSet) {
        map.fitBounds(bounds, { padding: [50, 50] });
        map._initialBoundsSet = true;
      } else if (bounds.length === 1 && !map._initialBoundsSet) {
        map.setView(bounds[0], 15);
        map._initialBoundsSet = true;
      }
    }
    
    // Initialize markers if map exists
    if (document.getElementById('adminTruckMap') && map) {
      // Initial marker creation
      updateMarkers(truckData);
      
      // Add legend
      if (truckMarkers.length > 0) {
        const legend = L.control({ position: 'bottomright' });
        legend.onAdd = function() {
          const div = L.DomUtil.create('div', 'bg-white p-3 rounded shadow-lg border border-gray-200');
          div.style.cssText = 'background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);';
          div.innerHTML = `
            <h4 class="font-semibold mb-2 text-sm text-gray-800">Truck Status</h4>
            <div class="space-y-1 text-xs">
              <div class="flex items-center"><img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png" class="w-4 h-6 mr-2">Active</div>
              <div class="flex items-center"><img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png" class="w-4 h-6 mr-2">On Break</div>
              <div class="flex items-center"><img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png" class="w-4 h-6 mr-2">Offline</div>
              <div class="flex items-center"><img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png" class="w-4 h-6 mr-2">Maintenance</div>
            </div>
          `;
          return div;
        };
        legend.addTo(map);
      }

      // Map click handler to set location
      map.on('click', function(e) {
        const lat = parseFloat(e.latlng.lat.toFixed(8));
        const lng = parseFloat(e.latlng.lng.toFixed(8));
        
        // Validate coordinates
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
          if (typeof showToast === 'function') {
            showToast('error', 'Invalid coordinates. Please click on a valid location on the map.');
          }
          return;
        }
        
        // Check if add truck modal is open
        const addTruckModal = document.getElementById('addTruckModal');
        if (addTruckModal && !addTruckModal.classList.contains('hidden')) {
          const latInput = document.getElementById('addTruckLatitude');
          const lngInput = document.getElementById('addTruckLongitude');
          if (latInput && lngInput) {
            latInput.value = lat.toFixed(8);
            lngInput.value = lng.toFixed(8);
            // Update marker on add truck map if it exists
            if (addTruckMapMarker) {
              addTruckMapMarker.setLatLng([lat, lng]);
              addTruckMapMarker.setPopupContent(`Selected Location<br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`).openPopup();
            }
          }
          return;
        }
        
        // Update form fields if update location modal is open
        const latInput = document.getElementById('updateLocationLatitude');
        const lngInput = document.getElementById('updateLocationLongitude');
        if (latInput && lngInput) {
          latInput.value = lat.toFixed(8);
          lngInput.value = lng.toFixed(8);
        }
        
        // Add/update click marker
        if (clickMarker) {
          clickMarker.setLatLng([lat, lng]);
          clickMarker.setPopupContent(`Selected Location<br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`).openPopup();
        } else {
          clickMarker = L.marker([lat, lng], {
            icon: L.icon({
              iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
              iconSize: [25, 41],
              iconAnchor: [12, 41],
            })
          }).addTo(map).bindPopup(`Selected Location<br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`).openPopup();
        }
      });
    }
    
    // Initialize map for Add Truck modal
    let addTruckMap = null;
    let addTruckMapMarker = null;
    let pendingServiceZoneLocation = null;

    function applyServiceZoneLocationToTruckMap() {
      if (!pendingServiceZoneLocation || !addTruckMap || typeof L === 'undefined') return;

      const { lat, lng } = pendingServiceZoneLocation;
      addTruckMap.setView([lat, lng], 15);
      // Reuse the existing Truck-map click handler to set its marker and coordinate fields.
      addTruckMap.fire('click', { latlng: L.latLng(lat, lng) });
    }
    
    function initializeAddTruckMap() {
      const mapElement = document.getElementById('addTruckMap');
      if (!mapElement) {
        return false;
      }
      
      // Check if element is visible
      const modal = document.getElementById('addTruckModal');
      if (modal && modal.classList.contains('hidden')) {
        return false;
      }
      
      // Check if Leaflet is loaded
      if (typeof L === 'undefined') {
        return false;
      }
      
      // If map already exists, just invalidate size and return
      if (addTruckMap) {
        setTimeout(function() {
          if (addTruckMap) {
            addTruckMap.invalidateSize();
          }
        }, 100);
        return true;
      }
      
      try {
        // Ensure element has dimensions (check both offset and computed style)
        const computedStyle = window.getComputedStyle(mapElement);
        const width = mapElement.offsetWidth || parseInt(computedStyle.width) || 0;
        const height = mapElement.offsetHeight || parseInt(computedStyle.height) || 0;
        
        if (width === 0 || height === 0) {
          // Force dimensions if they're 0
          if (width === 0) mapElement.style.width = '100%';
          if (height === 0) mapElement.style.height = '250px';
          setTimeout(initializeAddTruckMap, 300);
          return false;
        }
        
        // Clear any existing content
        mapElement.innerHTML = '';
        
        // Initialize map centered on default location
        addTruckMap = L.map('addTruckMap', {
          zoomControl: true
        }).setView([9.7870, 125.4928], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors',
          maxZoom: 19,
          minZoom: 3
        }).addTo(addTruckMap);
        
        // Invalidate size multiple times to ensure proper rendering
        setTimeout(function() {
          if (addTruckMap) {
            addTruckMap.invalidateSize();
          }
        }, 100);
        
        setTimeout(function() {
          if (addTruckMap) {
            addTruckMap.invalidateSize();
          }
        }, 300);
        
        setTimeout(function() {
          if (addTruckMap) {
            addTruckMap.invalidateSize();
          }
        }, 500);
        
        // Add click handler to set location
        addTruckMap.on('click', function(e) {
          const lat = parseFloat(e.latlng.lat.toFixed(8));
          const lng = parseFloat(e.latlng.lng.toFixed(8));
          
          // Validate coordinates
          if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            return;
          }
          
          // Update form fields
          const latInput = document.getElementById('addTruckLatitude');
          const lngInput = document.getElementById('addTruckLongitude');
          if (latInput && lngInput) {
            latInput.value = lat.toFixed(8);
            lngInput.value = lng.toFixed(8);
          }
          
          // Add/update marker
          if (addTruckMapMarker) {
            addTruckMapMarker.setLatLng([lat, lng]);
            addTruckMapMarker.setPopupContent(`Selected Location<br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`).openPopup();
          } else {
            addTruckMapMarker = L.marker([lat, lng], {
              icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
              })
            }).addTo(addTruckMap).bindPopup(`Selected Location<br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`).openPopup();
          }
        });
        applyServiceZoneLocationToTruckMap();
        
        return true;
      } catch (error) {
        return false;
      }
    }
    
    // Function to open Add Truck modal and initialize map
    function openAddTruckModal() {
      // Open the modal first
      if (typeof window.openModal === 'function') {
        window.openModal('addTruckModal');
      } else {
        const modal = document.getElementById('addTruckModal');
        if (modal) {
          modal.classList.remove('hidden');
        }
      }
      
      // Clear lat/lng fields first
      const latInput = document.getElementById('addTruckLatitude');
      const lngInput = document.getElementById('addTruckLongitude');
      if (latInput) latInput.value = '';
      if (lngInput) lngInput.value = '';
      
      // Initialize map after modal is visible
      setTimeout(function() {
        if (!addTruckMap) {
          const success = initializeAddTruckMap();
          if (!success) {
            setTimeout(function() {
              initializeAddTruckMap();
            }, 500);
          }
        } else {
          addTruckMap.invalidateSize();
        }
      }, 500);
    }
    
    function attachRouteChangeListeners() {
      const addTruckRouteSelect = document.getElementById('addTruckRoute');
      if (!addTruckRouteSelect || addTruckRouteSelect.dataset.serviceZoneLocationBound) return;

      addTruckRouteSelect.dataset.serviceZoneLocationBound = 'true';
      addTruckRouteSelect.addEventListener('change', function() {
        const coordinates = serviceZoneCoordinates[this.value];
        if (!coordinates) return;

        const lat = Number(coordinates.lat);
        const lng = Number(coordinates.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        pendingServiceZoneLocation = { lat, lng };
        applyServiceZoneLocationToTruckMap();
      });
    }
    
    // Listen for route selection and manual coordinate input
    document.addEventListener('DOMContentLoaded', function() {
      // Attach listeners on page load
      attachRouteChangeListeners();
      
      // Also listen for manual coordinate input to update map
      const latInput = document.getElementById('addTruckLatitude');
      const lngInput = document.getElementById('addTruckLongitude');
      if (latInput && lngInput) {
        latInput.addEventListener('change', function() {
          if (addTruckMap && this.value && lngInput.value) {
            const lat = parseFloat(this.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
              addTruckMap.setView([lat, lng], 15);
              if (addTruckMapMarker) {
                addTruckMapMarker.setLatLng([lat, lng]);
              } else {
                addTruckMapMarker = L.marker([lat, lng], {
                  icon: L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                  })
                }).addTo(addTruckMap).bindPopup(`Selected Location<br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`);
              }
            }
          }
        });
        lngInput.addEventListener('change', function() {
          if (addTruckMap && latInput.value && this.value) {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(this.value);
            if (!isNaN(lat) && !isNaN(lng)) {
              addTruckMap.setView([lat, lng], 15);
              if (addTruckMapMarker) {
                addTruckMapMarker.setLatLng([lat, lng]);
              } else {
                addTruckMapMarker = L.marker([lat, lng], {
                  icon: L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                  })
                }).addTo(addTruckMap).bindPopup(`Selected Location<br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`);
              }
            }
          }
        });
      }
    });

    // Truck selector change handler
    document.getElementById('truckSelector')?.addEventListener('change', function() {
      const selectedId = this.value;
      if (!selectedId) {
        if (selectedTruckMarker) {
          map.removeLayer(selectedTruckMarker);
          selectedTruckMarker = null;
        }
        return;
      }
      
      const option = this.options[this.selectedIndex];
      const lat = parseFloat(option.dataset.lat);
      const lng = parseFloat(option.dataset.lng);
      
      // Validate coordinates
      if (lat && lng && !isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        // Remove previous selection marker
        if (selectedTruckMarker) {
          map.removeLayer(selectedTruckMarker);
        }
        
        // Add selection marker
        selectedTruckMarker = L.marker([lat, lng], {
          icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
          })
        }).addTo(map).bindPopup(`Selected Truck Location<br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`).openPopup();
        
        map.setView([lat, lng], 15);
      }
    });

    function openUpdateLocationModal() {
      const selectedTruckId = document.getElementById('truckSelector').value;
      if (!selectedTruckId) {
        if (typeof showToast === 'function') {
          showToast('warning', 'Please select a truck first from the dropdown.');
        }
        return;
      }
      
      const form = document.getElementById('updateLocationForm');
      form.action = `/admin/tracker/${selectedTruckId}/location`;
      
      const option = document.getElementById('truckSelector').options[document.getElementById('truckSelector').selectedIndex];
      const truck = getTruckFromTable(parseInt(selectedTruckId));
      
      document.getElementById('updateLocationTruckId').value = selectedTruckId;
      document.getElementById('updateLocationTruckDisplay').value = `${truck.code} - ${truck.driver} (${truck.route})`;
      
      // Pre-fill coordinates if available
      if (option.dataset.lat && option.dataset.lng) {
        const lat = parseFloat(option.dataset.lat);
        const lng = parseFloat(option.dataset.lng);
        document.getElementById('updateLocationLatitude').value = lat.toFixed(8);
        document.getElementById('updateLocationLongitude').value = lng.toFixed(8);
      } else if (truck.latitude && truck.longitude) {
        const lat = parseFloat(truck.latitude);
        const lng = parseFloat(truck.longitude);
        document.getElementById('updateLocationLatitude').value = lat.toFixed(8);
        document.getElementById('updateLocationLongitude').value = lng.toFixed(8);
      } else {
        document.getElementById('updateLocationLatitude').value = '';
        document.getElementById('updateLocationLongitude').value = '';
      }
      
      // Center map on selected truck if it has coordinates
      if (truck.latitude && truck.longitude && map) {
        const lat = parseFloat(truck.latitude);
        const lng = parseFloat(truck.longitude);
        if (!isNaN(lat) && !isNaN(lng)) {
          map.setView([lat, lng], 15);
        }
      }
      
      openModal('updateLocationModal');
    }

    function refreshMap() {
      // Manually refresh truck data
      fetchTruckData();
    }

    // Auto-refresh functionality
    function fetchTruckData() {
      fetch(trackerDataUrl)
        .then(response => response.json())
        .then(data => {
          // Update truck data
          truckData = data.trucks;
          
          // Update markers on map
          updateMarkers(truckData);
          
          // Update table rows
          updateTableRows(data.trucks);
          
          // Reset countdown
          refreshCountdown = 30;
        })
        .catch(error => {
        });
    }

    // Update table rows with new data
    function updateTableRows(trucks) {
      trucks.forEach(truck => {
        const row = document.querySelector(`tr[data-truck*='"id":${truck.id}']`);
        if (row) {
          // Update last updated timestamp
          const lastUpdatedCell = row.querySelector('.last-updated');
          if (lastUpdatedCell && truck.last_updated) {
            lastUpdatedCell.setAttribute('data-timestamp', truck.last_updated);
            lastUpdatedCell.textContent = truck.last_updated_human;
          }
          
          // Update status badge if changed
          const statusBadge = row.querySelector('span[class*="bg-"]');
          if (statusBadge && truck.status) {
            const statusClasses = {
              'active': 'bg-green-100 text-green-800',
              'on_break': 'bg-yellow-100 text-yellow-800',
              'offline': 'bg-red-100 text-red-800',
              'maintenance': 'bg-blue-100 text-blue-800',
            };
            const statusClass = statusClasses[truck.status] || 'bg-gray-100 text-gray-800';
            statusBadge.className = statusClass + ' text-xs font-medium px-2.5 py-0.5 rounded-full';
            statusBadge.innerHTML = '<i class="fas fa-circle text-xs mr-1"></i>' + (truck.formatted_status || truck.status);
          }
        }
      });
    }

    // Show route history for a truck
    function showRouteHistory(truckId) {
      // Remove existing route if shown
      if (routePolylines[truckId]) {
        map.removeLayer(routePolylines[truckId]);
        delete routePolylines[truckId];
        if (typeof showToast === 'function') {
          showToast('info', 'Route history hidden. Click "Show Route" again to display.');
        }
        return;
      }
      
      // Show loading indicator
      if (typeof showToast === 'function') {
        showToast('info', 'Loading route history...', 2000);
      }
      
      fetch(`/admin/tracker/${truckId}/route-history`)
        .then(response => response.json())
        .then(data => {
          if (data.locations && data.locations.length > 1) {
            const routePoints = data.locations.map(loc => [parseFloat(loc.latitude), parseFloat(loc.longitude)]);
            const polyline = L.polyline(routePoints, {
              color: '#10B981',
              weight: 4,
              opacity: 0.7,
              smoothFactor: 1
            }).addTo(map);
            
            routePolylines[truckId] = polyline;
            
            // Fit map to show route
            map.fitBounds(polyline.getBounds(), { padding: [50, 50] });
            
            // Show success message
            if (typeof showToast === 'function') {
              showToast('success', `Route history loaded for ${data.truck_code} (${data.locations.length} points). Click "Show Route" again to hide.`);
            }
          } else {
            if (typeof showToast === 'function') {
              showToast('warning', 'No route history available for this truck.');
            }
          }
        })
        .catch(error => {
          if (typeof showToast === 'function') {
            showToast('error', 'Error loading route history. Please try again.');
          }
        });
    }

    // Update real-time timestamps
    function updateTimestamps() {
      document.querySelectorAll('.last-updated, .last-updated-popup').forEach(el => {
        const timestamp = el.getAttribute('data-timestamp');
        if (timestamp) {
          const date = new Date(timestamp);
          const now = new Date();
          const diff = Math.floor((now - date) / 1000);
          
          let text = 'Just now';
          if (diff < 60) {
            text = diff === 0 ? 'Just now' : `${diff} second${diff !== 1 ? 's' : ''} ago`;
          } else if (diff < 3600) {
            const minutes = Math.floor(diff / 60);
            text = `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
          } else if (diff < 86400) {
            const hours = Math.floor(diff / 3600);
            text = `${hours} hour${hours !== 1 ? 's' : ''} ago`;
          } else {
            const days = Math.floor(diff / 86400);
            text = `${days} day${days !== 1 ? 's' : ''} ago`;
          }
          
          el.textContent = text;
        }
      });
    }

    // Start auto-refresh
    function startAutoRefresh() {
      // Initial countdown
      refreshCountdown = 30;
      
      // Update countdown every second
      countdownInterval = setInterval(() => {
        refreshCountdown--;
        const countdownEl = document.getElementById('refreshCountdown');
        if (countdownEl) {
          countdownEl.textContent = refreshCountdown;
        }
        
        if (refreshCountdown <= 0) {
          refreshCountdown = 30;
        }
      }, 1000);
      
      // Fetch data every 30 seconds
      autoRefreshInterval = setInterval(() => {
        fetchTruckData();
      }, 30000);
      
      // Update timestamps every second
      setInterval(updateTimestamps, 1000);
    }

    // Initialize auto-refresh when page loads
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', startAutoRefresh);
    } else {
      startAutoRefresh();
    }

    function getTruckFromTable(id) {
      const row = document.querySelector(`tr[data-truck*='"id":${id}']`);
      if (row) {
        return JSON.parse(row.dataset.truck);
      }
      return truckData.find(t => t.id === Number(id));
    }

    function openTruckView(id) {
      currentTruckId = id;
      const truck = getTruckFromTable(id);
      if (!truck) {
        if (typeof showToast === 'function') {
          showToast('error', 'Truck not found. Please try again.');
        }
        return;
      }
      
      document.getElementById('viewTruckCode').textContent = truck.code;
      document.getElementById('viewTruckDriver').textContent = truck.driver;
      
      const statusBadges = {
        'active': 'bg-green-100 text-green-800',
        'on_break': 'bg-yellow-100 text-yellow-800',
        'offline': 'bg-red-100 text-red-800',
        'maintenance': 'bg-blue-100 text-blue-800',
      };
      
      const statusLabels = {
        'active': 'Active',
        'on_break': 'On Break',
        'offline': 'Offline',
        'maintenance': 'Maintenance',
      };
      
      document.getElementById('viewTruckBadges').innerHTML = `
        <span class="${statusBadges[truck.status] || 'bg-gray-100 text-gray-800'} text-sm font-medium px-3 py-1 rounded-full">
          <i class="fas fa-circle text-xs mr-1"></i>${statusLabels[truck.status] || truck.status}
        </span>
        <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded-full">
          <i class="fas fa-route mr-1"></i>${truck.route}
        </span>`;
      
      document.getElementById('viewTruckInfo').innerHTML = `
        <h4 class="font-semibold text-gray-800 mb-3"><i class="fas fa-info-circle mr-2 text-blue-600"></i>Truck Information</h4>
        <div class="space-y-2">
          <div class="flex justify-between"><span class="text-gray-600">Truck ID:</span><span class="font-medium">${truck.code}</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Driver:</span><span class="font-medium">${truck.driver}</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Status:</span><span class="font-medium">${statusLabels[truck.status] || truck.status}</span></div>
        </div>`;
      
      document.getElementById('viewTruckRoute').innerHTML = `
        <h4 class="font-semibold text-gray-800 mb-3"><i class="fas fa-route mr-2 text-blue-600"></i>Route Information</h4>
        <div class="space-y-2">
          <div class="flex justify-between"><span class="text-gray-600">Current Route:</span><span class="font-medium">${truck.route}</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Last Updated:</span><span class="font-medium">${truck.last_updated || 'Never'}</span></div>
          ${truck.latitude && truck.longitude ? `
          <div class="flex justify-between"><span class="text-gray-600">Location:</span><span class="font-medium">${parseFloat(truck.latitude).toFixed(6)}, ${parseFloat(truck.longitude).toFixed(6)}</span></div>
          ` : ''}
        </div>`;
      
      document.getElementById('viewTruckStats').innerHTML = `
        <h4 class="font-semibold text-gray-800 mb-3"><i class="fas fa-chart-line mr-2 text-blue-600"></i>Performance Stats</h4>
        <div class="space-y-2">
          <div class="flex justify-between"><span class="text-gray-600">Collections Today:</span><span class="font-medium">N/A</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Avg. Collection Time:</span><span class="font-medium">N/A</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Fuel Efficiency:</span><span class="font-medium text-green-600">N/A</span></div>
        </div>`;
      
      openModal('viewTruckModal');
    }

    function editTruck(id) {
      currentTruckId = id;
      const truck = getTruckFromTable(id);
      if (!truck) {
        if (typeof showToast === 'function') {
          showToast('error', 'Truck not found. Please try again.');
        }
        return;
      }
      
      const form = document.getElementById('editTruckForm');
      form.action = `/admin/tracker/${id}`;
      document.getElementById('editTruckId').value = truck.id;
      document.getElementById('editTruckCode').value = truck.code;
      document.getElementById('editTruckDriver').value = truck.driver;
      document.getElementById('editTruckRoute').value = truck.route;
      document.getElementById('editTruckStatus').value = truck.status;
      document.getElementById('editTruckLatitude').value = truck.latitude || '';
      document.getElementById('editTruckLongitude').value = truck.longitude || '';
      
      openModal('editTruckModal');
    }

    function editTruckFromView() {
      closeModal('viewTruckModal');
      setTimeout(() => editTruck(currentTruckId), 300);
    }

    function openTruckDelete(id) {
      currentTruckId = id;
      const form = document.getElementById('deleteTruckForm');
      form.action = `/admin/tracker/${id}`;
      openModal('deleteTruckModal');
    }

    // Handle form submission success
    document.getElementById('updateLocationForm')?.addEventListener('submit', function(e) {
      // Form will submit normally, page will reload on success
    });

    // Update click marker when coordinates change in form
    const latInput = document.getElementById('updateLocationLatitude');
    const lngInput = document.getElementById('updateLocationLongitude');
    
    if (latInput && lngInput) {
      latInput.addEventListener('change', updateClickMarker);
      lngInput.addEventListener('change', updateClickMarker);
    }
    
    function updateClickMarker() {
      const lat = parseFloat(latInput.value);
      const lng = parseFloat(lngInput.value);
      
      // Validate coordinates
      if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        return;
      }
      
      if (map) {
        if (clickMarker) {
          clickMarker.setLatLng([lat, lng]);
          clickMarker.setPopupContent(`Selected Location<br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`).openPopup();
        } else {
          clickMarker = L.marker([lat, lng], {
            icon: L.icon({
              iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
              iconSize: [25, 41],
              iconAnchor: [12, 41],
            })
          }).addTo(map).bindPopup(`Selected Location<br>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}`).openPopup();
        }
        map.setView([lat, lng], 15);
      }
    }
  </script>
@endpush
