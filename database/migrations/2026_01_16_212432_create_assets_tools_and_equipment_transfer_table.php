<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('assets_tools_and_equipment_transfer', function (Blueprint $table) {
            $table->id();

            // Link to asset
            $table->unsignedBigInteger('asset_id');
            $table->foreign('asset_id')
                    ->references('id')
                    ->on('assets_tools_and_equipment')
                    ->onDelete('cascade');

            // From and To sites
            $table->unsignedBigInteger('from_site_id')->nullable();
            $table->unsignedBigInteger('to_site_id')->nullable();

            // Quantity transferred (partial or full)
            $table->integer('quantity')->default(1);

            // Meta info
            $table->unsignedBigInteger('transferred_by')->nullable(); // user who did transfer
            $table->timestamp('transferred_at')->useCurrent();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('assets_tools_and_equipment_transfer');
    }
};
