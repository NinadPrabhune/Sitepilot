<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MachineryPaymentLogController extends Controller
{
    /**
     * Get logs filtered by payment_request_id
     * NOTE: This reads from log files - for production, consider using a log aggregation service
     */
    public function index(Request $request): JsonResponse
    {
        $paymentRequestId = $request->query('payment_request_id');
        $channel = $request->query('channel', 'payment_audit');
        $lines = $request->query('lines', 100);
        
        if (!$paymentRequestId) {
            return response()->json([
                'success' => false,
                'message' => 'payment_request_id is required',
            ], 400);
        }
        
        $logPath = storage_path("logs/{$channel}.log");
        
        if (!file_exists($logPath)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Log file not found',
            ]);
        }
        
        $logContent = file_get_contents($logPath);
        $logLines = explode("\n", $logContent);
        
        // Filter lines containing the payment_request_id
        $filteredLines = array_filter($logLines, function($line) use ($paymentRequestId) {
            return str_contains($line, (string)$paymentRequestId);
        });
        
        // Get last N lines
        $filteredLines = array_slice(array_reverse($filteredLines), 0, $lines);
        
        return response()->json([
            'success' => true,
            'data' => [
                'payment_request_id' => $paymentRequestId,
                'channel' => $channel,
                'lines' => array_values($filteredLines),
                'count' => count($filteredLines),
            ],
        ]);
    }
    
    /**
     * Get recent logs from all machinery payment channels
     */
    public function recent(Request $request): JsonResponse
    {
        $lines = $request->query('lines', 50);
        $channels = ['payment_audit', 'payment_debug'];
        
        $allLogs = [];
        
        foreach ($channels as $channel) {
            $logPath = storage_path("logs/{$channel}.log");
            
            if (file_exists($logPath)) {
                $logContent = file_get_contents($logPath);
                $logLines = explode("\n", $logContent);
                
                // Filter for machinery-related logs
                $filteredLines = array_filter($logLines, function($line) {
                    return str_contains($line, 'machinery') || str_contains($line, 'Machinery');
                });
                
                $recentLines = array_slice(array_reverse($filteredLines), 0, $lines);
                
                $allLogs[$channel] = array_values($recentLines);
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => $allLogs,
        ]);
    }
}
