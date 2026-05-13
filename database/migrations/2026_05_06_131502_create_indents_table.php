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
        Schema::create('indents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('indent_number');
            $table->date('indent_date');
            $table->string('supplier_invoice_number')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable()->index('indents_supplier_id_foreign');
            $table->decimal('total_amount', 15)->default(0);
            $table->enum('status', ['Open', 'Partially Closed', 'Closed'])->default('Open');
            $table->unsignedBigInteger('site_id')->nullable()->index('indents_site_id_foreign');
            $table->unsignedBigInteger('created_by')->index('indents_created_by_foreign');
            $table->unsignedBigInteger('workspace_id')->index('indents_workspace_id_foreign');
            $table->text('description')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('assign_to')->nullable();
            $table->date('delivery_date')->nullable();
            $table->text('remark')->nullable();
            $table->string('reference_file')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'id'], 'idx_indent_scope');
            $table->index(['site_id', 'id'], 'idx_indent_site_id_id');
            $table->index(['site_id', 'indent_number'], 'idx_indent_site_id_number');
            $table->unique(['site_id', 'indent_number'], 'unique_indent_number_per_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indents');
    }
};
