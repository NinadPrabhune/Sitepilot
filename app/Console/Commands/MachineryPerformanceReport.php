<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\Machinery;
use App\Models\DailyProgressReport;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MachineryPerformanceReport extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'machinery:performance-report {--workspace-id=} {--days=30}';
    
    /**
     * The console command description.
     */
    protected $description = 'Generate machinery billing performance report';
    
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $workspaceId = $this->option('workspace-id');
        $days = $this->option('days') ?? 30;
        
        $this->info('Generating machinery performance report...');
        
        try {
            $from = now()->subDays($days);
            $to = now();
            
            // Get performance metrics
            $metrics = $this->getPerformanceMetrics($workspaceId, $from, $to);
            
            // Generate report
            $this->displayReport($metrics);
            
            // Log performance data
            $this->logPerformanceData($metrics);
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Failed to generate performance report: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(?int $workspaceId, Carbon $from, Carbon $to): array
    {
        // Payment request metrics
        $paymentRequestQuery = MachineryPaymentRequest::whereBetween('created_at', [$from, $to]);
        if ($workspaceId) {
            $paymentRequestQuery->where('workspace_id', $workspaceId);
        }
        
        $paymentRequests = $paymentRequestQuery->get();
        
        // DPR metrics
        $dprQuery = DailyProgressReport::whereBetween('date', [$from, $to]);
        if ($workspaceId) {
            $dprQuery->where('workspace_id', $workspaceId);
        }
        
        $dprs = $dprQuery->get();
        
        // Ledger metrics
        $ledgerQuery = MachineryLedger::whereBetween('date', [$from, $to]);
        if ($workspaceId) {
            $ledgerQuery->where('workspace_id', $workspaceId);
        }
        
        $ledgers = $ledgerQuery->get();
        
        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days
            ],
            'payment_requests' => [
                'total' => $paymentRequests->count(),
                'by_status' => $paymentRequests->groupBy('status')->map(fn($group) => $group->count())->toArray(),
                'average_processing_time' => $this->calculateAverageProcessingTime($paymentRequests),
                'total_amount' => $paymentRequests->sum('net_payable'),
                'average_amount' => $paymentRequests->avg('net_payable'),
                'calculation_methods' => $paymentRequests->whereNotNull('calculation_method')->groupBy('calculation_method')->map(fn($group) => $group->count())->toArray()
            ],
            'daily_progress_reports' => [
                'total' => $dprs->count(),
                'average_calculated_amount' => $dprs->avg('calculated_amount'),
                'total_calculated_amount' => $dprs->sum('calculated_amount'),
                'by_status' => $dprs->groupBy('status')->map(fn($group) => $group->count())->toArray(),
                'average_billable_hours' => $dprs->avg('billable_hours'),
                'unique_machinery' => $dprs->distinct('machinery_id')->count('machinery_id')
            ],
            'ledger_entries' => [
                'total' => $ledgers->count(),
                'by_direction' => $ledgers->groupBy('entry_direction')->map(fn($group) => $group->count())->toArray(),
                'by_type' => $ledgers->groupBy('entry_type')->map(fn($group) => $group->count())->toArray(),
                'total_amount' => $ledgers->sum('amount'),
                'average_amount' => $ledgers->avg('amount'),
                'unique_machinery' => $ledgers->distinct('machinery_id')->count('machinery_id')
            ],
            'efficiency' => [
                'dprs_per_day' => $dprs->count() / $days,
                'payment_requests_per_day' => $paymentRequests->count() / $days,
                'ledger_entries_per_day' => $ledgers->count() / $days,
                'average_dpr_to_payment_ratio' => $paymentRequests->count() > 0 ? $dprs->count() / $paymentRequests->count() : 0
            ],
            'machinery_utilization' => $this->getMachineryUtilization($workspaceId, $from, $to)
        ];
    }
    
    /**
     * Calculate average processing time for payment requests
     */
    private function calculateAverageProcessingTime($paymentRequests): float
    {
        $completedRequests = $paymentRequests->whereIn('status', ['paid', 'rejected'])
            ->whereNotNull('created_at')
            ->whereNotNull('approved_at');
        
        if ($completedRequests->isEmpty()) {
            return 0;
        }
        
        $totalHours = $completedRequests->sum(function($pr) {
            return $pr->created_at->diffInHours($pr->approved_at);
        });
        
        return round($totalHours / $completedRequests->count(), 2);
    }
    
    /**
     * Get machinery utilization metrics
     */
    private function getMachineryUtilization(?int $workspaceId, Carbon $from, Carbon $to): array
    {
        $machineryQuery = Machinery::query();
        if ($workspaceId) {
            $machineryQuery->where('workspace_id', $workspaceId);
        }
        
        $allMachinery = $machineryQuery->get();
        
        $activeMachinery = DailyProgressReport::whereBetween('date', [$from, $to])
            ->when($workspaceId, fn($query) => $query->where('workspace_id', $workspaceId))
            ->distinct('machinery_id')
            ->pluck('machinery_id');
        
        return [
            'total_machinery' => $allMachinery->count(),
            'active_machinery' => $activeMachinery->count(),
            'utilization_rate' => $allMachinery->count() > 0 ? round(($activeMachinery->count() / $allMachinery->count()) * 100, 2) : 0,
            'by_ownership' => $allMachinery->groupBy('owned_by')->map(fn($group) => $group->count())->toArray()
        ];
    }
    
    /**
     * Display performance report
     */
    private function displayReport(array $metrics): void
    {
        $this->line("\n" . str_repeat('=', 60));
        $this->info('MACHINERY BILLING PERFORMANCE REPORT');
        $this->line(str_repeat('=', 60));
        
        $this->line("Period: {$metrics['period']['from']} to {$metrics['period']['to']} ({$metrics['period']['days']} days)");
        
        // Payment Requests
        $this->line("\nPAYMENT REQUESTS:");
        $this->line("  Total: {$metrics['payment_requests']['total']}");
        $this->line("  Average Amount: ₹" . number_format($metrics['payment_requests']['average_amount'], 2));
        $this->line("  Total Amount: ₹" . number_format($metrics['payment_requests']['total_amount'], 2));
        $this->line("  Average Processing Time: {$metrics['payment_requests']['average_processing_time']} hours");
        
        foreach ($metrics['payment_requests']['by_status'] as $status => $count) {
            $this->line("  {$status}: {$count}");
        }
        
        // DPRs
        $this->line("\nDAILY PROGRESS REPORTS:");
        $this->line("  Total: {$metrics['daily_progress_reports']['total']}");
        $this->line("  Average Amount: ₹" . number_format($metrics['daily_progress_reports']['average_calculated_amount'], 2));
        $this->line("  Average Hours: " . number_format($metrics['daily_progress_reports']['average_billable_hours'], 2));
        $this->line("  Unique Machinery: {$metrics['daily_progress_reports']['unique_machinery']}");
        
        // Ledger Entries
        $this->line("\nLEDGER ENTRIES:");
        $this->line("  Total: {$metrics['ledger_entries']['total']}");
        $this->line("  Average Amount: ₹" . number_format($metrics['ledger_entries']['average_amount'], 2));
        $this->line("  Total Amount: ₹" . number_format($metrics['ledger_entries']['total_amount'], 2));
        $this->line("  Unique Machinery: {$metrics['ledger_entries']['unique_machinery']}");
        
        foreach ($metrics['ledger_entries']['by_direction'] as $direction => $count) {
            $this->line("  {$direction}: {$count}");
        }
        
        // Efficiency
        $this->line("\nEFFICIENCY METRICS:");
        $this->line("  DPRs per Day: " . number_format($metrics['efficiency']['dprs_per_day'], 2));
        $this->line("  Payment Requests per Day: " . number_format($metrics['efficiency']['payment_requests_per_day'], 2));
        $this->line("  Ledger Entries per Day: " . number_format($metrics['efficiency']['ledger_entries_per_day'], 2));
        $this->line("  DPR to Payment Ratio: " . number_format($metrics['efficiency']['average_dpr_to_payment_ratio'], 2));
        
        // Machinery Utilization
        $this->line("\nMACHINERY UTILIZATION:");
        $this->line("  Total Machinery: {$metrics['machinery_utilization']['total_machinery']}");
        $this->line("  Active Machinery: {$metrics['machinery_utilization']['active_machinery']}");
        $this->line("  Utilization Rate: {$metrics['machinery_utilization']['utilization_rate']}%");
        
        foreach ($metrics['machinery_utilization']['by_ownership'] as $ownership => $count) {
            $this->line("  {$ownership}: {$count}");
        }
        
        $this->line("\n" . str_repeat('=', 60));
    }
    
    /**
     * Log performance data for monitoring
     */
    private function logPerformanceData(array $metrics): void
    {
        $logData = [
            'timestamp' => now()->toISOString(),
            'period_days' => $metrics['period']['days'],
            'payment_requests' => [
                'total' => $metrics['payment_requests']['total'],
                'total_amount' => $metrics['payment_requests']['total_amount'],
                'average_processing_time' => $metrics['payment_requests']['average_processing_time']
            ],
            'dprs' => [
                'total' => $metrics['daily_progress_reports']['total'],
                'total_amount' => $metrics['daily_progress_reports']['total_calculated_amount'],
                'unique_machinery' => $metrics['daily_progress_reports']['unique_machinery']
            ],
            'efficiency' => [
                'dprs_per_day' => $metrics['efficiency']['dprs_per_day'],
                'payment_requests_per_day' => $metrics['efficiency']['payment_requests_per_day'],
                'utilization_rate' => $metrics['machinery_utilization']['utilization_rate']
            ]
        ];
        
        Log::info('Machinery performance report generated', $logData);
    }
}
