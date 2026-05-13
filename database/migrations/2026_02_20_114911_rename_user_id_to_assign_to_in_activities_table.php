<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameUserIdToAssignToInActivitiesTable extends Migration
{
    public function up()
    {
        Schema::table('activities', function (Blueprint $table) {
            // First drop the foreign key constraint
            $table->dropForeign(['user_id']);

            // Then rename the column
            $table->renameColumn('user_id', 'assign_to');
        });

        Schema::table('activities', function (Blueprint $table) {
            // Change column type to string for comma-separated values
            $table->string('assign_to')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('activities', function (Blueprint $table) {
            // Revert back to unsignedBigInteger
            $table->unsignedBigInteger('assign_to')->nullable()->change();

            // Rename back to user_id
            $table->renameColumn('assign_to', 'user_id');

            // Restore foreign key
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }
}
