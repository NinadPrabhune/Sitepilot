<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Api\MaterialApiController;
use App\Http\Controllers\Api\UnitApiController;
use App\Http\Controllers\Api\MaterialCategoryApiController;
use App\Http\Controllers\Api\SupplierApiController;
use App\Http\Controllers\Api\SupplierCategoryApiController;
use App\Http\Controllers\Api\AssetsToolsAndEquipmentApiController;
use App\Http\Controllers\Api\MachineryCategoryApiController;
use App\Http\Controllers\Api\MachineryApiController;
use App\Http\Controllers\Api\ManPowerTypeApiController;
use App\Http\Controllers\Api\WorkSpaceApiController;
use App\Http\Controllers\Api\ProjectApiController;
use App\Http\Controllers\Api\PurchaseInvoiceApiController;
use App\Http\Controllers\Api\PurchaseOrderApiController;
use App\Http\Controllers\Api\SupplierAdvanceApiController;
use App\Http\Controllers\Api\ManPowerApiController;
use App\Http\Controllers\Api\DailyConsumptionApiController;
use App\Http\Controllers\Api\MaterialTransferApiController;
use App\Http\Controllers\Api\PaymentsModuleApiController;
use App\Http\Controllers\Api\EmployeeApiController;
use App\Http\Controllers\Api\GeneralTransferApiController;
use App\Http\Controllers\Api\DailyProgressReportApiController;
use App\Http\Controllers\Api\IndentApiController;
use App\Http\Controllers\vendor\Chatify\MessagesController;
use App\Http\Controllers\Api\GrnApiController;
use App\Http\Controllers\Api\MaterialIssueApiController;
use App\Http\Controllers\Api\MaterialReturnApiController;
use App\Http\Controllers\Api\ActivityApiController;
use App\Http\Controllers\Api\OpeningStockApiController;
use App\Http\Controllers\Api\StockLedgerApiController;
use App\Http\Controllers\Api\SiteStockApiController;

use App\Http\Controllers\Api\RolePermissionApiController;
use App\Http\Controllers\Api\StockReportApiController;
use App\Http\Controllers\Api\DeviceTokenApiController;
use App\Http\Controllers\Api\ProjectDocumentApiController;
use App\Http\Controllers\Api\ProjectFileApiController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AppInfoApiController;

use App\Http\Controllers\Api\NotificationPageApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\PaymentRequestApiController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\MachineryPaymentRequestController;
use App\Http\Controllers\MachineryPaymentLogController;
use App\Http\Controllers\Api\MachineryApiControllerLegacy;



// Public routes
Route::post('/register', [RegisteredUserController::class, 'register']);
Route::post('/login', [AuthApiController::class, 'login']);
Route::post('/auth/refresh', [AuthApiController::class, 'refresh'])->middleware('auth:sanctum');

Route::get('/test', fn() => response()->json(['message' => 'API is working']));


Route::post('/change-password', [AuthApiController::class, 'changePassword']);

Route::post('/forgot-password', [AuthApiController::class, 'sendResetLink']);
Route::post('/reset-password', [AuthApiController::class, 'resetPassword']);


// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Numbering Configuration API
    Route::get('/numbering-configs', [SettingsController::class, 'getNumberingConfigsApi']);

    // Materials
    Route::apiResource('materials', MaterialApiController::class)->names('api.materials');
    Route::get('materials/{id}/unit', [MaterialApiController::class, 'getUnit']);
    Route::get('materials/category/{categoryId}', [MaterialApiController::class, 'getByCategory']);
    Route::apiResource('material-categories', MaterialCategoryApiController::class)->names('api.material-categories');
    Route::apiResource('units', UnitApiController::class)->names('api.units');

    // Suppliers
    Route::apiResource('supplier-categories', SupplierCategoryApiController::class)->names('api.supplier-categories');
    Route::apiResource('suppliers', SupplierApiController::class)->names('api.suppliers');

    // Machinery & Tools
    Route::apiResource('machineries', MachineryApiController::class)->names('api.machineries');
    Route::apiResource('machinery-categories', MachineryCategoryApiController::class)->names('api.machinery-categories');
    Route::post('/machineries/create-data', [MachineryApiController::class, 'createData']);
    Route::apiResource('tools', AssetsToolsAndEquipmentApiController::class)->names('api.tools');
    
    Route::post('/tools/create-data', [AssetsToolsAndEquipmentApiController::class, 'createData']);
    
    Route::apiResource('manpower-types', ManPowerTypeApiController::class)->names('api.manpower-types');

    // Workspaces & Projects
    Route::apiResource('workspaces', WorkSpaceApiController::class)->names('api.workspaces');
    Route::apiResource('projects', ProjectApiController::class)->names('api.projects');
    Route::get('/projects/{project_id}/dashboard', [\App\Http\Controllers\Api\ProjectApiController::class, 'dashboard']);
    Route::post('/projects/create-data', [\App\Http\Controllers\Api\ProjectApiController::class, 'createData']);

    // Purchase Invoices
    Route::apiResource('purchase-invoice', PurchaseInvoiceApiController::class)->names('api.purchase-invoice');
    Route::post('/purchase-invoice/create-data', [PurchaseInvoiceApiController::class, 'createData']);
    Route::get('/ajax/get-purchase-invoice-by-supplier-id', [PurchaseInvoiceApiController::class, 'getPurchaseInvoiceBySupplierId']);
    Route::get('/ajax/get-purchase-invoice-by-supplier-id-edit', [PurchaseInvoiceApiController::class, 'getPurchaseInvoiceBySupplierIdEdit']);
    Route::get('/ajax/get-purchase-invoice-remaining-amount-by-purchase-invoice-id', [PurchaseInvoiceApiController::class, 'getPurchaseInvoiceRemainingAmountByPurchaseInvoiceId']);
    
    // GRN Invoice Routes
    Route::get('/purchase-invoice/grn/{grn_id}/invoice-preview', [PurchaseInvoiceApiController::class, 'getGrnDetailsForInvoice']);
    Route::post('/purchase-invoice/from-grn', [PurchaseInvoiceApiController::class, 'createInvoiceFromGrn']);

    // Payment Request Route
    Route::post('/purchase-invoice/request-payment', [PurchaseInvoiceApiController::class, 'requestPayment']);

    // Purchase Orders
    Route::post('/purchase-orders/create-data', [PurchaseOrderApiController::class, 'createData']);
    Route::get('/purchase-orders/{id}/indent-materials', [PurchaseOrderApiController::class, 'getIndentMaterials']);
    Route::get('/purchase-orders/{id}/approve', [PurchaseOrderApiController::class, 'showApproveForm']);
    Route::put('/purchase-orders/{id}/status', [PurchaseOrderApiController::class, 'updateStatus']);
    Route::post('/purchase-orders/{id}/short-close', [PurchaseOrderApiController::class, 'shortClose']);
    Route::apiResource('purchase-orders', PurchaseOrderApiController::class)->names('api.purchase-orders');

    // Supplier Advance System (Mobile Compatible with Idempotency)
    Route::post('/suppliers/{supplierId}/advances', [SupplierAdvanceApiController::class, 'createAdvance']);
    Route::get('/suppliers/{supplierId}/advances', [SupplierAdvanceApiController::class, 'getSupplierAdvanceSummary']);
    Route::post('/invoices/{invoiceId}/allocate-advance', [SupplierAdvanceApiController::class, 'allocateAdvanceToInvoice']);
    Route::post('/invoices/{invoiceId}/release-advance', [SupplierAdvanceApiController::class, 'releaseAdvanceAllocation']);
    Route::get('/invoices/{invoiceId}/net-payable', [SupplierAdvanceApiController::class, 'getInvoiceNetPayable']);
    Route::post('/invoices/{invoiceId}/finalize', [SupplierAdvanceApiController::class, 'finalizeInvoice']);

    // GRN (Goods Receipt Note)
    Route::post('/grn', [GrnApiController::class, 'store']);
    Route::post('/grn/direct', [GrnApiController::class, 'storeDirectGrn']);
    Route::get('/grn', [GrnApiController::class, 'index']);
    Route::get('/grn/create', [GrnApiController::class, 'create']);
    Route::get('/grn/po-details', [GrnApiController::class, 'getPoDetails']);
    Route::get('/grn/{id}', [GrnApiController::class, 'show']);
    Route::get('/grn/{id}/edit', [GrnApiController::class, 'edit']);
    Route::put('/grn/{id}', [GrnApiController::class, 'update']);
    Route::delete('/grn/{id}', [GrnApiController::class, 'destroy']);
    
    // GRN Invoice Preview Route (also accessible via /api/grn/{id}/invoice-preview)
    Route::get('/grn/{grn_id}/invoice-preview', [PurchaseInvoiceApiController::class, 'getGrnDetailsForInvoice']);
    
    // Legacy route for createData
    Route::post('/grn/create-data', [GrnApiController::class, 'createData']);

    // Manpower
    Route::apiResource('manpower', ManPowerApiController::class)->names('api.manpower');
    Route::post('/manpower/create-data', [ManPowerApiController::class, 'createData']);

    // Daily Consumptions
    Route::apiResource('daily-consumptions', DailyConsumptionApiController::class)->names('api.daily-consumptions');
    Route::post('/daily-consumptions/create-data', [DailyConsumptionApiController::class, 'createData']);

    // Material Transfers
    Route::apiResource('material-transfer', MaterialTransferApiController::class)->names('api.material-transfer');
    Route::post('/material-transfer/create-data', [MaterialTransferApiController::class, 'createData']);
    Route::get('/ajax/get-stock-by-site', [MaterialTransferApiController::class, 'getStockBySite']);

    // Material Issues
    Route::apiResource('material-issues', MaterialIssueApiController::class)->names('api.material-issues');
    Route::post('/material-issues/create-data', [MaterialIssueApiController::class, 'createData']);
    Route::post('/material-issues/get-available-stock', [MaterialIssueApiController::class, 'getAvailableStock']);
    Route::get('/material-issues/get-issue-to', [MaterialIssueApiController::class, 'getIssueTo']);

    // Material Returns
    Route::apiResource('material-returns', MaterialReturnApiController::class)->names('api.material-returns');
    Route::post('/material-returns/create-data', [MaterialReturnApiController::class, 'createData']);
    Route::post('/material-returns/get-issue-details', [MaterialReturnApiController::class, 'getIssueDetails']);

    // Indents
    Route::apiResource('indents', IndentApiController::class)->names('api.indents');
    Route::post('/indents/create-data', [IndentApiController::class, 'createData']);
    Route::get('/indents/{id}/materials', [IndentApiController::class, 'getIndentMaterials']);
    Route::get('/indents/available/list', [IndentApiController::class, 'getAvailableIndents']);
    Route::patch('/indents/{id}/status', [IndentApiController::class, 'updateStatus']);
        

     // Payments - TEMPORARILY DISABLED DUE TO SYNTAX ERROR
     Route::apiResource('payments', PaymentsModuleApiController::class)->names('api.payments');
     Route::post('/payments/create-data', [PaymentsModuleApiController::class, 'createData']);
     Route::get('/payments/supplier-unpaid-invoices', [PaymentsModuleApiController::class, 'getSupplierUnpaidInvoices']);
     Route::get('/payments/adjustable-advances', [PaymentsModuleApiController::class, 'getAdjustableAdvances']);
     Route::get('/payments/create-from-po/{po_id}', [PaymentsModuleApiController::class, 'createFromPo']);
     Route::get('/payments/create-from-invoice/{invoice_id}', [PaymentsModuleApiController::class, 'createFromInvoice']);

     // Supplier Ledger
     Route::get('/supplier-ledger', [PaymentsModuleApiController::class, 'getSupplierLedger']);

    // Payment Request (Mobile API - matches web logic)
    Route::get('/payment-request/list', [PaymentRequestApiController::class, 'list']);
    Route::get('/payment-request/{invoice_id}', [PaymentRequestApiController::class, 'getPaymentRequestData']);
    Route::post('/payment-request', [PaymentRequestApiController::class, 'store']);
    Route::get('/payment-request/details/{id}', [PaymentRequestApiController::class, 'show']);
    Route::post('/payment-request/{id}/approve', [PaymentRequestApiController::class, 'approve']);
    Route::post('/payment-request/{id}/payment', [PaymentRequestApiController::class, 'createPayment']);

    // PO Advance Request (Mobile API - matches web logic)
    Route::get('/po-advance-request/{po_id}', [PaymentRequestApiController::class, 'getPoAdvanceRequestData']);
    Route::post('/po-advance-request/{po_id}', [PaymentRequestApiController::class, 'storePoAdvanceRequest']);

    // Purchase Invoice Payment Request (Mobile API - matches web logic)
    Route::get('/purchase-invoice-payment-request/{invoice_id}', [PaymentRequestApiController::class, 'getPurchaseInvoicePaymentRequestData']);
    Route::post('/purchase-invoice-payment-request/{invoice_id}', [PaymentRequestApiController::class, 'storePurchaseInvoicePaymentRequest']);

    // Machinery Payment Requests (Ledger-based payment system)
    Route::prefix('machinery/payment-requests')->group(function () {
        Route::post('/', [MachineryPaymentRequestController::class, 'store']);
        Route::get('/', [MachineryPaymentRequestController::class, 'apiIndex']);
        Route::get('/{id}', [MachineryPaymentRequestController::class, 'apiShow']);
        Route::post('/{id}/submit', [MachineryPaymentRequestController::class, 'submit']);
        Route::post('/{id}/verify', [MachineryPaymentRequestController::class, 'verify']);
        Route::post('/{id}/approve', [MachineryPaymentRequestController::class, 'approve']);
        Route::post('/{id}/lock', [MachineryPaymentRequestController::class, 'lock']);
        Route::post('/{id}/pay', [MachineryPaymentRequestController::class, 'pay']);
        Route::post('/{id}/reject', [MachineryPaymentRequestController::class, 'reject']);
        Route::get('/{id}/debug', [MachineryPaymentRequestController::class, 'debug']);
        Route::get('/{id}/recalculate', [MachineryPaymentRequestController::class, 'recalculate']);
        
        // Admin controls
        Route::post('/{id}/force-reject', [MachineryPaymentRequestController::class, 'forceReject']);
        Route::post('/{id}/force-unlock', [MachineryPaymentRequestController::class, 'forceUnlock']);
        Route::post('/{id}/override-note', [MachineryPaymentRequestController::class, 'addOverrideNote']);
    });
    
    // Machinery Payment Logs
    Route::prefix('machinery/payment-logs')->group(function () {
        Route::get('/', [MachineryPaymentLogController::class, 'index']);
        Route::get('/recent', [MachineryPaymentLogController::class, 'recent']);
    });

    // Employees
    Route::apiResource('employee', EmployeeApiController::class)->names('api.employee');
    Route::post('/employee/create-data', [EmployeeApiController::class, 'createData']);
    Route::post('/getdepartment', [EmployeeApiController::class, 'getdepartment']);
    Route::post('/getdDesignation', [EmployeeApiController::class, 'getdDesignation']);

    // General Transfers
    Route::apiResource('general-transfers', GeneralTransferApiController::class)->names('api.general-transfers');
    Route::post('/general-transfers/create-data', [GeneralTransferApiController::class, 'createData']);

    // Daily Progress Reports
    Route::apiResource('daily-progress-reports', DailyProgressReportApiController::class)->names('api.daily-progress-reports');
    Route::post('/daily-progress-reports/create-data', [DailyProgressReportApiController::class, 'createData']);

    // Activities
    Route::apiResource('activities', ActivityApiController::class)->names('api.activities');
    Route::post('/activities/create-data', [ActivityApiController::class, 'createData']);
    Route::get('activities/progress/create', [ActivityApiController::class, 'createProgress'])->name('activities.progress.create');
    Route::post('activities/progress/store', [ActivityApiController::class, 'storeProgress'])->name('activities.progress.store');

    // Opening Stock
    Route::get('/opening-stock', [OpeningStockApiController::class, 'index']);
    Route::post('/opening-stock', [OpeningStockApiController::class, 'store']);
    Route::get('/opening-stock/stock', [OpeningStockApiController::class, 'getStock']);

    // Stock Ledger
    Route::prefix('stock-ledger')->group(function () {
        Route::get('/', [StockLedgerApiController::class, 'index']);
        Route::get('/export', [StockLedgerApiController::class, 'export']);
        Route::post('/create-data', [StockLedgerApiController::class, 'createData']);
    });

    // Site Stock
    Route::prefix('site-stock')->group(function () {
        Route::get('/', [SiteStockApiController::class, 'index']);
        Route::get('/export', [SiteStockApiController::class, 'export']);
        Route::post('/create-data', [SiteStockApiController::class, 'createData']);
    });

    // Role & Permissions
    Route::get('/role-permissions', [RolePermissionApiController::class, 'index']);

    // Stock Reports
    Route::get('/stock-reports-api', [StockReportApiController::class, 'index']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/device-tokens', [DeviceTokenApiController::class, 'index']);
    Route::post('/device-token', [DeviceTokenApiController::class, 'store']);
    Route::delete('/device-token', [DeviceTokenApiController::class, 'destroy']);
});



Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/UserNotifications', [NotificationPageApiController::class, 'index']);
    Route::get('/UserNotifications/unread', [NotificationPageApiController::class, 'unread']);
    Route::post('/UserNotifications/{notificationUser_id}/read', [NotificationPageApiController::class, 'markAsRead']);
    Route::post('/UserNotifications/read-all', [NotificationPageApiController::class, 'markAllAsRead']);
    Route::delete('/UserNotifications/{notificationUser}', [NotificationPageApiController::class, 'delete']);
    Route::delete('/UserNotifications', [NotificationPageApiController::class, 'deleteAll']);
    Route::get('/UserNotifications/count', [NotificationPageApiController::class, 'getCount']);
});

