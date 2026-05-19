<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;
use App\Models\User;


class PermissionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Artisan::call('cache:clear');

        // Super Admin
        $admin = User::where('type','super admin')->first();
        if(empty($admin))
        {
            $admin = new User();
            $admin->name = 'Super Admin';
            $admin->email = 'superadmin@example.com';
            $admin->password = Hash::make('1234');
            $admin->email_verified_at = date('Y-m-d H:i:s');
            $admin->type = 'super admin';
            $admin->active_status = 1;
            $admin->active_workspace = 0;
            $admin->avatar = 'uploads/users-avatar/avatar.png';
            $admin->dark_mode = 0;
            $admin->lang = 'en';
            $admin->workspace_id = 0;
            $admin->created_by = 0;
            $admin->save();

            $role = Role::where('name','super admin')->where('guard_name','web')->exists();
            if(!$role)
            {
                $superAdminRole        = Role::create(
                    [
                        'name' => 'super admin',
                        'created_by' => 0,
                    ]
                );
            }
            $role_r = Role::where('name','super admin')->first();
            $admin->addRole($role_r);
        }

        $adnin_permission = [
            // User Management
            'user manage',
            'user create',
            'user edit',
            'user delete',
            'user profile manage',
            'user reset password',
            'user login manage',
            'user import',
            'user logs history',

            // Settings
            'setting manage',
            'setting storage manage',

            // Coupons
            'coupon manage',
            'coupon create',
            'coupon edit',
            'coupon delete',

            // Plans
            'plan manage',
            'plan create',
            'plan edit',
            'plan delete',
            'plan orders',

            // Modules
            'module manage',
            'module add',
            'module remove',
            'module edit',

            // Email & Language
            'email template manage',
            'language manage',
            'language create',
            'language delete',

            // Helpdesk
            'helpdesk manage',
            'helpdesk ticket manage',
            'helpdesk ticket create',
            'helpdesk ticket edit',
            'helpdesk ticket show',
            'helpdesk ticket reply',
            'helpdesk ticket delete',
            'helpdeskticket setup manage',
            'helpdesk ticketcategory manage',
            'helpdesk ticketcategory create',
            'helpdesk ticketcategory edit',
            'helpdesk ticketcategory delete',

            // API Keys
            'api key setting manage',
            'api key setting create',
            'api key setting edit',
            'api key setting delete',

            // Notifications
            'notification template manage',

            // Referral Program
            'referral program manage',

            // GRN permissions
            'grn manage',
            'grn create',
            'grn edit',
            'grn delete',
            'grn show',
            'grn export',
            'grn print',

            // Inventory permissions
            'opening-stock manage',
            'opening-stock create',
            'opening-stock edit',
            'opening-stock delete',
            'opening-stock show',
            'stock-ledger manage',
            'stock-ledger create',
            'stock-ledger edit',
            'stock-ledger delete',
            'stock-ledger show',
            'stock-ledger export',
            'site-stock manage',
            'site-stock create',
            'site-stock edit',
            'site-stock delete',
            'site-stock show',
            'site-stock export',

            // Supplier Advance permissions
            'supplier-advance manage',
            'supplier-advance create',
            'supplier-advance edit',
            'supplier-advance delete',
            'supplier-advance show',
            'supplier-advance export',
            'supplier-advance approve',
            'supplier-advance reject',
            'supplier-advance payment',

            // Spent permissions
            'spent manage',
            'spent create',
            'spent edit',
            'spent delete',
            'spent show',
            'spent export',
            'spent ledger create',

            // Maintenance Logs permissions
            'maintenance-logs manage',
            'maintenance-logs create',
            'maintenance-logs edit',
            'maintenance-logs delete',
            'maintenance-logs show',

            // Machinery Payment Requests permissions
            'machinery-payment-requests manage',
            'machinery-payment-requests create',
            'machinery-payment-requests edit',
            'machinery-payment-requests delete',
            'machinery-payment-requests show',
            'machinery-payment-requests approve',
            'machinery-payment-requests reject',

            // Machinery Ledger permissions
            'machinery-ledger manage',
            'machinery-ledger show',

            // System Health permissions
            'system-health manage',
            'system-health view',

            // Reports permissions
            'reports manage',
            'reports view',

            // Attendance permissions
            'attendance monthly-report',

            // Additional missing permissions
            'machinery-payment manage',
            'monthly-control manage',
            'machinery-monthly-report manage',
            'report purchase',
            'report warehouse',

            // ============ HRM MODULE PERMISSIONS ============
            'hrm manage',
            'hrm dashboard manage',
            'sidebar hrm report manage',
            'document manage',
            'document create',
            'document edit',
            'document delete',
            'attendance manage',
            'attendance create',
            'attendance edit',
            'attendance delete',
            'attendance import',
            'branch manage',
            'branch create',
            'branch edit',
            'branch delete',
            'department manage',
            'department create',
            'department edit',
            'department delete',
            'designation manage',
            'designation create',
            'designation edit',
            'designation delete',
            'employee manage',
            'employee create',
            'employee edit',
            'employee delete',
            'employee show',
            'employee profile manage',
            'employee profile show',
            'employee import',
            'employee transfer',
            'documenttype manage',
            'documenttype create',
            'documenttype edit',
            'documenttype delete',
            'companypolicy manage',
            'companypolicy create',
            'companypolicy edit',
            'companypolicy delete',
            'leave manage',
            'leave create',
            'leave edit',
            'leave delete',
            'leave approver manage',
            'leavetype manage',
            'leavetype create',
            'leavetype edit',
            'leavetype delete',
            'award manage',
            'award create',
            'award edit',
            'award delete',
            'awardtype manage',
            'awardtype create',
            'awardtype edit',
            'awardtype delete',
            'transfer manage',
            'transfer create',
            'transfer edit',
            'transfer delete',
            'resignation manage',
            'resignation create',
            'resignation edit',
            'resignation delete',
            'travel manage',
            'travel create',
            'travel edit',
            'travel delete',
            'promotion manage',
            'promotion create',
            'promotion edit',
            'promotion delete',
            'complaint manage',
            'complaint create',
            'complaint edit',
            'complaint delete',
            'warning manage',
            'warning create',
            'warning edit',
            'warning delete',
            'termination manage',
            'termination create',
            'termination edit',
            'termination delete',
            'termination description',
            'terminationtype manage',
            'terminationtype create',
            'terminationtype edit',
            'terminationtype delete',
            'announcement manage',
            'announcement create',
            'announcement edit',
            'announcement delete',
            'holiday manage',
            'holiday create',
            'holiday edit',
            'holiday delete',
            'holiday import',
            'attendance report manage',
            'leave report manage',
            'payroll report manage',
            'paysliptype manage',
            'paysliptype create',
            'paysliptype edit',
            'paysliptype delete',
            'allowanceoption manage',
            'allowanceoption create',
            'allowanceoption edit',
            'allowanceoption delete',
            'loanoption manage',
            'loanoption create',
            'loanoption edit',
            'loanoption delete',
            'deductionoption manage',
            'deductionoption create',
            'deductionoption edit',
            'deductionoption delete',
            'setsalary manage',
            'setsalary pay slip manage',
            'setsalary create',
            'setsalary edit',
            'setsalary show',
            'allowance manage',
            'allowance create',
            'allowance edit',
            'allowance delete',
            'commission manage',
            'commission create',
            'commission edit',
            'commission delete',
            'loan manage',
            'loan create',
            'loan edit',
            'loan delete',
            'saturation deduction manage',
            'saturation deduction create',
            'saturation deduction edit',
            'saturation deduction delete',
            'other payment manage',
            'other payment create',
            'other payment edit',
            'other payment delete',
            'overtime manage',
            'overtime create',
            'overtime edit',
            'overtime delete',
            'company contribution manage',
            'company contribution create',
            'company contribution edit',
            'company contribution delete',
            'branch name edit',
            'department name edit',
            'designation name edit',
            'event manage',
            'event create',
            'event edit',
            'event delete',
            'sidebar payroll manage',
            'sidebar hr admin manage',
            'letter joining manage',
            'letter certificate manage',
            'letter noc manage',
            'ip restrict manage',
            'ip restrict create',
            'ip restrict edit',
            'ip restrict delete',
            'bulk attendance manage',
            'tax bracket manage',
            'tax bracket create',
            'tax bracket edit',
            'tax bracket delete',
            'tax rebate manage',
            'tax rebate create',
            'tax rebate edit',
            'tax rebate delete',
            'tax threshold manage',
            'tax threshold create',
            'tax threshold edit',
            'tax threshold delete',
            'allowance tax manage',
            'allowance tax create',
            'allowance tax edit',
            'allowance tax delete',

            // ============ ACCOUNT MODULE PERMISSIONS ============
            'account dashboard manage',
            'bank account manage',
            'bank account create',
            'bank account edit',
            'bank account delete',
            'bank transfer manage',
            'bank transfer create',
            'bank transfer edit',
            'bank transfer delete',
            'account manage',
            'customer manage',
            'customer create',
            'customer edit',
            'customer delete',
            'customer show',
            'customer import',
            'vendor manage',
            'vendor create',
            'vendor edit',
            'vendor delete',
            'vendor show',
            'vendor import',
            'creditnote manage',
            'creditnote create',
            'creditnote edit',
            'creditnote delete',
            'revenue manage',
            'revenue create',
            'revenue edit',
            'revenue delete',
            'report manage',
            'bill manage',
            'bill create',
            'bill edit',
            'bill delete',
            'bill payment manage',
            'bill payment create',
            'bill payment edit',
            'bill payment delete',
            'bill show',
            'bill duplicate',
            'bill product delete',
            'bill send',
            'debitnote manage',
            'debitnote create',
            'debitnote edit',
            'debitnote delete',
            'expense payment manage',
            'expense payment create',
            'expense payment edit',
            'expense payment delete',
            'report transaction manage',
            'report statement manage',
            'report income manage',
            'report expense manage',
            'report income vs expense manage',
            'report tax manage',
            'report loss & profit  manage',
            'report invoice manage',
            'report bill manage',
            'report stock manage',
            'sidebar income manage',
            'sidebar expanse manage',
            'sidebar banking manage',
            'chartofaccount manage',
            'chartofaccount create',
            'chartofaccount edit',
            'chartofaccount show',
            'chartofaccount delete',

            // ============ LEAD MODULE PERMISSIONS ============
            'crm manage',
            'crm dashboard manage',
            'crm setup manage',
            'crm report manage',
            'lead manage',
            'lead create',
            'lead edit',
            'lead delete',
            'lead show',
            'lead move',
            'lead import',
            'lead call create',
            'lead call edit',
            'lead call delete',
            'lead email create',
            'lead to deal convert',
            'lead report',
            'deal report',
            'deal manage',
            'deal create',
            'deal edit',
            'deal delete',
            'deal show',
            'deal move',
            'deal import',
            'deal task create',
            'deal task edit',
            'deal task delete',
            'deal task show',
            'deal call create',
            'deal call edit',
            'deal call delete',
            'deal email create',
            'pipeline manage',
            'pipeline create',
            'pipeline edit',
            'pipeline delete',
            'dealstages manage',
            'dealstages create',
            'dealstages edit',
            'dealstages delete',
            'leadstages manage',
            'leadstages create',
            'leadstages edit',
            'leadstages delete',
            'labels manage',
            'labels create',
            'labels edit',
            'labels delete',
            'source manage',
            'source create',
            'source edit',
            'source delete',
            'lead task create',
            'lead task edit',
            'lead task delete',

            // ============ PRODUCT SERVICE MODULE PERMISSIONS ============
            'product&service manage',
            'product&service create',
            'product&service edit',
            'product&service delete',
            'product&service import',
            'unit manage',
            'unit cerate',
            'unit edit',
            'unit delete',
            'tax manage',
            'tax create',
            'tax edit',
            'tax delete',
            'category manage',
            'category create',
            'category edit',
            'category delete',
            'product service manage',

            // ============ POS MODULE PERMISSIONS ============
            'pos manage',
            'pos show',
            'pos dashboard manage',
            'pos add manage',
            'pos cart manage',
            'report pos',
            'report pos vs expense',

            // ============ LANDING PAGE MODULE PERMISSIONS ============
            'landingpage manage',
            'landingpage create',
            'landingpage edit',
            'landingpage store',
            'landingpage update',
            'landingpage delete',
            'marketplace manage',
            'marketplace create',
            'marketplace edit',
            'marketplace store',
            'marketplace update',
            'marketplace delete',

            // ============ PAYMENT MODULE PERMISSIONS ============
            'paypal manage',
            'stripe manage',
        ];

