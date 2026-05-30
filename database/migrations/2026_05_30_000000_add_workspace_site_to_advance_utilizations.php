<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advance_utilizations', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable()->after('status');
            $table->unsignedBigInteger('site_id')->nullable()->after('workspace_id');
            $table->index(['workspace_id', 'site_id'], 'idx_workspace_site');
        });
    }

    public function down(): void
    {
        Schema::table('advance_utilizations', function (Blueprint $table) {
            $table->dropIndex('idx_workspace_site');
            $table->dropColumn(['workspace_id', 'site_id']);
        });
    }
};