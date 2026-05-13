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
        Schema::create('spents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->unsignedBigInteger('spent_ledger_id')->index('spents_spent_ledger_id_foreign');
            $table->decimal('amount', 15);
            $table->unsignedBigInteger('project_id')->index('spents_project_id_foreign');
            $table->unsignedBigInteger('workspace_id')->index('spents_workspace_id_foreign');
            $table->unsignedBigInteger('created_by')->nullable()->index('spents_created_by_foreign');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spents');
    }
};
