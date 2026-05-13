<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceLog;
use App\Models\Machinery;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Domain\Machinery\Services\MachineryLedgerService;
use Workdo\Taskly\Entities\Project;

class MaintenanceLogController extends Controller
{
    public function index()
    {
        if (!Auth::user()->isAbleTo('maintenance-logs manage')) {
            abort(403, 'Unauthorized action.');
        }

        $maintenanceLogs = MaintenanceLog::with(['machinery', 'vendor'])
            ->orderBy('maintenance_date', 'desc')
            ->paginate(20);

        return view('maintenance.index', compact('maintenanceLogs'));
    }

    public function create()
    {
        if (!Auth::user()->isAbleTo('maintenance-logs create')) {
            abort(403, 'Unauthorized action.');
        }

        $machineries = Machinery::all();
        $vendors = Supplier::all();
        $sites = Project::where('workspace', getActiveWorkSpace())->projectonly()->get()->pluck('name', 'id');

        return view('maintenance.create', compact('machineries', 'vendors', 'sites'));
    }

    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('maintenance-logs create')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'machinery_id' => 'required|exists:machineries,id',
            'vendor_id' => 'nullable|exists:suppliers,id',
            'maintenance_date' => 'required|date',
            'cost' => 'required|numeric|min:0',
            'paid_by' => 'required|in:company,supplier',
            'description' => 'nullable|string',
            'site_id' => 'required|exists:projects,id',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        DB::beginTransaction();

        try {
            $data = $validated;
            $data['workspace_id'] = getActiveWorkSpace();
            $data['created_by'] = Auth::id();
            $data['status'] = 0;

            // Handle file upload
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = time() . '_maintenance_' . $file->getClientOriginalExtension();
                $path = $file->storeAs('maintenance', $filename, 'public');
                $data['attachment'] = $path;
            }

            $maintenanceLog = MaintenanceLog::create($data);

            // Create ledger entry for maintenance (debit)
            if ($maintenanceLog->cost > 0) {
                $ledgerEntry = MachineryLedgerService::createDebit([
                    'machinery_id' => $maintenanceLog->machinery_id,
                    'amount' => $maintenanceLog->cost,
                    'reference_type' => MachineryLedgerService::REFERENCE_TYPE_MAINTENANCE,
                    'reference_id' => $maintenanceLog->id,
                    'entry_type' => MachineryLedgerService::ENTRY_TYPE_MAINTENANCE,
                    'date' => $maintenanceLog->maintenance_date,
                    'description' => "Maintenance - {$maintenanceLog->description}",
                    'metadata' => [
                        'vendor_id' => $maintenanceLog->vendor_id,
                        'paid_by' => $maintenanceLog->paid_by,
                        'site_id' => $maintenanceLog->site_id,
                    ],
                ]);

                // Hard enforcement: verify ledger amount matches cost
                if (abs($ledgerEntry->amount - $maintenanceLog->cost) > 0.01) {
                    throw new \RuntimeException("Ledger enforcement failed: Maintenance cost mismatch. Cost ₹{$maintenanceLog->cost} vs Ledger ₹{$ledgerEntry->amount}. Cannot proceed.");
                }

                // Link ledger entry to maintenance log
                $maintenanceLog->update(['ledger_entry_id' => $ledgerEntry->id]);
            }

            DB::commit();

