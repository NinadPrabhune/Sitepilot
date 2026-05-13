<?php


use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BanktransferController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Company\SettingsController as CompanySettingsController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomDomainRequestController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\HelpdeskConversionController;
use App\Http\Controllers\HelpdeskTicketCategoryController;
use App\Http\Controllers\HelpdeskTicketController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseDebitNoteController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierLedgerReportController;
use App\Http\Controllers\SupplierActivityReportController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SuperAdmin\SettingsController as SuperAdminSettingsController;
use App\Http\Controllers\WarehouseTransferController;
use App\Http\Controllers\WorkSpaceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReferralProgramController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\MaterialCategoryController;
use App\Http\Controllers\SupplierCategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\MachineryCategoryController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MachineryController;
use App\Http\Controllers\AssetsToolsAndEquipmentController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\MaintenanceLogController;
use App\Http\Controllers\MachineryPaymentPeriodController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\ReportsController;

use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\PurchaseInvoiceItemController;
use App\Http\Controllers\IndentController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\GrnController;
use App\Http\Controllers\SpentController;
use Workdo\Hrm\Http\Controllers\ManPowerController;
use App\Http\Controllers\ManPowerTypeController;
use App\Http\Controllers\DailyConsumptionController;

use App\Http\Controllers\MaterialTransferController;
use App\Http\Controllers\MaterialIssueController;
use App\Http\Controllers\MaterialReturnController;
use App\Http\Controllers\PaymentsModuleController;

use App\Http\Controllers\GeneralTransferController;

use App\Http\Controllers\DailyProgressReportController;
use App\Http\Controllers\OpeningStockController;
use App\Http\Controllers\StockLedgerController;
use App\Http\Controllers\SiteStockController;
use App\Http\Controllers\ActivityController;

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\NotificationPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PaymentRequestController;
use App\Http\Controllers\MachineryPaymentRequestController;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


// Auth::routes();
require __DIR__ . '/auth.php';

