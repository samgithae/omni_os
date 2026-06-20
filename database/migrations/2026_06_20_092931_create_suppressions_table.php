<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->enum('reason', ['unsubscribe', 'hard_bounce', 'complaint', 'manual'])->default('unsubscribe');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Invariant #2: One suppression per (brand, email) — no duplicates
            $table->unique(['brand_id', 'email'], 'suppressions_brand_email_unique');
            $table->index(['brand_id', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppressions');
    }
};
