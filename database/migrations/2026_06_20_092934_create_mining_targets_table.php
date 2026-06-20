<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mining_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();

            // Geography is data, not code (Section 10)
            $table->string('country')->default('Kenya');
            $table->string('city')->nullable();

            // What to mine
            $table->string('category');                    // e.g. "training provider", "SACCO", "NGO"
            $table->string('search_template')->nullable(); // e.g. "{category} in {city}"
            $table->enum('segment', ['rabbit', 'deer', 'mouse', 'elephant'])->default('rabbit');

            // Cadence
            $table->string('cadence')->default('weekly');  // daily, weekly, monthly
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_mined_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'is_active']);
            $table->index(['country', 'city']);
            $table->index('segment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mining_targets');
    }
};
