<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('material_transfers', function (Blueprint $table) {
            $table->decimal('transport_cost', 15, 2)->default(0)->after('total_amount');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null')->after('created_by');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->unsignedBigInteger('ledger_entry_id')->nullable()->after('rejection_reason');
        });
    }

    public function down()
    {
        Schema::table('material_transfers', function (Blueprint $table) {
            $table->dropColumn(['transport_cost', 'approved_by', 'approved_at', 'rejection_reason', 'ledger_entry_id']);
        });
    }
};
