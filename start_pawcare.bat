@echo off
setlocal

title PAW Care - Pet Adoption and Rescue System

echo ==============================================
echo        PAW CARE - SYSTEM STARTUP
echo ==============================================
echo.

REM ==============================================
REM PROJECT FOLDER
REM ==============================================

cd /d "C:\xampp\htdocs\pet_adoption_system"

if errorlevel 1 (
    echo ERROR: Cannot open PAW Care project folder.
    pause
    exit /b 1
)

echo Project folder:
echo %CD%
echo.


REM ==============================================
REM CHECK PYTHON VIRTUAL ENVIRONMENT
REM ==============================================

echo [1/4] Checking Python environment...

if not exist ".venv\Scripts\python.exe" (

    echo.
    echo WARNING: Python virtual environment was not found.
    echo.
    echo Expected location:
    echo C:\xampp\htdocs\pet_adoption_system\.venv\
    echo.

    echo Creating Python virtual environment...
    echo.

    python -m venv .venv

    if errorlevel 1 (
        echo.
        echo ERROR: Unable to create Python virtual environment.
        echo.
        echo Make sure Python is installed and added to PATH.
        echo.
        pause
        exit /b 1
    )

    echo.
    echo Python virtual environment created successfully.
    echo.
)

echo Python environment OK.
echo.


REM ==============================================
REM CHECK TRAIN MODEL
REM ==============================================

echo [2/4] Checking Pet Matching Model...

if not exist "train_model.py" (
    echo.
    echo ERROR: train_model.py was not found.
    echo.
    pause
    exit /b 1
)

echo train_model.py found.
echo.


REM ==============================================
REM CHECK ML API
REM ==============================================

echo [3/4] Checking Pet AI API...

if not exist "predict_api.py" (
    echo.
    echo ERROR: predict_api.py was not found.
    echo.
    pause
    exit /b 1
)

echo predict_api.py found.
echo.


REM ==============================================
REM START TRAINING
REM ==============================================

echo Starting Pet Matching Model...

start "PAW CARE - TRAIN MODEL" cmd /k "cd /d C:\xampp\htdocs\pet_adoption_system && .venv\Scripts\python.exe train_model.py"

timeout /t 3 /nobreak >nul


REM ==============================================
REM START ML API
REM ==============================================

echo Starting Pet AI API...

start "PAW CARE - ML API" cmd /k "cd /d C:\xampp\htdocs\pet_adoption_system && .venv\Scripts\python.exe predict_api.py"

timeout /t 5 /nobreak >nul


REM ==============================================
REM OPEN WEBSITE
REM ==============================================

echo.
echo [4/4] Opening PAW Care website...
echo.

start "" "http://localhost/pet_adoption_system/index.php"


echo.
echo ==============================================
echo           PAW CARE IS RUNNING
echo ==============================================
echo.
echo Website:
echo http://localhost/pet_adoption_system/
echo.
echo Pet AI:
echo train_model.py
echo predict_api.py
echo.
echo Keep the AI terminal windows open.
echo.
pause

endlocal