// Project File Manager API Routes (Sanctum authenticated)
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::prefix('project-files')->name('api.project-files.')->group(function () {
        Route::get('/', [ProjectFileApiController::class, 'index'])->name('index');
        Route::get('/tree', [ProjectFileApiController::class, 'getTree'])->name('tree');
        Route::get('/stats', [ProjectFileApiController::class, 'getStats'])->name('stats');
        Route::get('/search', [ProjectFileApiController::class, 'search'])->name('search');
        Route::post('/', [ProjectFileApiController::class, 'store'])->name('store');
        Route::post('/folder', [ProjectFileApiController::class, 'createFolder'])->name('folder.create');
        
        Route::get('{id}', [ProjectFileApiController::class, 'show'])->name('show');
        Route::put('{id}', [ProjectFileApiController::class, 'update'])->name('update');
        Route::delete('{id}', [ProjectFileApiController::class, 'destroy'])->name('destroy');
        Route::get('{id}/download', [ProjectFileApiController::class, 'download'])->name('download');
    });
});

// Project Documents API Routes (Sanctum authenticated)
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::prefix('projects')->name('api.projects.')->group(function () {
        Route::prefix('{projectId}/documents')->name('documents.')->group(function () {
            Route::get('/', [ProjectDocumentApiController::class, 'index'])->name('index');
            Route::get('/structure', [ProjectDocumentApiController::class, 'getFolderStructure'])->name('structure');
            
            // Nested structure 
            Route::get('/structure-nested', [ProjectDocumentApiController::class, 'getProjectFolderStructureNested']) ->name('structure.nested');
            
            Route::get('/stats', [ProjectDocumentApiController::class, 'getStats'])->name('stats');
            Route::post('/upload', [ProjectDocumentApiController::class, 'upload'])->name('upload');
            Route::post('/folders', [ProjectDocumentApiController::class, 'createFolder'])->name('folder.create');
            
            Route::get('{documentId}', [ProjectDocumentApiController::class, 'show'])->name('show');
            Route::put('{documentId}', [ProjectDocumentApiController::class, 'update'])->name('update');
            Route::delete('{documentId}', [ProjectDocumentApiController::class, 'delete'])->name('delete');
            Route::get('{documentId}/download', [ProjectDocumentApiController::class, 'download'])->name('download');
        });
    });
});

