<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('email_confidence')->nullable()->after('email_verified')
                ->comment('verified|inferred|estimated|unavailable');
            $table->timestamp('enriched_at')->nullable()->after('email_confidence');
            $table->text('enrichment_notes')->nullable()->after('enriched_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['email_confidence', 'enriched_at', 'enrichment_notes']);
        });
    }
};
