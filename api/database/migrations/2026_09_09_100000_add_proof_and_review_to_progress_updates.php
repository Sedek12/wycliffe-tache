<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_updates', function (Blueprint $table) {
            // Preuve jointe à chaque point d'avancement
            $table->string('disk')->nullable()->after('comment');
            $table->string('path')->nullable()->after('disk');
            $table->string('original_name')->nullable()->after('path');
            $table->string('mime')->nullable()->after('original_name');
            $table->unsignedBigInteger('size')->nullable()->after('mime');
            // Revue par le responsable / chef
            $table->dateTime('reviewed_at')->nullable()->after('size');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('progress_updates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['disk', 'path', 'original_name', 'mime', 'size', 'reviewed_at']);
        });
    }
};
