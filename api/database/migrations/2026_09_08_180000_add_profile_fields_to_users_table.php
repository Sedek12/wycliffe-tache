<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('system_role')->nullable()->after('email');
            $table->string('phone')->nullable()->after('system_role');
            $table->string('job_title')->nullable()->after('phone');
            $table->string('avatar')->nullable()->after('job_title');
            $table->boolean('is_active')->default(true)->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['system_role', 'phone', 'job_title', 'avatar', 'is_active']);
        });
    }
};
