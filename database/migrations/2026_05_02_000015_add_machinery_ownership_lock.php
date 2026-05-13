<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add machinery ownership lock to prevent changes after DPR creation
     */
    public function up(): void
    {
        if (!Schema::hasColumn('machineries', 'ownership_locked')) {
            Schema::table('machineries', function (Blueprint $table) {
                $table->boolean('ownership_locked')->default(false)->after('owned_by');
                $table->timestamp('ownership_locked_at')->nullable()->after('ownership_locked');
                $table->unsignedBigInteger('ownership_locked_by')->nullable()->after('ownership_locked_at');
                
                // Index for queries
                $table->index('ownership_locked', 'idx_ownership_locked');
                
                // Foreign key
                $table->foreign('ownership_locked_by')->references('id')->on('users');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('machineries', 'ownership_locked')) {
            Schema::table('machineries', function (Blueprint $table) {
                $table->dropForeign(['ownership_locked_by']);
                $table->dropIndex('idx_ownership_locked');
                $table->dropColumn(['ownership_locked', 'ownership_locked_at', 'ownership_locked_by']);
            });
        }
    }
};
