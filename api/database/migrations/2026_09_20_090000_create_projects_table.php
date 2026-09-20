<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('effet')->nullable(); // logique PTAB : Effet
            $table->text('extrant')->nullable(); // logique PTAB : Extrant
            $table->decimal('budget_previsionnel', 14, 2)->nullable();
            $table->json('parties_prenantes')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->string('status')->default('brouillon'); // App\Enums\ProjectStatus
            $table->timestamps();

            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
