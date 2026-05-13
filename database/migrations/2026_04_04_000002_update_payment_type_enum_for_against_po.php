<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\PurchaseOrder;
use App\Models\PaymentsModule;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            $table->enum('payment_type', ['advance_against_po', 'against_po', 'against_invoice', 'mixed', 'on_account'])->default('against_po')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            $table->enum('payment_type', ['advance_against_po', 'against_invoice', 'mixed', 'on_account'])->default('against_invoice')->change();
        });
    }
};