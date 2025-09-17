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

    REM Wait a moment for container to start
    echo [INFO] Waiting for container to start...
    timeout /t 3 /nobreak > nul

    REM Check if container is now running
    docker ps | findstr redis-smp > nul
    if %errorlevel% equ 0 (
        echo [SUCCESS] Redis container created and started successfully!
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
timeout /t 2 /nobreak > nul
docker exec redis-smp redis-cli ping 2>nul

if %errorlevel% equ 0 (
    echo.
    echo [SUCCESS] Redis is ready for your Social Media Platform!
    echo [INFO] Redis is now running on localhost:6379
    echo [INFO] Container will auto-restart when Docker starts
    echo.
    echo [NEXT] You can now run: php artisan serve
) else (
    echo [WARNING] Redis container is running but connection test failed.
    echo [INFO] This is normal for new containers - Redis might still be starting.
)

echo.
echo Press any key to continue...
pause > nul
