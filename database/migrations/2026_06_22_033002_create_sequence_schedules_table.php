<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequence_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('segment');
            $table->unsignedTinyInteger('step');
            $table->unsignedInteger('days_after_previous');
            $table->string('purpose')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['brand_id', 'segment', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_schedules');
    }
};
