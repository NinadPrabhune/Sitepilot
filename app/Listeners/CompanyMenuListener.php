<?php

namespace App\Listeners;

use App\Events\CompanyMenuEvent;

class CompanyMenuListener
{
    /**
     * Handle the event.
     */
    public function handle(CompanyMenuEvent $event): void
    {
        $module = 'Base';
        $menu = $event->menu;
        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('Dashboard'),
//            'icon' => 'home',
//            'name' => 'dashboard',
//            'parent' => null,
//            'order' => 1,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => '',
//            'module' => $module,
//            'permission' => ''
//        ]);
        
        
        
        
        
        
        $menu->add([
            'category' => 'General',
            'title' => __('Masters'),
            'icon' => 'list',
            'name' => 'masters',
            'parent' => null,
            'order' => 12,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => ''
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('Material'),
            'icon' => '',
            'name' => 'material',
            'parent' => 'masters',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => ''
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('All Material'),
            'icon' => '',
            'name' => 'all-material',
            'parent' => 'material',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'material.index',
            'module' => $module,
            'permission' => 'material manage'
        ]);

        $menu->add([
            'category' => 'General',
            'title' => __('Material Category'),
            'icon' => '',
            'name' => 'material-category',
            'parent' => 'material',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'material-categories.index',
            'module' => $module,
            'permission' => 'material-category manage'
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('Unit'),
            'icon' => '',
            'name' => 'material-unit',
            'parent' => 'material',
            'order' => 30,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'units.index',
            'module' => $module,
            'permission' => 'material-unit manage'
        ]);
        
        
        
        
        $menu->add([
            'category' => 'General',
            'title' => __('Supplier'),
            'icon' => '',
            'name' => 'supplier',
            'parent' => 'masters',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => ''
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('All Supplier'),
            'icon' => '',
            'name' => 'all-supplier',
            'parent' => 'supplier',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'supplier.index',
            'module' => $module,
            'permission' => 'supplier manage'
        ]);

        $menu->add([
            'category' => 'General',
            'title' => __('Supplier Category'),
            'icon' => '',
            'name' => 'supplier-category',
            'parent' => 'supplier',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'supplier-categories.index',
            'module' => $module,
            'permission' => 'supplier-category manage'
        ]);
        
        
         
         $menu->add([
            'category' => 'General',
            'title' => __('Man-Power Type'),
            'icon' => '',
            'name' => 'manpower-type',
            'parent' => 'supplier',
            'order' => 30,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'manpower-type.index',
            'module' => $module,
            'permission' => 'man-power-type manage'
        ]);
        
        
        
        $menu->add([
            'category' => 'General',
            'title' => __('Assets'),
            'icon' => 'tools',
            'name' => 'assets',
            'parent' => 'masters',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => ''
        ]);
        
        
        // Machinery Management Parent Menu
        $menu->add([
            'category' => 'General',
            'title' => __('Machinery Management'),
            'icon' => 'tools',
            'name' => 'machinery-management',
            'parent' => null,
            'order' => 15,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => ''
        ]);

        $menu->add([
            'category' => 'General',
            'title' => __('All Machinery'),
            'icon' => '',
            'name' => 'all-machinery',
            'parent' => 'assets',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'machineries.index',
            'module' => $module,
            'permission' => 'machinery manage'
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('Machinery Category'),
            'icon' => '',
            'name' => 'machinery-category',
            'parent' => 'assets',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'machinery-categories.index',
            'module' => $module,
            'permission' => 'machinery-category manage'
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('Tools & Equipment'),
            'icon' => '',
            'name' => 'tools-and-equipment',
            'parent' => 'assets',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'assets_tools_and_equipment.index',
            'module' => $module,
            'permission' => 'tools-and-equipment manage'
        ]);
        
        
        
        
        
        // ERP Menu
        $menu->add([
            'category' => 'General',
            'title' => __('ERP'),
            'icon' => 'file-invoice',
            'name' => 'erp',
            'parent' => null,
            'order' => 12,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => ''
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('Indent'),
            'icon' => 'file-invoice',
            'name' => 'indent',
            'parent' => 'erp',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'indent.index',
            'module' => $module,
            'permission' => 'indent show'
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('Purchase Order'),
            'icon' => 'shopping-cart',
            'name' => 'purchase-order',
            'parent' => 'erp',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'purchase-order.index',
            'module' => $module,
            'permission' => 'purchase-order show'
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('GRN'),
            'icon' => 'package',
            'name' => 'grn',
            'parent' => 'erp',
            'order' => 30,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'grn.index',
            'module' => $module,
            'permission' => 'grn show'
        ]);

        // Inventory Section
        $menu->add([
            'category' => 'General',
            'title' => __('Inventory'),
            'icon' => 'stack',
            'name' => 'inventory',
            'parent' => '',
            'order' => 14,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => ''
        ]);

        $menu->add([
            'category' => 'General',
            'title' => __('Opening Stock'),
            'icon' => 'plus-circle',
            'name' => 'opening-stock',
            'parent' => 'inventory',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'opening-stock.index',
            'module' => $module,
            'permission' => 'opening-stock manage'
        ]);

        $menu->add([
            'category' => 'General',
            'title' => __('Stock Ledger'),
            'icon' => 'book-open',
            'name' => 'stock-ledger',
            'parent' => 'inventory',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'stock-ledger.index',
            'module' => $module,
            'permission' => 'stock-ledger manage'
        ]);

        $menu->add([
            'category' => 'General',
            'title' => __('Site Stock'),
            'icon' => 'building-warehouse',
            'name' => 'site-stock',
            'parent' => 'inventory',
            'order' => 30,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'site-stock.index',
            'module' => $module,
            'permission' => 'site-stock manage'
        ]);

        $menu->add([
            'category' => 'General',
            'title' => __('Material Issue'),
            'icon' => 'arrow-down',
            'name' => 'material-issue',
            'parent' => 'inventory',
            'order' => 40,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'material-issues.index',
            'module' => $module,
            'permission' => 'material-issue manage'
        ]);

        $menu->add([
            'category' => 'General',
            'title' => __('Material Return'),
            'icon' => 'arrow-up',
            'name' => 'material-return',
            'parent' => 'inventory',
            'order' => 50,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'material-returns.index',
            'module' => $module,
            'permission' => 'material-return manage'
        ]);

        $menu->add([
            'category' => 'General',
            'title' => __('Purchase Invoice'),
            'icon' => 'file-invoice',
            'name' => 'purchase-invoice',
            'parent' => 'erp',
            'order' => 40,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'purchase-invoice.index',
            'module' => $module,
            'permission' => 'purchase-invoice manage'
        ]);

//        $menu->add([
//            'category' => 'General',
//            'title' => __('Supplier Advances'),
//            'icon' => 'credit-card',
//            'name' => 'supplier-advance',
//            'parent' => 'erp',
//            'order' => 50,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => 'supplier-advance.index',
//            'module' => $module,
//            'permission' => 'supplier-advance manage'
//        ]);



//        $menu->add([
//            'category' => 'General',
//            'title' => __('Transaction'),
//            'icon' => 'exchange',
//            'name' => 'transaction',
//            'parent' => null,
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => '',
//            'module' => $module,
//            'permission' => ''
//        ]);
//        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('Daily Transaction'),
//            'icon' => '',
//            'name' => 'daily-transaction',
//            'parent' => 'transaction',
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => '',
//            'module' => $module,
//            'permission' => ''
//        ]);
//        
//        
//        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('Purchase Invoice'),
//            'icon' => 'bill',
//            'name' => 'purchase-invoice',
//            'parent' => 'daily-transaction',
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => 'purchase-invoice.index',
//            'module' => $module,
//            'permission' => 'purchase-invoices manage'
//        ]);
//        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('Site Activity'),
//            'icon' => '',
//            'name' => 'site-activity',
//            'parent' => 'daily-transaction',
//            'order' => 15,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => 'activities.index',
//            'module' => $module,
//            'permission' => 'activities manage'
//        ]);
//        
//         $menu->add([
//            'category' => 'General',
//            'title' => __('Man-Power'),
//            'icon' => '',
//            'name' => 'manpower',
//            'parent' => 'daily-transaction',
//            'order' => 20,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => 'manpower.index',
//            'module' => $module,
//            'permission' => 'manpower manage'
//        ]);
//        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('Daily Consumption'),
//            'icon' => '',
//            'name' => 'daily-consumption',
//            'parent' => 'transaction',
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => '',
//            'module' => $module,
//            'permission' => ''
//        ]);
//        
//        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('Consumption Log'),
//            'icon' => '',
//            'name' => 'consumption-log',
//            'parent' => 'daily-consumption',
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => 'daily-consumption.index',
//            'module' => $module,
//            'permission' => 'consumption-log manage'
//        ]);
//        
//        
//        
//         $menu->add([
//            'category' => 'General',
//            'title' => __('Material Transfer'),
//            'icon' => '',
//            'name' => 'material-transfer',
//            'parent' => 'transaction',
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => 'material-transfer.index',
//            'module' => $module,
//            'permission' => 'material-transfer manage'
//        ]);
        
        
        $menu->add([
            'category' => 'General',
            'title' => __('Payment'),
            'icon' => 'credit-card',
            'name' => 'payment',
            'parent' => null,
            'order' => 15,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => 'manage-payment manage'
        ]);
        
        
       
        
        
        $menu->add([
            'category' => 'General',
            'title' => __('Payment Request'),
            'icon' => '',
            'name' => 'all-payment',
            'parent' => 'payment',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'payment-request.index',    
            'module' => $module,
            'permission' => 'manage-payment manage'
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('All Payment'),
            'icon' => '',
            'name' => 'all-payment',
            'parent' => 'payment',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'payments-module.index',    
            'module' => $module,
            'permission' => 'manage-payment manage'
        ]);
        
        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('Machinery Rental'),
//            'icon' => '',
//            'name' => 'machinery-rental',
//            'parent' => 'daily-consumption',
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => '',
//            'module' => $module,
//            'permission' => ''
//        ]);
//        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('Machinery Fuel'),
//            'icon' => '',
//            'name' => 'machinery-fuel',
//            'parent' => 'daily-consumption',
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => '',
//            'module' => $module,
//            'permission' => ''
//        ]);
        
        
        
        
        
       
         $menu->add([
            'category' => 'General',
            'title' => __('Reports'),
            'icon' => 'file',
            'name' => 'all-reports',
            'parent' => null,
            'order' => 15,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => ''
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('DPR Report'),
            'icon' => '',
            'name' => 'dpr-report',
            'parent' => 'machinery-management',
            'order' => 40,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'daily-progress-reports.index',    
            'module' => $module,
            'permission' => 'machinery-dpr manage'
        ]);

        // $menu->add([
        //     'category' => 'General',
        //     'title' => __('Monthly Control'),
        //     'icon' => 'lock',
        //     'name' => 'monthly-control',
        //     'parent' => 'machinery-management',
        //     'order' => 20,
        //     'ignore_if' => [],
        //     'depend_on' => [],
        //     'route' => 'monthly-control.index',
        //     'module' => $module,
        //     'permission' => 'monthly-control manage'
        // ]);

        $menu->add([
            'category' => 'General',
            'title' => __('Machinery Ledger'),
            'icon' => 'book-open',
            'name' => 'machinery-ledger',
            'parent' => 'machinery-management',
            'order' => 25,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'ledger.index',
            'module' => $module,
            'permission' => 'machinery-ledger manage'
        ]);

        $menu->add([
            'category' => 'General',
            'title' => __('Machinery Payment'),
            'icon' => 'credit-card',
            'name' => 'machinery-payment',
            'parent' => 'machinery-management',
            'order' => 30,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'machinery-payment.index',
            'module' => $module,
            'permission' => 'machinery-payment manage'
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('Stock Report'),
            'icon' => '',
            'name' => 'stock-report',
            'parent' => 'all-reports',
            'order' => 15,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'stock-reports.index',    
            'module' => $module,
            'permission' => 'stock-report manage'
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('Supplier Ledger Report'),
            'icon' => '',
            'name' => 'supplier-ledger-report',
            'parent' => 'all-reports',
            'order' => 16,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'reports.supplier-ledger',    
            'module' => $module,
            'permission' => 'supplier-ledger report'
        ]);
        
        $menu->add([
            'category' => 'General',
            'title' => __('Supplier Activity Report'),
            'icon' => '',
            'name' => 'supplier-activity-report',
            'parent' => 'all-reports',
            'order' => 17,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'reports.supplier-activity',    
            'module' => $module,
            'permission' => 'supplier-ledger report'
        ]);
        
        
        $menu->add([
            'category' => 'General',
            'title' => __('Transfer Report'),
            'icon' => '',
            'name' => 'general-transfer-report',
            'parent' => 'all-reports',
            'order' => 15,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'general_transfer.index',    
            'module' => $module,
            'permission' => 'stock-report manage'
        ]);
        
        
        
        $menu->add([
            'category' => 'General',
            'title' => __('User Management'),
            'icon' => 'users',
            'name' => 'user-management',
            'parent' => null,
            'order' => 30,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => 'user manage'
        ]);

        $menu->add([
            'category' => 'Settings',
            'title' => __('Numbering Configuration'),
            'icon' => 'hash',
            'name' => 'numbering-config',
            'parent' => 'settings',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'settings.numbering.index',
            'module' => $module,
            'permission' => ''
        ]);

        $menu->add([
            'category' => 'General',
            'title' => __('User'),
            'icon' => '',
            'name' => 'user',
            'parent' => 'user-management',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'users.index',
            'module' => $module,
            'permission' => 'user manage'
        ]);
        $menu->add([
            'category' => 'General',
            'title' => __('Role'),
            'icon' => '',
            'name' => 'role',
            'parent' => 'user-management',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'roles.index',
            'module' => $module,
            'permission' => 'roles manage'
        ]);
        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('Masters'),
//            'icon' => 'list',
//            'name' => 'masters',
//            'parent' => 'masters',
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => '',
//            'module' => $module,
//            'permission' => ''
//        ]);
//        
//        
//        
//        
//        
//        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('Inventory'),
//            'icon' => 'shopping-cart',
//            'name' => 'inventory',
//            'parent' => null,
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => '',
//            'module' => $module,
//            'permission' => ''
//        ]);
//        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('Inventory Catagory'),
//            'icon' => '',
//            'name' => 'inventory-catagory',
//            'parent' => 'inventory',
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => '',
//            'module' => $module,
//            'permission' => ''
//        ]);
//        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('All Inventory'),
//            'icon' => '',
//            'name' => 'all-inventory',
//            'parent' => 'inventory',
//            'order' => 20,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => '',
//            'module' => $module,
//            'permission' => ''
//        ]);
//        
//        
//        
//         $menu->add([
//            'category' => 'General',
//            'title' => __('Machinery'),
//            'icon' => 'tools',
//            'name' => 'machinery',
//            'parent' => null,
//            'order' => 20,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => '',
//            'module' => $module,
//            'permission' => ''
//        ]);
//        
//        
//        $menu->add([
//            'category' => 'General',
//            'title' => __('All Machinery'),
//            'icon' => '',
//            'name' => 'all-machinery',
//            'parent' => 'machinery',
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => '',
//            'module' => $module,
//            'permission' => ''
//        ]);
        
        $menu->add([
            'category' => 'Productivity',
            'title' => __('Project Documents'),
            'icon' => 'file-text',
            'name' => 'project-documents',
            'parent' => null,
            'order' => 1,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'project-documents.index',
            'module' => $module,
            'permission' => 'project-document manage'
        ]);

        $menu->add([
            'category' => 'Productivity',
            'title' => __('Task Board'),
            'icon' => 'list-check',
            'name' => 'task-board',
            'parent' => null,
            'order' => 2,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'projecttask.list',
            'parameters' => ['id' => getActiveProject() ?: 1],
            'module' => $module,
            'permission' => ''
        ]);
        
        
//        $menu->add([
//            'category' => 'Productivity',
//            'title' => __('Project File Manager'),
//            'icon' => 'file-text',
//            'name' => 'project-file-manager',
//            'parent' => null,
//            'order' => 1,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => 'file-manager.index',
//            'module' => $module,
//            'permission' => ''
//        ]);
        
        
        
        
        
        
//        $menu->add([
//            'category' => 'Finance',
//            'title' => __('Proposal'),
//            'icon' => 'replace',
//            'name' => 'proposal',
//            'parent' => '',
//            'order' => 150,
//            'ignore_if' => [],
//            'depend_on' => ['Account','Taskly'],
//            'route' => 'proposal.index',
//            'module' => $module,
//            'permission' => 'proposal manage'
//        ]);
//        
//        $menu->add([
//            'category' => 'Finance',
//            'title' => __('Invoice'),
//            'icon' => 'file-invoice',
//            'name' => 'invoice',
//            'parent' => '',
//            'order' => 200,
//            'ignore_if' => [],
//            'depend_on' => ['Account','Taskly'],
//            'route' => 'invoice.index',
//            'module' => $module,
//        'permission' => 'invoice manage'
//        ]);



//        $menu->add([
//            'category' => 'Finance',
//            'title' => __('Purchases'),
//            'icon' => 'shopping-cart',
//            'name' => 'purchases',
//            'parent' => null,
//            'order' => 250,
//            'ignore_if' => [],
//            'depend_on' => ['Account','Taskly'],
//            'route' => '',
//            'module' => $module,
//            'permission' => 'purchase manage'
//        ]);
//          $menu->add([
//            'category' => 'Finance',
//            'title' => __('Purchase'),
//            'icon' => '',
//            'name' => 'purchase',
//            'parent' => 'purchases',
//            'order' => 10,
//            'ignore_if' => [],
//            'depend_on' => ['Account','Taskly'],
//            'route' => 'purchases.index',
//            'module' => $module,
//            'permission' => 'purchase manage'
//        ]);
//
//        $menu->add([
//            'category' => 'Finance',
//            'title' => __('Warehouse'),
//            'icon' => '',
//            'name' => 'warehouse',
//            'parent' => 'purchases',
//            'order' => 15,
//            'ignore_if' => [],
//            'depend_on' => ['Account','Taskly'],
//            'route' => 'warehouses.index',
//            'module' => $module,
//            'permission' => 'warehouse manage'
//        ]);
//
//        $menu->add([
//            'category' => 'Finance',
//            'title' => __('Transfer'),
//            'icon' => '',
//            'name' => 'transfer',
//            'parent' => 'purchases',
//            'order' => 20,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => 'warehouses-transfer.index',
//            'module' => $module,
//            'permission' => 'warehouse manage'
//        ]);
//
//        $menu->add([
//            'category' => 'Finance',
//            'title' => __('Report'),
//            'icon' => '',
//            'name' => 'reports',
//            'parent' => 'purchases',
//            'order' => 25,
//            'ignore_if' => [],
//            'depend_on' => ['Account','Taskly'],
//            'route' => '',
//            'module' => $module,
//            'permission' => 'report purchase'
//        ]);

        $menu->add([
            'category' => 'Finance',
            'title' => __('Purchase Daily/Monthly Report'),
            'icon' => '',
            'name' => 'purchase-monthly',
            'parent' => 'reports',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'reports.daily.purchase',
            'module' => $module,
            'permission' => 'report purchase'
        ]);

        $menu->add([
            'category' => 'Finance',
            'title' => __('Warehouse Report'),
            'icon' => '',
            'name' => 'warehouse-report',
            'parent' => 'reports',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'reports.warehouse',
            'module' => $module,
            'permission' => 'report warehouse'
        ]);

        $menu->add([
            'category' => 'Communication',
            'title' => __('Messenger'),
            'icon' => 'brand-hipchat',
            'name' => 'messenger',
            'parent' => '',
            'order' => 1500,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'chatify',
            'module' => $module,
            'permission' => 'user chat manage'
        ]);
//        $menu->add([
//            'category' => 'Settings',
//            'title' => __('Helpdesk'),
//            'icon' => 'headphones',
//            'name' => 'helpdesk',
//            'parent' => null,
//            'order' => 1900,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => 'helpdesk.index',
//            'module' => $module,
//            'permission' => 'helpdesk ticket manage'
//        ]);
        $menu->add([
            'category' => 'Settings',
            'title' => __('Settings'),
            'icon' => 'settings',
            'name' => 'settings',
            'parent' => null,
            'order' => 2000,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => 'setting manage'
        ]);
        $menu->add([
            'category' => 'Settings',
            'title' => __('System Settings'),
            'icon' => '',
            'name' => 'system-settings',
            'parent' => 'settings',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'settings.index',
            'module' => $module,
            'permission' => 'setting manage'
        ]);
//        $menu->add([
//            'category' => 'Settings',
//            'title' => __('Setup Subscription Plan'),
//            'icon' => '',
//            'name' => 'setup-subscription-plan',
//            'parent' => 'settings',
//            'order' => 20,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => 'plans.index',
//            'module' => $module,
//            'permission' => 'plan manage'
//        ]);
//        $menu->add([
//            'category' => 'Settings',
//            'title' => __('Referral Program'),
//            'icon' => '',
//            'name' => 'referral-program',
//            'parent' => 'settings',
//            'order' => 25,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => 'referral-program.company',
//            'module' => $module,
//            'permission' => 'referral program manage'
//        ]);
//        $menu->add([
//            'category' => 'Settings',
//            'title' => __('Order'),
//            'icon' => '',
//            'name' => 'order',
//            'parent' => 'settings',
//            'order' => 30,
//            'ignore_if' => [],
//            'depend_on' => [],
//            'route' => 'plan.order.index',
//            'module' => $module,
//            'permission' => 'plan orders'
//        ]);
    }
}
