# ZIP só includes/ + arquivos críticos na raiz (hotfix deploy)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$dest = Join-Path $root 'deploy\hostinger-includes-fix'
$zipPath = Join-Path $root 'deploy\lubtek-includes-fix.zip'

if (Test-Path $dest) { Remove-Item -Recurse -Force $dest }
New-Item -ItemType Directory -Path $dest | Out-Null

$dirs = @('includes', 'api', 'assets\js', 'assets\css', 'migrations')
foreach ($dirRel in $dirs) {
    $srcDir = Join-Path $root ($dirRel -replace '/', '\')
    if (-not (Test-Path $srcDir)) { continue }
    Get-ChildItem -Path $srcDir -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($root.Length + 1)
        $target = Join-Path $dest $rel
        $td = Split-Path $target -Parent
        if (-not (Test-Path $td)) { New-Item -ItemType Directory -Path $td -Force | Out-Null }
        Copy-Item $_.FullName $target -Force
    }
}
foreach ($f in @('login.php', 'login_process.php', 'index.php', 'api.php', 'db.php', 'config.php', 'sw.js', 'service-worker.js')) {
    $src = Join-Path $root $f
    if (Test-Path $src) { Copy-Item $src (Join-Path $dest $f) -Force }
}

if (Test-Path $zipPath) { Remove-Item -Force $zipPath }
Push-Location $dest
try {
    tar -a -c -f $zipPath *
} finally {
    Pop-Location
}
Write-Host "ZIP: $zipPath ($((Get-Item $zipPath).Length) bytes)"
