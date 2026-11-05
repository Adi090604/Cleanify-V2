<?php

return [
    'smart_service_coverage' => [
        'enabled' => env('SMART_SERVICE_COVERAGE_ENABLED', false),
        'stale_after_hours' => (int) env('SMART_SERVICE_COVERAGE_STALE_AFTER_HOURS', 24),
    ],
];
