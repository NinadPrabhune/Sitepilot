@echo off
cd /d c:\wamp64\www\SitePilot
php artisan tinker --execute="include 'tinker_scenario8.php'" 2>&1
pause
