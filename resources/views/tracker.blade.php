@extends('layouts.app')

@section('title', 'Truck Tracker')

@push('styles')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
@endpush

@section('content')
  <div class="resident-tracker">
    <header class="resident-tracker-header">
      <p class="resident-eyebrow">Collection fleet</p>
      <h1 class="resident-page-title">Truck Tracker</h1>
      <p class="resident-page-subtitle">View current truck locations and service assignments.</p>
    </header>

    <section class="resident-tracker-controls" aria-label="Filter trucks">
      <div class="resident-tracker-search">
        <i class="fas fa-search" aria-hidden="true"></i>
        <label for="truckSearch" class="sr-only">Search trucks</label>
        <input type="search" id="truckSearch" placeholder="Search by truck, driver, or service assignment">
      </div>
      <select id="statusFilter" class="sr-only" aria-label="Filter by status">
          <option value="all">All statuses</option>
          @foreach($statusCounts as $status => $count)
            <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }} ({{ $count }})</option>
          @endforeach
      </select>
      <div class="resident-tracker-filter-chips" role="group" aria-label="Truck status">
        <button type="button" class="resident-tracker-filter-chip is-active" data-status-value="all">All</button>
        @foreach($statusCounts as $status => $count)
          <button type="button" class="resident-tracker-filter-chip" data-status-value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</button>
        @endforeach
      </div>
    </section>

    <div class="resident-tracker-content">
      <section class="resident-tracker-map-card" aria-labelledby="tracker-map-title">
        <div class="resident-tracker-map-header">
          <div>
            <p class="resident-tracker-section-eyebrow">Current positions</p>
            <h2 id="tracker-map-title">Truck locations</h2>
          </div>
          <div class="resident-tracker-refresh">
            <span class="resident-tracker-refresh-status">
              <i class="fas fa-sync-alt" aria-hidden="true"></i>Auto-refreshing
            </span>
            <span class="text-gray-400">·</span>
            <span>Refresh in <span id="refreshCountdown">30</span>s</span>
            <button id="manualRefreshBtn" type="button">Refresh now</button>
          </div>
        </div>
        <div id="map" class="resident-tracker-map"></div>
      </section>

      <div class="resident-tracker-sections">
        <section id="truckDetailPanel" class="resident-tracker-selected hidden" aria-labelledby="selected-truck-heading">
          <div class="resident-tracker-selected-header">
            <div>
              <p class="resident-tracker-section-eyebrow">Selected truck</p>
              <h2 id="selected-truck-heading"><i class="fas fa-truck" aria-hidden="true"></i><span id="detailTruckName"></span></h2>
            </div>
            <span id="detailStatusBadge" class="resident-tracker-status-badge bg-gray-100 text-gray-700"></span>
          </div>
          <div class="resident-tracker-selected-grid">
            <div><span>Driver</span><strong id="detailDriver"></strong></div>
            <div><span>Service assignment</span><strong id="detailRoute"></strong></div>
            <div><span>Last updated</span><strong id="detailLastUpdated"></strong></div>
            <div><span>Map location</span><strong id="detailLocation"></strong></div>
            <div><span>Coverage Radius</span><strong id="detailCoverageRadius"></strong></div>
          </div>
          <div class="resident-tracker-selected-actions">
            <button id="detailFocusBtn" type="button" class="resident-tracker-focus-button">
              <i class="fas fa-crosshairs" aria-hidden="true"></i>Focus
            </button>
          </div>
        </section>

        <section class="resident-tracker-trucks" aria-labelledby="tracker-trucks-title">
          <div class="resident-tracker-section-header">
            <div>
              <p class="resident-tracker-section-eyebrow">Service fleet</p>
              <h2 id="tracker-trucks-title">Trucks</h2>
            </div>
            <span id="truckCountSummary">{{ $trucks->count() }} total</span>
          </div>
          <div id="truckList" class="resident-tracker-grid">
            @forelse($trucks as $truck)
              <article class="resident-tracker-truck-card truck-item" data-id="{{ $truck->id }}" data-status="{{ $truck->status }}" data-search="{{ strtolower($truck->code . ' ' . $truck->driver . ' ' . $truck->route) }}">
                <div class="resident-tracker-truck-header">
                  <span class="resident-tracker-status-dot" data-status="{{ $truck->status }}" aria-hidden="true"></span>
                  <div class="resident-tracker-truck-heading">
                    <h3 class="resident-tracker-truck-code">{{ $truck->code }}</h3>
                    <p class="resident-tracker-driver">{{ $truck->driver ?? 'No driver assigned' }}</p>
                  </div>
                  <span class="resident-tracker-status-badge truck-status-badge {{ $truck->getStatusBadgeClass() }}">{{ $truck->formatted_status }}</span>
                </div>
                <div class="resident-tracker-assignment">
                  <i class="fas fa-route" aria-hidden="true"></i>
                  <div>
                    <span>Service assignment</span>
                    <strong class="resident-tracker-assignment-value">{{ $truck->route ?? 'No assignment' }}</strong>
                  </div>
                </div>
                <div class="resident-tracker-truck-meta">
                  @if($truck->latitude && $truck->longitude)
                    <span class="resident-tracker-location-indicator is-located"><i class="fas fa-map-marker-alt" aria-hidden="true"></i>On map</span>
                  @else
                    <span class="resident-tracker-location-indicator"><i class="fas fa-map-marker-alt" aria-hidden="true"></i>No location</span>
                  @endif
                  <span class="resident-tracker-last-updated">Last updated: {{ $truck->last_updated ? $truck->last_updated->diffForHumans() : 'Never' }}</span>
                </div>
                <div class="resident-tracker-truck-actions">
                  @if($truck->latitude && $truck->longitude)
                    <button type="button" class="resident-tracker-focus-button center-truck-btn" data-id="{{ $truck->id }}">
                      <i class="fas fa-crosshairs" aria-hidden="true"></i>Focus
                    </button>
                  @else
                    <button type="button" class="resident-tracker-focus-button" disabled title="No location data available">
                      <i class="fas fa-map-marker-alt" aria-hidden="true"></i>No location
                    </button>
                  @endif
                </div>
              </article>
            @empty
              <div class="resident-tracker-empty">
                <i class="fas fa-truck" aria-hidden="true"></i>
                <p>No trucks available</p>
              </div>
            @endforelse
          </div>
        </section>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
  <script>
    const trackerDataUrl = '{{ route('tracker.data.client') }}';
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
              'coverage_radius_meters' => $truck->coverage_radius_meters,
              'latitude' => $truck->latitude,
              'longitude' => $truck->longitude,
              'last_updated' => $truck->last_updated ? $truck->last_updated->toIso8601String() : null,
              'last_updated_human' => $truck->last_updated ? $truck->last_updated->diffForHumans() : 'Never',
          ];
      })->values();
    @endphp
    const initialTrucks = @json($initialTrucks);

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
    const coverageCircles = {};
    const coverageCircleLayer = L.layerGroup().addTo(map);
    const clusterGroup = L.markerClusterGroup({ showCoverageOnHover: false });
    map.addLayer(clusterGroup);
    const truckDetailPanel = document.getElementById('truckDetailPanel');
    const detailTruckName = document.getElementById('detailTruckName');
    const detailStatusBadge = document.getElementById('detailStatusBadge');
    const detailDriver = document.getElementById('detailDriver');
    const detailRoute = document.getElementById('detailRoute');
    const detailLastUpdated = document.getElementById('detailLastUpdated');
    const detailLocation = document.getElementById('detailLocation');
    const detailCoverageRadius = document.getElementById('detailCoverageRadius');
    const detailFocusBtn = document.getElementById('detailFocusBtn');
    let selectedTruckId = null;
    let truckCache = {};
    let countdown = 30;
    let countdownInterval = null;

    const hasValidTruckCoordinates = (truck) => {
      if (truck?.latitude === null || truck?.latitude === '' || truck?.longitude === null || truck?.longitude === '') {
        return false;
      }
      const latitude = Number(truck?.latitude);
      const longitude = Number(truck?.longitude);
      return Number.isFinite(latitude) && Number.isFinite(longitude)
        && latitude >= -90 && latitude <= 90
        && longitude >= -180 && longitude <= 180;
    };

    const formatCoverageRadius = (value) => {
      const radius = Number(value);
      if (!Number.isFinite(radius) || radius <= 0) return 'Not configured';
      return radius >= 1000 ? `${Number((radius / 1000).toFixed(1))} km` : `${radius} m`;
    };

    const removeCoverageCircle = (truckId) => {
      const circle = coverageCircles[truckId];
      if (!circle) return;
      coverageCircleLayer.removeLayer(circle);
      delete coverageCircles[truckId];
    };

    const upsertCoverageCircle = (truck, latitude, longitude) => {
      const radius = Number(truck.coverage_radius_meters);
      if (!Number.isFinite(radius) || radius <= 0) {
        removeCoverageCircle(truck.id);
        return;
      }

      const existingCircle = coverageCircles[truck.id];
      if (existingCircle) {
        existingCircle.setLatLng([latitude, longitude]);
        existingCircle.setRadius(radius);
        return;
      }

      coverageCircles[truck.id] = L.circle([latitude, longitude], {
        radius,
        color: '#16a34a',
        fillColor: '#22c55e',
        fillOpacity: 0.08,
        opacity: 0.55,
        weight: 1.5,
        interactive: false,
      }).addTo(coverageCircleLayer);
    };

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
          <p class="text-gray-600 text-xs"><strong>Coverage Radius:</strong> ${formatCoverageRadius(truck.coverage_radius_meters)}</p>
          <p class="text-gray-500 text-xs mt-2 pt-2 border-t border-gray-200"><strong>Last updated:</strong> ${truck.last_updated_human ?? 'Never'}</p>
        </div>
      `;
    };

    const upsertMarker = (truck) => {
      if (!hasValidTruckCoordinates(truck)) {
        if (markers[truck.id]) {
          clusterGroup.removeLayer(markers[truck.id]);
          delete markers[truck.id];
        }
        removeCoverageCircle(truck.id);
        return;
      }

      const latitude = Number(truck.latitude);
      const longitude = Number(truck.longitude);
      
      const icon = getTruckIcon(truck.status);
      
      if (!markers[truck.id]) {
        const marker = L.marker([latitude, longitude], { icon: icon })
          .bindPopup(makePopup(truck));
        marker.on('click', () => showTruckDetail(truckCache[truck.id] ?? truck));
        markers[truck.id] = marker;
        clusterGroup.addLayer(marker);
      } else {
        // Update marker position and icon if status changed
        markers[truck.id].setLatLng([latitude, longitude]);
        markers[truck.id].setIcon(icon);
        const popupContent = makePopup(truck);
        markers[truck.id].setPopupContent(popupContent);
        // Update truck cache for detail panel
        truckCache[truck.id] = truck;
      }

      upsertCoverageCircle(truck, latitude, longitude);
    };

    const removeMissingMarkers = (ids) => {
      Object.keys(markers).forEach(id => {
        if (!ids.includes(Number(id))) {
          clusterGroup.removeLayer(markers[id]);
          delete markers[id];
        }
      });
      Object.keys(coverageCircles).forEach(id => {
        if (!ids.includes(Number(id))) {
          removeCoverageCircle(id);
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
        el.setAttribute('data-search', [truck.code, truck.driver, truck.route].filter(Boolean).join(' ').toLowerCase());

        const driver = el.querySelector('.resident-tracker-driver');
        if (driver) {
          driver.textContent = truck.driver ?? 'No driver assigned';
        }

        const assignment = el.querySelector('.resident-tracker-assignment-value');
        if (assignment) {
          assignment.textContent = truck.route ?? 'No assignment';
        }
        
        // Update status badge
        const statusBadge = el.querySelector('.truck-status-badge');
        if (statusBadge) {
          statusBadge.textContent = truck.formatted_status;
          statusBadge.className = `resident-tracker-status-badge truck-status-badge ${truck.status_badge}`;
        }
        
        // Update status indicator dot
        const statusDot = el.querySelector('.resident-tracker-status-dot');
        if (statusDot) {
          statusDot.dataset.status = truck.status;
        }
        
        // Update last updated text
        const lastUpdatedEl = el.querySelector('.resident-tracker-last-updated');
        if (lastUpdatedEl) {
          lastUpdatedEl.textContent = `Last updated: ${truck.last_updated_human ?? 'Never'}`;
        }
        
        // Update location indicator
        const locationIndicator = el.querySelector('.resident-tracker-location-indicator');
        if (locationIndicator) {
          if (truck.latitude && truck.longitude) {
            locationIndicator.className = 'resident-tracker-location-indicator is-located';
            locationIndicator.innerHTML = '<i class="fas fa-map-marker-alt" aria-hidden="true"></i>On map';
          } else {
            locationIndicator.className = 'resident-tracker-location-indicator';
            locationIndicator.innerHTML = '<i class="fas fa-map-marker-alt" aria-hidden="true"></i>No location';
          }
        }

        const actions = el.querySelector('.resident-tracker-truck-actions');
        if (actions) {
          actions.innerHTML = truck.latitude && truck.longitude
            ? `<button type="button" class="resident-tracker-focus-button center-truck-btn" data-id="${truck.id}"><i class="fas fa-crosshairs" aria-hidden="true"></i>Focus</button>`
            : '<button type="button" class="resident-tracker-focus-button" disabled title="No location data available"><i class="fas fa-map-marker-alt" aria-hidden="true"></i>No location</button>';
        }
      });
    };

    const updateTruckCache = (data) => {
      data.forEach(truck => { truckCache[truck.id] = truck; });
      if (selectedTruckId && truckCache[selectedTruckId]) {
        showTruckDetail(truckCache[selectedTruckId]);
      }
    };

    const fetchTrucks = () => {
      fetch(trackerDataUrl)
        .then(res => res.json())
        .then(data => {
          data.trucks.forEach(upsertMarker);
          removeMissingMarkers(data.trucks.map(t => t.id));
          refreshList(data.trucks);
          filterList();
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

    document.getElementById('truckList')?.addEventListener('click', event => {
      const button = event.target.closest('.center-truck-btn');
      if (button) {
        event.stopPropagation();
        const id = Number(button.dataset.id);
        const marker = markers[id];
        if (marker) {
          showTruckDetail(truckCache[id]);
          map.setView(marker.getLatLng(), 15);
          marker.openPopup();
        } else {
          showToast?.('info', 'No current location is available for this truck.');
        }
      }
    });

    const filterList = () => {
      const search = document.getElementById('truckSearch').value.toLowerCase();
      const status = document.getElementById('statusFilter').value;
      let visibleCount = 0;
      document.querySelectorAll('.truck-item').forEach(item => {
        const matchesSearch = item.dataset.search.includes(search);
        const matchesStatus = status === 'all' || item.dataset.status === status;
        item.style.display = matchesSearch && matchesStatus ? 'block' : 'none';
        if (matchesSearch && matchesStatus) {
          visibleCount++;
        }
      });
      const countSummary = document.getElementById('truckCountSummary');
      if (countSummary) {
        const total = document.querySelectorAll('.truck-item').length;
        countSummary.textContent = visibleCount === total ? `${total} total` : `${visibleCount} shown · ${total} total`;
      }
    };

    const showTruckDetail = (truck) => {
      if (!truck) return;
      selectedTruckId = truck.id;
      truckDetailPanel.classList.remove('hidden');
      detailTruckName.textContent = truck.code;
      detailStatusBadge.textContent = truck.formatted_status;
      detailStatusBadge.className = `resident-tracker-status-badge ${truck.status_badge}`;
      detailDriver.textContent = truck.driver ?? 'No driver assigned';
      detailRoute.textContent = truck.route ?? 'No route assigned';
      detailLastUpdated.textContent = truck.last_updated_human ?? 'Never';
      detailCoverageRadius.textContent = formatCoverageRadius(truck.coverage_radius_meters);
      const hasLocation = hasValidTruckCoordinates(truck);
      detailLocation.textContent = hasLocation ? 'Available on map' : 'Not available';
      detailFocusBtn.disabled = !hasLocation;
      document.querySelectorAll('.truck-item').forEach(item => {
        item.classList.toggle('is-selected', Number(item.dataset.id) === Number(truck.id));
      });
      detailFocusBtn.onclick = () => {
        const marker = markers[truck.id];
        if (marker) {
          map.setView(marker.getLatLng(), 15);
          marker.openPopup();
        }
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

    document.getElementById('truckSearch').addEventListener('input', filterList);
    document.getElementById('statusFilter').addEventListener('change', filterList);
    document.querySelectorAll('.resident-tracker-filter-chip').forEach(chip => {
      chip.addEventListener('click', () => {
        const statusFilter = document.getElementById('statusFilter');
        statusFilter.value = chip.dataset.statusValue;
        document.querySelectorAll('.resident-tracker-filter-chip').forEach(item => {
          item.classList.toggle('is-active', item === chip);
        });
        filterList();
      });
    });

    initialTrucks.forEach(truck => {
      truckCache[truck.id] = truck;
      upsertMarker(truck);
    });
    startCountdown();
    filterList();
  </script>
@endpush
