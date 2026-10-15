# Deploy automatizado para Hostinger (lubteksystem.com)
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
    Write-Host "Sem deploy\.tus_session.json. Faca upload manual no hPanel."
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

$token = (Get-Content -Raw -Path $tokenPath).Trim()
$uri = "https://lubteksystem.com/hostinger_apply_sync.php?token=$token"
Write-Host "Extraindo no servidor: $uri"

try {
    $res = Invoke-WebRequest -Uri $uri -Method Get -TimeoutSec 120 -UseBasicParsing
    Write-Host $res.Content
    Write-Host "Deploy concluido com sucesso!"
} catch {
    $stream = $_.Exception.Response.GetResponseStream()
    if ($stream) {
        $reader = New-Object System.IO.StreamReader($stream)
        Write-Host "Erro do servidor: " ($reader.ReadToEnd())
    } else {
        Write-Host "Erro: " $_.Exception.Message
    }
    throw $_
}
