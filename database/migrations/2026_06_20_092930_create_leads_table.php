<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();

            // Segmentation
            $table->enum('segment', ['rabbit', 'deer', 'mouse', 'elephant'])->default('rabbit');
            $table->string('category')->nullable();         // e.g. "training provider", "SACCO", "NGO"
            $table->string('subcategory')->nullable();

            // Geography — data, not code (Section 10)
            $table->string('country')->default('Kenya');
            $table->string('city')->nullable();
            $table->string('address')->nullable();

            // Lead state machine: new → enriching → enriched | no_email_found
            $table->string('status')->default('new');
            $table->integer('enrichment_attempts')->default(0);
            $table->boolean('email_verified')->default(false);

            // Scoring
            $table->integer('score')->default(0);

            // Source tracking
            $table->string('source')->nullable();           // e.g. "google_maps", "google_search", "manual"
            $table->string('source_url')->nullable();

            // Raw mining payload (JSONB for flexible data)
            $table->jsonb('raw_data')->nullable();

            $table->timestamps();

            // Invariant #1: Dedup — unique on (brand, email)
            $table->unique(['brand_id', 'email'], 'leads_brand_email_unique');

            // Indexes per brief Section 10
            $table->index('brand_id');
            $table->index('status');
            $table->index('email');
            $table->index('source');
            $table->index('created_at');
            $table->index(['brand_id', 'segment']);
            $table->index(['brand_id', 'status']);
            $table->index(['country', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
