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
        // Add local environment locks after the incident
        DB::table('production_safety_locks')->insert([
            [
                'lock_type' => 'schema_changes',
                'environment' => 'local',
                'is_locked' => true,
                'reason' => 'Local environment locked after data loss incident - requires approval',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lock_type' => 'seeding',
                'environment' => 'local',
                'is_locked' => true,
                'reason' => 'Local environment seeding locked after data loss incident - requires approval',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lock_type' => 'destructive_commands',
                'environment' => 'local',
                'is_locked' => true,
                'reason' => 'Local environment destructive commands locked after data loss incident - requires approval',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lock_type' => 'destructive_commands',
                'environment' => 'development',
                'is_locked' => true,
                'reason' => 'Development environment destructive commands locked - requires approval',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'lock_type' => 'destructive_commands',
                'environment' => 'testing',
                'is_locked' => true,
                'reason' => 'Testing environment destructive commands locked - requires approval',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('production_safety_locks')
            ->whereIn('environment', ['local', 'development', 'testing'])
            ->delete();
    }
};
