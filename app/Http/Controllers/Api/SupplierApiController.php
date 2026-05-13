<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @group Suppliers
 * Endpoints for supplier management including CRUD operations
 */
class SupplierApiController extends Controller
{
    public function index()
    {
        if (!Auth::user()->isAbleTo('supplier manage')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        $suppliers = Supplier::where('is_active', true)->get();
        return response()->json(['status' => 1, 'data' => $suppliers]);
    }

    /**
     * Store a newly created supplier.
     *
     * @bodyParam name string required Supplier name. Example: ABC Materials Ltd
     * @bodyParam category_id integer required Supplier category ID. Example: 1
     * @bodyParam type string optional Type (company or individual). Example: company
     * @bodyParam contact_person string optional Contact person. Example: John Doe
     * @bodyParam phone string optional Phone number. Example: +91-9876543210
     * @bodyParam email string optional Email. Example: supplier@example.com
     * @bodyParam address string optional Address. Example: 123 Main Street
     * @bodyParam city string optional City. Example: Mumbai
     * @bodyParam state string optional State. Example: Maharashtra
     * @bodyParam pincode string optional Pincode. Example: 400001
     * @bodyParam country string optional Country. Example: India
     * @bodyParam gst_number string optional GST number. Example: 27ABCDE1234F1Z5
     * @bodyParam pan_number string optional PAN number. Example: ABCDE1234F
     * @bodyParam registration_number string optional Registration number. Example: REG-12345
     * @bodyParam bank_name string optional Bank name. Example: HDFC Bank
     * @bodyParam account_number string optional Account number. Example: 1234567890
     * @bodyParam ifsc_code string optional IFSC code. Example: HDFC0001234
     * @bodyParam payment_terms string optional Payment terms. Example: Net 30 days
     * @bodyParam upi_screenshot_1 image optional UPI screenshot 1 (max 2MB).
     * @bodyParam upi_screenshot_2 image optional UPI screenshot 2 (max 2MB).
     * @response {"status": 1, "data": {...}, "message": "Supplier created successfully"}
     */
    public function store(Request $request)
    {
        if (!Auth::user()->isAbleTo('supplier create')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:supplier_categories,id',
            'type' => 'nullable|in:company,individual',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'gst_number' => 'nullable|string|max:20',
            'pan_number' => 'nullable|string|max:20',
            'registration_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:30',
            'ifsc_code' => 'nullable|string|max:20',
            'payment_terms' => 'nullable|string|max:50',
            'upi_screenshot_1' => 'nullable|image|max:2048',
            'upi_screenshot_2' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $supplier = new Supplier();
        $supplier->fill($request->except(['upi_screenshot_1', 'upi_screenshot_2']));
        $supplier->created_by = $request->created_by;

        foreach (['upi_screenshot_1', 'upi_screenshot_2'] as $field) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store('images/supplier', 'public');
                $supplier->$field = $path;
            }
        }

        $supplier->save();

        return response()->json(['status' => 1, 'data' => $supplier, 'message' => 'Supplier created successfully']);
        
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        if (!Auth::user()->isAbleTo('supplier show')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['status' => 0, 'message' => 'Supplier not found'], 404);
        }
        return response()->json(['status' => 1, 'data' => $supplier]);
        
         } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user()->isAbleTo('supplier edit')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
        try {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['status' => 0, 'message' => 'Supplier not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:supplier_categories,id',
            'type' => 'nullable|in:company,individual',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'gst_number' => 'nullable|string|max:20',
            'pan_number' => 'nullable|string|max:20',
            'registration_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:30',
            'ifsc_code' => 'nullable|string|max:20',
            'payment_terms' => 'nullable|string|max:50',
            'upi_screenshot_1' => 'nullable|image|max:2048',
            'upi_screenshot_2' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $supplier->fill($request->except(['upi_screenshot_1', 'upi_screenshot_2']));

        foreach (['upi_screenshot_1', 'upi_screenshot_2'] as $field) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store('images/supplier', 'public');
                $supplier->$field = $path;
            }
        }

        $supplier->save();

        return response()->json(['status' => 1, 'data' => $supplier, 'message' => 'Supplier updated successfully']);
        
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        if (!Auth::user()->isAbleTo('supplier delete')) {
            return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
        }
         try {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['status' => 0, 'message' => 'Supplier not found'], 404);
        }
        
   
        // Check if supplier is linked in purchase_invoices
        $existsInPurchase = \DB::table('purchase_invoices')
            ->where('supplier_id', $supplier->id)
            ->exists();

        // Check if supplier is linked in man_power_masters
        $existsInManPower = \DB::table('man_power_masters')
            ->where('supplier_id', $supplier->id)
            ->exists();

        // Check if supplier is linked in payments_module
        $existsInPayment = \DB::table('payments_module')
            ->where('supplier_id', $supplier->id)
            ->exists();

        if ($existsInPurchase) {
            return response()->json([
                'status' => 0,
                'message' => 'Supplier cannot be deleted because it is used in Purchase Invoices.'
            ], 400);
        }

        if ($existsInManPower) {
            return response()->json([
                'status' => 0,
                'message' => 'Supplier cannot be deleted because it is used in Man-Power Masters records.'
            ], 400);
        }

        if ($existsInPayment) {
            return response()->json([
                'status' => 0,
                'message' => 'Supplier cannot be deleted because it is used in Payments records.'
            ], 400);
        }

        $supplier->delete();
        return response()->json(['status' => 1, 'message' => 'Supplier deleted successfully']);
        
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }
}

