<?php

use App\Http\Controllers\Admin\ServiceZoneController;
use App\Http\Controllers\Admin\TrackerController;
use App\Models\ServiceZone;
use App\Models\Truck;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('truck suggestions start at TRK-01 and ignore unrelated codes', function () {
    expect(Truck::suggestNextCode([]))->toBe('TRK-01')
        ->and(Truck::suggestNextCode(['CUSTOM-99', 'TRUCK-20', 'TRK-ABC']))->toBe('TRK-01');
});

test('truck suggestions increment the highest matching code', function () {
    expect(Truck::suggestNextCode(['TRK-01']))->toBe('TRK-02')
        ->and(Truck::suggestNextCode(['TRK-01', 'TRK-09', 'CUSTOM-100']))->toBe('TRK-10');
});

test('truck suggestions preserve a consistent existing padding width', function () {
    expect(Truck::suggestNextCode(['TRK-001', 'TRK-009']))->toBe('TRK-010');
});

test('service zone suggestions start at Zone 1 and ignore custom names', function () {
    expect(ServiceZone::suggestNextName([]))->toBe('Zone 1')
        ->and(ServiceZone::suggestNextName(['Downtown', 'zone 20', 'Zone A']))->toBe('Zone 1');
});

test('service zone suggestions increment the highest matching name', function () {
    expect(ServiceZone::suggestNextName(['Zone 1']))->toBe('Zone 2')
        ->and(ServiceZone::suggestNextName(['Zone 1', 'Zone 9', 'Custom Zone 100']))->toBe('Zone 10');
});

test('admin index views receive suggestions based on existing records', function () {
    Truck::create(['code' => 'TRK-09', 'driver' => 'Driver', 'route' => 'Zone 1', 'status' => 'active']);
    ServiceZone::create(['name' => 'Zone 9', 'barangay' => 'Test', 'status' => 'active']);

    $trackerView = app(TrackerController::class)->index();
    $serviceZoneView = app(ServiceZoneController::class)->index();

    expect($trackerView->getData()['suggestedTruckCode'])->toBe('TRK-10')
        ->and($serviceZoneView->getData()['suggestedZoneName'])->toBe('Zone 10');
});

test('add suggestions are editable and edit forms retain persisted-value assignments', function () {
    $trackerView = file_get_contents(resource_path('views/admin/tracker.blade.php'));
    $serviceZoneView = file_get_contents(resource_path('views/admin/service-zones.blade.php'));

    expect($trackerView)
        ->toContain('id="addTruckCode"')
        ->toContain("old('code', \$suggestedTruckCode)")
        ->not->toMatch('/id="addTruckCode"[^>]*readonly/')
        ->toContain("document.getElementById('editTruckCode').value = truck.code;")
        ->and($serviceZoneView)
        ->toContain('id="createServiceZoneName"')
        ->toContain("old('name', \$suggestedZoneName)")
        ->not->toMatch('/id="createServiceZoneName"[^>]*readonly/')
        ->toContain("document.getElementById('editServiceZoneName').value = zone.name;");
});
