<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'name']);
        });

        Schema::table('department_user', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->after('role')->constrained('positions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('department_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
        });
        Schema::dropIfExists('positions');
    }
};
