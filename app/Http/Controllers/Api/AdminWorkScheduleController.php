<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;

class AdminWorkScheduleController extends Controller
{
    /**
     * Menampilkan daftar jadwal kerja.
     */
    public function index()
    {
        $workSchedules = WorkSchedule::query()
            ->orderBy('name')
            ->paginate(50);

        return response()->json($workSchedules);
    }

    /**
     * Menampilkan detail jadwal kerja.
     */
    public function show(WorkSchedule $workSchedule)
    {
        return response()->json([
            'data' => $workSchedule,
        ]);
    }

    /**
     * Memperbarui jadwal kerja.
     */
    public function update(
        Request $request,
        WorkSchedule $workSchedule
    ) {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'start_time' => [
                'sometimes',
                'required',
                'date_format:H:i',
            ],
            'end_time' => [
                'sometimes',
                'required',
                'date_format:H:i',
            ],
            'late_tolerance_minutes' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],
            'office_latitude' => [
                'sometimes',
                'required',
                'numeric',
                'between:-90,90',
            ],
            'office_longitude' => [
                'sometimes',
                'required',
                'numeric',
                'between:-180,180',
            ],
            'geofence_radius_meters' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],
            'work_days' => [
                'sometimes',
                'required',
                'array',
            ],
            'work_days.*' => [
                'required',
                'integer',
                'between:1,7',
            ],
            'is_active' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ]);

        $workSchedule->update($validated);

        return response()->json([
            'message' => 'Jadwal kerja berhasil diperbarui.',
            'data' => $workSchedule->fresh(),
        ]);
    }
}
