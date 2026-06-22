<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_job_runs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name');
            $table->string('command')->nullable();
            $table->string('description')->nullable();
            $table->string('schedule')->nullable();
            $table->string('status');          // running, success, failed
            $table->integer('exit_code')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->text('output_summary')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('job_name');
            $table->index('status');
            $table->index('started_at');
            $table->index(['job_name', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_job_runs');
    }
};