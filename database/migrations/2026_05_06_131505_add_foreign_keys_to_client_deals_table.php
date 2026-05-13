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
        Schema::table('client_deals', function (Blueprint $table) {
            $table->foreign(['client_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['deal_id'])->references(['id'])->on('deals')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_deals', function (Blueprint $table) {
            $table->dropForeign('client_deals_client_id_foreign');
            $table->dropForeign('client_deals_deal_id_foreign');
        });
    }
};
