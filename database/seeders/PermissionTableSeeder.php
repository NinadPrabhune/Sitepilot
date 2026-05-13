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
            'user manage',
            'user create',
            'user edit',
            'user delete',
            'user profile manage',
            'user reset password',
            'user login manage',
            'user import',
            'user logs history',
            'setting manage',
            'setting storage manage',
            'coupon manage',
            'coupon create',
            'coupon edit',
            'coupon delete',
            'plan manage',
            'plan create',
            'plan edit',
            'plan delete',
            'plan orders',
            'module manage',
            'module add',
            'module remove',
            'module edit',
            'email template manage',
            'language manage',
            'language create',
            'language delete',
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

            'api key setting manage',
            'api key setting create',
            'api key setting edit',
            'api key setting delete',

            'notification template manage',

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
        ];

            $compnay_permission = [
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
                'setting manage',
                'helpdesk ticket manage',
                'helpdesk ticket create',
                'helpdesk ticket edit',
                'helpdesk ticket show',
                'helpdesk ticket reply',
                'helpdesk ticket delete',
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


                'man-power-type manage',
                'man-power-type create',
                'man-power-type edit',
                'man-power-type delete',
                'man-power-type show',
                'man-power-type export',

                'machinery manage',
                'machinery create',
                'machinery edit',
                'machinery delete',
                'machinery show',
                'machinery export',
                'machinery transfer',



                'supplier-category manage',
                'machinery-category create',
                'machinery-category edit',
                'machinery-category delete',
                'machinery-category show',
                'machinery-category export',


                'tools-and-equipment manage',
                'tools-and-equipment create',
                'tools-and-equipment edit',
                'tools-and-equipment delete',
                'tools-and-equipment show',
                'tools-and-equipment export',
                'tools-and-equipment transfer',


                'activity manage',
                'activity create',
                'activity edit',
                'activity delete',
                'activity show',
                'activity export',
                
                
                'indent manage',
                'indent create',
                'indent edit',
                'indent delete',
                'indent show',
                'indent export',
                
                'purchase-order manage',
                'purchase-order create',
                'purchase-order edit',
                'purchase-order delete',
                'purchase-order show',
                'purchase-order export',
                'purchase-order print',
                'purchase-order advance-request',

                'purchase-invoice manage',
                'purchase-invoice create',
                'purchase-invoice edit',
                'purchase-invoice delete',
                'purchase-invoice show',
                'purchase-invoice export',
                'purchase-invoice print',

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

                'man-power manage',
                'man-power create',
                'man-power edit',
                'man-power delete',
                'man-power show',
                'man-power export',

                'consumption-log manage',
                'consumption-log create',
                'consumption-log edit',
                'consumption-log delete',
                'consumption-log show',
                'consumption-log export',

                'material-transfer manage',
                'material-transfer create',
                'material-transfer edit',
                'material-transfer delete',
                'material-transfer show',
                'material-transfer export',


                'manage-payment manage',
                'manage-payment create',
                'manage-payment edit',
                'manage-payment delete',
                'manage-payment show',
                'manage-payment export',


                'spent manage',
                'spent create',
                'spent edit',
                'spent delete',
                'spent show',
                'spent export',
                'spent ledger create',


                'machinery-dpr manage',
                'machinery-dpr create',
                'machinery-dpr edit',
                'machinery-dpr delete',
                'machinery-dpr show',
                'machinery-dpr export',

                
                'stock-report manage',
                'stock-report add',
                'stock-report consume',
                'stock-report transfer',
                'stock-report export',

                // Supplier Ledger Report permissions
                'supplier-ledger report',

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
                'machinery-payment manage',
                'monthly-control manage',
                'report purchase',
                'report warehouse',

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

                // Material Issue permissions
                'material-issue manage',
                'material-issue create',
                'material-issue edit',
                'material-issue delete',
                'material-issue show',
                'material-issue export',
                'material-issue print',

                // Material Return permissions
                'material-return manage',
                'material-return create',
                'material-return edit',
                'material-return delete',
                'material-return show',
                'material-return export',
                'material-return print',

                // General Transfer permissions
                'general-transfer manage',
                'general-transfer create',
                'general-transfer edit',
                'general-transfer delete',
                'general-transfer show',
                'general-transfer export',

                // Taskly Task permissions - COMMENTED OUT
                // 'taskly manage',
                // 'taskly setup manage',
                // 'taskly dashboard manage',
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
                // 'team member remove',
                // 'team client remove',
                // 'bug manage',
                // 'bug create',
                // 'bug edit',
                // 'bug delete',
                // 'bug show',
                // 'bug move',
                // 'bug comments create',
                // 'bug comments delete',
                // 'bug file uploads',
                // 'bug file delete',
                // 'bugstage manage',
                // 'bugstage edit',
                // 'bugstage delete',
                // 'bugstage show',
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
            ];

        $compnay_permission = [
                'referral program manage',
                
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


                'man-power-type manage',
                'man-power-type create',
                'man-power-type edit',
                'man-power-type delete',
                'man-power-type show',
                'man-power-type export',

                'machinery manage',
                'machinery create',
                'machinery edit',
                'machinery delete',
                'machinery show',
                'machinery export',
                'machinery transfer',



                'supplier-category manage',
                'machinery-category create',
                'machinery-category edit',
                'machinery-category delete',
                'machinery-category show',
                'machinery-category export',


                'tools-and-equipment manage',
                'tools-and-equipment create',
                'tools-and-equipment edit',
                'tools-and-equipment delete',
                'tools-and-equipment show',
                'tools-and-equipment export',
                'tools-and-equipment transfer',


                'activity manage',
                'activity create',
                'activity edit',
                'activity delete',
                'activity show',
                'activity export',
                
                
                'indent manage',
                'indent create',
                'indent edit',
                'indent delete',
                'indent show',
                'indent export',
                
                'purchase-order manage',
                'purchase-order create',
                'purchase-order edit',
                'purchase-order delete',
                'purchase-order show',
                'purchase-order export',
                'purchase-order print',
                'purchase-order advance-request',

                'purchase-invoice manage',
                'purchase-invoice create',
                'purchase-invoice edit',
                'purchase-invoice delete',
                'purchase-invoice show',
                'purchase-invoice export',
                'purchase-invoice print',

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

                'man-power manage',
                'man-power create',
                'man-power edit',
                'man-power delete',
                'man-power show',
                'man-power export',

                'consumption-log manage',
                'consumption-log create',
                'consumption-log edit',
                'consumption-log delete',
                'consumption-log show',
                'consumption-log export',

                'material-transfer manage',
                'material-transfer create',
                'material-transfer edit',
                'material-transfer delete',
                'material-transfer show',
                'material-transfer export',


                'manage-payment manage',
                'manage-payment create',
                'manage-payment edit',
                'manage-payment delete',
                'manage-payment show',
                'manage-payment export',


                'spent manage',
                'spent create',
                'spent edit',
                'spent delete',
                'spent show',
                'spent export',
                'spent ledger create',


                'machinery-dpr manage',
                'machinery-dpr create',
                'machinery-dpr edit',
                'machinery-dpr delete',
                'machinery-dpr show',
                'machinery-dpr export',

                
                'stock-report manage',
                'stock-report add',
                'stock-report consume',
                'stock-report transfer',
                'stock-report export',

                // Supplier Ledger Report permissions
                'supplier-ledger report',

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
                'machinery-payment manage',
                'monthly-control manage',
                'report purchase',
                'report warehouse',

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

                // Material Issue permissions
                'material-issue manage',
                'material-issue create',
                'material-issue edit',
                'material-issue delete',
                'material-issue show',
                'material-issue export',
                'material-issue print',

                // Material Return permissions
                'material-return manage',
                'material-return create',
                'material-return edit',
                'material-return delete',
                'material-return show',
                'material-return export',
                'material-return print',

                // General Transfer permissions
                'general-transfer manage',
                'general-transfer create',
                'general-transfer edit',
                'general-transfer delete',
                'general-transfer show',
                'general-transfer export',

                // Taskly Task permissions - COMMENTED OUT
                // 'taskly manage',
                // 'taskly setup manage',
                // 'taskly dashboard manage',
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
                // 'team member remove',
                // 'team client remove',
                // 'bug manage',
                // 'bug create',
                // 'bug edit',
                // 'bug delete',
                // 'bug show',
                // 'bug move',
                // 'bug comments create',
                // 'bug comments delete',
                // 'bug file uploads',
                // 'bug file delete',
                // 'bugstage manage',
                // 'bugstage edit',
                // 'bugstage delete',
                // 'bugstage show',
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

                // Attendance permissions
                'attendance monthly-report',
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
