@echo off
REM Auto-start script for Sales and Procurement Management System
cd /d "%~dp0"

echo ===================================================
echo     Sales and Procurement Management System Launcher
echo ===================================================
echo.

REM 1. Check & Start MySQL
tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | find /I "mysqld.exe" >nul
if %ERRORLEVEL% equ 0 (
    echo [OK] MySQL is already running.
) else (
    echo [*] MySQL is not running. Attempting to start MySQL from XAMPP...
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        start "XAMPP MySQL" /b "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
        timeout /t 3 >nul
        echo [OK] MySQL started successfully.
    ) else (
        echo [WARNING] C:\xampp\mysql\bin\mysqld.exe not found.
        echo Please ensure MySQL is running via XAMPP Control Panel.
    )
)

REM 2. Find PHP executable
set "PHP_CMD=php"
where php >nul 2>nul
if %ERRORLEVEL% neq 0 (
    if exist "C:\xampp\php\php.exe" (
        set "PHP_CMD=C:\xampp\php\php.exe"
    ) else (
        echo [ERROR] PHP executable not found. Please install PHP or XAMPP.
        pause
        exit /b 1
    )
)

REM 3. Check if server already running on port 8000
netstat -ano | findstr :8000 | findstr LISTENING >nul
if %ERRORLEVEL% equ 0 (
    echo [OK] Web server is already running on http://localhost:8000
) else (
    echo [*] Starting PHP development server on http://localhost:8000 ...
    start "Sales and Procurement Server" cmd /k ""%PHP_CMD%" -S localhost:8000 router.php"
    timeout /t 2 >nul
    echo [OK] Web server started!
)

echo.
echo Opening browser to http://localhost:8000 ...
start http://localhost:8000

echo.
echo System is ready!
echo ---------------------------------------------------
echo  URL:      http://localhost:8000
echo  Admin:    admin / admin123
echo ---------------------------------------------------
echo (You can close this window now)
pause


