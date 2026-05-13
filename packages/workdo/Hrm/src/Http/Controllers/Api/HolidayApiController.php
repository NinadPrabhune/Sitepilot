<?php

namespace Workdo\Hrm\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Workdo\Hrm\Entities\Holiday;
use Workdo\Hrm\Events\CreateHolidays;
use Workdo\Hrm\Events\UpdateHolidays;
use Workdo\Hrm\Events\DestroyHolidays;

/**
 * @group HRM Holidays
 * Endpoints for holiday management
 */
class HolidayApiController extends Controller
{
    /**
     * List holidays with optional filters
     */
    public function index(Request $request)
    {
        try {
            if (!Auth::user()->isAbleTo('holiday manage')) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            $workspaceId = $request->input('workspace_id');
            $siteId      = $request->input('site_id');

            $query = Holiday::query();

            if (!empty($workspaceId) && $workspaceId != 0) {
                $query->where('workspace', $workspaceId);
            }

            if (!empty($siteId) && $siteId != 0) {
                $query->where('site_id', $siteId);
            }

            $holidays = $query->get();

            return response()->json(['status' => 1, 'data' => $holidays]);
        } catch (\Exception $e) {
            Log::error('HolidayApiController@index error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 0, 'message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a new holiday
     *
     * @bodyParam occasion string required Holiday occasion/name. Example: Diwali
     * @bodyParam start_date date required Start date (must be after yesterday). Example: 2024-11-01
     * @bodyParam end_date date required End date (must be after or equal to start_date). Example: 2024-11-05
     * @bodyParam workspace_id integer optional Workspace ID. Example: 1
     * @bodyParam site_id integer optional Site ID. Example: 5
     * @bodyParam created_by integer optional Creator user ID. Example: 1
     * @response {"status": 1, "message": "Holiday created successfully", "data": {...}}
     */
    public function store(Request $request)
    {
        try {
            if (!Auth::user()->isAbleTo('holiday create')) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            $validator = \Validator::make($request->all(), [
                'occasion'   => 'required',
                'start_date' => 'required|after:yesterday',
                'end_date'   => 'required|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
            }

            $holiday = new Holiday();
            $holiday->occasion   = $request->occasion;
            $holiday->start_date = $request->start_date;
            $holiday->end_date   = $request->end_date;
            $holiday->workspace  = $request->input('workspace_id', getActiveWorkSpace());
            $holiday->site_id    = $request->input('site_id', getActiveProject());
            $holiday->created_by = $request->input('created_by', creatorId());
            $holiday->save();

            event(new CreateHolidays($request, $holiday));

            return response()->json(['status' => 1, 'message' => 'Holiday created successfully', 'data' => $holiday]);
        } catch (\Exception $e) {
            Log::error('HolidayApiController@store error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 0, 'message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show holiday details
     */
    public function show($id)
    {
        try {
            $holiday = Holiday::find($id);

            if (!$holiday) {
                return response()->json(['status' => 0, 'message' => 'Holiday not found'], 404);
            }

            return response()->json(['status' => 1, 'data' => $holiday]);
        } catch (\Exception $e) {
            Log::error('HolidayApiController@show error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 0, 'message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update holiday
     */
    public function update(Request $request, $id)
    {
        try {
            $holiday = Holiday::find($id);

            if (!$holiday) {
                return response()->json(['status' => 0, 'message' => 'Holiday not found'], 404);
            }

            if (!Auth::user()->isAbleTo('holiday edit')) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            $validator = \Validator::make($request->all(), [
                'occasion'   => 'required',
                'start_date' => 'required|after:yesterday',
                'end_date'   => 'required|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
            }

            $holiday->occasion   = $request->occasion;
            $holiday->start_date = $request->start_date;
            $holiday->end_date   = $request->end_date;
            $holiday->save();

            event(new UpdateHolidays($request, $holiday));

            return response()->json(['status' => 1, 'message' => 'Holiday updated successfully', 'data' => $holiday]);
        } catch (\Exception $e) {
            Log::error('HolidayApiController@update error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 0, 'message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete holiday
     */
    public function destroy($id)
    {
        try {
            $holiday = Holiday::find($id);

            if (!$holiday) {
                return response()->json(['status' => 0, 'message' => 'Holiday not found'], 404);
            }

            if (!Auth::user()->isAbleTo('holiday delete')) {
                return response()->json(['status' => 0, 'message' => 'Permission denied'], 403);
            }

            event(new DestroyHolidays($holiday));
            $holiday->delete();

            return response()->json(['status' => 1, 'message' => 'Holiday deleted successfully']);
        } catch (\Exception $e) {
            Log::error('HolidayApiController@destroy error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 0, 'message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }
}
