<#
.SYNOPSIS
    Gera um pacote .zip ultra-leve contendo 100% do código (PHP, JS, CSS, HTML, SVG, SQL, JSON)
    excluindo imagens binárias pesadas, vídeos, PDFs, caches e bancos de dados locais.
.DESCRIPTION
    Ideal para auditoria completa com agentes de IA (Gemini, Cursor, Claude, Jules)
    sem estourar limites de contexto ou tamanho de arquivo.
#>

$ErrorActionPreference = "Stop"
$rootDir = Split-Path -Parent $PSScriptRoot
$outputZip = Join-Path $rootDir "lubtek-code-review.zip"

Write-Host "Iniciando empacotamento ultra-leve de: $rootDir" -ForegroundColor Cyan

if (Test-Path $outputZip) {
    Remove-Item $outputZip -Force
    Write-Host "Arquivo zip anterior removido." -ForegroundColor Yellow
}

$tempDir = Join-Path ([System.IO.Path]::GetTempPath()) ("lubtek_export_" + [System.Guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Path $tempDir | Out-Null

try {
    # Lista de exclusões de pastas
    $excludeDirs = @(
        ".git",
        "node_modules",
        "vendor",
        "uploads",
        "storage",
        "backups",
        "data\sessions",
        "data\ai_cache",
        "banco de dados",
        "databases",
        "deploy\hostinger-sync",
        "deploy\hostinger-includes-fix",
        "deploy\lubteksystem-production",
        "docs\planos-referencia"
    )

    # Extensões excluídas (binários e mídias pesadas)
    $excludeExts = @(
        ".png",
        ".jpeg",
        ".jpg",
        ".pdf",
        ".mp4",
        ".zip",
        ".tar",
        ".gz",
        ".sqlite",
        ".sqlite-wal",
        ".sqlite-shm",
        ".sqlite-journal"
    )

    Get-ChildItem -Path $rootDir -Recurse | ForEach-Object {
        $item = $_
        $relPath = $item.FullName.Substring($rootDir.Length).TrimStart('\', '/')
        
        # Checa exclusao de pasta
        $skip = $false
        foreach ($d in $excludeDirs) {
            if ($relPath -eq $d -or $relPath.StartsWith("$d\") -or $relPath.StartsWith("$d/")) {
                $skip = $true
                break
            }
        }
        if ($skip) { return }

        if (-not $item.PSIsContainer) {
            # Checa extensao
            if ($excludeExts -contains $item.Extension.ToLower()) {
                return
            }

            $destPath = Join-Path $tempDir $relPath
            $destParent = Split-Path -Parent $destPath
            if (-not (Test-Path $destParent)) {
                New-Item -ItemType Directory -Path $destParent -Force | Out-Null
            }
            Copy-Item -Path $item.FullName -Destination $destPath -Force
        }
    }

    Write-Host "Compactando arquivos para: $outputZip..." -ForegroundColor Cyan
    Compress-Archive -Path "$tempDir\*" -DestinationPath $outputZip -CompressionLevel Optimal

    $zipSize = (Get-Item $outputZip).Length / 1MB
    Write-Host "Pacote criado com sucesso!" -ForegroundColor Green
    Write-Host ("Tamanho final: {0:N2} MB (100% codigo puro, sem binarios)" -f $zipSize) -ForegroundColor Green
    Write-Host "Pronto para enviar ao Gemini ou qualquer agente de IA." -ForegroundColor Cyan
}
finally {
    if (Test-Path $tempDir) {
        Remove-Item -Path $tempDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}
