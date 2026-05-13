<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::rename('item_categories', 'material_categories');
    }

    public function down()
    {
        Schema::rename('material_categories', 'item_categories');
    }
};
