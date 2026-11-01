<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'work_schedule_id',
        'work_date',
        'check_in_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_photo_path',
        'check_out_at',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_photo_path',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
'check_in_at' => 'immutable_datetime',
'check_out_at' => 'immutable_datetime',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }
}