<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add new fields to indents table:
     * - assign_to: store multiple user IDs as comma-separated string
     * - delivery_date: date field
     * - remark: text field (nullable)
     * - reference_file: string (store uploaded file path, nullable)
     */
    public function up(): void
    {
        Schema::table('indents', function (Blueprint $table) {
            // Store multiple user IDs as comma-separated string
            $table->string('assign_to')->nullable()->after('rejection_reason');
            
            // Delivery date for the indent
            $table->date('delivery_date')->nullable()->after('assign_to');
            
            // Additional remarks (nullable text)
            $table->text('remark')->nullable()->after('delivery_date');
            
            // Reference file path (nullable string for uploaded file)
            $table->string('reference_file')->nullable()->after('remark');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indents', function (Blueprint $table) {
            $table->dropColumn([
                'assign_to',
                'delivery_date',
                'remark',
                'reference_file',
            ]);
        });
    }
};
