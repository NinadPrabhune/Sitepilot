<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            if (!Schema::hasColumn('machinery_ledger', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->unique()->after('metadata');
            }
        });

        Schema::table('supplier_ledger', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_ledger', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->unique()->after('metadata');
            }
        });
    }

    public function down()
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            // Drop unique constraint safely by checking if index exists first
            if (Schema::hasIndex('machinery_ledger', 'machinery_ledger_idempotency_key_unique')) {
                $table->dropUnique(['idempotency_key']);
            }
            
            // Drop column if it exists
            if (Schema::hasColumn('machinery_ledger', 'idempotency_key')) {
                $table->dropColumn('idempotency_key');
            }
        });

        Schema::table('supplier_ledger', function (Blueprint $table) {
            // Drop unique constraint safely by checking if index exists first
            if (Schema::hasIndex('supplier_ledger', 'supplier_ledger_idempotency_key_unique')) {
                $table->dropUnique(['idempotency_key']);
            }
            
            // Drop column if it exists
            if (Schema::hasColumn('supplier_ledger', 'idempotency_key')) {
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
