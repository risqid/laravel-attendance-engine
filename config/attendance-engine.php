<?php

return [
    'enabled_routes' => env('ATTENDANCE_ENGINE_ROUTES', false),

    'route_prefix' => env('ATTENDANCE_ENGINE_ROUTE_PREFIX', 'attendance-engine'),

    'route_middleware' => ['web'],

    'secret' => env('ATTENDANCE_ENGINE_SECRET', env('APP_KEY')),

    'timezone' => env('APP_TIMEZONE', config('app.timezone', 'UTC')),

    'defaults' => [
        'qr_rotation_seconds' => 10,
        'qr_grace_windows' => 1,
    ],

    'rules' => [
        'require_check_in_for_checkout' => false,
    ],

    'models' => [
        'session' => Risqid\AttendanceEngine\Models\AttendanceSession::class,
        'record' => Risqid\AttendanceEngine\Models\AttendanceRecord::class,
    ],

    'contracts' => [
        'participant_resolver' => Risqid\AttendanceEngine\Services\NullParticipantResolver::class,
        'eligibility_resolver' => Risqid\AttendanceEngine\Services\DenyAllEligibilityResolver::class,
        'location_policy' => Risqid\AttendanceEngine\Services\DefaultLocationPolicy::class,
    ],
];
