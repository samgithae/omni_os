<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_sequence_configs', function (Blueprint $table) {
            // Drop the old (brand_id, segment) unique constraint
            $table->dropUnique('brand_sequence_configs_brand_id_segment_unique');
        });

        // Add new unique constraint covering source_condition
        // PostgreSQL treats NULL as distinct in unique constraints, so
        // (brand_id=2, segment='deer', source_condition=null) and
        // (brand_id=2, segment='deer', source_condition='hiring_signal_%')
        // are considered different rows.
        Schema::table('brand_sequence_configs', function (Blueprint $table) {
            $table->unique(['brand_id', 'segment', 'source_condition'], 'brand_seq_config_unique');
        });
    }

    public function down(): void
    {
        Schema::table('brand_sequence_configs', function (Blueprint $table) {
            $table->dropUnique('brand_seq_config_unique');
        });

        Schema::table('brand_sequence_configs', function (Blueprint $table) {
            $table->unique(['brand_id', 'segment']);
        });
    }
};
