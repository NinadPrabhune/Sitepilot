<?php

namespace App\Http\Controllers;

use App\DataTables\SpentDataTable;
use App\Models\Spent;
use App\Models\SpentLedger;
use Workdo\Taskly\Entities\Project;
use Workdo\Taskly\Entities\WorkSpace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SpentController extends Controller
{
    /**
     * Display a listing of spents.
     *
     * @authenticated
     * @response view="spent.index"
     */
    public function index(SpentDataTable $dataTable)
    {
        if (!Auth::user()->isAbleTo('spent manage')) {
            abort(403, 'Permission denied');
        }

        $projects = Project::projectonly()
            ->pluck('name', 'id');

        return $dataTable->render('spent.index', compact('projects'));
    }

    /**
     * Show the form for creating a new spent.
     *
     * @authenticated
     * @response view="spent.create"
     */
    public function create()
    {
        if (!Auth::user()->isAbleTo('spent create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $ledgers = SpentLedger::all()->pluck('name', 'id');

        return view('spent.create', compact('ledgers'));
    }

    /**
     * Store a newly created spent.
     *
     * @authenticated
     * @bodyParam name string required The spent name. Example: Office Supplies
     * @bodyParam spent_ledger_id integer required The spent ledger ID. Example: 1
     * @bodyParam amount numeric required The spent amount. Minimum: 0. Example: 5000
     * @response redirect to="spent.index"
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('spent create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $request->validate([
            'name' => 'required|string',
            'spent_ledger_id' => 'required|exists:spent_ledgers,id',
            'amount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $spent = Spent::create([
                'name' => $request->name,
                'spent_ledger_id' => $request->spent_ledger_id,
                'amount' => $request->amount,
                'project_id' => getActiveProject(),
                'workspace_id' => getActiveWorkSpace(),
                'created_by' => Auth::id(),
            ]);

            DB::commit();
            return redirect()->route('spent.index')->with('success', __('Spent created successfully!'));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', __('Error creating spent: ') . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for editing a spent.
     *
     * @authenticated
     * @urlParam spent integer required The spent ID. Example: 1
     * @response view="spent.edit"
     */
    public function edit(Spent $spent)
    {
        if (!Auth::user()->isAbleTo('spent edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $ledgers = SpentLedger::all()->pluck('name', 'id');

        return view('spent.edit', compact('spent', 'ledgers'));
    }

    /**
     * Update the specified spent.
     *
     * @authenticated
     * @urlParam spent integer required The spent ID. Example: 1
     * @bodyParam name string required The spent name. Example: Office Supplies
     * @bodyParam spent_ledger_id integer required The spent ledger ID. Example: 1
     * @bodyParam amount numeric required The spent amount. Minimum: 0. Example: 5000
     * @response redirect to="spent.index"
     */
    public function update(Request $request, Spent $spent)
    {
        if (!Auth::user()->isAbleTo('spent edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $request->validate([
            'name' => 'required|string',
            'spent_ledger_id' => 'required|exists:spent_ledgers,id',
            'amount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $spent->update([
                'name' => $request->name,
                'spent_ledger_id' => $request->spent_ledger_id,
                'amount' => $request->amount,
            ]);

            DB::commit();
            return redirect()->route('spent.index')->with('success', __('Spent updated successfully!'));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', __('Error updating spent: ') . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified spent.
     *
     * @authenticated
     * @urlParam spent integer required The spent ID. Example: 1
     * @response status=200 scenario="success" {"success": true, "message": "Spent deleted successfully!"}
     */
    public function destroy(Spent $spent)
    {
        if (!Auth::user()->isAbleTo('spent delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        try {
            $spent->delete();
            return response()->json(['success' => true, 'message' => 'Spent deleted successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting spent: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a new spent ledger via AJAX.
     *
     * @authenticated
     * @bodyParam name string required The ledger name. Must be unique. Example: Office Expenses
     * @response status=200 scenario="success" {"success": true, "message": "Ledger created successfully!", "id": 1, "name": "Office Expenses"}
     */
    public function storeLedger(Request $request)
    {
        if (!Auth::user()->isAbleTo('spent ledger create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }

        $request->validate([
            'name' => 'required|string|unique:spent_ledgers,name',
        ]);

        try {
            $ledger = SpentLedger::create([
                'name' => $request->name,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ledger created successfully!',
                'id' => $ledger->id,
                'name' => $ledger->name,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error creating ledger: ' . $e->getMessage()], 500);
        }
    }
}
