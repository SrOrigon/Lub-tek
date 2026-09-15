# Inicia o proxy Headroom sob demanda (sem autostart).
# Uso:
#   .\scripts\start-headroom-proxy.ps1              # foreground (terminal aberto)
#   .\scripts\start-headroom-proxy.ps1 -Background  # background (sem janela)
#   .\scripts\start-headroom-proxy.ps1 -Status      # apenas verifica

param(
    [switch]$Background,
    [switch]$Status
)

$ErrorActionPreference = "Stop"
$port = 8787
$proxyUrl = "http://127.0.0.1:$port"
$logDir = Join-Path $env:USERPROFILE ".headroom"
$logFile = Join-Path $logDir "proxy.out.log"
$errFile = Join-Path $logDir "proxy.err.log"

$headroomCandidates = @(
    "$env:LOCALAPPDATA\Python\pythoncore-3.14-64\Scripts\headroom.exe",
    "$env:LOCALAPPDATA\Python\pythoncore-3.13-64\Scripts\headroom.exe",
    "$env:USERPROFILE\.local\bin\headroom.exe"
)

$headroom = $null
foreach ($candidate in $headroomCandidates) {
    if (Test-Path $candidate) {
        $headroom = $candidate
        break
    }
}

if (-not $headroom) {
    $found = Get-Command headroom -ErrorAction SilentlyContinue
    if ($found) { $headroom = $found.Source }
}

function Test-ProxyHealthy {
    try {
        $health = Invoke-RestMethod -Uri "$proxyUrl/health" -TimeoutSec 3
        return ($health.status -eq "healthy" -and $health.ready -eq $true)
    } catch {
        return $false
    }
}

if (-not $headroom) {
    Write-Host "Headroom nao encontrado. Instale com:" -ForegroundColor Red
    Write-Host '  pip install "headroom-ai[all]"' -ForegroundColor Yellow
    Write-Host '  ou: uv tool install --python 3.13 "headroom-ai[all]"' -ForegroundColor Yellow
    exit 1
}

if ($Status) {
    if (Test-ProxyHealthy) {
        Write-Host "Headroom proxy ativo em $proxyUrl" -ForegroundColor Green
        exit 0
    }
    Write-Host "Headroom proxy offline em $proxyUrl" -ForegroundColor Yellow
    exit 1
}

if (Test-ProxyHealthy) {
    Write-Host "Headroom proxy ja esta rodando em $proxyUrl" -ForegroundColor Green
    exit 0
}

$args = @("proxy", "--port", "$port", "--host", "127.0.0.1")

if ($Background) {
    New-Item -ItemType Directory -Force -Path $logDir | Out-Null
    Write-Host "Iniciando Headroom proxy em background ($proxyUrl)..." -ForegroundColor Cyan
    $proc = Start-Process -FilePath $headroom -ArgumentList $args -WindowStyle Hidden `
        -RedirectStandardOutput $logFile -RedirectStandardError $errFile -PassThru

    $deadline = (Get-Date).AddSeconds(20)
    while ((Get-Date) -lt $deadline) {
        Start-Sleep -Milliseconds 500
        if (Test-ProxyHealthy) {
            Write-Host "Headroom proxy ativo (pid $($proc.Id))" -ForegroundColor Green
            exit 0
        }
        if ($proc.HasExited) {
            Write-Error "Proxy encerrou (codigo $($proc.ExitCode)). Veja $logFile e $errFile"
        }
    }
    Write-Error "Proxy iniciou mas /health nao respondeu em 20s. Veja $logFile e $errFile"
}

Write-Host "Iniciando Headroom proxy em $proxyUrl ..." -ForegroundColor Cyan
Write-Host "Mantenha esta janela aberta. Ctrl+C para parar." -ForegroundColor DarkGray
& $headroom @args
