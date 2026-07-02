<?php

use App\Models\BrandSequenceConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_sequence_configs', function (Blueprint $table) {
            $table->string('subcategory')->nullable()->after('segment');
            $table->index(['brand_id', 'segment', 'subcategory']);
        });

        // Backfill existing configs: generic deer → general, hiring deer → hiring
        BrandSequenceConfig::where('segment', 'deer')
            ->whereNull('subcategory')
            ->whereNull('source_condition')
            ->update(['subcategory' => 'general']);

        BrandSequenceConfig::where('segment', 'deer')
            ->whereNull('subcategory')
            ->where('source_condition', 'hiring_signal_%')
            ->update(['subcategory' => 'hiring']);

        // Any other configs without subcategory → general
        BrandSequenceConfig::whereNull('subcategory')
            ->update(['subcategory' => 'general']);

        // Drop old unique constraint, add new one covering subcategory
        Schema::table('brand_sequence_configs', function (Blueprint $table) {
            $table->dropUnique('brand_seq_config_unique');
            $table->unique(
                ['brand_id', 'segment', 'subcategory'],
                'brand_seq_config_subcategory_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('brand_sequence_configs', function (Blueprint $table) {
            $table->dropUnique('brand_seq_config_subcategory_unique');
            $table->unique(
                ['brand_id', 'segment', 'source_condition'],
                'brand_seq_config_unique'
            );
            $table->dropIndex(['brand_id', 'segment', 'subcategory']);
            $table->dropColumn('subcategory');
        });
    }
};
