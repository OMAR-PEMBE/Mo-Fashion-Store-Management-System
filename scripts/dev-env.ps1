# Dot-source this script to set tool paths for this PowerShell session only.
$taskProjectRoot = Split-Path -Parent $PSScriptRoot
$taskPhpDirectory = Join-Path $taskProjectRoot '.tools/php'
$taskNodeDirectory = Get-ChildItem -LiteralPath (Join-Path $taskProjectRoot '.tools/node') -Directory -ErrorAction SilentlyContinue | Select-Object -First 1
if (Test-Path -LiteralPath (Join-Path $taskPhpDirectory 'php.exe')) {
    $env:Path = $taskPhpDirectory + ';' + $env:Path
}
if ($taskNodeDirectory) {
    $env:Path = $taskNodeDirectory.FullName + ';' + $env:Path
}
if (Test-Path -LiteralPath (Join-Path $taskProjectRoot '.tools/composer.phar')) {
    $env:MFBMS_COMPOSER = Join-Path $taskProjectRoot '.tools/composer.phar'
    function global:composer { & php $env:MFBMS_COMPOSER @args }
}
Write-Host 'MFBMS development tools are available in this terminal session.'
