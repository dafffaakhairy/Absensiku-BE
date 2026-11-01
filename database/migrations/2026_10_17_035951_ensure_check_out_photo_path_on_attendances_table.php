<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendances', 'check_out_photo_path')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->string('check_out_photo_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendances', 'check_out_photo_path')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropColumn('check_out_photo_path');
            });
        }
    }
};