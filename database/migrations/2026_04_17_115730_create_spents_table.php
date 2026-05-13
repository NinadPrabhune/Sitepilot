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
            $table->id();
            $table->string('name');
            $table->foreignId('spent_ledger_id')->constrained('spent_ledgers')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('workspace_id')->constrained('work_spaces')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
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
