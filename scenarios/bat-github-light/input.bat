@echo off
REM Build script for the project
setlocal enabledelayedexpansion

set "PROJECT=demo"
set /a COUNT=0

if "%1"=="" (
    echo Usage: build.bat ^<target^>
    exit /b 1
)

for %%f in (*.txt) do (
    set /a COUNT+=1
    echo Processing %%f [!COUNT!]
)

:loop
if %COUNT% gtr 0 (
    set /a COUNT-=1
    goto loop
)

echo Done building %PROJECT%
endlocal
