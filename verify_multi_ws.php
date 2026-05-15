<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Work Spaces ===\n";
$ws = DB::table('work_spaces')->get();
foreach ($ws as $w) {
    echo "ID: {$w->id}, Name: {$w->name}, Slug: {$w->slug}, CreatedBy: {$w->created_by}, Status: {$w->status}\n";
}

echo "\n=== Users ===\n";
$users = DB::table('users')->select('id','name','type','active_workspace','workspace_id')->get();
foreach ($users as $u) {
    echo "ID: {$u->id}, Name: {$u->name}, Type: {$u->type}, ActiveWS: {$u->active_workspace}, WS: {$u->workspace_id}\n";
}

echo "\n=== Projects ===\n";
$projects = DB::table('projects')->select('id','name','workspace')->get();
foreach ($projects as $p) {
    echo "ID: {$p->id}, Name: {$p->name}, Workspace: {$p->workspace}\n";
}

echo "\n=== User-Project Mappings ===\n";
$up = DB::table('user_projects')->get();
foreach ($up as $u) {
    echo "UserID: {$u->user_id}, ProjectID: {$u->project_id}, Active: {$u->is_active}\n";
}

echo "\n=== Suppliers (with site_id) ===\n";
$suppliers = DB::table('suppliers')->select('id','name','site_id')->get();
foreach ($suppliers as $s) {
    echo "ID: {$s->id}, Name: {$s->name}, SiteID: {$s->site_id}\n";
}

echo "\n=== Machinery Count ===\n";
echo "Total machinery: " . DB::table('machineries')->count() . "\n";

echo "\n=== Purchase Invoices Count ===\n";
echo "Total invoices: " . DB::table('purchase_invoices')->count() . "\n";

echo "\n=== ManPower Masters Count ===\n";
echo "Total masters: " . DB::table('man_power_masters')->count() . "\n";

echo "\n=== Attendance Records ===\n";
echo "Total attendance: " . DB::table('attendances')->count() . "\n";

echo "\n=== Material Transfers ===\n";
echo "Total transfers: " . DB::table('material_transfers')->count() . "\n";

echo "\n=== Daily Progress Reports ===\n";
echo "Total DPRs: " . DB::table('daily_progress_reports')->count() . "\n";

echo "\n=== Daily Consumptions ===\n";
echo "Total consumptions: " . DB::table('daily_consumption_masters')->count() . "\n";