// User Management API Routes
Route::group(['middleware' => ['auth:sanctum']], function () {
    // User CRUD
    Route::apiResource('users', UserApiController::class)->names('api.users');
    
    // User creation data (roles)
    Route::get('users/create-data', [UserApiController::class, 'createData']);
    
    // User Profile
    Route::get('users/profile', [UserApiController::class, 'profile']);
    Route::put('users/profile', [UserApiController::class, 'editprofile']);
    Route::post('users/profile/avatar', [UserApiController::class, 'updateAvatar']);
    Route::put('users/password', [UserApiController::class, 'updatePassword']);
    
    // Admin Password Reset
    Route::get('users/{id}/password', [UserApiController::class, 'UserPassword']);
    Route::put('users/{id}/password', [UserApiController::class, 'UserPasswordReset']);
    Route::post('users/{id}/login-manage', [UserApiController::class, 'LoginManage']);
    
    // User Import
    Route::get('users/import', [UserApiController::class, 'fileImportExport']);
    Route::post('users/import-preview', [UserApiController::class, 'fileImport']);
    Route::post('users/import', [UserApiController::class, 'UserImportdata']);
    
    // User Logs
    Route::get('users/logs', [UserApiController::class, 'UserLogHistory']);
    Route::get('users/logs/{id}', [UserApiController::class, 'UserLogView']);
    Route::delete('users/logs/{id}', [UserApiController::class, 'UserLogDestroy']);
    
    // Impersonation
    Route::post('users/{id}/impersonate', [UserApiController::class, 'LoginWithCompany']);
    Route::post('users/impersonate-exit', [UserApiController::class, 'ExitCompany']);
    
    // Company Info
    Route::get('users/{id}/company-info', [UserApiController::class, 'CompnayInfo']);
    Route::post('users/enable-disable', [UserApiController::class, 'UserUnable']);
    
    // Email Verification
    Route::post('users/{id}/verify', [UserApiController::class, 'verifeduser']);
    
    // App Info
    Route::apiResource('app-info', AppInfoApiController::class)->names('api.app-info');
});

// Chatify API Routes (Sanctum authenticated for mobile, session for web)
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::prefix('chat')->group(function () {
        Route::post('/send', [MessagesController::class, 'send']);
        Route::get('/fetch/{id}', [MessagesController::class, 'fetchMobile']);
        Route::get('/contacts', [MessagesController::class, 'getContactsMobile']);
        Route::get('/favorites', [MessagesController::class, 'getFavorites']);
        Route::post('/favorite', [MessagesController::class, 'favorite']);
        Route::post('/seen', [MessagesController::class, 'seen']);
        Route::post('/updateContactItem', [MessagesController::class, 'updateContactItem']);
        Route::post('/search', [MessagesController::class, 'search']);
        Route::get('/sharedPhotos/{user_id}', [MessagesController::class, 'sharedPhotos']);
        Route::post('/deleteConversation', [MessagesController::class, 'deleteConversation']);
        Route::post('/updateSettings', [MessagesController::class, 'updateSettings']);
        Route::post('/setActiveStatus', [MessagesController::class, 'setActiveStatus']);
        Route::get('/unseen-count', [MessagesController::class, 'getUnseenCount']);
    });
});


// Debug route for auth testing
Route::middleware('auth:sanctum')->get('/debug-user', function () {
    return auth()->user();
});
