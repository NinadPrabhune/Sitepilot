<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        // Create child table
        Schema::create('leave_request_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_request_id');
            $table->date('leave_date');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_half_day')->default(false);
            $table->timestamps();

            $table->foreign('leave_request_id')->references('id')->on('leaves')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index('leave_request_id');
            $table->index('leave_date');
            $table->index('status');
        });

        // Update leaves table
        Schema::table('leaves', function (Blueprint $table) {
            // Change total_leave_days from string to int if it's string
            $table->integer('total_leave_days')->change();
            
            // Add new fields
            $table->integer('rejected_days')->default(0)->after('approved_days');
            $table->integer('pending_days')->default(0)->after('rejected_days');
        });

        // Migrate existing data
        $this->migrateExistingLeaveData();
    }

    private function migrateExistingLeaveData()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        $leaves = DB::table('leaves')->get();
        
        foreach ($leaves as $leave) {
            $startDate = Carbon::parse($leave->start_date);
            $endDate = Carbon::parse($leave->end_date);
            $totalDays = $startDate->diffInDays($endDate) + 1;
            
            // Update total_leave_days
            DB::table('leaves')
                ->where('id', $leave->id)
                ->update(['total_leave_days' => $totalDays]);
            
            // Create date records
            $currentDate = $startDate;
            $dateIndex = 0;
            
            while ($currentDate <= $endDate) {
                $status = 'pending';
                $approvedBy = null;
                $approvedAt = null;
                
                // Determine status based on leave status
                if ($leave->status === 'Approved') {
                    $status = 'approved';
                    $approvedBy = $leave->created_by;
                    $approvedAt = $leave->updated_at;
                } elseif ($leave->status === 'Reject') {
                    $status = 'rejected';
                    $approvedBy = $leave->created_by;
                    $approvedAt = $leave->updated_at;
                } elseif ($leave->status === 'Partially Approved' && $leave->approved_days) {
                    // For existing partial approvals, approve first N days
                    if ($dateIndex < $leave->approved_days) {
                        $status = 'approved';
                        $approvedBy = $leave->created_by;
                        $approvedAt = $leave->updated_at;
                    } else {
                        $status = 'rejected';
                        $approvedBy = $leave->created_by;
                        $approvedAt = $leave->updated_at;
                    }
                }
                
                DB::table('leave_request_dates')->insert([
                    'leave_request_id' => $leave->id,
                    'leave_date' => $currentDate->format('Y-m-d'),
                    'status' => $status,
                    'approved_by' => $approvedBy,
                    'approved_at' => $approvedAt,
                    'created_at' => $leave->created_at,
                    'updated_at' => $leave->updated_at,
                ]);
                
                $currentDate->addDay();
                $dateIndex++;
            }
            
            // Recalculate days
            $approvedCount = DB::table('leave_request_dates')
                ->where('leave_request_id', $leave->id)
                ->where('status', 'approved')
                ->count();
            
            $rejectedCount = DB::table('leave_request_dates')
                ->where('leave_request_id', $leave->id)
                ->where('status', 'rejected')
                ->count();
            
            DB::table('leaves')
                ->where('id', $leave->id)
                ->update([
                    'approved_days' => $approvedCount,
                    'rejected_days' => $rejectedCount,
                    'pending_days' => $totalDays - $approvedCount - $rejectedCount,
                ]);
        }
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_dates');
        
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn(['rejected_days', 'pending_days']);
            $table->string('total_leave_days')->change();
        });
    }
};
