<?php

test('admin tracker markers and legend share the same truck status visuals', function () {
    $view = file_get_contents(resource_path('views/admin/tracker.blade.php'));
    $css = file_get_contents(resource_path('css/app.css'));
    $legendStart = strpos($view, 'Truck Status');
    $legendEnd = strpos($view, 'return div;', $legendStart);
    $legend = substr($view, $legendStart, $legendEnd - $legendStart);

    expect($view)
        ->toContain("active: { label: 'Active', markerClass: 'active' }")
        ->toContain("on_break: { label: 'On Break', markerClass: 'on-break' }")
        ->toContain("offline: { label: 'Offline', markerClass: 'offline' }")
        ->toContain("maintenance: { label: 'Maintenance', markerClass: 'maintenance' }")
        ->toContain('truck-map-marker__body--admin truck-map-marker--${statusMeta.markerClass}')
        ->toContain('Object.values(truckStatusMeta).map')
        ->toContain('markerCluster = L.markerClusterGroup')
        ->toContain('markerCluster.addLayer(marker)')
        ->toContain('coverageCircleLayer.clearLayers()')
        ->toContain('onclick="openTruckView(${truck.id})"')
        ->and($legend)
        ->toContain('fas fa-truck')
        ->toContain('truck-map-marker__body--legend')
        ->not->toContain('<img')
        ->not->toContain('marker-icon-')
        ->and($css)
        ->toContain('.truck-map-marker__body--admin')
        ->toContain('background: var(--truck-status-color)')
        ->toContain('.truck-map-marker--active { --truck-status-color: #22c55e; }')
        ->toContain('.truck-map-marker--on-break { --truck-status-color: #eab308; }')
        ->toContain('.truck-map-marker--offline { --truck-status-color: #ef4444; }')
        ->toContain('.truck-map-marker--maintenance { --truck-status-color: #3b82f6; }');
});
