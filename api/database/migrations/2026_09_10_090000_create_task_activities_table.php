<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');                 // progress_added, status_changed, member_removed…
            $table->string('stream')->default('activity'); // 'activity' (gauche) | 'authority' (droite)
            $table->text('description');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'stream', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
    }
};
