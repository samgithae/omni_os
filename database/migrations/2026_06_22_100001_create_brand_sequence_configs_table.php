<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_sequence_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->enum('segment', ['rabbit', 'deer', 'mouse', 'elephant', 'all'])->default('all');
            $table->text('prompt_text');
            $table->integer('sequence_steps')->default(4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // One active config per (brand, segment). 'all' is the fallback.
            $table->unique(['brand_id', 'segment']);
            $table->index(['brand_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_sequence_configs');
    }
};
