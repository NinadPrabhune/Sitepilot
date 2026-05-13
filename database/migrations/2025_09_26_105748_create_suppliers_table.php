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
        
        Schema::dropIfExists('suppliers');
        
       Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            // 🔑 Core Identification
            $table->string('name');
            $table->foreignId('category_id')->constrained('supplier_categories')->onDelete('cascade');
            $table->string('type')->nullable(); // e.g., company, individual

            // 📍 Contact & Location
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->string('country')->default('India');

            // 🧾 Business Details
            $table->string('upi_screenshot_1')->nullable(); // First UPI image
            $table->string('upi_screenshot_2')->nullable(); // Second UPI image

            $table->string('gst_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('payment_terms')->nullable(); // e.g., Net 30, Advance

            // 🧾 Meta
            $table->integer('site_id')->nullable()->default(null);
            $table->integer('workspace_id')->default(0);     
            $table->integer('created_by')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('status')->default(0); // optional workflow status            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
