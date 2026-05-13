<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove old unique constraint on indent_number that conflicts with per-site numbering.
     * The new constraint 'unique_indent_number_per_site' (site_id, indent_number) should be used instead.
     */
    public function up(): void
    {
        // Drop old single-column unique constraint if it exists
        if (Schema::hasIndex('indents', 'indents_indent_number_unique')) {
            Schema::table('indents', function (Blueprint $table) {
                $table->dropUnique('indents_indent_number_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add old constraint if needed (not recommended)
        Schema::table('indents', function (Blueprint $table) {
            $table->unique('indent_number', 'indents_indent_number_unique');
        });
    }
};