// custom domain code
Route::middleware('domain-check')->group(function () {
    Route::get('/register/{lang?}', [RegisteredUserController::class, 'create'])->name('register');
    Route::get('/login/{lang?}', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::get('/forgot-password/{lang?}', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::get('/verify-email/{lang?}', [EmailVerificationPromptController::class, '__invoke'])->name('verification.notice');

    // module page before login
    Route::get('add-on', [HomeController::class, 'Software'])->name('apps.software');
    Route::get('add-on/details/{slug}', [HomeController::class, 'SoftwareDetails'])->name('software.details');
    Route::get('pricing', [HomeController::class, 'Pricing'])->name('apps.pricing');
    Route::get('pricing/plans', [HomeController::class, 'PricingPlans'])->name('apps.pricing.plan');
    Route::get('pages', [HomeController::class, 'CustomPage'])->name('custompage');
    Route::get('/', [HomeController::class, 'index'])->name('start');
});
Route::middleware(['auth', 'verified'])->group(function () {

    // DEBUG ROUTE - REMOVE AFTER TESTING
    Route::get('/debug-projects', function () {
        $user = Auth::user();
        
        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'active_workspace' => $user->active_workspace,
                'type' => $user->type,
            ],
            'projects_4_5' => Workdo\Taskly\Entities\Project::whereIn('id', [4, 5])->get(['id', 'name', 'workspace', 'created_by']),
            'user_projects' => Workdo\Taskly\Entities\UserProject::where('user_id', $user->id)->get(),
            'all_projects_in_workspace' => Workdo\Taskly\Entities\Project::where('workspace', $user->active_workspace)->get(['id', 'name', 'workspace', 'created_by']),
            'getProject_result' => getProject()->pluck('id')->toArray(),
        ];
    })->name('debug.projects');

    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    
    
    
    //Role & Permission
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);

    //dashbord
    Route::get('/dashboard', [HomeController::class, 'Dashboard'])->name('dashboard');
    Route::get('/home', [HomeController::class, 'Dashboard'])->name('home');


    // Numbering configuration routes (must be before resource route to avoid conflicts)
    Route::prefix('settings/numbering')->middleware(['auth'])->group(function () {
        Route::get('/', [SettingsController::class, 'numberingIndex'])->name('settings.numbering.index');
        Route::post('/update', [SettingsController::class, 'updateNumberingConfig'])->name('settings.numbering.update');
        Route::get('/effective-config', [SettingsController::class, 'getEffectiveConfig'])->name('settings.numbering.effective');
        Route::get('/test-next-number', [SettingsController::class, 'testNextNumber'])->name('settings.numbering.test');
        Route::get('/audit', [SettingsController::class, 'auditLog'])->name('settings.numbering.audit');
        Route::post('/force-reset', [SettingsController::class, 'forceResetSequence'])->name('settings.numbering.force-reset');
    });
    
    // settings
    Route::resource('settings', SettingsController::class);
    Route::post('settings-save', [CompanySettingsController::class, 'store'])->name('settings.save');
    Route::post('company/settings-save', [CompanySettingsController::class, 'store'])->name('company.settings.save');
    Route::post('super-admin/settings-save', [SuperAdminSettingsController::class, 'store'])->name('super.admin.settings.save');
    Route::post('super-admin/system-settings-save', [SuperAdminSettingsController::class, 'SystemStore'])->name('super.admin.system.setting.store');
    Route::post('company/system-settings-save', [CompanySettingsController::class, 'SystemStore'])->name('company.system.setting.store');
    Route::post('company-setting-save', [CompanySettingsController::class, 'companySettingStore'])->name('company.setting.save');
    Route::post('comapny-currency-settings', [CompanySettingsController::class, 'saveCompanyCurrencySettings'])->name('company.setting.currency.settings');
    Route::post('company/update-note-value', [SuperAdminSettingsController::class, 'updateNoteValue'])->name('company.update.note.value');

    Route::post('email-settings-save', [SettingsController::class, 'mailStore'])->name('email.setting.store');
    Route::post('test-mail', [SettingsController::class, 'testMail'])->name('test.mail');
    Route::post('test-mail-send', [SettingsController::class, 'sendTestMail'])->name('test.mail.send');
    Route::post('email/getfields', [SettingsController::class, 'getfields'])->name('get.emailfields');
    Route::post('email-notification-settings-save', [SettingsController::class, 'mailNotificationStore'])->name('email.notification.setting.store');

    Route::post('cookie-settings-save', [SuperAdminSettingsController::class, 'CookieSetting'])->name('cookie.setting.store');
    Route::post('pusher-setting', [SuperAdminSettingsController::class, 'savePusherSettings'])->name('pusher.setting');
    Route::post('seo/setting/save', [SuperAdminSettingsController::class, 'seoSetting'])->name('seo.setting.save');
    Route::post('storage-settings-save', [SuperAdminSettingsController::class, 'storageStore'])->name('storage.setting.store');
    Route::post('ai/key/setting/save', [SuperAdminSettingsController::class, 'aiKeySettingSave'])->name('ai.key.setting.save');
    Route::post('currency-settings', [SuperAdminSettingsController::class, 'saveCurrencySettings'])->name('super.admin.currency.settings');
    Route::post('/update-note-value', [SuperAdminSettingsController::class, 'updateNoteValue'])->name('admin.update.note.value');

    Route::get('/setting/section/{module}/{method?}', [SettingsController::class, 'getSettingSection'])->name('setting.section.get');

    // bank-transfer
    Route::resource('bank-transfer-request', BanktransferController::class);
    Route::post('bank-transfer-setting', [BanktransferController::class, 'setting'])->name('bank.transfer.setting');
    Route::post('/bank/transfer/pay', [BanktransferController::class, 'planPayWithBank'])->name('plan.pay.with.bank');


    Route::get('invoice-bank-request/{id}', [BanktransferController::class, 'invoiceBankRequestEdit'])->name('invoice.bank.request.edit');
    Route::post('bank-transfer-request-edit/{id}', [BanktransferController::class, 'invoiceBankRequestupdate'])->name('invoice.bank.request.update');

    // domain Request Module
    Route::resource('custom_domain_request', CustomDomainRequestController::class);
    Route::get('custom_domain_request/{id}/{response}', [CustomDomainRequestController::class, 'acceptRequest'])->name('custom_domain_request.request');

    //users
    Route::resource('users', UserController::class);
    Route::get('users/list/view', [UserController::class, 'List'])->name('users.list.view');
    Route::get('profile', [UserController::class, 'profile'])->name('profile');
    Route::post('edit-profile', [UserController::class, 'editprofile'])->name('edit.profile');
    Route::post('change-password', [UserController::class, 'updatePassword'])->name('update.password');
    Route::any('user-reset-password/{id}', [UserController::class, 'UserPassword'])->name('users.reset');
    Route::get('user-login/{id}', [UserController::class, 'LoginManage'])->name('users.login');
    Route::post('user-reset-password/{id}', [UserController::class, 'UserPasswordReset'])->name('user.password.update');
    Route::get('users/{id}/login-with-company', [UserController::class, 'LoginWithCompany'])->name('login.with.company');
    Route::get('company-info/{id}', [UserController::class, 'CompnayInfo'])->name('company.info');
    Route::post('user-unable', [UserController::class, 'UserUnable'])->name('user.unable');
    Route::get('user-verified/{id}', [UserController::class, 'verifeduser'])->name('user.verified');

    //User Log
    Route::get('users/logs/history', [UserController::class, 'UserLogHistory'])->name('users.userlog.history');
    Route::get('users/logs/{id}', [UserController::class, 'UserLogView'])->name('users.userlog.view');
    Route::delete('users/logs/destroy/{id}', [UserController::class, 'UserLogDestroy'])->name('users.userlog.destroy');

    // users import
    Route::get('users/import/export', [UserController::class, 'fileImportExport'])->name('users.file.import');
    Route::get('users/import/modal', [UserController::class, 'fileImportModal'])->name('users.import.modal');
    Route::post('users/import', [UserController::class, 'fileImport'])->name('users.import');
    Route::post('users/data/import/', [UserController::class, 'UserImportdata'])->name('users.import.data');


    // impersonating
    Route::get('login-with-company/exit', [UserController::class, 'ExitCompany'])->name('exit.company');

    // Language
    Route::get('/lang/change/{lang}', [LanguageController::class, 'changeLang'])->name('lang.change');
    Route::get('langmanage/{lang?}/{module?}', [LanguageController::class, 'index'])->name('lang.index');
    Route::get('create-language', [LanguageController::class, 'create'])->name('create.language');
    Route::post('langs/{lang?}/{module?}', [LanguageController::class, 'storeData'])->name('lang.store.data');
    Route::post('disable-language', [LanguageController::class, 'disableLang'])->name('disablelanguage');
    Route::any('store-language', [LanguageController::class, 'store'])->name('store.language');
    Route::delete('/lang/{id}', [LanguageController::class, 'destroy'])->name('lang.destroy');
    // End Language

    // Workspace
    Route::resource('workspace', WorkSpaceController::class);
    Route::get('workspace/change/{id}', [WorkSpaceController::class, 'change'])->name('workspace.change');
    Route::post('workspace/check', [WorkSpaceController::class, 'workspaceCheck'])->name('workspace.check');

    // End Workspace
    
     Route::get('project/change/{id}', [WorkSpaceController::class, 'changeProject'])->name('project.changeProject');

    // Plans
    Route::resource('plans', PlanController::class);

    Route::get('plan/list', [PlanController::class, 'PlanList'])->name('plan.list');
    Route::post('plan/store', [PlanController::class, 'PlanStore'])->name('plan.store');
    Route::get('plan/active', [PlanController::class, 'ActivePlans'])->name('active.plans');
    Route::get('upgrade-plan/{id}', [PlanController::class, 'upgradePlan'])->name('upgrade.plan');
    Route::get('plan/buy/{plan_id}/{user_id}', [PlanController::class, 'planDetail'])->name('plan.details');
    Route::get('modules/buy/{user_id}', [PlanController::class, 'moduleBuy'])->name('module.buy');
    Route::post('direct-assign-plan-to-user/{plan_id}/{user_id}', [PlanController::class, 'directAssignPlanToUser'])->name('assign.plan.user');
    Route::any('plan/package-data', [PlanController::class, 'PackageData'])->name('package.data');
    Route::get('plan/plan-buy/{id}', [PlanController::class, 'PlanBuy'])->name('plan.buy');
    Route::get('plan/plan-trial/{id}', [PlanController::class, 'PlanTrial'])->name('plan.trial');
    Route::get('plan/order', [PlanController::class, 'orders'])->name('plan.order.index');
    Route::get('add-one/detail/{id}', [PlanController::class, 'AddOneDetail'])->name('add-one.detail');
    Route::post('add-one/detail/save/{id}', [PlanController::class, 'AddOneDetailSave'])->name('add-one.detail.save');
    Route::post('update-plan-status', [PlanController::class, 'updateStatus'])->name('update.plan.status');
    Route::get('plan/refund/{id}/{user_id}', [PlanController::class, 'refund'])->name('order.refund');

    Route::post('company/settings-save', [CompanySettingsController::class, 'store'])->name('company.settings.save');
    Route::post('super-admin/settings-save', [SuperAdminSettingsController::class, 'store'])->name('super.admin.settings.save');
    Route::post('storage-settings-save', [SuperAdminSettingsController::class, 'storageStore'])->name('storage.setting.store');

    // Coupon
    Route::resource('coupons', CouponController::class);
    Route::get('/apply-coupon', [CouponController::class, 'applyCoupon'])->name('apply.coupon');
    // end Coupon

    // Module Install
    Route::get('modules/list', [ModuleController::class, 'index'])->name('module.index');
    Route::get('modules/add', [ModuleController::class, 'add'])->name('module.add');
    Route::post('install-modules', [ModuleController::class, 'install'])->name('module.install');
    Route::post('modules-enable', [ModuleController::class, 'enable'])->name('module.enable');
    Route::get('cancel/add-on/{name}/{user_id?}', [ModuleController::class, 'CancelAddOn'])->name('cancel.add.on');
    // End Module Install

    // Email Templates
    Route::resource('email-templates', EmailTemplateController::class);
    Route::get('email_template_lang/{id}/{lang?}', [EmailTemplateController::class, 'show'])->name('manage.email.language');
    Route::put('email_template_store/{pid}', [EmailTemplateController::class, 'storeEmailLang'])->name('store.email.language');
    Route::put('email_template_status/{id}', [EmailTemplateController::class, 'updateStatus'])->name('status.email.language');
    Route::resource('email_template', EmailTemplateController::class);
    // End Email Templates

    // helpdesk
    Route::resource('helpdesk', HelpdeskTicketController::class);
    Route::resource('helpdeskticket-category', HelpdeskTicketCategoryController::class);
    Route::get('helpdesk-tickets/search/{status?}', [HelpdeskTicketController::class, 'index'])->name('helpdesk-tickets.search');
    Route::post('helpdesk-ticket/getUser', [HelpdeskTicketController::class, 'getUser'])->name('helpdesk-tickets.getuser');
    Route::post('helpdesk-ticket/{id}/conversion', [HelpdeskConversionController::class, 'store'])->name('helpdesk-ticket.conversion.store');
    Route::post('helpdesk-ticket/{id}/note', [HelpdeskTicketController::class, 'storeNote'])->name('helpdesk-ticket.note.store');
    Route::delete('helpdesk-ticket-attachment/{tid}/destroy/{id}', [HelpdeskTicketController::class, 'attachmentDestroy'])->name('helpdesk-ticket.attachment.destroy');
    // End helpdesk


    Route::group(['middleware' => 'PlanModuleCheck:Account-Taskly'], function () {
        // invoice
        Route::post('invoice/customer', [InvoiceController::class, 'customer'])->name('invoice.customer');
        Route::post('invoice-attechment/{id}', [InvoiceController::class, 'invoiceAttechment'])->name('invoice.file.upload');
        Route::delete('invoice-attechment/destroy/{id}', [InvoiceController::class, 'invoiceAttechmentDestroy'])->name('invoice.attachment.destroy');
        Route::post('invoice/product', [InvoiceController::class, 'product'])->name('invoice.product');
        Route::get('invoice/{id}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoice.duplicate');
        Route::get('invoice/{id}/recurring', [InvoiceController::class, 'recurring'])->name('invoice.recurring');
        Route::get('invoice/items', [InvoiceController::class, 'items'])->name('invoice.items');
        Route::post('invoice/product/destroy', [InvoiceController::class, 'productDestroy'])->name('invoice.product.destroy');
        Route::get('invoice/grid/view', [InvoiceController::class, 'Grid'])->name('invoice.grid.view');
        Route::resource('invoice', InvoiceController::class)->except(['create']);
        Route::get('invoice/create/{cid}', [InvoiceController::class, 'create'])->name('invoice.create');
        Route::get('/invoice/pay/{invoice}', [InvoiceController::class, 'payinvoice'])->name('pay.invoice');
        Route::get('invoice/{id}/sent', [InvoiceController::class, 'sent'])->name('invoice.sent');
        Route::get('invoice/{id}/resent', [InvoiceController::class, 'resent'])->name('invoice.resent');
        Route::get('invoice/{id}/payment/reminder', [InvoiceController::class, 'paymentReminder'])->name('invoice.payment.reminder');
        Route::get('invoice/pdf/{id}', [InvoiceController::class, 'invoice'])->name('invoice.pdf');
        Route::get('invoice/{id}/payment', [InvoiceController::class, 'payment'])->name('invoice.payment');
        Route::post('invoice/{id}/payment/store', [InvoiceController::class, 'createPayment'])->name('invoice.payment.store');
        Route::post('invoice/{id}/payment/{pid}/', [InvoiceController::class, 'paymentDestroy'])->name('invoice.payment.destroy');
        Route::get('invoice/{id}/send', [InvoiceController::class, 'customerInvoiceSend'])->name('invoice.send');
        Route::post('invoice/{id}/send/mail', [InvoiceController::class, 'customerInvoiceSendMail'])->name('invoice.send.mail');
        Route::post('invoice/section/type', [InvoiceController::class, 'InvoiceSectionGet'])->name('invoice.section.type');
        Route::get('delivery-form/pdf/{id}', [InvoiceController::class, 'pdf'])->name('delivery-form.pdf');

        Route::post('/get-invoice-customers', [InvoiceController::class, 'getInvoiceCustomers'])->name('invoice.customers');

        Route::post('invoice-item-detail', [InvoiceController::class, 'getInvoicItemeDetail'])->name('newspaper.invoice.item.details');

        Route::post('invoice/course', [InvoiceController::class, 'course'])->name('invoice.course');
        Route::get('invoice/status/view', [InvoiceController::class, 'InvocieStatus'])->name('invoice.status.view');

        // Proposal
        Route::post('proposal-attechment/{id}', [ProposalController::class, 'proposalAttechment'])->name('proposal.file.upload');
        Route::delete('proposal-attechment/destroy/{id}', [ProposalController::class, 'proposalAttechmentDestroy'])->name('proposal.attachment.destroy');
        Route::post('proposal/customer', [ProposalController::class, 'customer'])->name('proposal.customer');
        Route::post('proposal/product', [ProposalController::class, 'product'])->name('proposal.product');
        Route::get('proposal/{id}/convert', [ProposalController::class, 'convert'])->name('proposal.convert');
        Route::get('proposal/{id}/duplicate', [ProposalController::class, 'duplicate'])->name('proposal.duplicate');
        Route::get('proposal/items', [ProposalController::class, 'items'])->name('proposal.items');
        Route::post('proposal/product/destroy', [ProposalController::class, 'productDestroy'])->name('proposal.product.destroy');
        Route::resource('proposal', ProposalController::class)->except(['create']);
        Route::get('proposal/grid/view', [ProposalController::class, 'Grid'])->name('proposal.grid.view');
        Route::get('proposal/create/{cid}', [ProposalController::class, 'create'])->name('proposal.create');
        Route::get('proposal/{id}/status/change', [ProposalController::class, 'statusChange'])->name('proposal.status.change');
        Route::get('proposal/{id}/resent', [ProposalController::class, 'resent'])->name('proposal.resent');
        Route::post('proposal/section/type', [ProposalController::class, 'ProposalSectionGet'])->name('proposal.section.type');
        Route::get('proposal/{id}/sent', [ProposalController::class, 'sent'])->name('proposal.sent');
        Route::get('proposal/stats/view', [ProposalController::class, 'ProposalQuickStats'])->name('proposal.stats.view');

        // purchase
        Route::resource('purchases', PurchaseController::class)->except(['create']);
        Route::get('purchases-grid', [PurchaseController::class, 'grid'])->name('purchases.grid');
        Route::post('purchases/items', [PurchaseController::class, 'items'])->name('purchases.items');
        Route::get('purchases/{id}/payment', [PurchaseController::class, 'payment'])->name('purchases.payment');
        Route::post('purchases/{id}/payment/store', [PurchaseController::class, 'createPayment'])->name('purchases.payment.store');
        Route::post('purchases/{id}/payment/{pid}/destroy', [PurchaseController::class, 'paymentDestroy'])->name('purchases.payment.destroy');

        Route::post('purchases/product/destroy', [PurchaseController::class, 'productDestroy'])->name('purchases.product.destroy');
        Route::post('purchases/vender', [PurchaseController::class, 'vender'])->name('purchases.vender');
        Route::post('purchases/product', [PurchaseController::class, 'product'])->name('purchases.product');
        Route::get('purchases/create/{cid}', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::get('purchases/{id}/sent', [PurchaseController::class, 'sent'])->name('purchases.sent');
        Route::get('purchases/{id}/resent', [PurchaseController::class, 'resent'])->name('purchases.resent');


        Route::get('purchases/{id}/debit-note', [PurchaseDebitNoteController::class, 'create'])->name('purchases.debit.note')->middleware(
            [
                'auth',
            ]
        );
        Route::post('purchases/{id}/debit-note/store', [PurchaseDebitNoteController::class, 'store'])->name('purchases.debit.note.store')->middleware(
            [
                'auth',
            ]
        );
        Route::get('purchases/{id}/debit-note/edit/{cn_id}', [PurchaseDebitNoteController::class, 'edit'])->name('purchases.edit.debit.note')->middleware(
            [
                'auth',
            ]
        );
        Route::post('purchases/{id}/debit-note/update/{cn_id}', [PurchaseDebitNoteController::class, 'update'])->name('purchases.update.debit.note')->middleware(
            [
                'auth',
            ]
        );
        Route::delete('purchases/{id}/debit-note/delete/{cn_id}', [PurchaseDebitNoteController::class, 'destroy'])->name('purchases.delete.debit.note')->middleware(
            [
                'auth',
            ]
        );

        Route::post('purchase/{id}/file', [PurchaseController::class, 'fileUpload'])->name('purchases.files.upload')->middleware(['auth']);
        Route::delete("purchase/{id}/destroy", [PurchaseController::class, 'fileUploadDestroy'])->name("purchases.attachment.destroy")->middleware(['auth']);
        //warehouse

        Route::resource('warehouses', WarehouseController::class)->middleware(['auth',]);

        //warehouse import
        Route::get('warehouses/import/export', [WarehouseController::class, 'fileImportExport'])->name('warehouses.file.import')->middleware(['auth']);
        Route::post('warehouses/import', [WarehouseController::class, 'fileImport'])->name('warehouses.import')->middleware(['auth']);
        Route::get('warehouses/import/modal', [WarehouseController::class, 'fileImportModal'])->name('warehouses.import.modal')->middleware(['auth']);
        Route::post('warehouses/data/import/', [WarehouseController::class, 'warehouseImportdata'])->name('warehouses.import.data')->middleware(['auth']);

        Route::get('productservice/{id}/detail', [WarehouseController::class, 'warehouseDetail'])->name('productservices.detail');

        //warehouse-transfer
        Route::resource('warehouses-transfer', WarehouseTransferController::class)->middleware(['auth']);
        Route::post('warehouses-transfer/getproduct', [WarehouseTransferController::class, 'getproduct'])->name('warehouses-transfer.getproduct')->middleware(['auth']);
        Route::post('warehouses-transfer/getquantity', [WarehouseTransferController::class, 'getquantity'])->name('warehouses-transfer.getquantity')->middleware(['auth']);

        //Reports
        Route::get('reports-warehouses', [ReportController::class, 'warehouseReport'])->name('reports.warehouse')->middleware(['auth']);
        Route::get('reports-daily-purchases', [ReportController::class, 'purchaseDailyReport'])->name('reports.daily.purchase')->middleware(['auth']);
        Route::get('reports-monthly-purchases', [ReportController::class, 'purchaseMonthlyReport'])->name('reports.monthly.purchase')->middleware(['auth']);
        Route::get('reports-supplier-ledger', [SupplierLedgerReportController::class, 'index'])->name('reports.supplier-ledger')->middleware(['auth']);
        Route::get('reports-supplier-activity', [SupplierActivityReportController::class, 'index'])->name('reports.supplier-activity')->middleware(['auth']);
    });
    // invoices template setting save
    Route::post('/invoices/template/setting', [InvoiceController::class, 'saveTemplateSettings'])->name('invoice.template.setting');
    Route::get('/invoices/preview/{template}/{color}', [InvoiceController::class, 'previewInvoice'])->name('invoice.preview');

    // proposal template setting save
    Route::get('/proposal/preview/{template}/{color}', [ProposalController::class, 'previewInvoice'])->name('proposal.preview');
    Route::post('/proposal/template/setting', [ProposalController::class, 'saveTemplateSettings'])->name('proposal.template.setting');

    // purchase template setting save
    Route::get('purchases/preview/{template}/{color}', [PurchaseController::class, 'previewPurchase'])->name('purchases.preview');
    Route::post('/purchase/template/setting', [PurchaseController::class, 'savePurchaseTemplateSettings'])->name('purchases.template.setting');


    //notification
    Route::resource('notification-template', NotificationController::class);
    Route::get('notification-template/{id}/{lang?}', [NotificationController::class, 'show'])->name('manage.notification.language');
    Route::post('notification-template/{pid}', [NotificationController::class, 'storeNotificationLang'])->name('store.notification.language');

    // Referral Program
    Route::resource('referral-program', ReferralProgramController::class);
    Route::get('referral-program-company', [ReferralProgramController::class, 'companyIndex'])->name('referral-program.company');
    Route::get('request-amount-sent/{id}', [ReferralProgramController::class, 'requestedAmountSent'])->name('request.amount.sent');
    Route::post('request-amount-store/{id}', [ReferralProgramController::class, 'requestedAmountStore'])->name('request.amount.store');
    Route::get('request-amount-cancel/{id}', [ReferralProgramController::class, 'requestCancel'])->name('request.amount.cancel');
    Route::get('request-amount/{id}/{status}', [ReferralProgramController::class, 'requestedAmount'])->name('amount.request');

    // language import & export
    Route::get('export/lang/json',[LanguageController::class,'exportLangJson'])->name('export.lang.json');
    Route::get('import/lang/json/upload',[LanguageController::class,'importLangJsonUpload'])->name('import.lang.json.upload');
    Route::post('import/lang/json',[LanguageController::class,'importLangJson'])->name('import.lang.json');
});
Route::get('module/reset', [ModuleController::class, 'ModuleReset'])->name('module.reset');
Route::post('guest/module/selection', [ModuleController::class, 'GuestModuleSelection'])->name('guest.module.selection');

