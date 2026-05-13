<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machinery;
use App\Http\Resources\MachineryResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * @group Machinery
 * Endpoints for machinery management including CRUD operations
 * 
 * @authenticated
 * @header Authorization Bearer {token} Required authentication token
 */
class MachineryApiController extends Controller
{
    public function index(Request $request)
    {
        if (!Auth::user()->isAbleTo('machinery manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
            // Get filters from request
            $workspaceId = $request->input('workspace_id');
            $siteId      = $request->input('site_id');

            // Build query
            $query = Machinery::where('status', 0);

            if (!empty($workspaceId)) {
                $query->where('workspace_id', $workspaceId);
            }

            if (!empty($siteId)) {
                $query->where('site_id', $siteId);
            }

            $machineries = $query->get();

            return response()->json([
                'status' => 1,
                'data'   => MachineryResource::collection($machineries),
            ], 200);

        } catch (\Exception $e) {
            // Log error for debugging
            Log::error('Error fetching machineries: ' . $e->getMessage());

            return response()->json([
                'status'  => 0,
                'message' => 'Failed to fetch machineries.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create Machinery
     *
     * Create a new machinery record
     *
     * @bodyParam name string required Machinery name. Example: Excavator JCB
     * @bodyParam category_id integer required Machinery category ID. Example: 1
     * @bodyParam model_number string optional Model number. Example: JCB 3DX
     * @bodyParam manufacturer string optional Manufacturer. Example: JCB
     * @bodyParam purchase_date date optional Purchase date. Example: 2024-01-15
     * @bodyParam capacity string optional Capacity. Example: 10 tons
     * @bodyParam maintenance_schedule date optional Next maintenance date. Example: 2024-06-15
     * @bodyParam remarks string optional Remarks. Example: Regular maintenance required
     * @bodyParam description string optional Description. Example: Heavy duty excavator
     * @bodyParam vehicle_number string required Vehicle registration number. Example: MH-01-AB-1234
     * @bodyParam owned_by string required Ownership type (owned, rental). Example: owned
     * @bodyParam supplier_id integer required_if:owned_by,rental Supplier ID for rental machinery. Example: 5
     * @bodyParam rate numeric required Rate value. Example: 1500.00
     * @bodyParam rate_type string required_if:owned_by,rental Rate type (hourly, daily, monthly). Example: hourly
     * @bodyParam minimum_billing_hours numeric required_if:owned_by,rental Minimum billing hours. Example: 8
     * @bodyParam diesel_by_company boolean optional Diesel provided by company. Example: false
     * @bodyParam operator_by_supplier boolean optional Operator provided by supplier. Example: true
     * @bodyParam number_of_operators integer required_if:operator_by_supplier,1 Number of operators. Example: 2
     * @bodyParam purchase_value numeric required_if:owned_by,owned Purchase value. Example: 2500000.00
     * @bodyParam insurance_due_date date required_if:owned_by,owned Insurance due date. Example: 2024-12-31
     * @bodyParam puc_due_date date required_if:owned_by,owned PUC due date. Example: 2024-11-30
     * @bodyParam fitness_due_date date required_if:owned_by,owned Fitness due date. Example: 2024-10-31
     * @bodyParam last_service_date date required_if:owned_by,owned Last service date. Example: 2024-05-01
     * @bodyParam operational_status string required Status (active, breakdown, scrap). Example: active
     * @bodyParam site_id integer required Site ID. Example: 5
     * @bodyParam status string optional Status. Example: 0
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam rental_agreement_file file optional Rental agreement file (PDF/DOC/DOCX max 10MB)
     * @bodyParam ownership_documents_file file optional Ownership documents file (PDF/DOC/DOCX/IMG max 10MB)
     * @response {"status": 1, "data": {...}, "message": "Machinery created successfully"}
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('machinery create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $validator = Validator::make($request->all(), [
            // Basic fields
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:machinery_categories,id',
            'model_number' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'capacity' => 'nullable|string|max:255',
            'maintenance_schedule' => 'nullable|date',
            'remarks' => 'nullable|string',
            'description' => 'nullable|string',
            'vehicle_number' => 'required|string|max:255',
            'operational_status' => 'required|in:active,breakdown,scrap',
            'site_id' => 'nullable|integer',
            'status' => 'nullable|string|max:50',
            'created_by' => 'required|integer',
            'workspace_id' => 'required|integer',
            
            // Ownership and rate fields
            'owned_by' => 'required|in:owned,rental',
            'supplier_id' => 'required_if:owned_by,rental|prohibited_unless:owned_by,rental|nullable|exists:suppliers,id',
            'rate' => 'required_if:owned_by,rental|nullable|numeric|min:0',
            
            // Rental-specific fields
            'rate_type' => 'required_if:owned_by,rental|in:hourly,daily,monthly',
            'minimum_billing_hours' => 'required_if:owned_by,rental|numeric|min:0',
            'diesel_by_company' => 'boolean',
            'operator_by_supplier' => 'boolean',
            'number_of_operators' => 'required_if:operator_by_supplier,1|nullable|integer|min:1',
            
            // Owned-specific fields
            'purchase_value' => 'required_if:owned_by,owned|numeric|min:0',
            'insurance_due_date' => 'required_if:owned_by,owned|date',
            'puc_due_date' => 'required_if:owned_by,owned|date',
            'fitness_due_date' => 'required_if:owned_by,owned|date',
            'last_service_date' => 'required_if:owned_by,owned|date',
            
            // File uploads
            'rental_agreement_file' => 'nullable|mimes:pdf,doc,docx|max:10240',
            'ownership_documents_file' => 'nullable|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $machineryData = [
            // Basic fields
            'name' => $request->name,
            'category_id' => $request->category_id,
            'model_number' => $request->model_number,
            'manufacturer' => $request->manufacturer,
            'purchase_date' => $request->purchase_date,
            'capacity' => $request->capacity,
            'maintenance_schedule' => $request->maintenance_schedule,
            'remarks' => $request->remarks,
            'description' => $request->description,
            'vehicle_number' => $request->vehicle_number,
            'operational_status' => $request->operational_status,
            'site_id' => $request->site_id,
            'status' => $request->status ?? '0',
            'created_by' => $request->created_by,
            'workspace_id' => $request->workspace_id,
            
            // Ownership fields
            'owned_by' => $request->owned_by,
            'supplier_id' => $request->owned_by === 'rental' ? $request->supplier_id : null,
            'rate' => $request->rate,
            
            // Rental fields (with company policy defaults)
            'rate_type' => $request->rate_type,
            'minimum_billing_hours' => $request->minimum_billing_hours,
            'diesel_by_company' => $request->boolean('diesel_by_company', false), // Company Policy: Always false
            'operator_by_supplier' => $request->boolean('operator_by_supplier', true), // Company Policy: Always true
            'number_of_operators' => $request->number_of_operators,
            
            // Owned fields
            'purchase_value' => $request->purchase_value,
            'insurance_due_date' => $request->insurance_due_date,
            'puc_due_date' => $request->puc_due_date,
            'fitness_due_date' => $request->fitness_due_date,
            'last_service_date' => $request->last_service_date,
        ];

        $machinery = Machinery::create($machineryData);

        // Handle file uploads after machinery creation (to get machine_id)
        if ($request->hasFile('rental_agreement_file')) {
            $fileName = $this->handleFileUpload($request->file('rental_agreement_file'), $machinery->machine_id);
            $machinery->update(['rental_agreement_file' => $fileName]);
        }

        if ($request->hasFile('ownership_documents_file')) {
            $fileName = $this->handleFileUpload($request->file('ownership_documents_file'), $machinery->machine_id);
            $machinery->update(['ownership_documents_file' => $fileName]);
        }

        return response()->json(['status' => 1, 'data' => new MachineryResource($machinery), 'message' => 'Machinery created successfully']);
    }

    /**
     * Handle file upload for machinery documents
     */
    private function handleFileUpload($file, $machineId)
    {
        $uuid = \Illuminate\Support\Str::uuid()->toString();
        $fileName = $uuid . '_' . $machineId . '_' . $file->getClientOriginalName();
        $file->storeAs('machinery_documents', $fileName, 'public');
        return $fileName;
    }

    /**
     * Get data needed for creating machinery
     *
     * @group Machinery
     * @bodyParam site_id integer required Site ID. Example: 1
     * @bodyParam workspace_id integer required Workspace ID. Example: 1
     * @bodyParam created_by integer required Creator user ID. Example: 1
     * @response {
     *   "status": 1,
     *   "data": {
     *     "categories": [{"id": 1, "name": "Excavators"}],
     *     "suppliers": [{"id": 1, "name": "ABC Rentals"}],
     *     "sites": [{"id": 1, "name": "Project Site A"}],
     *     "rate_types": [{"value": "hourly", "label": "Hourly"}],
     *     "operational_statuses": [{"value": "active", "label": "Active"}],
     *     "ownership_types": [{"value": "owned", "label": "Owned"}]
     *   },
     *   "message": "Create data fetched successfully"
     * }
     */
    public function createData(Request $request)
    {
        if (!Auth::user()->isAbleTo('machinery create')) {
            return response()->json([
                'status' => 0,
                'message' => 'Permission denied'
            ], 403);
        }

        try {
            $request->validate([
                'site_id' => 'required|integer',
                'workspace_id' => 'required|integer',
                'created_by' => 'required|integer',
            ]);

            $workspaceId = $request->workspace_id;
            $siteId = $request->site_id;

            // Get machinery categories
            $categories = \App\Models\MachineryCategory::select('id', 'name')
                ->where('status', 0)
                ->orderBy('name')
                ->get();

            // Get suppliers for rental machinery
            $suppliers = \App\Models\Supplier::select('id', 'name')
                ->where('status', 0)
                ->orderBy('name')
                ->get();

            // Get sites for the workspace
            $sites = \Workdo\Taskly\Entities\Project::select('id', 'name')
                ->where('workspace', $workspaceId)
                ->orderBy('name')
                ->get();

            // Get available rate types
            $rateTypes = [
                ['value' => 'hourly', 'label' => 'Hourly'],
                ['value' => 'daily', 'label' => 'Daily'],
                ['value' => 'monthly', 'label' => 'Monthly']
            ];

            // Get operational statuses
            $operationalStatuses = [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'breakdown', 'label' => 'Breakdown'],
                ['value' => 'scrap', 'label' => 'Scrap']
            ];

            // Get ownership types
            $ownershipTypes = [
                ['value' => 'owned', 'label' => 'Owned'],
                ['value' => 'rental', 'label' => 'Rental']
            ];

            return response()->json([
                'status' => 1,
                'data' => [
                    'categories' => $categories,
                    'suppliers' => $suppliers,
                    'sites' => $sites,
                    'rate_types' => $rateTypes,
                    'operational_statuses' => $operationalStatuses,
                    'ownership_types' => $ownershipTypes,
                    'workspace_id' => $workspaceId,
                    'site_id' => $siteId
                ],
                'message' => 'Create data fetched successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching machinery create data: ' . $e->getMessage());

            return response()->json([
                'status' => 0,
                'message' => 'Failed to fetch create data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        if (!Auth::user()->isAbleTo('machinery show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $machinery = Machinery::find($id);
        if (!$machinery) {
            return response()->json(['status' => 0, 'message' => 'Machinery not found'], 404);
        }

        return response()->json(['status' => 1, 'data' => new MachineryResource($machinery)]);
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('machinery edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $machinery = Machinery::find($id);
        if (!$machinery) {
            return response()->json(['status' => 0, 'message' => 'Machinery not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            // Basic fields
            'name' => 'required|string|max:255|unique:machineries,name,' . $id,
            'category_id' => 'required|integer|exists:machinery_categories,id',
            'model_number' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'capacity' => 'nullable|string|max:255',
            'maintenance_schedule' => 'nullable|date',
            'remarks' => 'nullable|string',
            'description' => 'nullable|string',
            'vehicle_number' => 'required|string|max:255',
            'operational_status' => 'required|in:active,breakdown,scrap',
            'site_id' => 'nullable|integer',
            'status' => 'nullable|string|max:50',
            'created_by' => 'required|integer',
            'workspace_id' => 'required|integer',
            
            // Ownership and rate fields
            'owned_by' => 'required|in:owned,rental',
            'supplier_id' => 'required_if:owned_by,rental|prohibited_unless:owned_by,rental|nullable|exists:suppliers,id',
            'rate' => 'required_if:owned_by,rental|nullable|numeric|min:0',
            
            // Rental-specific fields
            'rate_type' => 'required_if:owned_by,rental|in:hourly,daily,monthly',
            'minimum_billing_hours' => 'required_if:owned_by,rental|numeric|min:0',
            'diesel_by_company' => 'boolean',
            'operator_by_supplier' => 'boolean',
            'number_of_operators' => 'required_if:operator_by_supplier,1|nullable|integer|min:1',
            
            // Owned-specific fields
            'purchase_value' => 'required_if:owned_by,owned|numeric|min:0',
            'insurance_due_date' => 'required_if:owned_by,owned|date',
            'puc_due_date' => 'required_if:owned_by,owned|date',
            'fitness_due_date' => 'required_if:owned_by,owned|date',
            'last_service_date' => 'required_if:owned_by,owned|date',
            
            // File uploads
            'rental_agreement_file' => 'nullable|mimes:pdf,doc,docx|max:10240',
            'ownership_documents_file' => 'nullable|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $machineryData = [
            // Basic fields
            'name' => $request->name,
            'category_id' => $request->category_id,
            'model_number' => $request->model_number,
            'manufacturer' => $request->manufacturer,
            'purchase_date' => $request->purchase_date,
            'capacity' => $request->capacity,
            'maintenance_schedule' => $request->maintenance_schedule,
            'remarks' => $request->remarks,
            'description' => $request->description,
            'vehicle_number' => $request->vehicle_number,
            'operational_status' => $request->operational_status,
            'site_id' => $request->site_id,
            'status' => $request->status ?? '0',
            'created_by' => $request->created_by,
            'workspace_id' => $request->workspace_id,
            
            // Ownership fields
            'owned_by' => $request->owned_by,
            'supplier_id' => $request->owned_by === 'rental' ? $request->supplier_id : null,
            'rate' => $request->rate,
            
            // Rental fields (with company policy defaults)
            'rate_type' => $request->rate_type,
            'minimum_billing_hours' => $request->minimum_billing_hours,
            'diesel_by_company' => $request->boolean('diesel_by_company', false), // Company Policy: Always false
            'operator_by_supplier' => $request->boolean('operator_by_supplier', true), // Company Policy: Always true
            'number_of_operators' => $request->number_of_operators,
            
            // Owned fields
            'purchase_value' => $request->purchase_value,
            'insurance_due_date' => $request->insurance_due_date,
            'puc_due_date' => $request->puc_due_date,
            'fitness_due_date' => $request->fitness_due_date,
            'last_service_date' => $request->last_service_date,
        ];

        $machinery->update($machineryData);

        // Handle file uploads
        if ($request->hasFile('rental_agreement_file')) {
            // Delete old file if exists
            if ($machinery->rental_agreement_file) {
                Storage::disk('public')->delete('machinery_documents/' . $machinery->rental_agreement_file);
            }
            $fileName = $this->handleFileUpload($request->file('rental_agreement_file'), $machinery->machine_id);
            $machinery->update(['rental_agreement_file' => $fileName]);
        }

        if ($request->hasFile('ownership_documents_file')) {
            // Delete old file if exists
            if ($machinery->ownership_documents_file) {
                Storage::disk('public')->delete('machinery_documents/' . $machinery->ownership_documents_file);
            }
            $fileName = $this->handleFileUpload($request->file('ownership_documents_file'), $machinery->machine_id);
            $machinery->update(['ownership_documents_file' => $fileName]);
        }

        return response()->json(['status' => 1, 'data' => new MachineryResource($machinery), 'message' => 'Machinery updated successfully']);
    }

    public function destroy($id)
    {
        if (!Auth::user()->isAbleTo('machinery delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $machinery = Machinery::find($id);
        if (!$machinery) {
            return response()->json(['status' => 0, 'message' => 'Machinery not found'], 404);
        }

        $machinery->delete();
        return response()->json(['status' => 1, 'message' => 'Machinery deleted successfully']);
    }
}

