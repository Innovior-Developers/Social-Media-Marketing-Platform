@echo off
echo =========================================
echo   Social Media Marketing Platform
echo   Redis Container Manager
echo   Developer: J33WAKASUPUN
echo   Date: 2025-08-22
echo =========================================
echo.

echo [INFO] Starting Redis for Social Media Platform...

REM Check if container exists and is stopped
echo [INFO] Checking Redis container status...
docker start redis-smp 2>nul

REM If container doesn't exist, create it
if %errorlevel% neq 0 (
    echo [INFO] Redis container not found. Creating new container...
    docker run --name redis-smp -p 6379:6379 -d --restart unless-stopped redis:latest
    if %errorlevel% equ 0 (
        echo [SUCCESS] Redis container created successfully!
    ) else (
        echo [ERROR] Failed to create Redis container!
        echo [INFO] Make sure Docker Desktop is running.
        pause
        exit /b 1
    )
) else (
    echo [SUCCESS] Redis container started successfully!
)

echo.
echo [INFO] Testing Redis connection...
docker exec -it redis-smp redis-cli ping

if %errorlevel% equ 0 (
    echo.
    echo [SUCCESS] Redis is ready for your Social Media Platform!
    echo [INFO] Redis is now running on localhost:6379
    echo [INFO] Container will auto-restart when Docker starts
    echo.
    echo [NEXT] You can now run: php artisan serve
) else (
    echo [ERROR] Redis connection test failed!
)

echo.
echo Press any key to continue...
pause > nul