// cookie
Route::get('cookie/consent', [SuperAdminSettingsController::class, 'CookieConsent'])->name('cookie.consent');

// cache
Route::get('/config-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');
    return redirect()->back()->with('success', 'Cache Clear Successfully');
})->name('config.cache');

//helpdesk
Route::post('helpdesk-ticket/{id}', [HelpdeskTicketController::class, 'reply'])->name('helpdesk-ticket.reply');
Route::get('helpdesk-ticket-show/{id}', [HelpdeskTicketController::class, 'show'])->name('helpdesk.view');

// invoice
Route::get('/invoice/pay/{invoice}', [InvoiceController::class, 'payinvoice'])->name('pay.invoice');
Route::get('invoice/pdf/{id}', [InvoiceController::class, 'invoice'])->name('invoice.pdf');
Route::post('/bank/transfer/invoice', [BanktransferController::class, 'invoicePayWithBank'])->name('invoice.pay.with.bank');

// proposal
Route::get('/proposal/pay/{proposal}', [ProposalController::class, 'payproposal'])->name('pay.proposalpay');
Route::get('proposal/pdf/{id}', [ProposalController::class, 'proposal'])->name('proposal.pdf');


// purchase
Route::get('/vendor/purchases/{id}/', [PurchaseController::class, 'purchaseLink'])->name('purchases.link.copy');
Route::get('/vend0r/bill/{id}/', [PurchaseController::class, 'invoiceLink'])->name('bill.link.copy')->middleware(['auth']);
Route::get('purchases/pdf/{id}', [PurchaseController::class, 'purchase'])->name('purchases.pdf');


