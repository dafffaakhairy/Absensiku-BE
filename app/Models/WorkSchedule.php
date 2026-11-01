<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedule extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'late_tolerance_minutes',
        'office_latitude',
        'office_longitude',
        'geofence_radius_meters',
        'work_days',
        'is_active',
    ];

    protected function casts(): array
{
    return [
        'work_days' => 'array',
        'is_active' => 'boolean',
        'office_latitude' => 'float',
        'office_longitude' => 'float',
        'geofence_radius_meters' => 'integer',
        'late_tolerance_minutes' => 'integer',
    ];
}

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}