<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('machinery_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('payment_request_id')->nullable()->constrained('payment_requests')->onDelete('set null');
            
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->integer('total_dprs')->default(0);
            $table->decimal('total_hours', 10, 2)->default(0);
            $table->decimal('total_diesel', 10, 2)->default(0);
            
            $table->enum('status', ['draft', 'submitted', 'approved', 'paid'])->default('draft');
            $table->text('remarks')->nullable();
            $table->json('audit_snapshot')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'workspace_id']);
            $table->index(['supplier_id', 'workspace_id']);
            $table->index(['from_date', 'to_date', 'workspace_id']);
            $table->index(['payment_request_id', 'workspace_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('machinery_bills');
    }
};