Route::get('composer/json',function(){
    $path = base_path('packages/workdo');
    $modules = \Illuminate\Support\Facades\File::directories($path);

    $moduleNames = array_map(function($dir) {
        return basename($dir);
    }, $modules);

    $require = '';
    $repo = '';
    foreach($moduleNames as $module){
        $packageName = preg_replace('/([a-z])([A-Z])/', '$1-$2', $module);
        $require .= '"workdo/'.strtolower($packageName).'": "dev-testing",';
        $repo .= '{
            "type": "path",
            "url": "packages/workdo/'.$module.'"
        },';
    }
    return $require . '<br><br><br>' . $repo;
});



Route::resource('material-categories', MaterialCategoryController::class)->names([
    'index' => 'material-categories.index',
    'create' => 'material-categories.create',
    'store' => 'material-categories.store',
    'show' => 'material-categories.show',
    'edit' => 'material-categories.edit',
    'update' => 'material-categories.update',
    'destroy' => 'material-categories.destroy',
]);
Route::resource('units', UnitController::class)->middleware(['auth',])->names([
    'index' => 'units.index',
    'create' => 'units.create',
    'store' => 'units.store',
    'show' => 'units.show',
    'edit' => 'units.edit',
    'update' => 'units.update',
    'destroy' => 'units.destroy',
]);


Route::resource('supplier-categories', SupplierCategoryController::class)->middleware(['auth']);
Route::resource('supplier', SupplierController::class)->middleware(['auth']);

Route::get('/export-selected', [\App\Http\Controllers\ExportController::class, 'exportSelected'])->name('export.selected');

Route::resource('machineries', MachineryController::class);
Route::resource('machinery-categories', MachineryCategoryController::class);
Route::resource('maintenance', MaintenanceLogController::class);

// Machinery Payment Requests (Ledger-based payment system)
Route::prefix('machinery/payment-requests')->name('machinery-payment.')->group(function () {
    Route::get('/', [MachineryPaymentRequestController::class, 'index'])->name('index');
    Route::get('/create', [MachineryPaymentRequestController::class, 'create'])->name('create');
    Route::get('/api-index', [MachineryPaymentRequestController::class, 'apiIndex'])->name('api-index');
    Route::post('/store-ajax', [MachineryPaymentRequestController::class, 'calculate'])->name('store-ajax');
    Route::get('/debug-ledger-query', [MachineryPaymentRequestController::class, 'debugLedgerQuery'])->name('debug-ledger-query');
    Route::get('/{id}', [MachineryPaymentRequestController::class, 'show'])->name('show');

    // Web AJAX routes (using session auth instead of API auth)
    Route::get('/{id}/data', [MachineryPaymentRequestController::class, 'apiShow'])->name('data');
    Route::post('/{id}/submit', [MachineryPaymentRequestController::class, 'submit'])->name('submit');
    Route::post('/{id}/verify', [MachineryPaymentRequestController::class, 'verify'])->name('verify');
    Route::post('/{id}/approve', [MachineryPaymentRequestController::class, 'approve'])->name('approve');
    Route::post('/{id}/lock', [MachineryPaymentRequestController::class, 'lock'])->name('lock');
    Route::post('/{id}/pay', [MachineryPaymentRequestController::class, 'pay'])->name('pay');
    Route::post('/{id}/reject', [MachineryPaymentRequestController::class, 'reject'])->name('reject');
    Route::get('/{id}/debug', [MachineryPaymentRequestController::class, 'debug'])->name('debug');
    Route::get('/{id}/recalculate', [MachineryPaymentRequestController::class, 'recalculate'])->name('recalculate');
    Route::post('/{id}/force-reject', [MachineryPaymentRequestController::class, 'forceReject'])->name('force-reject');
    Route::post('/{id}/force-unlock', [MachineryPaymentRequestController::class, 'forceUnlock'])->name('force-unlock');
    Route::post('/{id}/upload-payment-proof', [MachineryPaymentRequestController::class, 'uploadPaymentProof'])->name('upload-payment-proof');
    Route::post('/{id}/override-note', [MachineryPaymentRequestController::class, 'addOverrideNote'])->name('override-note');
    Route::post('/{id}/upload-invoice', [MachineryPaymentRequestController::class, 'uploadInvoice'])->name('upload-invoice');
    Route::get('/{id}/payment-modal', [MachineryPaymentRequestController::class, 'paymentModal'])->name('payment-modal');
    Route::post('/{id}/create-erp-payment', [MachineryPaymentRequestController::class, 'createErpPayment'])->name('create-erp-payment');
});


// Approvals
Route::prefix('approvals')->name('approvals.')->group(function () {
    Route::get('/', [ApprovalController::class, 'index'])->name('index');
    Route::post('/{id}/approve', [ApprovalController::class, 'approve'])->middleware('throttle:10,1')->name('approve');
    Route::post('/{id}/reject', [ApprovalController::class, 'reject'])->middleware('throttle:10,1')->name('reject');
    
    // DPR Approval Routes
    Route::post('/dpr/{id}/approve', [ApprovalController::class, 'approveDPR'])->middleware('throttle:10,1')->name('dpr-approve');
    Route::post('/dpr/{id}/reject', [ApprovalController::class, 'rejectDPR'])->middleware('throttle:10,1')->name('dpr-reject');
});

