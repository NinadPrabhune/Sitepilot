<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentsModuleApiController extends Controller
{
    public function index(Request $request)
    {
        try {
            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'name' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false], 422);
            }

            DB::beginTransaction();
            try {
                $payment = [];
                DB::commit();
                return response()->json(['success' => true, 'data' => $payment], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['success' => false], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false], 500);
        }
    }

    public function show($id)
    {
        try {
            return response()->json(['id' => $id], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false], 500);
        }
    }
}