param(
    [Parameter(Mandatory = $true)][string]$LocalPath,
    [Parameter(Mandatory = $true)][string]$RemoteName,
    [Parameter(Mandatory = $true)][string]$SessionPath
)
$ErrorActionPreference = 'Stop'
if (-not (Test-Path $LocalPath)) { throw "Arquivo local ausente: $LocalPath" }
$s = Get-Content -Raw -Path $SessionPath | ConvertFrom-Json
$size = (Get-Item -LiteralPath $LocalPath).Length
$baseUrl = if ($s.tus_base_url) { $s.tus_base_url } else { $s.url }
if (-not $baseUrl) { throw "Nenhum tus_base_url ou url encontrado em $SessionPath" }
$uri = ($baseUrl.TrimEnd('/') + '/' + $RemoteName + '?override=true')
$headers = @{
    'X-Auth' = $s.auth_key
    'X-Auth-Rest' = $s.rest_auth_key
    'Tus-Resumable' = '1.0.0'
}
Write-Host "TUS POST $RemoteName ($size bytes)"
$postHeaders = @{
    'X-Auth'       = [string]$s.auth_key
    'X-Auth-Rest'  = [string]$s.rest_auth_key
    'Tus-Resumable' = '1.0.0'
    'Upload-Length' = [string]$size
    'Upload-Offset' = '0'
}
try {
    $post = Invoke-WebRequest -Method POST -Uri $uri -Headers $postHeaders -UseBasicParsing
} catch {
    $post = $_.Exception.Response
    if ($null -eq $post) { throw $_ }
}
$postCode = [int]$post.StatusCode
if ($postCode -ne 201) {
    throw "TUS POST falhou para $RemoteName (HTTP $postCode)"
}
Write-Host "TUS PATCH $RemoteName"
$bytes = [System.IO.File]::ReadAllBytes($LocalPath)
$patchHeaders = @{
    'X-Auth'        = [string]$s.auth_key
    'X-Auth-Rest'   = [string]$s.rest_auth_key
    'Tus-Resumable' = '1.0.0'
    'Content-Type'  = 'application/offset+octet-stream'
    'Upload-Offset' = '0'
}
try {
    $patch = Invoke-WebRequest -Method PATCH -Uri $uri -Headers $patchHeaders -Body $bytes -UseBasicParsing
    $patchCode = [int]$patch.StatusCode
} catch {
    $resp = $_.Exception.Response
    if ($null -eq $resp) { throw $_ }
    $patchCode = [int]$resp.StatusCode
}
if ($patchCode -ne 204) {
    throw "TUS PATCH falhou para $RemoteName (HTTP $patchCode)"
}
Write-Host "OK $RemoteName"
