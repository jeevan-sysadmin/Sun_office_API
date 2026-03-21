@echo off
title MySQL Database Auto-Backup Service
color 0A

echo ========================================
echo    MySQL Database Auto-Backup Service
echo    Started: %date% %time%
echo ========================================
echo.

REM Set paths
set PHP_PATH="C:\xampp\php\php.exe"
set BACKUP_SCRIPT="C:\xampp\htdocs\sun_office\backup\DatabaseBackup.php"
set LOG_FILE="E:\MySQL_Backups\startup_backup.log"

REM Check if PHP exists
if not exist %PHP_PATH% (
    echo ERROR: PHP not found at %PHP_PATH%
    echo Please check your PHP installation.
    pause
    exit /b 1
)

REM Check if backup script exists
if not exist %BACKUP_SCRIPT% (
    echo ERROR: Backup script not found at %BACKUP_SCRIPT%
    pause
    exit /b 1
)

echo [%date% %time%] Starting backup process... >> %LOG_FILE%

REM Wait for system to fully start (60 seconds)
echo Waiting for system services to initialize...
echo [%date% %time%] Waiting 60 seconds for services... >> %LOG_FILE%
timeout /t 60 /nobreak > nul

REM Check if drive E: is available
echo Checking backup drive...
if not exist E:\ (
    echo ERROR: Backup drive E: not available
    echo [%date% %time%] ERROR: Backup drive E: not available >> %LOG_FILE%
    pause
    exit /b 1
)

REM Run the backup
echo.
echo Running database backup...
echo [%date% %time%] Running backup command... >> %LOG_FILE%

%PHP_PATH% %BACKUP_SCRIPT% >> %LOG_FILE% 2>&1

REM Check result
if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo    BACKUP COMPLETED SUCCESSFULLY
    echo ========================================
    echo [%date% %time%] Backup completed successfully >> %LOG_FILE%
) else (
    echo.
    echo ========================================
    echo    BACKUP FAILED - Check log file
    echo ========================================
    echo [%date% %time%] Backup FAILED with error code %errorlevel% >> %LOG_FILE%
)

echo.
echo Log file: %LOG_FILE%
echo.
echo Press any key to exit...
pause > nul
exit /b %errorlevel%