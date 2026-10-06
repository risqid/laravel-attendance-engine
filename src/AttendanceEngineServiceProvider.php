<?php

namespace Unwahas\AttendanceEngine;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Unwahas\AttendanceEngine\Contracts\EligibilityResolver;
use Unwahas\AttendanceEngine\Contracts\LocationPolicy;
use Unwahas\AttendanceEngine\Contracts\ParticipantResolver;

class AttendanceEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/attendance-engine.php', 'attendance-engine');

        $this->app->bind(ParticipantResolver::class, config('attendance-engine.contracts.participant_resolver'));
        $this->app->bind(EligibilityResolver::class, config('attendance-engine.contracts.eligibility_resolver'));
        $this->app->bind(LocationPolicy::class, config('attendance-engine.contracts.location_policy'));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/attendance-engine.php' => config_path('attendance-engine.php'),
        ], 'attendance-engine-config');

        if (config('attendance-engine.enabled_routes')) {
            Route::middleware(config('attendance-engine.route_middleware', ['web']))
                ->prefix(config('attendance-engine.route_prefix', 'attendance-engine'))
                ->group(__DIR__ . '/../routes/attendance-engine.php');
        }
    }
}
