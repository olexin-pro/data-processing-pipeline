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
        Schema::create('pipeline_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('pipeline_runs')->onDelete('cascade');
            $table->string('step_class');
            $table->string('key')->nullable();
            $table->string('policy')->nullable();
            $table->string('status'); // ok, skipped, failed
            $table->decimal('duration_ms', 10, 2);
            $table->json('result')->nullable();
            $table->dateTimeTz('created_at');
            $table->index('run_id');
            $table->index('step_class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_steps');
    }
};
