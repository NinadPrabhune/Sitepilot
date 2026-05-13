<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameStartDateAndAddDueDateToActivitiesTable extends Migration
{
    public function up()
    {
        Schema::table('activities', function (Blueprint $table) {
            // Rename start_date to new name (example: begin_date)
            $table->renameColumn('date', 'start_date');
        });

        Schema::table('activities', function (Blueprint $table) {
            // Change datatype of renamed column to datetime
            $table->dateTime('start_date')->change();

            // Add new due_date column as datetime
            $table->dateTime('due_date')->nullable()->after('start_date');
        });
    }

    public function down()
    {
        Schema::table('activities', function (Blueprint $table) {
            // Drop due_date column
            $table->dropColumn('due_date');

            // Change datatype back to date
            $table->date('start_date')->change();

            // Rename back to start_date
            $table->renameColumn('start_date', 'start_date');
        });
    }
}

