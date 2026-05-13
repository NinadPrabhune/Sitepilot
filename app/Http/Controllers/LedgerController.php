<?php

namespace App\Http\Controllers;

use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\Machinery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LedgerController extends Controller
{
    /**
     * Display ledger entries list
     */
    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('machinery-payment-requests manage') && !Auth::user()->hasRole('admin') && !Auth::user()->hasRole('company')) {
            abort(403, 'Unauthorized action.');
        }

        $query = MachineryLedger::with(['machinery', 'paymentRequest'])
            ->where('is_reversal', false)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        // Filter by machinery
        if ($request->has('machinery_id') && $request->machinery_id) {
            $query->where('machinery_id', $request->machinery_id);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->where('date', '<=', $request->date_to);
        }

        // Filter by entry type
        if ($request->has('entry_type') && $request->entry_type) {
            $query->where('entry_type', $request->entry_type);
        }

        $ledgerEntries = $query->paginate(50);
        $machineries = Machinery::all();

        return view('ledger.index', compact('ledgerEntries', 'machineries'));
    }
}
