@echo off
rem Local test helper: builds the site and starts it, keeping the panel install between builds.
rem Run from the project folder:  dev.bat
if exist dist\cms-api\config.local.php (
  if not exist .devkeep mkdir .devkeep
  copy /y dist\cms-api\config.local.php .devkeep\ >nul
  if exist dist\cms-api\data xcopy /e /i /y dist\cms-api\data .devkeep\data >nul
  if exist dist\cms-api\uploads xcopy /e /i /y dist\cms-api\uploads .devkeep\uploads >nul
)
call npm run build
if errorlevel 1 exit /b 1
if exist .devkeep\config.local.php (
  copy /y .devkeep\config.local.php dist\cms-api\ >nul
  if exist .devkeep\data xcopy /e /i /y .devkeep\data dist\cms-api\data >nul
  if exist .devkeep\uploads xcopy /e /i /y .devkeep\uploads dist\cms-api\uploads >nul
)
echo.
echo Site and panel:  http://localhost:8080/   http://localhost:8080/panel/
echo First time only:  http://localhost:8080/cms-api/install.php
C:\xampp\php\php.exe -S localhost:8080 -t dist
