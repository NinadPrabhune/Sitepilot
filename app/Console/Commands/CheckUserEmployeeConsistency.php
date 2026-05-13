<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Workdo\Hrm\Entities\Employee;
use App\Models\User;

class CheckUserEmployeeConsistency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:check-consistency {--fix : Automatically fix the inconsistencies}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and fix data consistency between users and employees tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking User-Employee data consistency...');
        $this->newLine();

        // Find Employees without Users (orphan employees)
        $orphanEmployees = Employee::whereNotIn('user_id', function($query) {
                $query->select('id')->from('users');
            })->get();

        // Find Users without Employees (users who were deleted from employee but user still exists)
        // Note: 'super admin' and 'company' users are excluded as they may not have employee records
        $orphanUsers = User::whereNotIn('id', function($query) {
                $query->select('user_id')->from('employees')->whereNotNull('user_id');
            })
            ->whereNotIn('type', ['super admin', 'company'])
            ->get();

        $this->info('=== CONSISTENCY CHECK RESULTS ===');
        $this->newLine();

        // Display orphan employees
        if ($orphanEmployees->count() > 0) {
            $this->warn('Found ' . $orphanEmployees->count() . ' orphan Employee(s) (Employees without User):');
            $this->table(
                ['ID', 'Name', 'User ID', 'Workspace', 'Created By'],
                $orphanEmployees->map(fn($e) => [
                    $e->id,
                    $e->name,
                    $e->user_id,
                    $e->workspace,
                    $e->created_by
                ])->toArray()
            );
        } else {
            $this->info('✓ No orphan Employees found');
        }

        $this->newLine();

        // Display orphan users
        if ($orphanUsers->count() > 0) {
            $this->warn('Found ' . $orphanUsers->count() . ' orphan User(s) (Users without Employee):');
            $this->table(
                ['ID', 'Name', 'Email', 'Type', 'Active Workspace'],
                $orphanUsers->map(fn($u) => [
                    $u->id,
                    $u->name,
                    $u->email,
                    $u->type,
                    $u->active_workspace
                ])->toArray()
            );
        } else {
            $this->info('✓ No orphan Users found');
        }

        $this->newLine();
        $this->info('=== SUMMARY ===');
        $this->info('Total Users: ' . User::count());
        $this->info('Total Employees: ' . Employee::count());
        $this->info('Orphan Employees: ' . $orphanEmployees->count());
        $this->info('Orphan Users: ' . $orphanUsers->count());

        // Auto-fix if requested
        if ($this->option('fix')) {
            $this->newLine();
            $this->info('=== AUTO-FIX MODE ===');

            // Delete orphan employees
            if ($orphanEmployees->count() > 0) {
                $this->warn('Deleting ' . $orphanEmployees->count() . ' orphan Employee(s)...');
                foreach ($orphanEmployees as $employee) {
                    \Log::info('CheckUserEmployeeConsistency: Deleting orphan Employee ID: ' . $employee->id . ' (user_id: ' . $employee->user_id . ')');
                    $employee->delete();
                }
                $this->info('✓ Orphan Employees deleted');
            }

            // Note: We don't automatically delete orphan users as they might be legitimate users
            // (e.g., 'super admin', 'company' users who don't have employee records)
            if ($orphanUsers->count() > 0) {
                $this->warn('Note: ' . $orphanUsers->count() . ' orphan User(s) found but NOT deleted automatically.');
                $this->warn('These could be legitimate users (super admin, company, clients) without employee records.');
            }

            $this->newLine();
            $this->info('✓ Consistency check and fix completed!');
        } else {
            $this->newLine();
            $this->info('To auto-fix orphan employees, run: php artisan users:check-consistency --fix');
        }

        return Command::SUCCESS;
    }
}
