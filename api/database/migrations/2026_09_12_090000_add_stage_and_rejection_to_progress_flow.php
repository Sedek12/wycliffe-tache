<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_updates', function (Blueprint $table) {
            // Étape choisie : demarrage | mi_parcours | finalisation | livraison
            $table->string('stage')->nullable()->after('percent');
            // « Dévaluation » d'une version par le responsable / chef
            $table->dateTime('rejected_at')->nullable()->after('reviewed_by');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('task_user', function (Blueprint $table) {
            // L'assigné demande la réouverture de sa part après livraison à 100 %
            $table->dateTime('reopen_requested_at')->nullable()->after('done_at');
        });
    }

    public function down(): void
    {
        Schema::table('progress_updates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['stage', 'rejected_at']);
        });

        Schema::table('task_user', function (Blueprint $table) {
            $table->dropColumn('reopen_requested_at');
        });
    }
};
