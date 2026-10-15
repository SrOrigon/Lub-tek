param(
    [Parameter(Mandatory = $true)][string]$CredsFile,
    [Parameter(Mandatory = $true, ValueFromRemainingArguments = $true)][string[]]$Files
)
$ErrorActionPreference = 'Stop'
$c = Get-Content $CredsFile -Raw | ConvertFrom-Json
foreach ($localPath in $Files) {
    if (-not (Test-Path $localPath)) {
        Write-Host "SKIP missing $localPath"
        continue
    }
    $root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
    $rel = (Resolve-Path $localPath).Path.Substring($root.Length + 1) -replace '\\', '/'
    $c.remote_name = $rel
    $c | ConvertTo-Json | Set-Content $CredsFile
    & (Join-Path $PSScriptRoot 'tus_upload.ps1') -FilePath $localPath -CredsFile $CredsFile
    Write-Host "OK $rel"
}
