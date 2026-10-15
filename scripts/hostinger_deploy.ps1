# Padrao: so rebuild do espelho local (Hostinger local). Upload TUS/extracao e opcional.
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$buildScript = Join-Path $root 'scripts\build_hostinger_full_sync.ps1'
$zipPath = Join-Path $root 'deploy\lubtek-hostinger-sync.zip'
$tusScript = Join-Path $root 'deploy\tus_upload_file.ps1'
$applyLocal = Join-Path $root 'deploy\hostinger_apply_sync.php'
$sessionPath = Join-Path $root 'deploy\.tus_session.json'
$tokenPath = Join-Path $root 'deploy\.deploy_token'

& $buildScript

Write-Host ""
Write-Host "Pacote pronto: $zipPath"
Write-Host "Arvore: $(Join-Path $root 'deploy\hostinger-sync')"

if (-not (Test-Path $sessionPath)) {
    Write-Host ""
    Write-Host "Sem deploy\.tus_session.json — faca upload manual no hPanel (extrair na raiz de public_html)."
    Write-Host "Veja docs/HOSTINGER_SYNC.md"
    Start-Process explorer.exe (Split-Path $zipPath -Parent)
    exit 0
}

if (-not (Test-Path $applyLocal)) {
    throw "deploy\hostinger_apply_sync.php ausente. Gere novamente antes do upload automatizado."
}
if (-not (Test-Path $tokenPath)) {
    throw "deploy\.deploy_token ausente (token da extracao HTTP)."
}

& $tusScript -LocalPath $applyLocal -RemoteName 'hostinger_apply_sync.php' -SessionPath $sessionPath
& $tusScript -LocalPath $zipPath -RemoteName 'lubtek-hostinger-sync.zip' -SessionPath $sessionPath

$token = Get-Content -Raw -Path $tokenPath
$uri = "https://lubteksystem.com/hostinger_apply_sync.php?token=$token"
Write-Host "Extraindo no servidor: $uri"
$res = Invoke-RestMethod -Uri $uri -Method Get -TimeoutSec 120
$res | ConvertTo-Json -Depth 5
if (-not $res.success) { throw "Extracao falhou." }
Write-Host "Deploy concluido."
