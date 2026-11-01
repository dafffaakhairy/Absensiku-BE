<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AdminEmployeeController;
use App\Http\Controllers\Api\AdminAttendanceController;
use App\Http\Controllers\Api\AdminWorkScheduleController;
use App\Http\Middleware\CheckTokenInactivity;
use App\Http\Middleware\EnsureAdmin;
use App\Models\WorkSchedule;


Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware([
    'auth:sanctum',
    CheckTokenInactivity::class,
])->group(function () {

    // Autentikasi
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Absensi karyawan
    Route::get('/attendance/today', [
        AttendanceController::class,
        'today',
    ]);

    Route::post('/attendance/check-in', [
        AttendanceController::class,
        'checkIn',
    ])->middleware('throttle:5,1');

    Route::post('/attendance/check-out', [
        AttendanceController::class,
        'checkOut',
    ])->middleware('throttle:5,1');

    Route::get('/attendance/history', [
        AttendanceController::class,
        'history',
    ]);

    Route::get('/attendance/check-out/preview', [
        AttendanceController::class,
        'previewCheckOut',
    ]);

    // Informasi jadwal dan lokasi kantor
    Route::get('/attendance/schedule', function () {
    $schedule = WorkSchedule::query()
        ->where('is_active', true)
        ->first([
            'id',
            'name',
            'office_latitude',
            'office_longitude',
            'geofence_radius_meters',
            'start_time',
            'end_time',
        ]);

    if (! $schedule) {
        return response()->json([
            'message' => 'Belum ada jadwal kerja aktif.',
        ], 404);
    }

    return response()->json([
        'data' => $schedule,
    ]);
    });

    // Hanya admin
    Route::middleware(EnsureAdmin::class)
        ->prefix('admin')
        ->group(function () {

            // Manajemen karyawan
            Route::get('/employees', [
                AdminEmployeeController::class,
                'index',
            ]);

            Route::post('/employees', [
                AdminEmployeeController::class,
                'store',
            ]);

            Route::put('/employees/{user}', [
                AdminEmployeeController::class,
                'update',
            ]);

            Route::patch('/employees/{user}/deactivate', [
                AdminEmployeeController::class,
                'deactivate',
            ]);

            
Route::patch(
    '/employees/{user}/activate',
    [AdminEmployeeController::class, 'activate']
);


            // Rekap absensi
            Route::get('/attendances', [
                AdminAttendanceController::class,
                'index',
            ]);

            Route::put('/attendances/{attendance}', [
                AdminAttendanceController::class,
                'update',
            ]);

            // Jadwal kerja
            Route::get('/work-schedules', [
                AdminWorkScheduleController::class,
                'index',
            ]);

            Route::get('/work-schedules/{workSchedule}', [
                AdminWorkScheduleController::class,
                'show',
            ]);

            Route::put('/work-schedules/{workSchedule}', [
                AdminWorkScheduleController::class,
                'update',
            ]);
        });
});
