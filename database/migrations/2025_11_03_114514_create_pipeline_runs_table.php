<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pipeline_runs', function (Blueprint $table) {
            $table->id();
            $table->string('pipeline_name');
            $table->string('status'); // running, completed, failed
            $table->jsonb('payload');
            $table->jsonb('final')->nullable();
            $table->jsonb('meta')->nullable();
            $table->dateTimeTz('created_at');
            $table->dateTimeTz('finished_at')->nullable();
            $table->index(['pipeline_name', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_runs');
    }
};
