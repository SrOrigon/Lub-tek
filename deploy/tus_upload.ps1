param(
    [Parameter(Mandatory = $true)][string]$FilePath,
    [string]$CredsFile = '',
    [string]$TusBaseUrl = '',
    [string]$AuthKey = '',
    [string]$RestAuthKey = '',
    [string]$RemoteName = 'lubtek-hostinger-sync.zip'
)
$ErrorActionPreference = 'Stop'
if ($CredsFile -ne '' -and (Test-Path $CredsFile)) {
    $c = Get-Content $CredsFile -Raw | ConvertFrom-Json
    $TusBaseUrl = $c.tus_base_url
    $AuthKey = $c.auth_key
    $RestAuthKey = $c.rest_auth_key
    if ($c.remote_name) { $RemoteName = $c.remote_name }
}
if (-not $TusBaseUrl -or -not $AuthKey -or -not $RestAuthKey) {
    throw 'Informe -CredsFile ou TusBaseUrl + AuthKey + RestAuthKey'
}
$size = (Get-Item $FilePath).Length
$uploadUrl = ($TusBaseUrl.TrimEnd('/')) + '/' + $RemoteName + '?override=true'
Write-Host "POST $uploadUrl ($size bytes)"
$postOut = curl.exe -s -i -X POST $uploadUrl `
    -H "X-Auth: $AuthKey" `
    -H "X-Auth-Rest: $RestAuthKey" `
    -H "Tus-Resumable: 1.0.0" `
    -H "Upload-Length: $size" `
    -H "Upload-Offset: 0"
$postText = ($postOut | Out-String)
Write-Host $postText
if ($postText -notlike '*201*') {
    throw 'TUS POST failed'
}
Write-Host 'PATCH upload...'
$patchOut = curl.exe -s -i -X PATCH $uploadUrl `
    -H "X-Auth: $AuthKey" `
    -H "X-Auth-Rest: $RestAuthKey" `
    -H "Tus-Resumable: 1.0.0" `
    -H "Content-Type: application/offset+octet-stream" `
    -H "Upload-Offset: 0" `
    --data-binary "@$FilePath"
$patchText = ($patchOut | Out-String)
Write-Host $patchText
if ($patchText -notlike '*204*') { throw 'TUS PATCH failed' }
Write-Host 'OK upload complete'
