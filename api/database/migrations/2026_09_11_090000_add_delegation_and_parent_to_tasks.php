<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Sous-tâche : rattachement à une tâche parente (supprimée => sous-tâches supprimées).
            $table->foreignId('parent_id')
                ->nullable()
                ->after('department_id')
                ->constrained('tasks')
                ->cascadeOnDelete();

            // Pouvoirs que le chef délègue au responsable de la tâche (opt-in strict).
            $table->json('delegated_abilities')->nullable()->after('supervisor_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'delegated_abilities']);
        });
    }
};
