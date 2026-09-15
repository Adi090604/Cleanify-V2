@extends('layouts.app')

@section('title', 'Garbage Truck Tracker')

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
  <style>
    #map {
      height: 60vh;
      z-index: 10;
    }
    .zone-chip {
      @apply px-3 py-1 rounded-full border text-sm transition;
    }
    .zone-chip-active {
      @apply bg-green-600 border-green-600 text-white;
    }
  </style>
@endpush

@section('content')
  <div class="space-y-6">
    <div class="flex flex-wrap gap-4 items-center justify-between">
      <div>
        <h4 class="text-green-600 font-semibold text-2xl flex items-center gap-2">
          <i class="fas fa-route"></i> Live Truck Tracker
        </h4>
        <p class="text-sm text-gray-500">See where each truck is right now and follow their routes.</p>
      </div>
      <div class="flex flex-wrap gap-3 items-center">
        <div class="relative w-72">
          <input type="text" id="truckSearch" placeholder="Search by truck, driver, route..." class="w-full pl-4 pr-10 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-green-500">
          <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>
        <select id="statusFilter" class="border border-gray-300 rounded-full px-4 py-2 text-sm focus:ring-green-500">
          <option value="all">All statuses</option>
          @foreach($statusCounts as $status => $count)
            <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }} ({{ $count }})</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="grid md:grid-cols-3 gap-4">
      @foreach($statusCounts as $status => $count)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
          <span class="w-3 h-3 rounded-full {{ $status === 'active' ? 'bg-green-500' : ($status === 'on_break' ? 'bg-yellow-500' : ($status === 'maintenance' ? 'bg-blue-500' : 'bg-gray-400')) }}"></span>
          <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">{{ ucfirst(str_replace('_', ' ', $status)) }}</p>
            <p class="text-xl font-semibold text-gray-800">{{ $count }}</p>
          </div>
        </div>
      @endforeach
    </div>

    <div class="bg-white border border-green-100 rounded-2xl p-4 flex flex-wrap items-center gap-3">
      <p class="text-sm text-gray-600 font-semibold">Highlight a service zone:</p>
      <div class="flex flex-wrap gap-2 flex-1" id="zoneFilterGroup">
        @foreach($zones as $zoneName => $coords)
          <button class="zone-chip border-gray-200 text-gray-600 hover:border-green-500" data-zone="{{ $zoneName }}">{{ $zoneName }}</button>
        @endforeach
      </div>
      <button id="clearZoneHighlight" class="text-sm text-gray-500 hover:text-green-600">Clear</button>
    </div>

    <div class="grid lg:grid-cols-3 gap-6 items-start">
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg p-4 border border-green-100">
        <div class="flex items-center justify-between mb-3 text-sm text-gray-500">
          <div class="flex items-center gap-2">
            <span class="flex items-center gap-1 text-green-600">
              <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> Live updating
            </span>
            <span class="text-gray-400">·</span>
            <span>Refresh in <span id="refreshCountdown">30</span>s</span>
          </div>
          <button id="manualRefreshBtn" class="text-green-600 hover:underline">Refresh now</button>
        </div>
        <div id="map" class="rounded-xl overflow-hidden"></div>
      </div>

      <div class="space-y-4">
        <div id="zoneEtaPanel" class="bg-white rounded-2xl shadow border border-green-100 p-4 hidden">
          <div class="flex items-center justify-between mb-2">
            <h5 class="text-lg font-semibold text-gray-800" id="zoneEtaTitle"></h5>
            <span class="text-xs text-gray-400" id="zoneEtaSubtitle"></span>
          </div>
          <div id="zoneEtaList" class="space-y-2"></div>
        </div>

        <div id="truckDetailPanel" class="bg-white rounded-2xl shadow border border-green-100 p-4 hidden">
          <div class="flex items-center justify-between mb-3">
            <h5 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
              <i class="fas fa-truck text-green-600"></i><span id="detailTruckName"></span>
            </h5>
            <span id="detailStatusBadge" class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-700"></span>
          </div>
          <div class="space-y-2 text-sm text-gray-600">
            <p><strong>Driver:</strong> <span id="detailDriver"></span></p>
            <p><strong>Route:</strong> <span id="detailRoute"></span></p>
            <p><strong>Last update:</strong> <span id="detailLastUpdated"></span></p>
            <p><strong>ETA to highlighted zone:</strong> <span id="detailEta">Select a zone</span></p>
          </div>
          <div class="flex items-center gap-2 mt-4">
            <button id="detailFocusBtn" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
              <i class="fas fa-crosshairs text-green-600 mr-1"></i>Center map
            </button>
            <button id="detailRouteBtn" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
              <i class="fas fa-map-marked-alt text-blue-600 mr-1"></i>View route
            </button>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-4 border border-green-100">
        <div class="flex items-center justify-between mb-3">
          <h5 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-list-ul text-green-600"></i> Active Trucks
          </h5>
          <span class="text-sm text-gray-500">{{ $trucks->count() }} total</span>
        </div>
        <div id="truckList" class="space-y-3 max-h-[65vh] overflow-y-auto pr-1">
          @forelse($trucks as $truck)
            <div class="border border-gray-100 rounded-xl p-3 truck-item hover:border-green-300 transition-colors duration-200" data-id="{{ $truck->id }}" data-status="{{ $truck->status }}" data-search="{{ strtolower($truck->code . ' ' . $truck->driver . ' ' . $truck->route) }}">
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <div class="w-3 h-3 rounded-full {{ $truck->status === 'active' ? 'bg-green-500' : ($truck->status === 'on_break' ? 'bg-yellow-500' : ($truck->status === 'maintenance' ? 'bg-blue-500' : 'bg-gray-400')) }}"></div>
                  <div>
                    <p class="font-semibold text-gray-800">{{ $truck->code }}</p>
                    <p class="text-sm text-gray-500">{{ $truck->driver ?? 'No driver assigned' }}</p>
                  </div>
                </div>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full {{ $truck->getStatusBadgeClass() }}">{{ $truck->formatted_status }}</span>
              </div>
              <div class="flex items-center gap-4 text-sm text-gray-600 mt-2">
                <p>
                  <i class="fas fa-route text-green-600 mr-1"></i>{{ $truck->route ?? 'No route' }}
                </p>
                @if($truck->latitude && $truck->longitude)
                  <p class="text-green-600">
                    <i class="fas fa-map-marker-alt mr-1"></i>On map
                  </p>
                @else
                  <p class="text-gray-400">
                    <i class="fas fa-map-marker-alt mr-1"></i>No location
                  </p>
                @endif
              </div>
              <p class="text-xs text-gray-400 mt-2">Last updated: {{ $truck->last_updated ? $truck->last_updated->diffForHumans() : 'Never' }}</p>
              <div class="flex items-center gap-2 mt-3 text-sm">
                @if($truck->latitude && $truck->longitude)
                  <button class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-50 center-truck-btn" data-id="{{ $truck->id }}">
                    <i class="fas fa-crosshairs text-green-600 mr-1"></i>Focus
                  </button>
                  <button class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-50 route-history-btn" data-id="{{ $truck->id }}">
                    <i class="fas fa-map-marked-alt text-blue-600 mr-1"></i>Route
                  </button>
                @else
                  <button class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-gray-400 cursor-not-allowed" disabled title="No location data available">
                    <i class="fas fa-map-marker-alt mr-1"></i>No location
                  </button>
                @endif
              </div>
            </div>
          @empty
            <div class="text-center py-8 text-gray-500">
              <i class="fas fa-truck text-4xl mb-3 block text-gray-300"></i>
              <p>No trucks available</p>
            </div>
          @endforelse
        </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
  <script>
    const trackerDataUrl = '{{ route('tracker.data.client') }}';
    const routeHistoryUrlTemplate = '{{ route('tracker.route-history.client', ['truck' => '__ID__']) }}';
    @php
      $initialTrucks = $trucks->map(function ($truck) {
          return [
              'id' => $truck->id,
              'code' => $truck->code,
              'driver' => $truck->driver,
              'route' => $truck->route,
              'status' => $truck->status,
              'formatted_status' => $truck->formatted_status,
              'status_badge' => $truck->getStatusBadgeClass(),
              'latitude' => $truck->latitude,
              'longitude' => $truck->longitude,
              'last_updated' => $truck->last_updated ? $truck->last_updated->toIso8601String() : null,
              'last_updated_human' => $truck->last_updated ? $truck->last_updated->diffForHumans() : 'Never',
          ];
      })->values();
    @endphp
    const initialTrucks = @json($initialTrucks);

    const zones = @json($zones);
    const map = L.map('map').setView([{{ $centerLat }}, {{ $centerLng }}], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const getTruckIcon = (status) => {
      const statusClasses = {
        'active': 'active',
        'on_break': 'on-break',
        'offline': 'offline',
        'maintenance': 'maintenance',
      };
      const statusClass = statusClasses[status] || 'unknown';

      return L.divIcon({
        className: 'truck-map-marker',
        html: `<span class="truck-map-marker__body truck-map-marker--${statusClass}">
          <i class="fas fa-truck" aria-hidden="true"></i>
          <span class="truck-map-marker__status" aria-hidden="true"></span>
        </span>`,
        iconSize: [36, 36],
        iconAnchor: [18, 18],
        popupAnchor: [0, -20],
      });
    };

    const markers = {};
    const clusterGroup = L.markerClusterGroup({ showCoverageOnHover: false });
    map.addLayer(clusterGroup);
    const zoneLayers = {};
    let selectedZone = null;
    let zoneEtaEntries = [];
    const zoneEtaPanel = document.getElementById('zoneEtaPanel');
    const zoneEtaTitle = document.getElementById('zoneEtaTitle');
    const zoneEtaSubtitle = document.getElementById('zoneEtaSubtitle');
    const zoneEtaList = document.getElementById('zoneEtaList');
    const zoneFilterGroup = document.getElementById('zoneFilterGroup');
    const truckDetailPanel = document.getElementById('truckDetailPanel');
    const detailTruckName = document.getElementById('detailTruckName');
    const detailStatusBadge = document.getElementById('detailStatusBadge');
    const detailDriver = document.getElementById('detailDriver');
    const detailRoute = document.getElementById('detailRoute');
    const detailLastUpdated = document.getElementById('detailLastUpdated');
    const detailEta = document.getElementById('detailEta');
    const detailFocusBtn = document.getElementById('detailFocusBtn');
    const detailRouteBtn = document.getElementById('detailRouteBtn');
    let selectedTruckId = null;
    let truckCache = {};
    let routeLayer = null;
    let countdown = 30;
    let countdownInterval = null;

    const makePopup = (truck) => {
      const statusColors = {
        'active': 'bg-green-100 text-green-800',
        'on_break': 'bg-yellow-100 text-yellow-800',
        'offline': 'bg-red-100 text-red-800',
        'maintenance': 'bg-blue-100 text-blue-800',
      };
      const statusClass = statusColors[truck.status] || 'bg-gray-100 text-gray-800';
      
      return `
        <div class="text-sm" style="min-width: 180px;">
          <div class="flex items-center justify-between mb-2">
            <p class="font-semibold text-gray-800">${truck.code}</p>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full ${statusClass}">${truck.formatted_status || truck.status || 'Unknown'}</span>
          </div>
          <p class="text-gray-600 text-xs"><strong>Driver:</strong> ${truck.driver ?? 'N/A'}</p>
          <p class="text-gray-600 text-xs"><strong>Route:</strong> ${truck.route ?? 'N/A'}</p>
          <p class="text-gray-500 text-xs mt-2 pt-2 border-t border-gray-200"><strong>Last updated:</strong> ${truck.last_updated_human ?? 'Never'}</p>
        </div>
      `;
    };

    const upsertMarker = (truck) => {
      if (!truck.latitude || !truck.longitude) return;
      
      const icon = getTruckIcon(truck.status);
      
      if (!markers[truck.id]) {
        const marker = L.marker([truck.latitude, truck.longitude], { icon: icon })
          .bindPopup(makePopup(truck));
        marker.on('click', () => showTruckDetail(truck));
        markers[truck.id] = marker;
        clusterGroup.addLayer(marker);
      } else {
        // Update marker position and icon if status changed
        markers[truck.id].setLatLng([truck.latitude, truck.longitude]);
        markers[truck.id].setIcon(icon);
        const popupContent = makePopup(truck);
        markers[truck.id].setPopupContent(popupContent);
        // Update truck cache for detail panel
        truckCache[truck.id] = truck;
      }
    };

    const removeMissingMarkers = (ids) => {
      Object.keys(markers).forEach(id => {
        if (!ids.includes(Number(id))) {
          clusterGroup.removeLayer(markers[id]);
          delete markers[id];
        }
      });
    };

    const refreshList = (data) => {
      updateTruckCache(data);
      data.forEach(truck => {
        const el = document.querySelector(`.truck-item[data-id="${truck.id}"]`);
        if (!el) return;
        
        // Update status
        el.setAttribute('data-status', truck.status);
        
        // Update status badge
        const statusBadge = el.querySelector('span.text-xs.font-semibold.px-2\\.5');
        if (statusBadge) {
          statusBadge.textContent = truck.formatted_status;
          statusBadge.className = `text-xs font-semibold px-2.5 py-0.5 rounded-full ${truck.status_badge}`;
        }
        
        // Update status indicator dot
        const statusDot = el.querySelector('.w-3.h-3.rounded-full');
        if (statusDot) {
          const statusColors = {
            'active': 'bg-green-500',
            'on_break': 'bg-yellow-500',
            'maintenance': 'bg-blue-500',
            'offline': 'bg-red-500',
          };
          statusDot.className = `w-3 h-3 rounded-full ${statusColors[truck.status] || 'bg-gray-400'}`;
        }
        
        // Update last updated text
        const lastUpdatedEl = el.querySelector('.text-xs.text-gray-400');
        if (lastUpdatedEl) {
          lastUpdatedEl.textContent = `Last updated: ${truck.last_updated_human ?? 'Never'}`;
        }
        
        // Update location indicator
        const locationIndicator = el.querySelector('.fa-map-marker-alt')?.parentElement;
        if (locationIndicator) {
          if (truck.latitude && truck.longitude) {
            locationIndicator.className = 'text-green-600';
            locationIndicator.innerHTML = '<i class="fas fa-map-marker-alt mr-1"></i>On map';
          } else {
            locationIndicator.className = 'text-gray-400';
            locationIndicator.innerHTML = '<i class="fas fa-map-marker-alt mr-1"></i>No location';
          }
        }
      });
    };

    const updateTruckCache = (data) => {
      data.forEach(truck => { truckCache[truck.id] = truck; });
      if (selectedTruckId && truckCache[selectedTruckId]) {
        showTruckDetail(truckCache[selectedTruckId]);
      }
      if (selectedZone) {
        updateZoneEta(selectedZone);
      }
    };

    const fetchTrucks = () => {
      fetch(trackerDataUrl)
        .then(res => res.json())
        .then(data => {
          data.trucks.forEach(upsertMarker);
          removeMissingMarkers(data.trucks.map(t => t.id));
          refreshList(data.trucks);
        })
        .catch(() => showToast?.('error', 'Unable to refresh tracker data.'));
    };

    const startCountdown = () => {
      countdown = 30;
      document.getElementById('refreshCountdown').textContent = countdown;
      clearInterval(countdownInterval);
      countdownInterval = setInterval(() => {
        countdown--;
        if (countdown <= 0) {
          fetchTrucks();
          countdown = 30;
        }
        document.getElementById('refreshCountdown').textContent = countdown;
      }, 1000);
    };

    document.getElementById('manualRefreshBtn')?.addEventListener('click', fetchTrucks);

    document.querySelectorAll('.center-truck-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = Number(btn.dataset.id);
        const marker = markers[id];
        if (marker) {
          map.setView(marker.getLatLng(), 15);
          marker.openPopup();
        } else {
          showToast?.('info', 'No live location for this truck yet.');
        }
      });
    });

    document.querySelectorAll('.route-history-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        fetch(routeHistoryUrlTemplate.replace('__ID__', id))
          .then(res => res.json())
          .then(data => {
            if (routeLayer) {
              map.removeLayer(routeLayer);
            }
            if (!data.locations.length) {
              showToast?.('info', 'No route history available for the last 24 hours.');
              return;
            }
            const latlngs = data.locations.map(loc => [loc.latitude, loc.longitude]);
            routeLayer = L.polyline(latlngs, { color: '#2563eb', weight: 4, opacity: 0.7 }).addTo(map);
            map.fitBounds(routeLayer.getBounds(), { padding: [30, 30] });
          })
          .catch(() => showToast?.('error', 'Unable to load route history.'));
      });
    });

    const filterList = () => {
      const search = document.getElementById('truckSearch').value.toLowerCase();
      const status = document.getElementById('statusFilter').value;
      document.querySelectorAll('.truck-item').forEach(item => {
        const matchesSearch = item.dataset.search.includes(search);
        const matchesStatus = status === 'all' || item.dataset.status === status;
        item.style.display = matchesSearch && matchesStatus ? 'block' : 'none';
      });
    };

    const calculateDistanceKm = (lat1, lon1, lat2, lon2) => {
      const toRad = (deg) => deg * Math.PI / 180;
      const R = 6371;
      const dLat = toRad(lat2 - lat1);
      const dLon = toRad(lon2 - lon1);
      const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
      return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };

    const estimateEta = (truck, zone) => {
      if (!truck.latitude || !truck.longitude) return null;
      const distance = calculateDistanceKm(truck.latitude, truck.longitude, zone.lat, zone.lng);
      const assumedSpeed = 20;
      const hours = distance / assumedSpeed;
      return {
        distance: distance.toFixed(2),
        minutes: Math.round(hours * 60),
      };
    };

    const updateZoneEta = (zoneName) => {
      const zone = zones[zoneName];
      if (!zone) return;
      const entries = Object.values(truckCache)
        .map(truck => {
          const eta = estimateEta(truck, zone);
          if (!eta) {
            return null;
          }
          return {
            truck,
            eta,
          };
        })
        .filter(Boolean)
        .sort((a, b) => a.eta.minutes - b.eta.minutes)
        .slice(0, 3);

      zoneEtaPanel.classList.remove('hidden');
      zoneEtaTitle.textContent = `${zoneName} arrivals`;
      zoneEtaSubtitle.textContent = `Highlighting ${entries.length} truck(s)`;

      if (!entries.length) {
        zoneEtaList.innerHTML = '<p class="text-sm text-gray-500">No active trucks with location data for this zone.</p>';
        return;
      }

      zoneEtaList.innerHTML = entries.map(entry => `
        <div class="border border-gray-100 rounded-xl p-3 flex items-center justify-between">
          <div>
            <p class="font-semibold text-gray-800">${entry.truck.code}</p>
            <p class="text-xs text-gray-500">${entry.truck.route ?? 'No route'} · ${entry.truck.formatted_status}</p>
          </div>
          <div class="text-right">
            <p class="text-lg font-bold text-green-600">${entry.eta.minutes} min</p>
            <p class="text-xs text-gray-400">${entry.eta.distance} km</p>
          </div>
        </div>
      `).join('');
    };

    const showTruckDetail = (truck) => {
      if (!truck) return;
      selectedTruckId = truck.id;
      truckDetailPanel.classList.remove('hidden');
      detailTruckName.textContent = truck.code;
      detailStatusBadge.textContent = truck.formatted_status;
      detailStatusBadge.className = `text-xs font-semibold px-2.5 py-0.5 rounded-full ${truck.status_badge}`;
      detailDriver.textContent = truck.driver ?? 'No driver assigned';
      detailRoute.textContent = truck.route ?? 'No route assigned';
      detailLastUpdated.textContent = truck.last_updated_human ?? 'Never';
      if (selectedZone && zones[selectedZone]) {
        const eta = estimateEta(truck, zones[selectedZone]);
        detailEta.textContent = eta ? `${eta.minutes} minutes (${eta.distance} km)` : 'Not available';
      } else {
        detailEta.textContent = 'Select a zone';
      }
      detailFocusBtn.onclick = () => {
        const marker = markers[truck.id];
        if (marker) {
          map.setView(marker.getLatLng(), 15);
          marker.openPopup();
        }
      };
      detailRouteBtn.onclick = () => {
        const btn = document.querySelector(`.route-history-btn[data-id="${truck.id}"]`);
        btn?.click();
      };
    };

    document.querySelectorAll('.truck-item').forEach(item => {
      item.addEventListener('click', () => {
        const id = Number(item.dataset.id);
        if (truckCache[id]) {
          showTruckDetail(truckCache[id]);
        }
      });
    });

    const highlightZone = (zoneName) => {
      Object.values(zoneLayers).forEach(layer => layer.setStyle({ fillOpacity: 0.1, opacity: 0.2 }));
      if (zoneLayers[zoneName]) {
        zoneLayers[zoneName].setStyle({ fillOpacity: 0.3, opacity: 0.6 });
        map.setView(zoneLayers[zoneName].getLatLng(), 14);
      }
      selectedZone = zoneName;
      document.querySelectorAll('.zone-chip').forEach(btn => btn.classList.remove('zone-chip-active'));
      const activeBtn = document.querySelector(`.zone-chip[data-zone="${zoneName}"]`);
      activeBtn?.classList.add('zone-chip-active');
      updateZoneEta(zoneName);
      if (selectedTruckId && truckCache[selectedTruckId]) {
        showTruckDetail(truckCache[selectedTruckId]);
      }
    };

    document.getElementById('clearZoneHighlight').addEventListener('click', () => {
      selectedZone = null;
      zoneEtaPanel.classList.add('hidden');
      document.querySelectorAll('.zone-chip').forEach(btn => btn.classList.remove('zone-chip-active'));
      Object.values(zoneLayers).forEach(layer => layer.setStyle({ fillOpacity: 0.1, opacity: 0.3 }));
      detailEta.textContent = 'Select a zone';
    });

    zoneFilterGroup.querySelectorAll('.zone-chip').forEach(btn => {
      btn.addEventListener('click', () => highlightZone(btn.dataset.zone));
    });

    const drawZones = () => {
      Object.entries(zones).forEach(([name, coords], index) => {
        const hue = (index * 60) % 360;
        const color = `hsl(${hue}, 70%, 45%)`;
        const circle = L.circle([coords.lat, coords.lng], {
          radius: 600,
          color,
          fillColor: color,
          fillOpacity: 0.1,
          weight: 1,
        }).bindPopup(`<strong>${name}</strong>`);
        circle.addTo(map);
        circle.on('click', () => highlightZone(name));
        zoneLayers[name] = circle;
      });
    };

    document.getElementById('truckSearch').addEventListener('input', filterList);
    document.getElementById('statusFilter').addEventListener('change', filterList);

    initialTrucks.forEach(truck => {
      truckCache[truck.id] = truck;
      upsertMarker(truck);
    });
    drawZones();
    startCountdown();
    filterList();
  </script>
@endpush
