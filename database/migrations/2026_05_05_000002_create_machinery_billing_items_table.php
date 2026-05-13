<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('machinery_billing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machinery_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignId('bill_id')->nullable()->constrained('machinery_bills')->onDelete('set null');
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('total_hours', 10, 2)->default(0);
            $table->decimal('total_diesel', 10, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('rate_per_hour', 10, 2)->default(0);
            $table->decimal('diesel_rate', 10, 2)->default(0);
            
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->text('remarks')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['machinery_id', 'from_date', 'to_date', 'workspace_id'], 'billing_items_unique');
            $table->index(['status', 'workspace_id']);
            $table->index(['supplier_id', 'workspace_id']);
            $table->index(['from_date', 'to_date', 'workspace_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('machinery_billing_items');
    }
};
