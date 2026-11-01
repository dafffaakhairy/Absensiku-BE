<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function today(Request $request)
    {
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();

        return response()->json(['data' => $attendance]);
    }

    public function checkIn(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $now = now();
        $date = $now->toDateString();

        $schedule = WorkSchedule::where('is_active', true)->first();

        if (! $schedule) {
            return response()->json([
                'message' => 'Jadwal kerja belum dikonfigurasi.',
            ], 422);
        }

        if (! in_array($now->dayOfWeekIso, $schedule->work_days ?? [], true)) {
            return response()->json([
                'message' => 'Hari ini bukan hari kerja.',
            ], 422);
        }

        $distance = $this->distanceInMeters(
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) $schedule->office_latitude,
            (float) $schedule->office_longitude
        );

        if ($distance > $schedule->geofence_radius_meters) {
            return response()->json([
                'message' => 'Anda berada di luar area kantor.',
                'distance_meters' => round($distance),
            ], 422);
        }

        if (
            Attendance::where('user_id', $request->user()->id)
                ->whereDate('work_date', $date)
                ->exists()
        ) {
            return response()->json([
                'message' => 'Absensi hari ini sudah tercatat.',
            ], 409);
        }

        $start = Carbon::parse($date . ' ' . $schedule->start_time);
        $lateDeadline = $start->copy()
            ->addMinutes($schedule->late_tolerance_minutes);

        $status = $now->greaterThan($lateDeadline) ? 'late' : 'present';

        $photoPath = $request->file('photo')->store(
            'attendance-selfies',
            'public'
        );

        try {
            $attendance = DB::transaction(function () use (
                $request,
                $schedule,
                $data,
                $date,
                $now,
                $status,
                $photoPath
            ) {
                return Attendance::create([
                    'user_id' => $request->user()->id,
                    'work_schedule_id' => $schedule->id,
                    'work_date' => $date,
                    'check_in_at' => $now,
                    'check_in_latitude' => $data['latitude'],
                    'check_in_longitude' => $data['longitude'],
                    'check_in_photo_path' => $photoPath,
                    'status' => $status,
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            Storage::disk('local')->delete($photoPath);

            // PostgreSQL unique violation.
            if ($e->getCode() === '23505') {
                return response()->json([
                    'message' => 'Absensi hari ini sudah tercatat.',
                ], 409);
            }

            throw $e;
        }

        return response()->json([
            'message' => 'Check-in berhasil.',
            'data' => $attendance,
        ], 201);
    }

public function checkOut(Request $request)
{
    $data = $request->validate([
        'latitude' => ['required', 'numeric', 'between:-90,90'],
        'longitude' => ['required', 'numeric', 'between:-180,180'],
        'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
    ]);

    $attendance = Attendance::where('user_id', $request->user()->id)
        ->whereDate('work_date', now()->toDateString())
        ->first();

    if (! $attendance || ! $attendance->check_in_at) {
        return response()->json([
            'message' => 'Anda belum melakukan check-in.',
        ], 422);
    }

    if ($attendance->check_out_at) {
        return response()->json([
            'message' => 'Check-out hari ini sudah tercatat.',
        ], 409);
    }

$schedule = $attendance->workSchedule;

// Tolak check-out jika jadwal kerja tidak ditemukan.
if (! $schedule) {
    return response()->json([
        'message' => 'Jadwal kerja untuk absensi ini tidak ditemukan. Silakan hubungi administrator.',
    ], 422);
}

// Hitung jarak pengguna dari lokasi kantor.
$distance = $this->distanceInMeters(
    (float) $data['latitude'],
    (float) $data['longitude'],
    (float) $schedule->office_latitude,
    (float) $schedule->office_longitude
);

// Tolak check-out jika pengguna berada di luar radius kantor.
if ($distance > $schedule->geofence_radius_meters) {
    return response()->json([
        'message' => 'Anda berada di luar area kantor.',
        'distance_meters' => round($distance),
    ], 422);
}

    // Simpan foto selfie check-out.
    $photoPath = $request->file('photo')->store(
        'attendance-selfies',
        'public'
    );

    $attendance->update([
        'check_out_at' => now(),
        'check_out_latitude' => $data['latitude'],
        'check_out_longitude' => $data['longitude'],
        'check_out_photo_path' => $photoPath,
    ]);

    return response()->json([
        'message' => 'Check-out berhasil.',
        'data' => $attendance->fresh(),
    ]);
}

public function previewCheckOut(Request $request)
{
    $attendance = Attendance::where('user_id', $request->user()->id)
        ->whereDate('work_date', now()->toDateString())
        ->first();

    if (! $attendance || ! $attendance->check_in_at) {
        return response()->json([
            'message' => 'Anda belum melakukan check-in.',
        ], 422);
    }

    if ($attendance->check_out_at) {
        return response()->json([
            'message' => 'Check-out hari ini sudah tercatat.',
        ], 409);
    }
$minimumWorkMinutes = 9 * 60;

$workedMinutes = max(
    0,
    (int) floor(
        (now()->getTimestamp() - $attendance->check_in_at->getTimestamp()
             ) / 60
    )
);

$remainingMinutes = max(
    0,
    $minimumWorkMinutes - $workedMinutes
);

    return response()->json([
        'requires_confirmation' => $remainingMinutes > 0,
        'worked_minutes' => $workedMinutes,
        'remaining_minutes' => $remainingMinutes,
        'minimum_work_minutes' => $minimumWorkMinutes,
    ]);
}

    public function history(Request $request)
    {
        $data = Attendance::where('user_id', $request->user()->id)
            ->orderByDesc('work_date')
            ->paginate(20);

        return response()->json($data);
    }

    private function distanceInMeters(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(
            sqrt($a),
            sqrt(max(0, 1 - $a))
        );
    }
}