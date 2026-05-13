<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AttachmentController extends Controller {

    /**
     * Download an attachment by filename.
     *
     * @param  string  $file
     * @return \Illuminate\Http\Response
     */
    public function download($file) {
        try {
            // Build the full path to the file
            $path = public_path('uploads/attachments/' . $file);

            // Check if file exists
            if (!file_exists($path)) {
                throw new \Exception('File not found.');
            }

            // Force download with original filename
            return response()->download($path, $file);
        } catch (\Exception $e) {
            // Handle error gracefully
            return response()->json([
                        'status' => 'error',
                        'message' => $e->getMessage(),
                        'file' => $file,
                            ], 404);
        }
    }
}
