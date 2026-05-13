<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add site_id column to machinery_payment_requests table
     */
    public function up(): void
    {
        Schema::table('machinery_payment_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('site_id')->nullable()->after('workspace_id');
            $table->index(['site_id'], 'idx_machinery_payment_site_id');
        });
    }

    public function down(): void
    {
        Schema::table('machinery_payment_requests', function (Blueprint $table) {
            $table->dropIndex('idx_machinery_payment_site_id');
            $table->dropColumn('site_id');
        });
    }
};
