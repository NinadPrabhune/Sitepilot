<?php
echo "=== RELATIONSHIP HANDLING ANALYSIS ===\n";

echo "\n--- STORE METHOD (CREATE) ---\n";
echo "1. Delegates consumption creation to DailyConsumptionController@store\n";
echo "2. Passes consumption data array with daily_progress_report_id\n";
echo "3. DailyConsumptionController handles both master and details creation\n";
echo "4. Uses external controller - less direct control\n";

echo "\n--- UPDATE METHOD ---\n";
echo "1. Directly creates/updates DailyConsumptionMaster\n";
echo "2. Sets daily_progress_report_id explicitly: 'daily_progress_report_id' => \$report->id\n";
echo "3. Directly handles details creation: \$master->details()->create([...])\n";
echo "4. More direct control over relationships\n";

echo "\n--- IDENTIFIED ISSUES ---\n";
echo "1. STORE METHOD: Relies on external controller - potential relationship issues\n";
echo "2. UPDATE METHOD: Direct control - relationships should work properly\n";
echo "3. Both methods handle rental machinery differently\n";
echo "4. File handling uses different approaches\n";

echo "\n--- POTENTIAL FIXES ---\n";
echo "1. Make STORE method more like UPDATE method for consistency\n";
echo "2. Ensure DailyConsumptionController properly sets relationships\n";
echo "3. Add debugging to DailyConsumptionController\n";
echo "4. Standardize relationship handling between both methods\n";

echo "\n=== ANALYSIS COMPLETE ===\n";
