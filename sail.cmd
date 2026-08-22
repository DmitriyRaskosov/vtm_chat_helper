@echo off
setlocal
cd /d "%~dp0"

if "%~1"=="" (
    docker compose exec laravel.test bash
    exit /b %ERRORLEVEL%
)

if /I "%~1"=="up" (
    shift
    docker compose up %*
    exit /b %ERRORLEVEL%
)

if /I "%~1"=="down" (
    docker compose down %2 %3 %4 %5
    exit /b %ERRORLEVEL%
)

if /I "%~1"=="build" (
    docker compose build %2 %3 %4 %5
    exit /b %ERRORLEVEL%
)

if /I "%~1"=="ps" (
    docker compose ps
    exit /b %ERRORLEVEL%
)

docker compose exec laravel.test %*
