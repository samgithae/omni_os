<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_event_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_event_id')->constrained()->cascadeOnDelete();
            $table->string('author');                 // human | hermes | agent
            $table->text('body');
            $table->jsonb('metadata')->nullable();
            $table->boolean('is_instruction')->default(false);
            $table->string('instruction_status')->nullable();  // null | pending | acknowledged | addressed
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['activity_event_id', 'created_at']);
            $table->index(['is_instruction', 'instruction_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_event_comments');
    }
};