// Ledger
Route::prefix('ledger')->name('ledger.')->group(function () {
    Route::get('/', [LedgerController::class, 'index'])->name('index');
});

// Daily Progress Reports
Route::prefix('daily-progress-reports')->name('daily-progress-reports.')->group(function () {
    Route::get('/', [DailyProgressReportController::class, 'index'])->name('index');
    Route::get('/create', [DailyProgressReportController::class, 'create'])->name('create');
    Route::post('/', [DailyProgressReportController::class, 'store'])->name('store');
    Route::get('/get-previous-reading', [DailyProgressReportController::class, 'getPreviousReading'])->name('get-previous-reading');
    Route::get('/{id}', [DailyProgressReportController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [DailyProgressReportController::class, 'edit'])->name('edit');
    Route::put('/{id}', [DailyProgressReportController::class, 'update'])->name('update');
    Route::delete('/{id}', [DailyProgressReportController::class, 'destroy'])->name('destroy');
});

// Payment Periods
Route::prefix('periods')->name('periods.')->group(function () {
    Route::get('/', [MachineryPaymentPeriodController::class, 'index'])->name('index');
    Route::post('/{id}/lock', [MachineryPaymentPeriodController::class, 'lock'])->name('lock');
    Route::post('/{id}/unlock', [MachineryPaymentPeriodController::class, 'unlock'])->name('unlock');
});

// System Health
Route::prefix('system-health')->name('system-health.')->group(function () {
    Route::get('/', [SystemHealthController::class, 'index'])->name('index');
    Route::get('/summary', [SystemHealthController::class, 'summary'])->name('summary');
    Route::post('/verify-hashes', [SystemHealthController::class, 'verifyHashes'])->name('verify-hashes');
    Route::get('/approval-delay-metrics', [SystemHealthController::class, 'approvalDelayMetrics'])->name('approval-delay-metrics');
    Route::get('/reversal-rate-metrics', [SystemHealthController::class, 'reversalRateMetrics'])->name('reversal-rate-metrics');
});

// Reports
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportsController::class, 'index'])->name('index');
    Route::get('/machinery-ledger-summary', [ReportsController::class, 'machineryLedgerSummary'])->name('machinery-ledger-summary');
    Route::get('/supplier-outstanding', [ReportsController::class, 'supplierOutstanding'])->name('supplier-outstanding');
    Route::get('/monthly-cost', [ReportsController::class, 'monthlyCostReport'])->name('monthly-cost');
});
Route::resource('material', MaterialController::class);

Route::resource('assets_tools_and_equipment', AssetsToolsAndEquipmentController::class);



//unit import
Route::get('units/import/export', [UnitController::class, 'fileImportExport'])->name('units.file.import')->middleware(['auth']);
Route::post('units/import', [UnitController::class, 'fileImport'])->name('units.import')->middleware(['auth']);
Route::get('units/import/modal', [UnitController::class, 'fileImportModal'])->name('units.import.modal')->middleware(['auth']);
Route::post('units/data/import/', [UnitController::class, 'unitImportdata'])->name('units.import.data')->middleware(['auth']);

//material-categories import
Route::get('material-categories/import/export', [MaterialCategoryController::class, 'fileImportExport'])->name('material-categories.file.import')->middleware(['auth']);
Route::post('material-categories/import', [MaterialCategoryController::class, 'fileImport'])->name('material-categories.import')->middleware(['auth']);
Route::get('material-categories/import/modal', [MaterialCategoryController::class, 'fileImportModal'])->name('material-categories.import.modal')->middleware(['auth']);
Route::post('material-categories/data/import/', [MaterialCategoryController::class, 'material-categoriesImportdata'])->name('material-categories.import.data')->middleware(['auth']);

//supplier-categories import
Route::get('supplier-categories/import/export', [SupplierCategoryController::class, 'fileImportExport'])->name('supplier-categories.file.import')->middleware(['auth']);
Route::post('supplier-categories/import', [SupplierCategoryController::class, 'fileImport'])->name('supplier-categories.import')->middleware(['auth']);
Route::get('supplier-categories/import/modal', [SupplierCategoryController::class, 'fileImportModal'])->name('supplier-categories.import.modal')->middleware(['auth']);
Route::post('supplier-categories/data/import/', [SupplierCategoryController::class, 'supplier-categoriesImportdata'])->name('supplier-categories.import.data')->middleware(['auth']);

//machinery-categories import
Route::get('machinery-categories/import/export', [MachineryCategoryController::class, 'fileImportExport'])->name('machinery-categories.file.import')->middleware(['auth']);
Route::post('machinery-categories/import', [MachineryCategoryController::class, 'fileImport'])->name('machinery-categories.import')->middleware(['auth']);
Route::get('machinery-categories/import/modal', [MachineryCategoryController::class, 'fileImportModal'])->name('machinery-categories.import.modal')->middleware(['auth']);
Route::post('machinery-categories/data/import/', [MachineryCategoryController::class, 'machinery-categoriesImportdata'])->name('machinery-categories.import.data')->middleware(['auth']);

//material import
Route::get('materials/import/export', [MaterialController::class, 'fileImportExport'])->name('materials.file.import')->middleware(['auth']);
Route::post('materials/import', [MaterialController::class, 'fileImport'])->name('materials.import')->middleware(['auth']);
Route::get('materials/import/modal', [MaterialController::class, 'fileImportModal'])->name('materials.import.modal')->middleware(['auth']);
Route::post('materials/data/import/', [MaterialController::class, 'materialImportdata'])->name('materials.import.data')->middleware(['auth']);

//material import - new excel based
Route::get('materials/import/template', [\App\Http\Controllers\Inventory\MaterialImportController::class, 'downloadTemplate'])->name('materials.import.template');
Route::post('materials/import/excel', [\App\Http\Controllers\Inventory\MaterialImportController::class, 'import'])->name('materials.import.excel');
Route::get('materials/import/excel/form', [\App\Http\Controllers\Inventory\MaterialImportController::class, 'showImportForm'])->name('materials.import.excel.form');

//material import - dedicated page
Route::get('materials/import/page', [\App\Http\Controllers\Inventory\MaterialImportController::class, 'showImportPage'])->name('materials.import.page')->middleware(['auth']);
Route::post('materials/import/process', [\App\Http\Controllers\Inventory\MaterialImportController::class, 'processImport'])->name('materials.import.process')->middleware(['auth']);

Route::get('machineries/import/export', [MachineryController::class, 'fileImportExport'])->name('machineries.file.import')->middleware(['auth']);
Route::post('machineries/import', [MachineryController::class, 'fileImport'])->name('machineries.import')->middleware(['auth']);
Route::get('machineries/import/modal', [MachineryController::class, 'fileImportModal'])->name('machineries.import.modal')->middleware(['auth']);
Route::post('machineries/data/import/', [MachineryController::class, 'machineriesImportdata'])->name('machineries.import.data')->middleware(['auth']);

//supplier import
Route::get('suppliers/import/export', [SupplierController::class, 'fileImportExport'])->name('suppliers.file.import')->middleware(['auth']);
Route::post('suppliers/import', [SupplierController::class, 'fileImport'])->name('suppliers.import')->middleware(['auth']);
Route::get('suppliers/import/modal', [SupplierController::class, 'fileImportModal'])->name('suppliers.import.modal')->middleware(['auth']);
Route::post('suppliers/data/import/', [SupplierController::class, 'supplierImportdata'])->name('suppliers.import.data')->middleware(['auth']);

//supplier import - new excel based
Route::get('suppliers/import/template', [\App\Http\Controllers\SupplierImportController::class, 'downloadTemplate'])->name('suppliers.import.template')->middleware(['auth']);
Route::post('suppliers/import/excel', [\App\Http\Controllers\SupplierImportController::class, 'import'])->name('suppliers.import.excel')->middleware(['auth']);
Route::get('suppliers/import/excel/form', [\App\Http\Controllers\SupplierImportController::class, 'showImportForm'])->name('suppliers.import.excel.form')->middleware(['auth']);

//supplier import - dedicated page
Route::get('suppliers/import/page', [\App\Http\Controllers\SupplierImportController::class, 'showImportPage'])->name('suppliers.import.page')->middleware(['auth']);
Route::post('suppliers/import/process', [\App\Http\Controllers\SupplierImportController::class, 'processImport'])->name('suppliers.import.process')->middleware(['auth']);


