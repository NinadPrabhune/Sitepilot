<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Remove duplicate projects (IDs 4-6 are duplicates of 1-3 in workspace 1)
$duplicates = [4, 5, 6];
DB::table('user_projects')->whereIn('project_id', $duplicates)->delete();
DB::table('purchase_invoices')->whereIn('site_id', $duplicates)->delete();
DB::table('man_power_masters')->whereIn('site_id', $duplicates)->delete();
DB::table('daily_progress_reports')->whereIn('site_id', $duplicates)->delete();
DB::table('daily_consumption_masters')->whereIn('site_id', $duplicates)->delete();
DB::table('attendances')->whereIn('site_id', $duplicates)->delete();
DB::table('material_transfers')->whereIn('from_site_id', $duplicates)->delete();
DB::table('material_transfers')->whereIn('to_site_id', $duplicates)->delete();
DB::table('machineries')->whereIn('site_id', $duplicates)->delete();
DB::table('assets_tools_and_equipment')->whereIn('site_id', $duplicates)->delete();
DB::table('projects')->whereIn('id', $duplicates)->delete();

echo "Cleaned up duplicate projects (IDs 4-6).\n";

// Verify
echo "\n=== Work Spaces ===\n";
$ws = DB::table('work_spaces')->get();
foreach ($ws as $w) {
    echo "ID: {$w->id}, Name: {$w->name}, Slug: {$w->slug}\n";
}

echo "\n=== Projects (by workspace) ===\n";
$projects = DB::table('projects')->select('id','name','workspace')->orderBy('workspace')->get();
foreach ($projects as $p) {
    echo "ID: {$p->id}, Name: {$p->name}, Workspace: {$p->workspace}\n";
}

echo "\n=== User-Project Mappings ===\n";
$up = DB::table('user_projects')->get();
echo "Total mappings: " . count($up) . "\n";

echo "\n=== Summary Counts ===\n";
echo "Machinery: " . DB::table('machineries')->count() . "\n";
echo "Purchase Invoices: " . DB::table('purchase_invoices')->count() . "\n";
echo "ManPower Masters: " . DB::table('man_power_masters')->count() . "\n";
echo "Attendance: " . DB::table('attendances')->count() . "\n";
echo "Material Transfers: " . DB::table('material_transfers')->count() . "\n";
echo "DPRs: " . DB::table('daily_progress_reports')->count() . "\n";
echo "Consumptions: " . DB::table('daily_consumption_masters')->count() . "\n";