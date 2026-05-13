<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentsModule;
use App\Models\Supplier;
use App\Models\PurchaseInvoice;
use App\Models\User;
use Workdo\Taskly\Entities\Project;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PaymentsModuleSeeder extends Seeder
{
    public function run(): void
    {
        // 🛡️ DATA PROTECTION: This seeder has been disabled to prevent data loss
        // The original version used TRUNCATE which deletes all payment data
        // Use SAFE_SEED_ONLY=false in .env to enable if absolutely needed for testing
        
        $this->command->error('❌ PaymentsModuleSeeder is disabled for data protection.');
        $this->command->info('💡 To enable: Set SAFE_SEED_ONLY=false in .env file');
        return;
        
        // Original dangerous code commented out for safety:
        /*
        // Disable foreign key checks (optional but useful if there are constraints)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate the table - DANGEROUS: Deletes all payment data!
        DB::table('payments_module')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        */
    }
}