Route::get('assets_tools_and_equipment/import/export', [AssetsToolsAndEquipmentController::class, 'fileImportExport'])->name('assets_tools_and_equipment.file.import')->middleware(['auth']);
Route::post('assets_tools_and_equipment/import', [AssetsToolsAndEquipmentController::class, 'fileImport'])->name('assets_tools_and_equipment.import')->middleware(['auth']);
Route::get('assets_tools_and_equipment/import/modal', [AssetsToolsAndEquipmentController::class, 'fileImportModal'])->name('assets_tools_and_equipment.import.modal')->middleware(['auth']);
Route::post('assets_tools_and_equipment/data/import/', [AssetsToolsAndEquipmentController::class, 'assets_tools_and_equipmentImportdata'])->name('assets_tools_and_equipment.import.data')->middleware(['auth']);

Route::get('/material/{id}/unit', [MaterialController::class, 'getUnit']);
Route::get('/material/{id}/details', [MaterialController::class, 'getMaterialDetails'])->name('material.details');



// Transaction
Route::resource('purchase-invoice', PurchaseInvoiceController::class);

// Purchase Invoice GRN Routes
Route::post('purchase-invoice/store-from-grn', [PurchaseInvoiceController::class, 'storeFromGrn'])->name('purchase-invoice.store-from-grn');
Route::get('purchase-invoice/{purchaseInvoice}/print', [PurchaseInvoiceController::class, 'print'])->name('purchase-invoice.print');
Route::get('purchase-invoice/{purchaseInvoice}/download-pdf', [PurchaseInvoiceController::class, 'downloadPdf'])->name('purchase-invoice.download-pdf');
Route::post('purchase-invoice/debug-log', [PurchaseInvoiceController::class, 'debugLog'])->name('purchase-invoice.debug-log');

// Indent
Route::resource('indent', IndentController::class);
Route::post('indent/debug-log', [IndentController::class, 'debugLog'])->name('indent.debug-log');
Route::get('indent/{indent}/materials', [IndentController::class, 'getIndentMaterials'])->name('indent.materials');
Route::get('indents/available', [IndentController::class, 'getAvailableIndents'])->name('indent.available');

// Materials (for AJAX requests with session auth)
Route::get('materials/ajax', [\App\Http\Controllers\MaterialController::class, 'getMaterialsAjax'])->name('materials.ajax')->middleware(['auth']);
Route::get('material-categories/ajax', [\App\Http\Controllers\MaterialCategoryController::class, 'getCategoriesAjax'])->name('material-categories.ajax')->middleware(['auth']);

// Spent
Route::resource('spent', SpentController::class)->middleware(['auth']);
Route::post('spent/ledger/store', [SpentController::class, 'storeLedger'])->name('spent.ledger.store')->middleware(['auth']);

// Purchase Order
Route::get('purchase-order/get-po-by-supplier', [PurchaseOrderController::class, 'getPOBySupplier'])->name('purchase-order.get-po-by-supplier');
Route::get('purchase-order/payment-request-details', [PurchaseOrderController::class, 'getPaymentRequestDetails'])
    ->name('purchase-order.payment-request-details');
Route::resource('purchase-order', PurchaseOrderController::class);
Route::post('purchase-order/debug-log', [PurchaseOrderController::class, 'debugLog'])->name('purchase-order.debug-log');
Route::get('purchase-order/{purchaseOrder}/materials', [PurchaseOrderController::class, 'getIndentMaterials'])->name('purchase-order.materials');
Route::get('purchase-order/{purchaseOrder}/approve', [PurchaseOrderController::class, 'showApproveForm'])->name('purchase-order.approve');
Route::patch('purchase-order/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus'])->name('purchase-order.update-status');
Route::post('purchase-order/{purchaseOrder}/short-close', [PurchaseOrderController::class, 'shortClose'])->name('purchase-order.short-close');
Route::get('purchase-order/{purchaseOrder}/print', [PurchaseOrderController::class, 'printInvoice'])->name('purchase-order.print-invoice');
Route::get('purchase-order/{purchaseOrder}/print-2', [PurchaseOrderController::class, 'printInvoice2'])->name('purchase-order.print-invoice-2');

// Supplier Advance System
Route::resource('supplier-advance', \App\Http\Controllers\SupplierAdvanceController::class);
Route::get('supplier-advance/create-from-po/{poId}', [\App\Http\Controllers\SupplierAdvanceController::class, 'createFromPO'])->name('supplier-advance.create-from-po');
Route::post('supplier-advance/{id}/approve', [\App\Http\Controllers\SupplierAdvanceController::class, 'approve'])->name('supplier-advance.approve');
Route::post('supplier-advance/{id}/reject', [\App\Http\Controllers\SupplierAdvanceController::class, 'reject'])->name('supplier-advance.reject');
Route::get('supplier-advance/{id}/payment-form', [\App\Http\Controllers\SupplierAdvanceController::class, 'showPaymentForm'])->name('supplier-advance.payment-form');
Route::post('supplier-advance/{id}/record-payment', [\App\Http\Controllers\SupplierAdvanceController::class, 'recordPayment'])->name('supplier-advance.record-payment');
Route::get('supplier-advance/{id}/timeline', [\App\Http\Controllers\SupplierAdvanceController::class, 'timeline'])->name('supplier-advance.timeline');

// Inventory - Opening Stock
Route::resource('opening-stock', OpeningStockController::class);
Route::get('/ajax/get-stock', [OpeningStockController::class, 'getStock'])->name('ajax.getStock');

// Inventory - Opening Stock Import
Route::get('opening-stock-import', [\App\Http\Controllers\Inventory\OpeningStockImportController::class, 'showImportForm'])->name('opening-stock.import.form');
Route::get('opening-stock-import/download-template', [\App\Http\Controllers\Inventory\OpeningStockImportController::class, 'downloadTemplate'])->name('opening-stock.import.download-template');
Route::post('opening-stock-import', [\App\Http\Controllers\Inventory\OpeningStockImportController::class, 'import'])->name('opening-stock.import');

// Inventory - Stock Ledger
Route::get('stock-ledger', [StockLedgerController::class, 'index'])->name('stock-ledger.index');
Route::get('stock-ledger/export', [StockLedgerController::class, 'export'])->name('stock-ledger.export');

// Inventory - Site Stock Report
Route::get('site-stock', [SiteStockController::class, 'index'])->name('site-stock.index');
Route::get('site-stock/export', [SiteStockController::class, 'export'])->name('site-stock.export');

// Goods Receipt Note (GRN)
Route::get('ajax/grn-po-details', [GrnController::class, 'getPoDetails'])->name('grn.get-po-details');
Route::get('grn/{grn}/print', [GrnController::class, 'print'])->name('grn.print');

// GRN Invoice Routes
Route::get('grn/{grn}/invoice-data', [GrnController::class, 'getInvoiceData'])->name('grn.invoice-data');
Route::get('grn/check-invoice', [GrnController::class, 'checkInvoice'])->name('grn.check-invoice');

Route::resource('grn', GrnController::class);
Route::post('grn/debug-log', [GrnController::class, 'debugLog'])->name('grn.debug-log');
Route::post('grn/correct-received-qty', [GrnController::class, 'correctReceivedQty'])->name('grn.correct-received-qty');

Route::resource('manpower', ManPowerController::class);

Route::resource('manpower-type', ManPowerTypeController::class);

Route::resource('daily-consumption', DailyConsumptionController::class)->middleware('system.lock')->middleware('throttle:5,1', ['except' => ['index', 'show']]);

Route::resource('material-transfer', MaterialTransferController::class);

Route::resource('material-issues', MaterialIssueController::class);
Route::post('material-issues/get-available-stock', [MaterialIssueController::class, 'getAvailableStock'])->name('material-issues.get-available-stock');

Route::resource('material-returns', MaterialReturnController::class);
Route::post('material-returns/get-issue-details', [MaterialReturnController::class, 'getIssueDetails'])->name('material-returns.get-issue-details');

Route::get('/ajax/get-stock-by-site', [MaterialTransferController::class, 'getStockBySite'])->name('ajax.getStockBySite');

Route::get('/ajax/get-stock-by-site-for-material-transfer-edit', [MaterialTransferController::class, 'getStockBySiteMaterialTransferEdit'])->name('ajax.getStockBySiteMaterialTransferEdit');

Route::get('/ajax/get-stock-by-site-for-daily-consumption', [DailyConsumptionController::class, 'getStockBySiteForDailyConsumption'])->name('ajax.getStockBySiteForDailyConsumption');

Route::get('/ajax/get-stock-by-site-for-daily-consumption-edit', [DailyConsumptionController::class, 'edit'])->name('ajax.getStockBySiteForDailyConsumptionEdit');



