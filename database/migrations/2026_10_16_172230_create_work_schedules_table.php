<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('work_schedules', function (Blueprint $table) {
        $table->id();
        $table->string('name')->default('Jam Kerja Kantor');
        $table->time('start_time');
        $table->time('end_time');
        $table->unsignedSmallInteger('late_tolerance_minutes')
            ->default(0);

        $table->decimal('office_latitude', 10, 7);
        $table->decimal('office_longitude', 10, 7);
        $table->unsignedInteger('geofence_radius_meters')->default(100);

        $table->jsonb('work_days')->default('[1,2,3,4,5]');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('work_schedules');
}
};
