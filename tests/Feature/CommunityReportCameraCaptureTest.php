<?php

use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('community report photo control offers camera and device inputs with shared image state', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('community-reports'))->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('Take Photo', 'Choose from Device', 'Remove Photo', 'Selected report photo preview')
        ->and(preg_match('/<input\b[^>]*id="reportCameraImageInput"[^>]*>/', $html, $cameraMatch))->toBe(1)
        ->and($cameraMatch[0])->toContain('accept="image/*"', 'capture="environment"')
        ->and(preg_match('/<input\b[^>]*id="reportDeviceImageInput"[^>]*>/', $html, $deviceMatch))->toBe(1)
        ->and($deviceMatch[0])->toContain('name="image"', 'accept="image/*"')
        ->and($deviceMatch[0])->not->toContain('capture=')
        ->and($html)->toContain(
            'if (!input.files?.length) return;',
            "input.setAttribute('name', 'image');",
            "alternateInput.removeAttribute('name');",
            "alternateInput.value = '';",
            "reportImageLabel.textContent = 'Replace Photo';",
            "reportImageLabel.textContent = 'Add Photo';",
        );
});

test('web report submission stores either selected image through the unchanged image field', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('reports.store'), [
        'description' => 'Report submitted with a browser-selected image.',
        'image' => UploadedFile::fake()->image('camera-capture.jpg'),
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $report = Report::query()->sole();
    expect($report->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($report->image_path);
});

test('existing backend report image validation still rejects invalid files', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('reports.store'), [
        'description' => 'Invalid attachment report.',
        'image' => UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors(['image']);

    expect(Report::count())->toBe(0);
});
