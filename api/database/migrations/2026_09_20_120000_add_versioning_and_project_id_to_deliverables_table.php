<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('task_id')
                ->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1)->after('note');
            $table->string('group_key')->nullable()->after('version');

            $table->index(['project_id', 'created_at']);
            $table->index('group_key');
        });

        // Un livrable appartient à une tâche OU à un projet (task_id devient facultatif).
        Schema::table('deliverables', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn(['version', 'group_key']);
            $table->foreignId('task_id')->nullable(false)->change();
        });
    }
};
