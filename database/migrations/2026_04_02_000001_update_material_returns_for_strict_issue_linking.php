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
        // Make issue_id required in material_returns table
        Schema::table('material_returns', function (Blueprint $table) {
            $table->unsignedBigInteger('issue_id')->nullable(false)->change();
            $table->foreign('issue_id')->references('id')->on('material_issues')->onDelete('cascade')->change();
        });

        // Add issue_item_id to material_return_items table
        Schema::table('material_return_items', function (Blueprint $table) {
            $table->unsignedBigInteger('issue_item_id')->nullable()->after('return_id');
            $table->foreign('issue_item_id')->references('id')->on('material_issue_items')->onDelete('cascade');
            $table->index('issue_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_returns', function (Blueprint $table) {
            $table->unsignedBigInteger('issue_id')->nullable()->change();
            $table->foreign('issue_id')->references('id')->on('material_issues')->onDelete('set null')->change();
        });

        Schema::table('material_return_items', function (Blueprint $table) {
            $table->dropForeign(['issue_item_id']);
            $table->dropIndex(['issue_item_id']);
            $table->dropColumn('issue_item_id');
        });
    }
};
