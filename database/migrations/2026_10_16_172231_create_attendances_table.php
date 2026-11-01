<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('attendances', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->foreignId('work_schedule_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();

        $table->date('work_date');

        $table->timestampTz('check_in_at')->nullable();
        $table->decimal('check_in_latitude', 10, 7)->nullable();
        $table->decimal('check_in_longitude', 10, 7)->nullable();
        $table->string('check_in_photo_path')->nullable();

        $table->timestampTz('check_out_at')->nullable();
        $table->decimal('check_out_latitude', 10, 7)->nullable();
        $table->decimal('check_out_longitude', 10, 7)->nullable();

        $table->string('status', 20)->default('present');
        $table->text('notes')->nullable();

        $table->timestamps();

        $table->unique(['user_id', 'work_date']);
    });
}

public function down(): void
{
    Schema::dropIfExists('attendances');
}
};
