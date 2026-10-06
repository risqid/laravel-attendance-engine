<?php

use Illuminate\Support\Facades\Route;
use Unwahas\AttendanceEngine\Http\Controllers\AttendanceChallengeController;
use Unwahas\AttendanceEngine\Http\Controllers\AttendanceScanController;

Route::get('/sessions/{session}/challenge', AttendanceChallengeController::class)
    ->middleware('throttle:120,1');

Route::post('/scan', AttendanceScanController::class)
    ->middleware('throttle:30,1');
