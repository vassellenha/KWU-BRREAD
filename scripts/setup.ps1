<#
    One-command local setup for Windows PowerShell.
    Run from the project root:  powershell -ExecutionPolicy Bypass -File scripts\setup.ps1
#>

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)

function Assert-Tool([string]$Name, [string]$Hint) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        Write-Host "Missing '$Name'. $Hint" -ForegroundColor Red
        exit 1
    }
}

function Invoke-Step([string]$Label, [scriptblock]$Command) {
    Write-Host "==> $Label" -ForegroundColor Magenta
    & $Command
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Step failed: $Label" -ForegroundColor Red
        exit $LASTEXITCODE
    }
}

Assert-Tool 'php'      'Install PHP 8.3 with the sqlite, zip and gd extensions.'
Assert-Tool 'composer' 'Install Composer 2 from getcomposer.org.'
Assert-Tool 'node'     'Install Node.js 22.'

Invoke-Step 'Installing PHP packages'  { composer install }
Invoke-Step 'Installing Node packages' { npm install }

if (-not (Test-Path '.env')) {
    Copy-Item '.env.example' '.env'
    Write-Host 'Created .env from .env.example'
}

Invoke-Step 'Generating the app key' { php artisan key:generate }

if (-not (Test-Path 'database/database.sqlite')) {
    New-Item -ItemType File -Path 'database/database.sqlite' | Out-Null
}

Invoke-Step 'Migrating and seeding the database' { php artisan migrate --seed }
Invoke-Step 'Building the assets'                { npm run build }

Write-Host ''
Write-Host 'Done. Start the app with:  php artisan serve' -ForegroundColor Green
Write-Host 'Storefront  http://127.0.0.1:8000'
Write-Host 'Staff       http://127.0.0.1:8000/staff'
Write-Host 'Superadmin  http://127.0.0.1:8000/superadmin'
