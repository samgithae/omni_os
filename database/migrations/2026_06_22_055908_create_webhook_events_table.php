<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('smtp2go');
            $table->string('event_type');
            $table->string('recipient_email')->nullable();
            $table->string('smtp2go_id')->nullable();
            $table->foreignId('email_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('payload');
            $table->boolean('processed')->default(false);
            $table->text('processing_notes')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->index(['source', 'event_type']);
            $table->index('recipient_email');
            $table->index('smtp2go_id');
            $table->index('processed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
