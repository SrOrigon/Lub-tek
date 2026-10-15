# Empacota alterações LUB-TEK para lubteksystem.com (ZIP com paths Unix /).
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$dest = Join-Path $root 'deploy\hostinger-sync'
$zipPath = Join-Path $root 'deploy\lubtek-hostinger-sync.zip'

$excludeNames = @('config.local.php', '.env', 'database.sqlite')
$excludeDirFragments = @('\uploads\', '\data\sessions\', '\.git\', '\deploy\', '\backups\')

$relativePaths = @(
    'api.php', 'index.php', 'login.php', 'login_process.php', 'db.php', 'config.php',
    'migrate_all_tenants.php', 'generate_orders.php', 'sw.js', 'service-worker.js',
    'AGENTS.md', '.htaccess', 'manifest.json', 'robots.txt', 'clear_session.php',
    'export_lubrication_plan.php', 'seed.php', 'sap_exporter.php'
)

$relativeDirs = @(
    'api', 'includes', 'assets\js', 'assets\css', 'assets\pwa', 'assets\img', 'migrations', 'scripts', 'databases'
)

function Should-SkipFile([string]$fullPath) {
    $name = [IO.Path]::GetFileName($fullPath)
    if ($excludeNames -contains $name) { return $true }
    if ($name -like '*.sqlite') { return $true }
    foreach ($frag in $excludeDirFragments) {
        if ($fullPath -like "*$frag*") { return $true }
    }
    return $false
}

if (Test-Path $dest) { Remove-Item -Recurse -Force $dest }
New-Item -ItemType Directory -Path $dest | Out-Null

$copied = 0
foreach ($rel in $relativePaths) {
    $src = Join-Path $root ($rel -replace '/', '\')
    if (-not (Test-Path $src)) { Write-Host "SKIP missing $rel"; continue }
    $target = Join-Path $dest ($rel -replace '/', '\')
    $dir = Split-Path $target -Parent
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    Copy-Item $src $target -Force
    $copied++
}

foreach ($dirRel in $relativeDirs) {
    $srcDir = Join-Path $root ($dirRel -replace '/', '\')
    if (-not (Test-Path $srcDir)) { continue }
    Get-ChildItem -Path $srcDir -Recurse -File | ForEach-Object {
        if (Should-SkipFile $_.FullName) { return }
        $rel = $_.FullName.Substring($root.Length + 1)
        $target = Join-Path $dest $rel
        $td = Split-Path $target -Parent
        if (-not (Test-Path $td)) { New-Item -ItemType Directory -Path $td -Force | Out-Null }
        Copy-Item $_.FullName $target -Force
        $copied++
    }
}

if (Test-Path $zipPath) { Remove-Item -Force $zipPath }
Push-Location $dest
try {
    # tar no Windows grava paths com / — compatível com Linux na Hostinger
    tar -a -c -f $zipPath *
} finally {
    Pop-Location
}

Write-Host "Pacote: $dest ($copied arquivos copiados)"
Write-Host "ZIP: $zipPath ($((Get-Item $zipPath).Length) bytes)"
