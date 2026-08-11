# Vision Prime - install permanent Windows services via NSSM (run as Administrator).
#
#   powershell -NoProfile -ExecutionPolicy Bypass -File scripts/install-windows-services.ps1
#
# Services:
#   VisionPrimeQueueWorker  -> php artisan queue:work database --tries=3 --timeout=300
#   VisionPrimeScheduler    -> php artisan schedule:work (runs the daily gsc:import)
#
# Idempotent: existing services are updated, not duplicated.
# NOTE: keep this file ASCII-only (PowerShell 5.1 reads .ps1 as ANSI without BOM).

param(
    [string]$AppDir = "C:\Users\AMIRO\Documents\workspace-arena-fainal\vision-prime",
    [string]$PhpExe = "",
    [string]$Nssm = "C:\Windows\nssm.exe",
    [string]$LogDir = "C:\Users\AMIRO\Documents\workspace-arena-fainal\.freebuff"
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $Nssm)) { throw "nssm not found at $Nssm" }
if (-not $PhpExe) {
    $cmd = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($cmd) { $PhpExe = $cmd.Source } else { throw "php.exe not found. Pass -PhpExe <path>." }
}
if (-not (Test-Path $AppDir)) { throw "App dir not found: $AppDir" }
if (-not (Test-Path $LogDir)) { New-Item -ItemType Directory -Path $LogDir -Force | Out-Null }

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) { throw "Run this script as Administrator (NSSM installs services)." }

function Ensure-Service {
    param([string]$Name, [string]$AppParams, [string]$Stdout)
    $exists = Get-Service -Name $Name -ErrorAction SilentlyContinue
    if (-not $exists) {
        & $Nssm install $Name $PhpExe $AppParams | Out-Null
    }
    & $Nssm set $Name AppDirectory $AppDir | Out-Null
    & $Nssm set $Name AppParameters $AppParams | Out-Null
    & $Nssm set $Name Start SERVICE_AUTO_START | Out-Null
    & $Nssm set $Name AppStdout $Stdout | Out-Null
    & $Nssm set $Name AppStderr $Stdout | Out-Null
    & $Nssm set $Name AppRotateFiles 1 | Out-Null
    & $Nssm set $Name AppRotateBytes 10485760 | Out-Null
    & $Nssm set $Name AppExit Default Restart | Out-Null
    & $Nssm set $Name AppRestartDelay 5000 | Out-Null
    if ((Get-Service -Name $Name).Status -ne 'Running') {
        & $Nssm start $Name | Out-Null
    }
    $st = Get-Service -Name $Name | Select-Object -ExpandProperty Status
    Write-Host "OK: $Name -> $st"
}

Write-Host "PHP:      $PhpExe"
Write-Host "App dir:  $AppDir"

Ensure-Service -Name "VisionPrimeQueueWorker" `
    -AppParams "artisan queue:work database --tries=3 --timeout=300" `
    -Stdout "$LogDir\queue-worker.log"

Ensure-Service -Name "VisionPrimeScheduler" `
    -AppParams "artisan schedule:work" `
    -Stdout "$LogDir\scheduler.log"

Write-Host ""
Write-Host "Done. Verify with: Get-Service VisionPrime*"
