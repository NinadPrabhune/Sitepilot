<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'name' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false], 422);
            }

            return response()->json([
                'success' => true,
                'data' => []
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false], 500);
        }
    }

    public function show($id)
    {
        try {
            return response()->json(['id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false], 500);
        }
    }
}