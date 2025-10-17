# PowerShell setup script for Mocha Schools Web
# - Installs PHP dependencies in /api via Composer
# - Verifies vendor/autoload.php exists
# Usage:
#   Right-click > Run with PowerShell (or)
#   powershell -ExecutionPolicy Bypass -File .\scripts\setup.ps1

$ErrorActionPreference = 'Stop'

Write-Host "=== Mocha Schools Web :: Setup (Composer install) ===" -ForegroundColor Cyan

$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent $repoRoot
$apiDir = Join-Path $projectRoot 'api'

# Try to find composer
function Get-ComposerCmd {
  if (Get-Command composer -ErrorAction SilentlyContinue) { return 'composer' }
  $xamppPhp = 'C:\xampp\php\php.exe'
  $composerPhar = Join-Path $apiDir 'composer.phar'
  if (Test-Path $xamppPhp -PathType Leaf -ErrorAction SilentlyContinue -and (Test-Path $composerPhar -PathType Leaf -ErrorAction SilentlyContinue)) {
    return "$xamppPhp `"$composerPhar`""
  }
  return $null
}

$composerCmd = Get-ComposerCmd
if (-not $composerCmd) {
  Write-Warning "Composer not found. Install Composer for Windows and ensure php.exe is at C:\xampp\php\php.exe, or place composer.phar in /api." 
  Write-Host "Download: https://getcomposer.org/Composer-Setup.exe" -ForegroundColor Yellow
  exit 1
}

Push-Location $apiDir
try {
  Write-Host "Running: $composerCmd install" -ForegroundColor Green
  if ($composerCmd -eq 'composer') {
    composer install
  } else {
    & cmd /c $composerCmd install
  }
}
finally {
  Pop-Location
}

$vAutoload = Join-Path $apiDir 'vendor\autoload.php'
if (Test-Path $vAutoload -PathType Leaf) {
  Write-Host "OK: vendor/autoload.php exists" -ForegroundColor Green
  exit 0
} else {
  Write-Error "Missing vendor/autoload.php (composer install may have failed)."
  exit 2
}
