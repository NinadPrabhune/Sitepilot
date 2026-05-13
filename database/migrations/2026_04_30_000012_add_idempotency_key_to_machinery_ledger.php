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
                $table->string('idempotency_key')->nullable()->after('metadata');
                $table->index('idempotency_key');
            }
        });
    }

    public function down()
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            if (Schema::hasColumn('machinery_ledger', 'idempotency_key')) {
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
