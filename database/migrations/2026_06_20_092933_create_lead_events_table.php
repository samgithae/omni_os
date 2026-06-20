<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');     // mined, enriched, email_sent, email_opened, reply_received, status_changed, suppressed, scored
            $table->jsonb('payload')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'event_type']);
            $table->index(['brand_id', 'event_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_events');
    }
};
