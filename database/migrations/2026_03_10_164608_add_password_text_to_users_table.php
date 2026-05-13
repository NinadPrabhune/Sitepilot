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
        // Only modify table if it exists
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Only add column if it doesn't exist
                if (!Schema::hasColumn('users', 'password_text')) {
                    $table->string('password_text')->nullable()->after('password');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only modify table if it exists
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Only drop column if it exists
                if (Schema::hasColumn('users', 'password_text')) {
                    $table->dropColumn('password_text');
                }
            });
        }
    }
};
