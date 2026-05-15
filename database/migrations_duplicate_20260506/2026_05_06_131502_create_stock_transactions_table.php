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
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('material_id')->index();
            $table->enum('type', ['opening', 'grn', 'issue', 'transfer_in', 'transfer_out', 'adjustment']);
            $table->decimal('quantity', 20);
            $table->decimal('rate', 20)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->index('stock_transactions_created_by_foreign');
            $table->timestamps();

            $table->index(['project_id', 'material_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
