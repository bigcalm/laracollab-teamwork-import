<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teamwork_id_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teamwork_id');
            $table->string('teamwork_type');
            $table->morphs('local');
            $table->foreignId('import_run_id')->constrained('teamwork_import_runs')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['teamwork_id', 'teamwork_type', 'local_type']);
            $table->index('import_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teamwork_id_mappings');
    }
};
