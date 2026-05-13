<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Insert Indian states data
        DB::table('states')->insert([
            ['id' => 1, 'name' => 'Jammu and Kashmir', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Himachal Pradesh', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Punjab', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Chandigarh', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Uttarakhand', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Haryana', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'name' => 'Delhi', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'name' => 'Rajasthan', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'name' => 'Uttar Pradesh', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'name' => 'Bihar', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'name' => 'Sikkim', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'name' => 'Arunachal Pradesh', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'name' => 'Nagaland', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'name' => 'Manipur', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 15, 'name' => 'Mizoram', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'name' => 'Tripura', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 17, 'name' => 'Meghalaya', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 18, 'name' => 'Assam', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 19, 'name' => 'West Bengal', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'name' => 'Jharkhand', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'name' => 'Odisha', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 22, 'name' => 'Chhattisgarh', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 23, 'name' => 'Madhya Pradesh', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 24, 'name' => 'Gujarat', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 25, 'name' => 'Daman and Diu', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 26, 'name' => 'Dadra and Nagar Haveli', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 27, 'name' => 'Maharashtra', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 28, 'name' => 'Andhra Pradesh', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 29, 'name' => 'Karnataka', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 30, 'name' => 'Goa', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 31, 'name' => 'Lakshadweep', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 32, 'name' => 'Kerala', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 33, 'name' => 'Tamil Nadu', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 34, 'name' => 'Puducherry', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 35, 'name' => 'Andaman and Nicobar Islands', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 36, 'name' => 'Telangana', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 37, 'name' => 'Andhra Pradesh (New)', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
