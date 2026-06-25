<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            // Approval workflow: pending → approved | rejected
            $table->string('approval_status')->default('pending')->after('status');
            // draft | pending_approval | approved | rejected | queued | sent | failed
            $table->timestamp('approved_at')->nullable()->after('approval_status');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->text('approval_notes')->nullable()->after('rejected_at');
            $table->timestamp('scheduled_for')->nullable()->after('approval_notes');

            $table->index(['brand_id', 'approval_status']);
            $table->index(['lead_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropIndex(['brand_id', 'approval_status']);
            $table->dropIndex(['lead_id', 'approval_status']);
            $table->dropColumn([
                'approval_status',
                'approved_at',
                'rejected_at',
                'approval_notes',
                'scheduled_for',
            ]);
        });
    }
};
