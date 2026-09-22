@echo off
setlocal
pushd "%~dp0.."
if exist ".tools\php\php.exe" (
    ".tools\php\php.exe" artisan serve --host=127.0.0.1 --port=8000
) else (
    php artisan serve --host=127.0.0.1 --port=8000
)
popd
endlocal