// New routes for payment allocations (must be defined before resource route)
Route::get('payments-module/get-supplier-invoices', [PaymentsModuleController::class, 'getSupplierUnpaidInvoices'])->name('payments-module.get-supplier-invoices');
Route::get('payments-module/get-adjustable-advances', [PaymentsModuleController::class, 'getAdjustableAdvances'])->name('payments-module.get-adjustable-advances');
Route::get('payments-module/get-suppliers-with-invoices', [PaymentsModuleController::class, 'getSuppliersWithInvoices'])->name('payments-module.get-suppliers-with-invoices');
Route::get('payments-module/get-suppliers-with-pending-pos', [PaymentsModuleController::class, 'getSuppliersWithPendingPOs'])->name('payments-module.get-suppliers-with-pending-pos');
Route::get('payments-module/get-pos-with-pending-balance', [PaymentsModuleController::class, 'getPOsWithPendingBalance'])->name('payments-module.get-pos-with-pending-balance');
Route::get('payments-module/get-po-summary', [PaymentsModuleController::class, 'getPOSummary'])->name('payments-module.get-po-summary');
Route::get('payments-module/get-po-ledger', [PaymentsModuleController::class, 'getPOLedger'])->name('payments-module.get-po-ledger');
Route::get('payments-module/get-supplier-ledger', [PaymentsModuleController::class, 'getSupplierLedger'])->name('payments-module.get-supplier-ledger');
Route::post('payments-module/get-remaining-payment', [PaymentsModuleController::class, 'getRemainingPayment'])->name('payments-module.get-remaining-payment');

// Route for creating payment from PO
Route::get('payments-module/create-from-po/{purchaseOrder}', [PaymentsModuleController::class, 'createFromPo'])->name('payments-module.create-from-po');

// Route for creating payment from Invoice
Route::get('payments-module/create-from-invoice/{purchaseInvoice}', [PaymentsModuleController::class, 'createFromInvoice'])->name('payments-module.create-from-invoice');

// Route for creating payment from Payment Request
Route::get('payments-module/create-from-payment-request/{paymentRequest}', [PaymentsModuleController::class, 'createFromPaymentRequest'])->name('payments-module.create-from-payment-request');

// Route for showing advance request details (modal support)
Route::get('payments-module/show-advance-request/{paymentRequest}', [PaymentsModuleController::class, 'showAdvanceRequest'])->name('payments-module.show-advance-request');

// Route for manual PDF generation
Route::get('payments-module/{id}/generate-pdf', [PaymentsModuleController::class, 'generatePdf'])->name('payments-module.generate-pdf');

// Route for payment proof upload
Route::post('machinery/payments/{id}/upload-proof', [PaymentsModuleController::class, 'uploadPaymentProof'])->name('machinery.payments.upload-proof');

Route::resource('payments-module', PaymentsModuleController::class);


Route::get('/ajax/get-suppliers-by-site-id', [SupplierController::class, 'getSuppliersBySiteId'])->name('ajax.getStockBySiteId');

Route::get('/ajax/get-purchase-invoice-by-supplier-id', [PurchaseInvoiceController::class, 'getPurchaseInvoiceBySupplierId'])->name('ajax.getPurchaseInvoiceBySupplierId');

Route::get('/ajax/get-purchase-invoice-by-supplier-id-edit', [PurchaseInvoiceController::class, 'getPurchaseInvoiceBySupplierIdEdit'])->name('ajax.getPurchaseInvoiceBySupplierIdEdit');

Route::get('/ajax/get-purchase-invoice-remaining-amount-by-purchase-invoice-id', [PurchaseInvoiceController::class, 'getPurchaseInvoiceRemainingAmountByPurchaseInvoiceId'])->name('ajax.getPurchaseInvoiceRemainingAmountByPurchaseInvoiceId');



// Nested payment route with separate method
Route::get('purchase-invoice/{purchase_invoice}/payments/create',
    [PaymentsModuleController::class, 'createFromInvoice']
)->name('purchase-invoice.payments.create');




Route::post('purchase-invoice/{purchase_invoice}/payments', [PaymentsModuleController::class, 'store'])
    ->name('purchase-invoice.payments.store');





Route::resource('general_transfer', GeneralTransferController::class);

// routes/web.php


Route::get('/daily-progress-reports-new/createdpr/{activity_completed_id?}', [DailyProgressReportController::class, 'createdpr'])->name('daily-progress-reports-new.createdpr');
Route::post('/daily-progress-reports/check-duplicate', [DailyProgressReportController::class, 'checkDuplicate'])->name('daily-progress-reports.check-duplicate');

//Route::get('/test-notification', function () {
//    event(new \App\Events\UserNotificationEvent(auth()->id(), [
//        'type' => 'chat',
//        'title' => 'New Message',
//        'body' => 'Hi! This is a test notification',
//        'message' => 'Hello from backend!',
//        'message_id' => time(),
//    ]));
//
//    return 'Notification sent!';
//});




Route::resource('activities', ActivityController::class);

// Route to record completion
Route::post('activities/{id}/complete', [ActivityController::class, 'recordCompletion'])->name('activities.complete');


Route::get('/attachments/download/{fileName}', [AttachmentController::class, 'download']) ->name('app.attachments.download');
Route::resource('stock-reports', StockReportController::class);

Route::get('/clear-all', function () {
     Artisan::call('view:clear');
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('optimize:clear');
    Artisan::call('route:clear');
   
    

    return back()->with('success', 'Application caches cleared successfully!');
})->middleware('auth');



