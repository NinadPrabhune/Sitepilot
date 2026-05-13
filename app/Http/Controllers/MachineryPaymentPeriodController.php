<?php

namespace App\Http\Controllers;

use App\Domain\Machinery\Models\MachineryPaymentPeriod;
use App\Models\Machinery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MachineryPaymentPeriodController extends Controller
{
    public function index()
    {
        if (!Auth::user()->isAbleTo('machinery-payment-requests manage')) {
            abort(403, 'Unauthorized action.');
        }

        $periods = MachineryPaymentPeriod::with(['machinery', 'paymentRequest'])
            ->orderBy('start_date', 'desc')
            ->paginate(20);

        $machineries = Machinery::all();

        return view('periods.index', compact('periods', 'machineries'));
    }

    public function lock(Request $request, int $id)
    {
        if (!Auth::user()->isAbleTo('machinery-payment-requests manage')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $period = MachineryPaymentPeriod::findOrFail($id);

        if ($period->is_locked) {
            return back()->with('error', 'Period is already locked.');
        }

        DB::transaction(function () use ($period, $validated) {
            $period->update([
                'is_locked' => true,
                'locked_at' => now(),
                'locked_by' => Auth::id(),
                'notes' => $validated['notes'] ?? $period->notes,
            ]);

            // Lock all ledger entries in this period
            $lockedCount = \App\Domain\Machinery\Models\MachineryLedger::where('machinery_id', $period->machinery_id)
                ->whereBetween('date', [$period->start_date, $period->end_date])
                ->where('is_reversal', false)
                ->update([
                    'is_locked' => true,
                    'locked_at' => now(),
                    'locked_by' => Auth::id(),
                ]);

            \Log::info('period.locked', [
                'event' => 'payment.period.locked',
                'period_id' => $period->id,
                'machinery_id' => $period->machinery_id,
                'start_date' => $period->start_date,
                'end_date' => $period->end_date,
                'ledger_entries_locked' => $lockedCount,
                'user_id' => Auth::id(),
                'timestamp' => now()->toISOString(),
            ]);
        });

        return back()->with('success', 'Period locked successfully.');
    }

    public function unlock(Request $request, int $id)
    {
        if (!Auth::user()->isAbleTo('machinery-payment-requests manage')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'override_reason' => 'required|string|max:500',
        ]);

        $period = MachineryPaymentPeriod::findOrFail($id);

        if (!$period->is_locked) {
            return back()->with('error', 'Period is not locked.');
        }

        DB::transaction(function () use ($period, $validated) {
            $period->update([
                'is_locked' => false,
                'locked_at' => null,
                'locked_by' => null,
                'notes' => "ADMIN UNLOCK: {$validated['override_reason']}\n\n" . $period->notes,
            ]);

            // Unlock all ledger entries in this period
            $unlockedCount = \App\Domain\Machinery\Models\MachineryLedger::where('machinery_id', $period->machinery_id)
                ->whereBetween('date', [$period->start_date, $period->end_date])
                ->update([
                    'is_locked' => false,
                    'locked_at' => null,
                    'locked_by' => null,
                ]);

            \Log::info('period.unlocked', [
                'event' => 'payment.period.unlocked',
                'period_id' => $period->id,
                'machinery_id' => $period->machinery_id,
                'start_date' => $period->start_date,
                'end_date' => $period->end_date,
                'ledger_entries_unlocked' => $unlockedCount,
                'override_reason' => $validated['override_reason'],
                'user_id' => Auth::id(),
                'timestamp' => now()->toISOString(),
            ]);
        });

        return back()->with('success', 'Period unlocked successfully.');
    }
}
