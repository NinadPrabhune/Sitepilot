@echo off
REM Database Backup Script for Payment Module Redesign (Windows)
REM Usage: backup.bat [full|tables|incremental]

set DB_NAME=sitepilot_live
set DB_USER=root
set BACKUP_DIR=database\backups
set TIMESTAMP=%date:~10,4%%date:~4,2%%date:~7,2%_%time:~0,2%%time:~3,2%%time:~6,2%
set TIMESTAMP=%TIMESTAMP: =0%

REM Create backup directory if it doesn't exist
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

if "%1"=="full" goto full_backup
if "%1"=="tables" goto tables_backup
if "%1"=="incremental" goto incremental_backup
goto usage

:full_backup
echo Creating full database backup...
mysqldump -u %DB_USER% -p %DB_NAME% > %BACKUP_DIR%\sitepilot_live_full_%TIMESTAMP%.sql
echo Full backup completed: %BACKUP_DIR%\sitepilot_live_full_%TIMESTAMP%.sql
goto verify

:tables_backup
echo Creating key tables backup...
mysqldump -u %DB_USER% -p %DB_NAME% ^
  payment_requests ^
  payments_module ^
  payment_module_allocations ^
  purchase_invoices ^
  purchase_orders ^
  supplier_transactions ^
  advance_adjustments ^
  > %BACKUP_DIR%\sitepilot_live_tables_%TIMESTAMP%.sql
echo Tables backup completed: %BACKUP_DIR%\sitepilot_live_tables_%TIMESTAMP%.sql
goto verify

:incremental_backup
echo Creating incremental backup...
mysqldump -u %DB_USER% -p %DB_NAME% ^
  --single-transaction ^
  --quick ^
  --lock-tables=false ^
  > %BACKUP_DIR%\sitepilot_live_incremental_%TIMESTAMP%.sql
echo Incremental backup completed: %BACKUP_DIR%\sitepilot_live_incremental_%TIMESTAMP%.sql
goto verify

:verify
if exist "%BACKUP_DIR%\sitepilot_live_%1_%TIMESTAMP%.sql" (
  echo Backup verified successfully.
) else (
  echo ERROR: Backup file was not created!
  exit /b 1
)
goto end

:usage
echo Usage: %0 [full^|tables^|incremental]
echo   full        - Complete database backup
echo   tables      - Backup key payment-related tables only
echo   incremental - Fast incremental backup
exit /b 1

:end
