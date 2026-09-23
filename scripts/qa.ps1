[CmdletBinding()]
param(
    [string]$PhpPath,
    [string]$NodeDirectory,
    [switch]$SkipMySql
)
$ErrorActionPreference = 'Stop'
$workspace = Split-Path -Parent $PSScriptRoot
$originalPath = $env:PATH
Push-Location -LiteralPath $workspace
try {
    if (Test-Path -LiteralPath 'bootstrap/cache/config.php') {
        throw 'Cached configuration exists. Verify this is a development checkout, run artisan config:clear, and retry. Tests must never use production configuration.'
    }
    if (-not $PhpPath) {
        $bundledPhp = Join-Path $workspace '.tools/php/php.exe'
        $PhpPath = if (Test-Path -LiteralPath $bundledPhp) { $bundledPhp } else { (Get-Command php -ErrorAction Stop).Source }
    }
    if (-not $NodeDirectory) {
        $bundledNode = Join-Path $workspace '.tools/node/node-v24.21.0-win-x64'
        $NodeDirectory = if (Test-Path -LiteralPath $bundledNode) { $bundledNode } else { Split-Path -Parent (Get-Command node -ErrorAction Stop).Source }
    }
    $env:PATH = $NodeDirectory + ';' + (Split-Path -Parent $PhpPath) + ';' + $env:PATH
    $npm = (Get-Command npm.cmd -ErrorAction Stop).Source
    function Invoke-QaStep {
        param([string]$Label, [string]$Executable, [string[]]$Arguments)
        Write-Host "QA: $Label"
        & $Executable @Arguments
        if ($LASTEXITCODE -ne 0) { throw "$Label failed (exit $LASTEXITCODE)." }
    }
    Invoke-QaStep 'PHP formatting' $PhpPath @('vendor/bin/pint', '--test')
    Invoke-QaStep 'Unit and feature tests' $PhpPath @('vendor/bin/phpunit', '--no-progress')
    if (-not $SkipMySql) {
        Invoke-QaStep 'Dedicated MySQL tests' $PhpPath @('vendor/bin/phpunit', '--configuration', 'phpunit.mysql.xml', '--no-progress')
    } else {
        Write-Warning 'MySQL tests explicitly skipped; this is not a complete QA run.'
    }
    Invoke-QaStep 'Production assets' $npm @('run', 'build')
    Invoke-QaStep 'Blade compilation' $PhpPath @('artisan', 'view:cache')
    try { Invoke-QaStep 'Route cache' $PhpPath @('artisan', 'route:cache') }
    finally { Invoke-QaStep 'Route cache cleanup' $PhpPath @('artisan', 'route:clear') }
    Invoke-QaStep 'Whitespace check' 'git' @('diff', '--check')
    Write-Host 'Selected automated QA checks passed. Browser checks and actual staff acceptance are separate.'
} finally {
    $env:PATH = $originalPath
    Pop-Location
}