$compnay_permission = [
                // User Management
                'user manage',
                'user create',
                'user edit',
                'user delete',
                'user profile manage',
                'user chat manage',
                'user reset password',
                'user login manage',
                'user import',
                'user logs history',
                'workspace manage',
                'workspace create',
                'workspace edit',
                'workspace delete',
                'roles manage',
                'roles create',
                'roles edit',
                'roles delete',
                'plan manage',
                'plan purchase',
                'plan subscribe',
                'plan orders',

                // Proposals & Invoices
                'proposal manage',
                'proposal create',
                'proposal edit',
                'proposal delete',
                'proposal show',
                'proposal send',
                'proposal duplicate',
                'proposal product delete',
                'proposal convert invoice',
                'invoice manage',
                'invoice create',
                'invoice edit',
                'invoice delete',
                'invoice show',
                'invoice send',
                'invoice duplicate',
                'invoice product delete',
                'invoice payment create',
                'invoice payment delete',

                // Settings
                'setting manage',
                'helpdesk ticket manage',
                'helpdesk ticket create',
                'helpdesk ticket edit',
                'helpdesk ticket show',
                'helpdesk ticket reply',
                'helpdesk ticket delete',

                // Purchase
                'purchase manage',
                'purchase create',
                'purchase edit',
                'purchase delete',
                'purchase show',
                'purchase send',
                'purchase payment create',
                'purchase payment delete',
                'purchase product delete',
                'purchase debitnote create',
                'purchase debitnote edit',
                'purchase debitnote delete',
                'report warehouse',
                'report purchase',
                'warehouse manage',
                'warehouse create',
                'warehouse edit',
                'warehouse delete',
                'warehouse show',
                'warehouse import',
                'referral program manage',

                // Material
                'material manage',
                'material create',
                'material edit',
                'material delete',
                'material show',
                'material export',

                'material-category manage',
                'material-category create',
                'material-category edit',
                'material-category delete',
                'material-category show',
                'material-category export',

                'material-unit manage',
                'material-unit create',
                'material-unit edit',
                'material-unit delete',
                'material-unit show',
                'material-unit export',

                // Supplier
                'supplier manage',
                'supplier create',
                'supplier edit',
                'supplier delete',
                'supplier show',
                'supplier export',

                'supplier-category manage',
                'supplier-category create',
                'supplier-category edit',
                'supplier-category delete',
                'supplier-category show',
                'supplier-category export',

                // Man Power
                'man-power-type manage',
                'man-power-type create',
                'man-power-type edit',
                'man-power-type delete',
                'man-power-type show',
                'man-power-type export',

                'man-power manage',
                'man-power create',
                'man-power edit',
                'man-power delete',
                'man-power show',
                'man-power export',

                // Machinery
                'machinery manage',
                'machinery create',
                'machinery edit',
                'machinery delete',
                'machinery show',
                'machinery export',
                'machinery transfer',

                'machinery-category manage',
                'machinery-category create',
                'machinery-category edit',
                'machinery-category delete',
                'machinery-category show',
                'machinery-category export',

                'machinery-dpr manage',
                'machinery-dpr create',
                'machinery-dpr edit',
                'machinery-dpr delete',
                'machinery-dpr show',
                'machinery-dpr export',

                'machinery-payment manage',
                'machinery-monthly-report manage',

                // Tools & Equipment
                'tools-and-equipment manage',
                'tools-and-equipment create',
                'tools-and-equipment edit',
                'tools-and-equipment delete',
                'tools-and-equipment show',
                'tools-and-equipment export',
                'tools-and-equipment transfer',

                // Activity
                'activity manage',
                'activity create',
                'activity edit',
                'activity delete',
                'activity show',
                'activity export',

                // Indent
                'indent manage',
                'indent create',
                'indent edit',
                'indent delete',
                'indent show',
                'indent export',

                // Purchase Order
                'purchase-order manage',
                'purchase-order create',
                'purchase-order edit',
                'purchase-order delete',
                'purchase-order show',
                'purchase-order export',
                'purchase-order print',
                'purchase-order advance-request',

                // Purchase Invoice
                'purchase-invoice manage',
                'purchase-invoice create',
                'purchase-invoice edit',
                'purchase-invoice delete',
                'purchase-invoice show',
                'purchase-invoice export',
                'purchase-invoice print',

                // Supplier Advance
                'supplier-advance manage',
                'supplier-advance create',
                'supplier-advance edit',
                'supplier-advance delete',
                'supplier-advance show',
                'supplier-advance export',
                'supplier-advance approve',
                'supplier-advance reject',
                'supplier-advance payment',

                // Consumption Log
                'consumption-log manage',
                'consumption-log create',
                'consumption-log edit',
                'consumption-log delete',
                'consumption-log show',
                'consumption-log export',

                // Material Transfer
                'material-transfer manage',
                'material-transfer create',
                'material-transfer edit',
                'material-transfer delete',
                'material-transfer show',
                'material-transfer export',

                // Payment
                'manage-payment manage',
                'manage-payment create',
                'manage-payment edit',
                'manage-payment delete',
                'manage-payment show',
                'manage-payment export',

                // Spent
                'spent manage',
                'spent create',
                'spent edit',
                'spent delete',
                'spent show',
                'spent export',
                'spent ledger create',

                // Stock Report
                'stock-report manage',
                'stock-report add',
                'stock-report consume',
                'stock-report transfer',
                'stock-report export',

                // Supplier Ledger
                'supplier-ledger report',

                // Maintenance Logs
                'maintenance-logs manage',
                'maintenance-logs create',
                'maintenance-logs edit',
                'maintenance-logs delete',
                'maintenance-logs show',

                // Machinery Payment Requests
                'machinery-payment-requests manage',
                'machinery-payment-requests create',
                'machinery-payment-requests edit',
                'machinery-payment-requests delete',
                'machinery-payment-requests show',
                'machinery-payment-requests approve',
                'machinery-payment-requests reject',

                // Machinery Ledger
                'machinery-ledger manage',
                'machinery-ledger show',

                // System Health
                'system-health manage',
                'system-health view',

                // Reports
                'reports manage',
                'reports view',
                'monthly-control manage',

                // GRN
                'grn manage',
                'grn create',
                'grn edit',
                'grn delete',
                'grn show',
                'grn export',
                'grn print',

                // Inventory
                'opening-stock manage',
                'opening-stock create',
                'opening-stock edit',
                'opening-stock delete',
                'opening-stock show',
                'stock-ledger manage',
                'stock-ledger create',
                'stock-ledger edit',
                'stock-ledger delete',
                'stock-ledger show',
                'stock-ledger export',
                'site-stock manage',
                'site-stock create',
                'site-stock edit',
                'site-stock delete',
                'site-stock show',
                'site-stock export',

                // Material Issue
                'material-issue manage',
                'material-issue create',
                'material-issue edit',
                'material-issue delete',
                'material-issue show',
                'material-issue export',
                'material-issue print',

                // Material Return
                'material-return manage',
                'material-return create',
                'material-return edit',
                'material-return delete',
                'material-return show',
                'material-return export',
                'material-return print',

                // General Transfer
                'general-transfer manage',
                'general-transfer create',
                'general-transfer edit',
                'general-transfer delete',
                'general-transfer show',
                'general-transfer export',

                // Project & Tasks
                'project manage',
                'project create',
                'project edit',
                'project delete',
                'project show',
                'project invite user',
                'project report manage',
                'project import',
                'project setting',
                'project finance manage',
                'project dashboard manage',
                'milestone manage',
                'milestone create',
                'milestone edit',
                'milestone delete',
                'milestone show',
                'task manage',
                'task create',
                'task edit',
                'task delete',
                'task show',
                'task move',
                'task file manage',
                'task file uploads',
                'task file delete',
                'task file show',
                'task comment manage',
                'task comment create',
                'task comment edit',
                'task comment delete',
                'task comment show',
                'taskstage manage',
                'taskstage edit',
                'taskstage delete',
                'taskstage show',
                'sub-task manage',
                'sub-task create',
                'sub-task edit',
                'sub-task delete',
                'project-document manage',
                'project-document create',
                'project-document edit',
                'project-document delete',
                'project-document show',
                'project-document export',

                // Attendance
                'attendance monthly-report',

                // ============ HRM MODULE PERMISSIONS ============
                'hrm manage',
                'hrm dashboard manage',
                'sidebar hrm report manage',
                'document manage',
                'document create',
                'document edit',
                'document delete',
                'attendance manage',
                'attendance create',
                'attendance edit',
                'attendance delete',
                'attendance import',
                'branch manage',
                'branch create',
                'branch edit',
                'branch delete',
                'department manage',
                'department create',
                'department edit',
                'department delete',
                'designation manage',
                'designation create',
                'designation edit',
                'designation delete',
                'employee manage',
                'employee create',
                'employee edit',
                'employee delete',
                'employee show',
                'employee profile manage',
                'employee profile show',
                'employee import',
                'employee transfer',
                'documenttype manage',
                'documenttype create',
                'documenttype edit',
                'documenttype delete',
                'companypolicy manage',
                'companypolicy create',
                'companypolicy edit',
                'companypolicy delete',
                'leave manage',
                'leave create',
                'leave edit',
                'leave delete',
                'leave approver manage',
                'leavetype manage',
                'leavetype create',
                'leavetype edit',
                'leavetype delete',
                'award manage',
                'award create',
                'award edit',
                'award delete',
                'awardtype manage',
                'awardtype create',
                'awardtype edit',
                'awardtype delete',
                'transfer manage',
                'transfer create',
                'transfer edit',
                'transfer delete',
                'resignation manage',
                'resignation create',
                'resignation edit',
                'resignation delete',
                'travel manage',
                'travel create',
                'travel edit',
                'travel delete',
                'promotion manage',
                'promotion create',
                'promotion edit',
                'promotion delete',
                'complaint manage',
                'complaint create',
                'complaint edit',
                'complaint delete',
                'warning manage',
                'warning create',
                'warning edit',
                'warning delete',
                'termination manage',
                'termination create',
                'termination edit',
                'termination delete',
                'termination description',
                'terminationtype manage',
                'terminationtype create',
                'terminationtype edit',
                'terminationtype delete',
                'announcement manage',
                'announcement create',
                'announcement edit',
                'announcement delete',
                'holiday manage',
                'holiday create',
                'holiday edit',
                'holiday delete',
                'holiday import',
                'attendance report manage',
                'leave report manage',
                'payroll report manage',
                'paysliptype manage',
                'paysliptype create',
                'paysliptype edit',
                'paysliptype delete',
                'allowanceoption manage',
                'allowanceoption create',
                'allowanceoption edit',
                'allowanceoption delete',
                'loanoption manage',
                'loanoption create',
                'loanoption edit',
                'loanoption delete',
                'deductionoption manage',
                'deductionoption create',
                'deductionoption edit',
                'deductionoption delete',
                'setsalary manage',
                'setsalary pay slip manage',
                'setsalary create',
                'setsalary edit',
                'setsalary show',
                'allowance manage',
                'allowance create',
                'allowance edit',
                'allowance delete',
                'commission manage',
                'commission create',
                'commission edit',
                'commission delete',
                'loan manage',
                'loan create',
                'loan edit',
                'loan delete',
                'saturation deduction manage',
                'saturation deduction create',
                'saturation deduction edit',
                'saturation deduction delete',
                'other payment manage',
                'other payment create',
                'other payment edit',
                'other payment delete',
                'overtime manage',
                'overtime create',
                'overtime edit',
                'overtime delete',
                'company contribution manage',
                'company contribution create',
                'company contribution edit',
                'company contribution delete',
                'branch name edit',
                'department name edit',
                'designation name edit',
                'event manage',
                'event create',
                'event edit',
                'event delete',
                'sidebar payroll manage',
                'sidebar hr admin manage',
                'letter joining manage',
                'letter certificate manage',
                'letter noc manage',
                'ip restrict manage',
                'ip restrict create',
                'ip restrict edit',
                'ip restrict delete',
                'bulk attendance manage',
                'tax bracket manage',
                'tax bracket create',
                'tax bracket edit',
                'tax bracket delete',
                'tax rebate manage',
                'tax rebate create',
                'tax rebate edit',
                'tax rebate delete',
                'tax threshold manage',
                'tax threshold create',
                'tax threshold edit',
                'tax threshold delete',
                'allowance tax manage',
                'allowance tax create',
                'allowance tax edit',
                'allowance tax delete',

                // ============ ACCOUNT MODULE PERMISSIONS ============
                'account dashboard manage',
                'bank account manage',
                'bank account create',
                'bank account edit',
                'bank account delete',
                'bank transfer manage',
                'bank transfer create',
                'bank transfer edit',
                'bank transfer delete',
                'account manage',
                'customer manage',
                'customer create',
                'customer edit',
                'customer delete',
                'customer show',
                'customer import',
                'vendor manage',
                'vendor create',
                'vendor edit',
                'vendor delete',
                'vendor show',
                'vendor import',
                'creditnote manage',
                'creditnote create',
                'creditnote edit',
                'creditnote delete',
                'revenue manage',
                'revenue create',
                'revenue edit',
                'revenue delete',
                'report manage',
                'bill manage',
                'bill create',
                'bill edit',
                'bill delete',
                'bill payment manage',
                'bill payment create',
                'bill payment edit',
                'bill payment delete',
                'bill show',
                'bill duplicate',
                'bill product delete',
                'bill send',
                'debitnote manage',
                'debitnote create',
                'debitnote edit',
                'debitnote delete',
                'expense payment manage',
                'expense payment create',
                'expense payment edit',
                'expense payment delete',
                'report transaction manage',
                'report statement manage',
                'report income manage',
                'report expense manage',
                'report income vs expense manage',
                'report tax manage',
                'report loss & profit  manage',
                'report invoice manage',
                'report bill manage',
                'report stock manage',
                'sidebar income manage',
                'sidebar expanse manage',
                'sidebar banking manage',
                'chartofaccount manage',
                'chartofaccount create',
                'chartofaccount edit',
                'chartofaccount show',
                'chartofaccount delete',

                // ============ LEAD MODULE PERMISSIONS ============
                'crm manage',
                'crm dashboard manage',
                'crm setup manage',
                'crm report manage',
                'lead manage',
                'lead create',
                'lead edit',
                'lead delete',
                'lead show',
                'lead move',
                'lead import',
                'lead call create',
                'lead call edit',
                'lead call delete',
                'lead email create',
                'lead to deal convert',
                'lead report',
                'deal report',
                'deal manage',
                'deal create',
                'deal edit',
                'deal delete',
                'deal show',
                'deal move',
                'deal import',
                'deal task create',
                'deal task edit',
                'deal task delete',
                'deal task show',
                'deal call create',
                'deal call edit',
                'deal call delete',
                'deal email create',
                'pipeline manage',
                'pipeline create',
                'pipeline edit',
                'pipeline delete',
                'dealstages manage',
                'dealstages create',
                'dealstages edit',
                'dealstages delete',
                'leadstages manage',
                'leadstages create',
                'leadstages edit',
                'leadstages delete',
                'labels manage',
                'labels create',
                'labels edit',
                'labels delete',
                'source manage',
                'source create',
                'source edit',
                'source delete',
                'lead task create',
                'lead task edit',
                'lead task delete',

                // ============ PRODUCT SERVICE MODULE PERMISSIONS ============
                'product&service manage',
                'product&service create',
                'product&service edit',
                'product&service delete',
                'product&service import',
                'unit manage',
                'unit cerate',
                'unit edit',
                'unit delete',
                'tax manage',
                'tax create',
                'tax edit',
                'tax delete',
                'category manage',
                'category create',
                'category edit',
                'category delete',
                'product service manage',

                // ============ POS MODULE PERMISSIONS ============
                'pos manage',
                'pos show',
                'pos dashboard manage',
                'pos add manage',
                'pos cart manage',
                'report pos',
                'report pos vs expense',

                // ============ PAYMENT MODULE PERMISSIONS ============
                'paypal manage',
                'stripe manage',
            ];


        $superAdminRole  = Role::where('name','super admin')->first();
        foreach ($adnin_permission  as $key => $value)
        {
            $permission = Permission::where('name',$value)->first();
            if(empty($permission))
            {
                $permission = Permission::create(
                    [
                        'name' => $value,
                        'guard_name' => 'web',
                        'module' => 'General',
                        'created_by' => $admin->id,
                        "created_at" => date('Y-m-d H:i:s'),
                        "updated_at" => date('Y-m-d H:i:s')
                    ]
                );
            }
            if(!$superAdminRole->hasPermission($value))
            {
                $superAdminRole->givePermission($permission);
            }
        }
        // Company ..
        $role = Role::where('name','company')->where('guard_name','web')->exists();
        if(!$role)
        {
            $company_role        = Role::create(
                [
                    'name' => 'company',
                    'created_by' => $admin->id,
                ]
            );
        }
        $company_role = Role::where('name','company')->first();
        foreach ($compnay_permission as $key => $value)
        {
            $permission = Permission::where('name',$value)->first();
            if(empty($permission))
            {
                $permission = Permission::create(
                    [
                        'name' => $value,
                        'guard_name' => 'web',
                        'module' => 'General',
                        'created_by' => $admin->id,
                        "created_at" => date('Y-m-d H:i:s'),
                        "updated_at" => date('Y-m-d H:i:s')
                    ]
                );
            }
            if(!$company_role->hasPermission($value))
            {
                $company_role->givePermission($permission);
            }
        }


        $company = User::where('type','company')->first();
        try{

            $assigned_role = $company->roles->first();
            
        }catch(\Exception $e){
            $assigned_role = null;
        }
        if(!$assigned_role && !empty($company))
        {
            $company->addRole($company_role);
        }

        // Ensure Admin role has same permissions as Company
        $companyRole = Role::where('name', 'company')->first();
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        if ($companyRole && $adminRole) {
            $adminRole->syncPermissions($companyRole->permissions);
        }

        // Create Admin user if not exists and assign admin role
        $adminUser = User::where('type', 'admin')->first();
        if (empty($adminUser)) {
            $adminUser = new User();
            $adminUser->name = 'Admin';
            $adminUser->email = 'admin@example.com';
            $adminUser->password = Hash::make('1234');
            $adminUser->email_verified_at = date('Y-m-d H:i:s');
            $adminUser->type = 'admin';
            $adminUser->active_status = 1;
            $adminUser->active_workspace = 0;
            $adminUser->avatar = 'uploads/users-avatar/avatar.png';
            $adminUser->dark_mode = 0;
            $adminUser->lang = 'en';
            $adminUser->workspace_id = 0;
            $adminUser->created_by = $admin->id;
            $adminUser->save();

            $adminUser->addRole($adminRole);
        } else {
            // Ensure admin user has admin role
            if (!$adminUser->hasRole('admin')) {
                $adminUser->addRole($adminRole);
            }
        }

        // Give all task-related permissions to ALL roles by default
        $taskPermissions = [
            'task manage',
            'task create',
            'task edit',
            'task delete',
            'task show',
            'task move',
            'task file manage',
            'task file uploads',
            'task file delete',
            'task file show',
            'task comment manage',
            'task comment create',
            'task comment edit',
            'task comment delete',
            'task comment show',
            'taskstage manage',
            'taskstage edit',
            'taskstage delete',
            'taskstage show',
            'sub-task manage',
            'sub-task create',
            'sub-task edit',
            'sub-task delete',
        ];

        // Get all roles
        $allRoles = Role::all();

        foreach ($allRoles as $role) {
            foreach ($taskPermissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && !$role->hasPermission($permissionName)) {
                    $role->givePermission($permission);
                }
            }
        }
    }
}
