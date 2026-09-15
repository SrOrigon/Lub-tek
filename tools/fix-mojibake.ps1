$utf8NoBom = New-Object System.Text.UTF8Encoding $false
$root = Split-Path $PSScriptRoot -Parent
$files = @(
    (Join-Path $root 'assets\js\scripts_main.js'),
    (Join-Path $root 'assets\js\scripts_extra.js')
)

function Ch([int[]]$codes) { -join ($codes | ForEach-Object { [char]$_ }) }

$replacements = @(
    ,@(0xC3,0x160), @(0xCA)
    ,@(0xC3,0x201C), @(0xD3)
    ,@(0xC3,0x201A), @(0xD3)
    ,@(0xC3,0x2030), @(0xC9)
    ,@(0xC3,0x008D), @(0xCD)
    ,@(0xC3,0xA3), @(0xE3)
    ,@(0xC3,0xA7), @(0xE7)
    ,@(0xC3,0xA1), @(0xE1)
    ,@(0xC3,0xA9), @(0xE9)
    ,@(0xC3,0xAD), @(0xED)
    ,@(0xC3,0xB3), @(0xF3)
    ,@(0xC3,0xBA), @(0xFA)
    ,@(0xC3,0xA2), @(0xE2)
    ,@(0xC3,0xB5), @(0xF5)
    ,@(0xC3,0xAA), @(0xEA)
    ,@(0xC2,0xB3), @(0x33)
)

foreach ($path in $files) {
    if (-not (Test-Path $path)) { continue }
    $t = [System.IO.File]::ReadAllText($path, $utf8NoBom)
    $total = 0
    for ($i = 0; $i -lt $replacements.Count; $i += 2) {
        $old = Ch $replacements[$i]
        $new = if ($replacements[$i+1].Count -eq 1) { Ch $replacements[$i+1] } else { Ch $replacements[$i+1] }
        while ($t.Contains($old)) {
            $t = $t.Replace($old, $new)
            $total++
        }
    }
    [System.IO.File]::WriteAllText($path, $t, $utf8NoBom)
    Write-Host "$path : $total replacements"
}
