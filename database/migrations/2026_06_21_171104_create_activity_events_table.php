<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('event_type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('severity')->default('info');
            $table->timestamps();

            $table->index(['brand_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
    }
};
