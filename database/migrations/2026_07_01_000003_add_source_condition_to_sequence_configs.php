<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_sequence_configs', function (Blueprint $table) {
            $table->string('source_condition')->nullable()->after('segment')
                ->comment('Optional LIKE pattern to filter by lead source (e.g. hiring_signal_%). When null, matches any source.');
            $table->index(['brand_id', 'segment', 'source_condition']);
        });
    }

    public function down(): void
    {
        Schema::table('brand_sequence_configs', function (Blueprint $table) {
            $table->dropIndex(['brand_id', 'segment', 'source_condition']);
            $table->dropColumn('source_condition');
        });
    }
};