            return redirect()->route('maintenance.index')->with('success', 'Maintenance log created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error creating maintenance log: ' . $e->getMessage())->withInput();
        }
    }

    public function show(MaintenanceLog $maintenanceLog)
    {
        if (!Auth::user()->isAbleTo('maintenance-logs show')) {
            abort(403, 'Unauthorized action.');
        }

        $maintenanceLog->load(['machinery', 'vendor', 'site', 'creator']);

        return view('maintenance.show', compact('maintenanceLog'));
    }

    public function edit(MaintenanceLog $maintenanceLog)
    {
        if (!Auth::user()->isAbleTo('maintenance-logs edit')) {
            abort(403, 'Unauthorized action.');
        }

        $machineries = Machinery::all();
        $vendors = Supplier::all();
        $sites = Project::where('workspace', getActiveWorkSpace())->projectonly()->get()->pluck('name', 'id');

        return view('maintenance.edit', compact('maintenanceLog', 'machineries', 'vendors', 'sites'));
    }

    public function update(Request $request, MaintenanceLog $maintenanceLog)
    {
        if (!Auth::user()->isAbleTo('maintenance-logs edit')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'machinery_id' => 'required|exists:machineries,id',
            'vendor_id' => 'nullable|exists:suppliers,id',
            'maintenance_date' => 'required|date',
            'cost' => 'required|numeric|min:0',
            'paid_by' => 'required|in:company,supplier',
            'description' => 'nullable|string',
            'site_id' => 'required|exists:projects,id',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        DB::beginTransaction();

        try {
            // Period lock guard
            if (\App\Domain\Machinery\Models\MachineryPaymentPeriod::isDateLocked($maintenanceLog->machinery_id, $maintenanceLog->maintenance_date->format('Y-m-d'))) {
                throw new \RuntimeException("Cannot edit Maintenance Log #{$maintenanceLog->id} because the date is within a locked period.");
            }

            // Prevent editing cost if ledger entry exists
            if ($maintenanceLog->ledger_entry_id && $maintenanceLog->cost != $validated['cost']) {
                throw new \RuntimeException('Cannot modify cost when ledger entry exists. Use reversal to modify.');
            }

            $data = $validated;
            $data['workspace_id'] = getActiveWorkSpace();
            $data['created_by'] = Auth::id();

            // Handle file upload
            if ($request->hasFile('attachment')) {
                if ($maintenanceLog->attachment) {
                    Storage::disk('public')->delete($maintenanceLog->attachment);
                }
                $file = $request->file('attachment');
                $filename = time() . '_maintenance_' . $file->getClientOriginalExtension();
                $path = $file->storeAs('maintenance', $filename, 'public');
                $data['attachment'] = $path;
            }

            $maintenanceLog->update($data);

            // If no ledger entry exists, create one
            if (!$maintenanceLog->ledger_entry_id && $maintenanceLog->cost > 0) {
                $ledgerEntry = MachineryLedgerService::createDebit([
                    'machinery_id' => $maintenanceLog->machinery_id,
                    'amount' => $maintenanceLog->cost,
                    'reference_type' => MachineryLedgerService::REFERENCE_TYPE_MAINTENANCE,
                    'reference_id' => $maintenanceLog->id,
                    'entry_type' => MachineryLedgerService::ENTRY_TYPE_MAINTENANCE,
                    'date' => $maintenanceLog->maintenance_date,
                    'description' => "Maintenance - {$maintenanceLog->description}",
                    'metadata' => [
                        'vendor_id' => $maintenanceLog->vendor_id,
                        'paid_by' => $maintenanceLog->paid_by,
                        'site_id' => $maintenanceLog->site_id,
                    ],
                ]);

                $maintenanceLog->update(['ledger_entry_id' => $ledgerEntry->id]);
            }

            DB::commit();

            return redirect()->route('maintenance.index')->with('success', 'Maintenance log updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error updating maintenance log: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(MaintenanceLog $maintenanceLog)
    {
        if (!Auth::user()->isAbleTo('maintenance-logs delete')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Period lock guard
            if (\App\Domain\Machinery\Models\MachineryPaymentPeriod::isDateLocked($maintenanceLog->machinery_id, $maintenanceLog->maintenance_date->format('Y-m-d'))) {
                throw new \RuntimeException("Cannot delete Maintenance Log #{$maintenanceLog->id} because the date is within a locked period.");
            }

            // Orphan prevention: block delete if ledger entry exists
            if ($maintenanceLog->ledger_entry_id) {
                throw new \RuntimeException("Cannot delete Maintenance Log #{$maintenanceLog->id} because it has a linked ledger entry. Use reversal to remove the financial impact first.");
            }

            if ($maintenanceLog->attachment) {
                Storage::disk('public')->delete($maintenanceLog->attachment);
            }
            $maintenanceLog->delete();

            return redirect()->route('maintenance.index')->with('success', 'Maintenance log deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error deleting record: ' . $e->getMessage());
        }
    }
}