// web.php
Route::prefix('notifications')->middleware('auth')->group(function () {
    Route::get('/', [NotificationPageController::class, 'index'])->name('notifications.index');
    Route::get('/unread', [NotificationPageController::class, 'unread'])->name('notifications.unread');
    Route::get('/count', [NotificationPageController::class, 'getCount'])->name('notifications.count');

    Route::post('/{notificationUser}/read', [NotificationPageController::class, 'markAsRead'])->name('notifications.markAsRead');
    Route::post('/read-all', [NotificationPageController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
    Route::post('/delete', [NotificationPageController::class, 'delete'])->name('notifications.delete');
    Route::post('/delete-all', [NotificationPageController::class, 'deleteAll'])->name('notifications.deleteAll');
    Route::post('/mark-all-as-read', [NotificationPageController::class, 'markAllAsRead'])->name('notifications.markAllAsReadAll');

});

// API endpoints for frontend (session-based auth)
Route::prefix('api/notifications')->middleware('auth')->group(function () {
    Route::get('/unread', [App\Http\Controllers\Api\NotificationController::class, 'getUnread']);
    Route::get('/all', [App\Http\Controllers\Api\NotificationController::class, 'getAll']);
    Route::get('/count', [App\Http\Controllers\Api\NotificationController::class, 'getCount']);
    Route::post('/mark-as-read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/mark-all-as-read', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    Route::post('/delete', [App\Http\Controllers\Api\NotificationController::class, 'delete']);
    Route::post('/delete-all', [App\Http\Controllers\Api\NotificationController::class, 'deleteAll']);
});

// Chatify unseen-count endpoint for web (session-based auth)
Route::middleware('auth')->get('/api/chat/unseen-count', [App\Http\Controllers\vendor\Chatify\MessagesController::class, 'getUnseenCount']);

// Chat routes moved to routes/api.php to avoid CSRF protection

// File Manager Routes
use App\Http\Controllers\FileManagerController;

Route::prefix('file-manager')->middleware('auth')->name('file-manager.')->group(function () {
    Route::get('/', [FileManagerController::class, 'index'])->name('index');
    Route::post('/upload', [FileManagerController::class, 'upload'])->name('upload');
    Route::post('/create-folder', [FileManagerController::class, 'createFolder'])->name('create-folder');
    Route::get('/{fileId}/download', [FileManagerController::class, 'download'])->name('download');
    Route::post('/{fileId}/rename', [FileManagerController::class, 'rename'])->name('rename');
    Route::delete('/{fileId}', [FileManagerController::class, 'delete'])->name('delete');
    Route::post('/{fileId}/make-public', [FileManagerController::class, 'makePublic'])->name('make-public');
    Route::post('/{fileId}/archive', [FileManagerController::class, 'archive'])->name('archive');
    Route::get('/switch/{projectId}', [FileManagerController::class, 'switchProject'])->name('switch');
});

// Project Documents Routes
use App\Http\Controllers\ProjectDocumentController;

Route::prefix('project-documents')->middleware('auth')->name('project-documents.')->group(function () {
    // Web Interface Routes
    Route::get('/', [ProjectDocumentController::class, 'index'])->name('index');
    Route::post('/upload', [ProjectDocumentController::class, 'upload'])->name('upload');
    Route::get('/{projectId}/download/{documentId}', [ProjectDocumentController::class, 'download'])->name('download');
    Route::delete('/{projectId}/delete/{documentId}', [ProjectDocumentController::class, 'delete'])->name('delete');
    Route::put('/{projectId}/rename/{documentId}', [ProjectDocumentController::class, 'rename'])->name('rename');
    Route::post('/{projectId}/folder', [ProjectDocumentController::class, 'createFolder'])->name('folder.create');
    Route::post('/{projectId}/switch', [ProjectDocumentController::class, 'switchProject'])->name('switch');
    Route::get('/{projectId}/folder', [ProjectDocumentController::class, 'getFolder'])->name('folder.get');
    Route::get('/{projectId}/stats', [ProjectDocumentController::class, 'getStats'])->name('stats');
});

// Route::resource('payment-request', PaymentRequestController::class); // Commented out - legacy PO-based routes causing parameter conflicts

Route::get('payment-request', [PaymentRequestController::class, 'index'])->name('payment-request.index');

Route::get('payment-request/create', [PaymentRequestController::class, 'create'])
    ->name('payment-request.create');

Route::get('payment-request/create-modal/{invoiceId}', [PaymentRequestController::class, 'createModal'])
    ->name('payment-request.create-modal');

Route::post('payment-request/store', [PaymentRequestController::class, 'store'])
    ->name('payment-request.store');

Route::get('payment-request/{id}/approval', [PaymentRequestController::class, 'approval'])->name('payment-request.approval');

Route::post('payment-request/{id}/approval', [PaymentRequestController::class, 'approvalUpdate'])->name('payment-request.approval.update');

Route::post('payment-request/{id}/approve-single', [PaymentRequestController::class, 'approveSingle'])
    ->name('payment-request.approve.single');

Route::post('/payment-request/update', [PaymentRequestController::class, 'updatePaymentRequest'])
    ->name('payment-request.update');

Route::get('payment-request/{id}', [PaymentRequestController::class, 'show'])->name('payment-request.show');

Route::put('payment-request/{id}', [PaymentRequestController::class, 'update'])->name('payment-request.update-api');

// PO Advance Request Routes
Route::get('purchase-order/{po}/advance-request-modal', [PurchaseOrderController::class, 'advanceRequestModal'])
    ->name('purchase-order.advance-request-modal');

Route::post('purchase-order/{po}/advance-request', [PurchaseOrderController::class, 'storeAdvanceRequest'])
    ->name('purchase-order.advance-request');

// API Documentation Route (Scribe)
Route::get('/api-docs', function () {
    return redirect('/docs/index.html');
})->name('api.docs');

// API Documentation Health Check Endpoint (for Apidog/Monitoring)
Route::get('/docs-status', function () {
    $openapiPath = public_path('docs/openapi.yaml');
    $htmlPath = public_path('docs/index.html');

    $currentHash = file_exists($openapiPath) ? md5_file($openapiPath) : null;
    $lastHash = cache('last_openapi_hash');
    $changeHistory = cache('api_change_history', []);

    return response()->json([
        'status' => 'ok',
        'openapi_exists' => file_exists($openapiPath),
        'openapi_size' => file_exists($openapiPath) ? filesize($openapiPath) : 0,
        'openapi_last_updated' => file_exists($openapiPath) ? filemtime($openapiPath) : null,
        'openapi_last_updated_human' => file_exists($openapiPath) ? date('Y-m-d H:i:s', filemtime($openapiPath)) : null,
        'openapi_hash' => $currentHash ? substr($currentHash, 0, 8) . '...' : null,
        'schema_changed' => $currentHash !== $lastHash,
        'html_exists' => file_exists($htmlPath),
        'html_last_updated' => file_exists($htmlPath) ? filemtime($htmlPath) : null,
        'cache_last_modified' => cache('scribe_last_modified'),
        'cache_last_openapi_hash' => $lastHash ? substr($lastHash, 0, 8) . '...' : null,
        'change_history_count' => count($changeHistory),
        'change_history' => array_map(function ($change) {
            return [
                'hash' => substr($change['hash'], 0, 8) . '...',
                'timestamp' => $change['timestamp'],
                'file_size' => $change['file_size'] . ' bytes',
            ];
        }, $changeHistory),
        'environment' => app()->environment(),
        'auto_generation_enabled' => env('AUTO_GENERATE_API_DOCS', false),
    ]);
})->name('api.docs.status');

// Monthly Control Routes (Production-Ready System) - DISABLED
// Route::prefix('monthly-control')->name('monthly-control.')->middleware(['auth'])->group(function () {
//     Route::get('/', [\App\Http\Controllers\MonthlyControlController::class, 'index'])->name('index');
//     Route::get('/lock-confirm', [\App\Http\Controllers\MonthlyControlController::class, 'lockConfirm'])->name('lock-confirm');
//     Route::post('/lock', [\App\Http\Controllers\MonthlyControlController::class, 'lock'])->name('lock');
//     Route::post('/generate-billing', [\App\Http\Controllers\MonthlyControlController::class, 'generateBilling'])->name('generate-billing');
//     Route::post('/group-bills', [\App\Http\Controllers\MonthlyControlController::class, 'groupBills'])->name('group-bills');
//     Route::get('/check-status', [\App\Http\Controllers\MonthlyControlController::class, 'checkStatus'])->name('check-status');
// });

// Machinery Monthly Report Routes
Route::prefix('machinery/monthly-report')->name('machinery.monthly-report.')->middleware(['auth'])->group(function () {
    Route::get('/', [\App\Http\Controllers\MachineryMonthlyReportController::class, 'index'])->name('index');
});

// Machinery Billing Routes
Route::prefix('machinery/billing')->name('machinery.billing.')->middleware(['auth'])->group(function () {
    Route::get('/', [\App\Http\Controllers\MachineryBillingController::class, 'index'])->name('index');
    Route::get('/create', [\App\Http\Controllers\MachineryBillingController::class, 'create'])->name('create');
    Route::post('/review', [\App\Http\Controllers\MachineryBillingController::class, 'review'])->name('review');
    Route::post('/store', [\App\Http\Controllers\MachineryBillingController::class, 'store'])->name('store');
    Route::get('/items', [\App\Http\Controllers\MachineryBillingController::class, 'getBillingItems'])->name('items');
    Route::get('/{id}', [\App\Http\Controllers\MachineryBillingController::class, 'show'])->name('show');
    Route::delete('/{id}', [\App\Http\Controllers\MachineryBillingController::class, 'destroy'])->name('destroy');
});

// Direct Machinery DPR Routes (Dual Flow - Direct Path)
Route::prefix('machinery/{machinery}/dpr')->name('machinery.dpr.')->middleware(['auth'])->group(function () {
    Route::get('/', [\App\Http\Controllers\MachineryDprController::class, 'index'])->name('index');
    Route::get('/create', [\App\Http\Controllers\MachineryDprController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\MachineryDprController::class, 'store'])->name('store');
    Route::get('/{dpr}', [\App\Http\Controllers\MachineryDprController::class, 'show'])->name('show');
    Route::get('/{dpr}/edit', [\App\Http\Controllers\MachineryDprController::class, 'edit'])->name('edit');
    Route::put('/{dpr}', [\App\Http\Controllers\MachineryDprController::class, 'update'])->name('update');
});

// Error Page Preview Routes (for testing and demonstration)
Route::prefix('error-preview')->name('error-preview.')->group(function () {
    Route::get('/403', function () {
        abort(403, 'Access Denied - You don\'t have permission to view this page.');
    })->name('403');
    
    Route::get('/404', function () {
        abort(404, 'Page Not Found - The page you\'re looking for doesn\'t exist.');
    })->name('404');
    
    Route::get('/405', function () {
        abort(405, 'Method Not Allowed - This request method is not supported.');
    })->name('405');
    
    Route::get('/419', function () {
        abort(419, 'Page Expired - Your session has timed out.');
    })->name('419');
    
    Route::get('/429', function () {
        abort(429, 'Too Many Requests - Please wait before trying again.');
    })->name('429');
    
    Route::get('/500', function () {
        abort(500, 'Server Error - Something went wrong on our end.');
    })->name('500');
    
    Route::get('/503', function () {
        abort(503, 'Service Unavailable - We are upgrading the system, please try again later.');
    })->name('503');
    
    // Index page to show all error preview links
    Route::get('/', function () {
        return response()->view('errors.preview-index');
    })->name('index');
});
