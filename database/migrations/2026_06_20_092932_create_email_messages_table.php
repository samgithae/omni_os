<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence_step')->default(1);
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status')->default('draft');    // draft, queued, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Invariant #3: Idempotency — unique key per (lead, sequence_step)
            $table->unique(['lead_id', 'sequence_step'], 'email_messages_lead_step_unique');
            $table->index(['brand_id', 'status']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
