<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('codename')->unique();
            $table->string('display_name');
            $table->string('role')->nullable();
            $table->text('description')->nullable();
            $table->string('function_area')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_active')->default(true);
            $table->string('token_hash')->nullable()->unique();
            $table->string('token_last_four', 8)->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
