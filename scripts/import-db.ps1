# PowerShell DB import script for Mocha Schools Web
# - Imports database.sql into local MySQL (XAMPP)
# Usage:
#   powershell -ExecutionPolicy Bypass -File .\scripts\import-db.ps1 [-User root] [-Password ""]

param(
  [string]$User = 'root',
  [string]$Password = ''
)

$ErrorActionPreference = 'Stop'

Write-Host "=== Mocha Schools Web :: Import database.sql ===" -ForegroundColor Cyan

$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent $repoRoot
$sqlFile = Join-Path $projectRoot 'database.sql'
$mysqlExe = 'C:\xampp\mysql\bin\mysql.exe'

if (-not (Test-Path $sqlFile)) { Write-Error "database.sql not found at $sqlFile"; exit 1 }
if (-not (Test-Path $mysqlExe)) { Write-Error "MySQL not found at $mysqlExe (start XAMPP and verify path)"; exit 2 }

# Build command
# Use cmd.exe for input redirection in PowerShell
if ($Password -eq '') {
  & cmd /c "\"$mysqlExe\" -u $User < \"$sqlFile\""
} else {
  & cmd /c "\"$mysqlExe\" -u $User -p$Password < \"$sqlFile\""
}

if ($LASTEXITCODE -eq 0) {
  Write-Host "OK: database imported successfully." -ForegroundColor Green
  exit 0
} else {
  Write-Error "Import failed with exit code $LASTEXITCODE"
  exit $LASTEXITCODE
}
