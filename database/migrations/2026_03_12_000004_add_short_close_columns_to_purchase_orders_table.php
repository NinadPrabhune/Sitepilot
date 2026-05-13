<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->text('short_close_reason')->nullable()->after('rejection_reason');
            $table->timestamp('short_closed_at')->nullable()->after('short_close_reason');
            $table->unsignedBigInteger('short_closed_by')->nullable()->after('short_closed_at');
            
            // Add foreign key if users table exists
            $table->foreign('short_closed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['short_closed_by']);
            $table->dropColumn(['short_close_reason', 'short_closed_at', 'short_closed_by']);
        });
    }
};
