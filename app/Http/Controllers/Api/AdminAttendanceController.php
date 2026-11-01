<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:present,late'],
        ]);

        $query = Attendance::query()
            ->with([
                'user:id,employee_number,name,email',
                'workSchedule:id,name,start_time,end_time',
            ]);

        if (isset($filters['date_from'])) {
            $query->whereDate('work_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('work_date', '<=', $filters['date_to']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return response()->json(
            $query->orderByDesc('work_date')
                ->orderByDesc('check_in_at')
                ->paginate(50)
        );
    }

    /**
     * Update catatan absensi oleh admin.
     */
    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'work_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'check_in_at' => ['sometimes', 'nullable', 'date'],
            'check_out_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:check_in_at',
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['present', 'late']),
            ],
        ]);

        $attendance->fill($validated);
        $attendance->save();

        $attendance->load([
            'user:id,employee_number,name,email',
            'workSchedule:id,name,start_time,end_time',
        ]);

        return response()->json([
            'message' => 'Data absensi berhasil diperbarui.',
            'data' => $attendance,
        ]);
    }
}
