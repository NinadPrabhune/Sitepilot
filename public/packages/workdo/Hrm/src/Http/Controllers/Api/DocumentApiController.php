<?php

namespace Workdo\Hrm\Http\Controllers\Api;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Workdo\Hrm\Entities\Document;
use Workdo\Hrm\Events\CreateDocument;
use Workdo\Hrm\Events\DestroyDocument;
use Workdo\Hrm\Events\UpdateDocument;

class DocumentApiController extends Controller
{
    /**
     * List all documents
     */
    public function index(Request $request)
    {
        try {
            if (!Auth::user()->isAbleTo('document manage')) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

//            $documents = Document::where('workspace', getActiveWorkSpace())
//                ->where('site_id', getActiveProject())                
//                ->orderBy('id', 'desc')
//                ->get();
            
            
            $workspaceId = $request->input('workspace_id');
            $siteId      = $request->input('site_id');

            $query = Document::query();

            if (!empty($workspaceId) && $workspaceId != 0) {
                $query->where('workspace', $workspaceId);
            }

            if (!empty($siteId) && $siteId != 0) {
                $query->where('site_id', $siteId);
            }

            $documents = $query->get();
            
            
            

            return response()->json(['status' => 1, 'data' => $documents], 200);

        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a new document
     */
    public function store(Request $request)
    {
        try {
            if (!Auth::user()->isAbleTo('document create')) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'documents' => 'required|file',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
            }

            $url = '';
            if ($request->hasFile('documents')) {
                $filenameWithExt = $request->file('documents')->getClientOriginalName();
                $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension       = $request->file('documents')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $upload = upload_file($request, 'documents', $fileNameToStore, 'document');
                if ($upload['flag'] == 1) {
                    $url = $upload['url'];
                } else {
                    return response()->json(['status' => 0, 'message' => $upload['msg']], 500);
                }
            }

            $document = new Document();
            $document->name        = $request->name;
            $document->document    = $url;
            $document->role        = 0;
            $document->description = $request->description;
            $document->workspace   = $request->workspace_id;
            $document->site_id     = $request->site_id;
            $document->created_by  = $request->created_by;
            $document->save();

            event(new CreateDocument($request, $document));

            return response()->json(['status' => 1, 'message' => 'Document created successfully', 'data' => $document], 201);

        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show a single document
     */
    public function show($id)
    {
        try {
            $document = Document::findOrFail($id);
            return response()->json(['status' => 1, 'data' => $document], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => 'Document not found'], 404);
        }
    }

    /**
     * Update a document
     */
    public function update(Request $request, $id)
    {
        try {
            if (!Auth::user()->isAbleTo('document edit')) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
            }

            $document = Document::findOrFail($id);

            if ($request->hasFile('documents')) {
                $filenameWithExt = $request->file('documents')->getClientOriginalName();
                $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension       = $request->file('documents')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $upload = upload_file($request, 'documents', $fileNameToStore, 'document');
                if ($upload['flag'] == 1) {
                    if (!empty($document->document)) {
                        delete_file($document->document);
                    }
                    $document->document = $upload['url'];
                } else {
                    return response()->json(['status' => 0, 'message' => $upload['msg']], 500);
                }
            }

            $document->name        = $request->name;
            $document->role        = 0;
            $document->description = $request->description;
            $document->save();

            event(new UpdateDocument($request, $document));

            return response()->json(['status' => 1, 'message' => 'Document updated successfully', 'data' => $document], 200);

        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a document
     */
    public function destroy($id)
    {
        try {
            if (!Auth::user()->isAbleTo('document delete')) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            $document = Document::findOrFail($id);

            if (!empty($document->document)) {
                delete_file($document->document);
            }

            event(new DestroyDocument($document));
            $document->delete();

            return response()->json(['status' => 1, 'message' => 'Document deleted successfully'], 200);

        } catch (\Exception $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 500);
        }
    }
}
