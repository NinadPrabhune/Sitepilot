<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class MachineryRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'site_engineer',
            'pm',
            'admin',
            'accounts',
            'management',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $this->command->info('Machinery roles seeded successfully.');
    }
}
