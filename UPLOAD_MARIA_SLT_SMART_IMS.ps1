# ==============================================================================
# 1-CLICK GITHUB UPLOAD FOR MARIA (mariaslt999@gmail.com)
# Project: WP plugin SLT SMART IMS | Developer: Shahzad Smart Life Teck
# ==============================================================================
$ErrorActionPreference = "Continue"
Write-Host "=== SLT SMART IMS - MARIA (mariaslt999@gmail.com) GITHUB UPLOADER ===" -ForegroundColor Cyan

$PluginDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location $PluginDir

if (-not (Get-Command git -ErrorAction SilentlyContinue)) { winget install --id Git.Git -e --accept-package-agreements --accept-source-agreements }
if (-not (Get-Command gh -ErrorAction SilentlyContinue))  { winget install --id GitHub.cli -e --accept-package-agreements --accept-source-agreements }
if (-not (Get-Command code -ErrorAction SilentlyContinue)){ winget install --id Microsoft.VisualStudioCode -e --accept-package-agreements --accept-source-agreements }

$env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")

# Clear old cached credentials and set Maria's Git identity
cmdkey /delete:LegacyGeneric:target=git:https://github.com 2>$null
gh auth logout -h github.com 2>$null

git config --global user.name "Maria Noor (Smart Life Teck)"
git config --global user.email "mariaslt999@gmail.com"
git config --global init.defaultBranch main

Write-Host "Browser khul raha hai — mariaslt999@gmail.com wale GitHub account se Login & Authorize karein..." -ForegroundColor Yellow
gh auth login -h github.com -p https --web

if (Test-Path ".git") { Remove-Item -Recurse -Force ".git" }
git init -b main
git add slt-smart-ims.php SLT_Google_Sheets_TwoWay_Sync.gs README.md .gitignore
git commit -m "Initial Open-Source Release: WP plugin SLT SMART IMS (Developer Shahzad Smart Life Teck)"

$RepoName = "slt-smart-ims"
gh repo create $RepoName --public --description "WP plugin SLT SMART IMS - Open-Source WooCommerce & Google Sheets Inventory Management System by Developer Shahzad Smart Life Teck" --source=. --remote=origin --push

$GhUser = (gh api /user --jq .login).Trim()
if ($GhUser) {
    gh api -X PUT "/user/starred/$GhUser/$RepoName" | Out-Null
    Start-Process "https://github.com/$GhUser/$RepoName"
}

Write-Host "Mubarak ho! SLT SMART IMS Open-Source Repository ($RepoName) mariaslt999@gmail.com par live ho gayi!" -ForegroundColor Green
