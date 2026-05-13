<?php

use Illuminate\Http\Request;
use Workdo\Hrm\Http\Controllers\Api\AttendanceApiController;
use Workdo\Hrm\Http\Controllers\Api\HolidaylistApiController;
use Workdo\Hrm\Http\Controllers\Api\HomeApiController;
use Workdo\Hrm\Http\Controllers\Api\LeaveApiController;
use Workdo\Hrm\Http\Controllers\Api\LeaveTypeApiController;
use Workdo\Hrm\Http\Controllers\Api\DocumentApiController;
use Workdo\Hrm\Http\Controllers\Api\EventApiController;

use Workdo\Hrm\Http\Controllers\Api\AnnouncementApiController;
use Workdo\Hrm\Http\Controllers\Api\HolidayApiController;


/*
  |--------------------------------------------------------------------------
  | API Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register API routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | is assigned the "api" middleware group. Enjoy building your API!
  |
 */

Route::middleware('auth:sanctum')->get('/hrm', function (Request $request) {
    return $request->user();
});

Route::prefix('Hrm')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {

        Route::post('home', [HomeApiController::class, 'index']);

        Route::get('events', [HomeApiController::class, 'getEvents']);

        Route::post('holidays-list', [HolidaylistApiController::class, 'index']);

        Route::get('attendence-history', [AttendanceApiController::class, 'attendenceHistory']);
        Route::post('clock-in-out', [AttendanceApiController::class, 'clockInOut']);
        Route::post('admin-attendence-insert', [AttendanceApiController::class, 'AdminAttendenceInsert']);
        Route::get('admin-attendence/{id}', [AttendanceApiController::class, 'show']);   
        Route::post('admin-attendence-update/{id}', [AttendanceApiController::class, 'AdminAttendenceUpdate']); 
        Route::delete('admin-attendence-delete/{id}', [AttendanceApiController::class, 'AdminAttendenceDelete']);

        Route::post('createData', [AttendanceApiController::class, 'createData']);
        // Route::post('clock-out',[AttendanceApiController::class,'clockOut']);

        
        
        Route::get('leaves', [LeaveApiController::class, 'index']);
        Route::post('leaves', [LeaveApiController::class, 'store']);
        Route::get('leaves/{id}', [LeaveApiController::class, 'show']);     // show single leave
        Route::put('leaves/{id}', [LeaveApiController::class, 'update']);  // update leave
        Route::delete('leaves/{id}', [LeaveApiController::class, 'destroy']); // delete leave

        // Additional Leave API endpoints
        Route::get('leaves/{id}/action', [LeaveApiController::class, 'action']);
        Route::post('leaves/change-status', [LeaveApiController::class, 'changeStatus']);
        Route::post('leaves/summary', [LeaveApiController::class, 'leaveSummary']);
        Route::get('leaves/{id}/description', [LeaveApiController::class, 'description']);
        Route::get('leaves/{id}/status-reason', [LeaveApiController::class, 'status_reason']);


        Route::get('leaves-types', [LeaveTypeApiController::class, 'index']);
        Route::post('leaves-types', [LeaveTypeApiController::class, 'store']);        
        Route::get('leaves-types/{id}', [LeaveTypeApiController::class, 'show']);     // show single leave
        Route::put('leaves-types/{id}', [LeaveTypeApiController::class, 'update']);  // update leave
        Route::delete('leaves-types/{id}', [LeaveTypeApiController::class, 'destroy']); // delete leave

        // List all documents
//        Route::get('/documents', [DocumentApiController::class, 'index'])->name('api.documents.index');
//        
//
//        // Create a new document
//        Route::post('/documents', [DocumentApiController::class, 'store'])->name('api.documents.store');
//
//        // Show a single document
//        Route::get('/documents/{id}', [DocumentApiController::class, 'show'])->name('api.documents.show');
//
//        // Update a document
//        Route::put('/documents/{id}', [DocumentApiController::class, 'update'])->name('api.documents.update');
//
//        // Delete a document
//        Route::delete('/documents/{id}', [DocumentApiController::class, 'destroy'])->name('api.documents.destroy');

        Route::get('/events', [EventApiController::class, 'index']);
        Route::post('/events', [EventApiController::class, 'store']);
        Route::get('/events/{id}', [EventApiController::class, 'show']);
        Route::put('/events/{id}', [EventApiController::class, 'update']);
        Route::delete('/events/{id}', [EventApiController::class, 'destroy']);

        Route::get('/announcements', [AnnouncementApiController::class, 'index']);
        Route::post('/announcements', [AnnouncementApiController::class, 'store']);
        Route::get('/announcements/{id}', [AnnouncementApiController::class, 'show']);
        Route::put('/announcements/{id}', [AnnouncementApiController::class, 'update']);
        Route::delete('/announcements/{id}', [AnnouncementApiController::class, 'destroy']);
        Route::post('/announcements/create-data', [AnnouncementApiController::class, 'createData']);
      
        Route::get('/holidays', [HolidayApiController::class, 'index']);
        Route::post('/holidays', [HolidayApiController::class, 'store']);
        Route::get('/holidays/{id}', [HolidayApiController::class, 'show']);
        Route::put('/holidays/{id}', [HolidayApiController::class, 'update']);
        Route::delete('/holidays/{id}', [HolidayApiController::class, 'destroy']);
    });
});
