<?php
class Test extends Controller {
    public function index() {
        try {
            $data = ['test'];
            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id) {
        return response()->json(['id' => $id]);
    }
}