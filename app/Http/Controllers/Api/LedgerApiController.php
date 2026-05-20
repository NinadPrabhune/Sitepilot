<?php

namespace App\Http\Controllers\Api;

use App\Domain\Machinery\Models\MachineryLedger;
use App\Http\Controllers\Controller;
use App\Models\Machinery;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @group Machinery Ledger
 * Endpoints for viewing and querying machinery ledger entries (immutable credit/debit record for each machine)
 */
class LedgerApiController extends Controller
{
    /**
     * List machinery ledger entries.
     *
     * Returns a paginated list of ledger entries for the authenticated user's workspace.
     * Ledger entries are always in descending date order (most recent first).
     * Reversal entries are excluded from default listing.
     *
     * @authenticated
     *
     * @urlParam machinery_id int optional Filter ledger entries to a specific machinery ID. Example: 3
     * @urlParam date_from date optional Filter entries from this date (YYYY-MM-DD). Example: 2025-01-01
     * @urlParam date_to date optional Filter entries up to this date (YYYY-MM-DD). Example: 2025-12-31
     * @urlParam entry_type string optional Filter by entry type (e.g. `dpr`, `payment`, `advance`, `opening_balance`, `adjustment`). Example: dpr
     *
     * @queryParam per_page int optional Number of records per page. Default: 50. Example: 50
     *
     * @responseField success bool Whether the request succeeded. Example: true
     * @responseField message string Response message. Example: Ledger entries fetched successfully.
     * @responseField data array List of ledger entry records.
     * @responseField data.*.id int The ledger entry ID. Example: 101
     * @responseField data.*.machinery_id int The machinery ID. Example: 3
     * @responseField data.*.entry_direction string `credit` or `debit`. Example: credit
     * @responseField data.*.entry_type string Type of entry. Example: dpr
     * @responseField data.*.amount string Amount recorded. Example: 5000.00
     * @responseField data.*.running_balance string Running balance after this entry. Example: 15000.00
     * @responseField data.*.date date Entry date. Example: 2025-06-15
     * @responseField data.*.description string|null Optional description. Example: DPR for June week 2
     * @responseField data.*.machinery object Nested machinery details.
     * @responseField data.*.machinery.id int Machinery ID.
     * @responseField data.*.machinery.name string Machinery name. Example: Excavator EX-01
     * @responseField data.*.is_reversal bool Whether this is a reversal entry. Example: false
     * @responseField data.*.is_locked bool Whether this entry is locked. Example: false
     * @responseField pagination array Standard Laravel pagination meta data.
     * @responseField pagination.current_page int Current page number. Example: 1
     * @responseField pagination.per_page int Records per page. Example: 50
     * @responseField pagination.total int Total matching records. Example: 127
     *
     * @throws 403 Unauthorized when the authenticated user lacks `machinery-payment-requests manage` permission and is not an admin or company role.
     * @throws 422 Validation error when a query parameter has an invalid format.
     *
     * @response status=200 scenario="Success"
     * {
     *   "success": true,
     *   "message": "Ledger entries fetched successfully.",
     *   "data": [
     *     {
     *       "id": 101,
     *       "machinery_id": 3,
     *       "entry_direction": "credit",
     *       "entry_type": "dpr",
     *       "amount": "5000.00",
     *       "running_balance": "15000.00",
     *       "date": "2025-06-15",
     *       "description": "DPR for June week 2",
     *       "is_reversal": false,
     *       "is_locked": false,
     *       "machinery": {
     *         "id": 3,
     *         "name": "Excavator EX-01"
     *       }
     *     }
     *   ],
     *   "pagination": {
     *     "current_page": 1,
     *     "per_page": 50,
     *     "total": 127,
     *     "last_page": 3
     *   }
     * }
     * @response status=403 scenario="Permission denied"
     * {
     *   "success": false,
     *   "message": "Unauthorized action."
     * }
     */
    public function index(Request $request)
    {
        if (! Auth::user()->isAbleTo('machinery-payment-requests manage') && ! Auth::user()->hasRole('admin') && ! Auth::user()->hasRole('company')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $perPage = (int) $request->get('per_page', 50);

        $query = MachineryLedger::with(['machinery:id,name,registration_number', 'locker:id,name'])
            ->where('is_reversal', false)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        // Filter by machinery
        if ($request->filled('machinery_id')) {
            $query->where('machinery_id', $request->machinery_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        // Filter by entry type
        if ($request->filled('entry_type')) {
            $query->where('entry_type', $request->entry_type);
        }

        try {
            $paginator = $query->paginate($perPage);
            $entries = $paginator->items();

            $data = array_map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'machinery_id' => $entry->machinery_id,
                    'supplier_id' => $entry->supplier_id,
                    'entry_direction' => $entry->entry_direction,
                    'entry_type' => $entry->entry_type,
                    'amount' => (string) $entry->amount,
                    'running_balance' => (string) $entry->running_balance,
                    'date' => $entry->date ? $entry->date->format('Y-m-d') : null,
                    'description' => $entry->description,
                    'is_reversal' => (bool) $entry->is_reversal,
                    'is_locked' => (bool) $entry->is_locked,
                    'locked_at' => $entry->locked_at ? $entry->locked_at->format('Y-m-d H:i:s') : null,
                    'is_settled' => (bool) $entry->is_settled,
                    'reference_type' => $entry->reference_type,
                    'reference_id' => $entry->reference_id,
                    'payment_request_id' => $entry->payment_request_id,
                    'machinery' => $entry->machinery ? [
                        'id' => $entry->machinery->id,
                        'name' => $entry->machinery->name,
                        'registration_number' => $entry->machinery->registration_number,
                    ] : null,
                    'locker' => $entry->locker ? [
                        'id' => $entry->locker->id,
                        'name' => $entry->locker->name,
                    ] : null,
                ];
            }, $entries);

            return response()->json([
                'success' => true,
                'message' => 'Ledger entries fetched successfully.',
                'data' => $data,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_next' => $paginator->hasMorePages(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Ledger API Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch ledger entries.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single machinery ledger entry by ID.
     *
     * @authenticated
     *
     * @urlParam id int required The ID of the ledger entry. Example: 101
     *
     * @responseField success bool Whether the request succeeded. Example: true
     * @responseField message string Response message. Example: Ledger entry fetched successfully.
     * @responseField data object The ledger entry record.
     *
     * @throws 403 Unauthorized action.
     * @throws 404 Ledger entry not found.
     *
     * @response status=200 scenario="Success"
     * {
     *   "success": true,
     *   "message": "Ledger entry fetched successfully.",
     *   "data": {
     *     "id": 101,
     *     "machinery_id": 3,
     *     "entry_direction": "credit",
     *     "entry_type": "dpr",
     *     "amount": "5000.00",
     *     "running_balance": "15000.00",
     *     "date": "2025-06-15",
     *     "description": "DPR for June week 2",
     *     "is_reversal": false,
     *     "is_locked": false,
     *     "machinery": {"id": 3, "name": "Excavator EX-01"}
     *   }
     * }
     */
    public function show($id)
    {
        if (! Auth::user()->isAbleTo('machinery-payment-requests manage') && ! Auth::user()->hasRole('admin') && ! Auth::user()->hasRole('company')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.',
            ], 403);
        }

        try {
            $entry = MachineryLedger::with(['machinery:id,name,registration_number', 'locker:id,name'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Ledger entry fetched successfully.',
                'data' => [
                    'id' => $entry->id,
                    'machinery_id' => $entry->machinery_id,
                    'supplier_id' => $entry->supplier_id,
                    'entry_direction' => $entry->entry_direction,
                    'entry_type' => $entry->entry_type,
                    'amount' => (string) $entry->amount,
                    'running_balance' => (string) $entry->running_balance,
                    'date' => $entry->date ? $entry->date->format('Y-m-d') : null,
                    'description' => $entry->description,
                    'ledger_type' => $entry->ledger_type,
                    'cost_category' => $entry->cost_category,
                    'is_reversal' => (bool) $entry->is_reversal,
                    'is_locked' => (bool) $entry->is_locked,
                    'locked_at' => $entry->locked_at ? $entry->locked_at->format('Y-m-d H:i:s') : null,
                    'is_settled' => (bool) $entry->is_settled,
                    'reference_type' => $entry->reference_type,
                    'reference_id' => $entry->reference_id,
                    'payment_request_id' => $entry->payment_request_id,
                    'idempotency_key' => $entry->idempotency_key,
                    'machinery' => $entry->machinery ? [
                        'id' => $entry->machinery->id,
                        'name' => $entry->machinery->name,
                        'registration_number' => $entry->machinery->registration_number,
                    ] : null,
                    'locker' => $entry->locker ? [
                        'id' => $entry->locker->id,
                        'name' => $entry->locker->name,
                    ] : null,
                ],
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ledger entry not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Ledger show API Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unexpected error.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the running balance summary for a specific machinery within a date range.
     *
     * Returns the opening balance (from the earliest entry before or on the start date)
     * and the closing balance (last entry in the range).
     *
     * @authenticated
     *
     * @queryParam machinery_id int required The machinery ID. Example: 3
     * @queryParam date_from date optional Range start date (YYYY-MM-DD). Defaults to beginning of current month. Example: 2025-06-01
     * @queryParam date_to date optional Range end date (YYYY-MM-DD). Defaults to today. Example: 2025-06-30
     *
     * @response status=200 scenario="Success"
     * {
     *   "success": true,
     *   "message": "Ledger balance fetched successfully.",
     *   "data": {
     *     "machinery_id": 3,
     *     "opening_balance": "10000.00",
     *     "closing_balance": "15000.00",
     *     "net_change": "5000.00",
     *     "total_credits": "25000.00",
     *     "total_debits": "10000.00",
     *     "entry_count": 12,
     *     "date_range": {
     *       "from": "2025-06-01",
     *       "to": "2025-06-30"
     *     }
     *   }
     * }
     * @response status=403 scenario="Permission denied"
     * @response status=404 scenario="Machinery not found"
     */
    public function balance(Request $request)
    {
        if (! Auth::user()->isAbleTo('machinery-payment-requests manage') && ! Auth::user()->hasRole('admin') && ! Auth::user()->hasRole('company')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'machinery_id' => 'required|integer|exists:machineries,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $machineryId = $request->machinery_id;
        $dateFrom = $request->filled('date_from') ? $request->date_from : null;
        $dateTo = $request->filled('date_to') ? $request->date_to : null;

        $machinery = Machinery::findOrFail($machineryId);

        // Get entry range data
        $entriesQuery = MachineryLedger::where('machinery_id', $machineryId)
            ->where('is_reversal', false);

        if ($dateFrom) {
            $entriesQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $entriesQuery->whereDate('date', '<=', $dateTo);
        }

        $entries = $entriesQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();

        // Get opening balance: last running_balance of the entry immediately before the period
        $openingQuery = MachineryLedger::where('machinery_id', $machineryId)
            ->where('is_reversal', false)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        if ($dateFrom) {
            $openingQuery->whereDate('date', '<', $dateFrom);
        }

        $openingEntry = $openingQuery->first();
        $openingBalance = $openingEntry ? (float) $openingEntry->running_balance : 0;

        $totalCredits = $entries->where('entry_direction', 'credit')->sum('amount');
        $totalDebits = $entries->where('entry_direction', 'debit')->sum('amount');
        $netChange = $totalCredits - $totalDebits;
        $closingBalance = $openingBalance + $netChange;

        return response()->json([
            'success' => true,
            'message' => 'Ledger balance fetched successfully.',
            'data' => [
                'machinery_id' => $machineryId,
                'machinery_name' => $machinery->name,
                'opening_balance' => (string) round($openingBalance, 2),
                'closing_balance' => (string) round($closingBalance, 2),
                'net_change' => (string) round($netChange, 2),
                'total_credits' => (string) round($totalCredits, 2),
                'total_debits' => (string) round($totalDebits, 2),
                'entry_count' => $entries->count(),
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ],
            ],
        ], 200);
    }

    /**
     * List all machinery available for ledger filtering.
     *
     * @authenticated
     *
     * @response status=200 scenario="Success"
     * {
     *   "success": true,
     *   "message": "Machinery list fetched successfully.",
     *   "data": [
     *     {"id": 1, "name": "Excavator EX-01"},
     *     {"id": 3, "name": "Crane CR-02"}
     *   ]
     * }
     */
    public function machineryList()
    {
        if (! Auth::user()->isAbleTo('machinery-payment-requests manage') && ! Auth::user()->hasRole('admin') && ! Auth::user()->hasRole('company')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $machineries = Machinery::select('id', 'name', 'registration_number')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Machinery list fetched successfully.',
            'data' => $machineries,
        ], 200);
    }
}
