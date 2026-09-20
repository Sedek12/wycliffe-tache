<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->dateTime('starts_at');
            $table->dateTime('due_at');
            $table->string('status')->default('brouillon'); // App\Enums\TaskStatus
            $table->unsignedTinyInteger('progress')->default(0); // 0..100
            $table->boolean('is_team')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
            $table->index('due_at');
        });

        Schema::create('task_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('instructions')->nullable(); // précisions "qui fait quoi" pour une équipe
            $table->boolean('is_external')->default(false); // membre prêté par un autre département
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_user');
        Schema::dropIfExists('tasks');
    }
};
