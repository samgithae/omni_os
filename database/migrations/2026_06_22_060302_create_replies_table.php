<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_email');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->text('body_html')->nullable();
            $table->string('classification')->nullable();
            $table->string('classification_confidence')->nullable();
            $table->text('classification_summary')->nullable();
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->boolean('read')->default(false);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'classification']);
            $table->index(['lead_id', 'received_at']);
            $table->index('read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replies');
    }
};