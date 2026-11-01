<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('employee_number', 30)->nullable()->unique();
        $table->string('role', 20)->default('employee');
        $table->boolean('is_active')->default(true);
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropUnique(['employee_number']);
        $table->dropColumn([
            'employee_number',
            'role',
            'is_active',
        ]);
    });
}
};
