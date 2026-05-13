<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('{module}')->group(function () {

    Route::post('/login',[AuthApiController::class,'login']);
    Route::post('/logout',[AuthApiController::class,'logout'])->middleware('auth:sanctum');
    Route::post('/refresh',[AuthApiController::class,'refresh'])->middleware('auth:sanctum');
    Route::post('/edit-profile',[AuthApiController::class,'editProfile'])->middleware('auth:sanctum');
    Route::post('/change-password',[AuthApiController::class,'changePassword'])->middleware('auth:sanctum');
    Route::post('/delete-account',[AuthApiController::class,'deleteAccount'])->middleware('auth:sanctum');
    Route::post('get-workspace-users',[AuthApiController::class,'getWorkspaceUsers'])->middleware('auth:sanctum');

});



