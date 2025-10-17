<#
 PowerShell DB import script for Mocha Schools Web
 - Imports database.sql into local MySQL (XAMPP or standard MySQL install)
 Usage examples:
   powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\import-db.ps1 -Server localhost -User root -Password "" -SqlFile .\database.sql
   powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\import-db.ps1 -User root -SqlFile .\database.sql -MySqlExe "C:\Tools\mysql\bin\mysql.exe"
#>

param(
  [string]$Server = 'localhost',
  [int]$Port = 3306,
  [string]$User = 'root',
  [string]$Password = '',
  [string]$Database = '',   # Optional: not required when SQL does CREATE/USE
  [string]$SqlFile = '',
  [string]$MySqlExe = ''
)

$ErrorActionPreference = 'Stop'

Write-Host "=== Mocha Schools Web :: Import database.sql ===" -ForegroundColor Cyan

$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path  # .../scripts
$projectRoot = Split-Path -Parent $repoRoot                  # repo root

# Resolve SQL file
if ([string]::IsNullOrWhiteSpace($SqlFile)) {
  $SqlFile = Join-Path $projectRoot 'database.sql'
} else {
  # Try provided path first; if relative and not found, try relative to repo root
  if (-not (Test-Path -LiteralPath $SqlFile)) {
    $alt = Join-Path $projectRoot $SqlFile
    if (Test-Path -LiteralPath $alt) { $SqlFile = $alt }
  }
}

if (-not (Test-Path -LiteralPath $SqlFile)) {
  Write-Error "database.sql not found at '$SqlFile'"
  exit 1
}

# Discover mysql.exe if not provided
function Find-MySqlExe {
  param([string]$Hint)
  if ($Hint -and (Test-Path -LiteralPath $Hint)) { return (Resolve-Path -LiteralPath $Hint).Path }
  $candidates = @(
    'C:\xampp\mysql\bin\mysql.exe',
    "$env:ProgramFiles\MySQL\MySQL Server 8.0\bin\mysql.exe",
    "$env:ProgramFiles\MySQL\MySQL Server 5.7\bin\mysql.exe",
    "$env:ProgramFiles(x86)\MySQL\MySQL Server 5.7\bin\mysql.exe"
  )
  foreach ($c in $candidates) { if (Test-Path -LiteralPath $c) { return $c } }
  try {
    $cmd = Get-Command mysql.exe -ErrorAction Stop
    if ($cmd -and $cmd.Source) { return $cmd.Source }
  } catch {}
  return $null
}

if ([string]::IsNullOrWhiteSpace($MySqlExe)) { $MySqlExe = Find-MySqlExe } else { $MySqlExe = (Resolve-Path -LiteralPath $MySqlExe).Path }

if (-not $MySqlExe -or -not (Test-Path -LiteralPath $MySqlExe)) {
  Write-Error "MySQL client (mysql.exe) not found. Checked XAMPP and PATH. You can pass -MySqlExe to specify it explicitly."
  exit 2
}

# Build argument list (avoid cmd redirection; use mysql -e "SOURCE ...")
$argsList = @('-h', $Server, '-P', $Port, '-u', $User)
if ($Password -ne '') { $argsList += "-p$Password" }

# Note: We intentionally do not set -D $Database because the schema file creates/uses the database.
$sourceCmd = "SOURCE `"$SqlFile`""
$argsList += @('-e', $sourceCmd)

# Log sanitized command for troubleshooting (mask password if present)
$maskPwd = if ($Password -ne '') { '-p****' } else { '' }
$displayArgs = @('-h', $Server, '-P', $Port, '-u', $User)
if ($maskPwd) { $displayArgs += $maskPwd }
$displayArgs += @('-e', $sourceCmd)
Write-Host ("mysql.exe " + ($displayArgs -join ' ')) -ForegroundColor DarkGray

try {
  & $MySqlExe @argsList
  $exit = $LASTEXITCODE
} catch {
  Write-Error $_
  $exit = 1
}

if ($exit -eq 0) {
  Write-Host "OK: database imported successfully." -ForegroundColor Green
  exit 0
} else {
  Write-Error "Import failed with exit code $exit"
  exit $exit
}
