<#
.SYNOPSIS
    Builds the installable plugin zip and updates updates.xml with its version, download URL and SHA-512.

.DESCRIPTION
    The zip is written to dist\pankyreadingtime-<version>.zip. Upload that exact file to the
    GitHub release v<version>: rebuilding changes the hash, so rebuild and re-upload together.

.PARAMETER Version
    Optional new version (e.g. 3.1.1). When given, pankyreadingtime.xml and media\joomla.asset.json
    are bumped before building. Without it, the manifest's current version is used.

.EXAMPLE
    .\build.ps1

.EXAMPLE
    .\build.ps1 -Version 3.1.1
#>
[CmdletBinding()]
param(
    [ValidatePattern('^\d+\.\d+\.\d+([-.][0-9A-Za-z.]+)?$')]
    [string] $Version
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$root           = [System.IO.Path]::GetFullPath($PSScriptRoot)
$element        = 'pankyreadingtime'
$manifestPath   = Join-Path $root "$element.xml"
$assetPath      = Join-Path $root 'media\joomla.asset.json'
$updatesPath    = Join-Path $root 'updates.xml'
$distDir        = Join-Path $root 'dist'
$releaseBaseUrl = 'https://github.com/pnkr/PankyReadTime/releases/download'
$include        = @("$element.xml", 'services', 'src', 'media', 'language', 'LICENSE.txt')
$utf8NoBom      = New-Object System.Text.UTF8Encoding $false

function Read-Text([string] $Path) {
    [System.IO.File]::ReadAllText($Path)
}

function Write-Text([string] $Path, [string] $Text) {
    [System.IO.File]::WriteAllText($Path, $Text, $utf8NoBom)
}

# Replaces group 1 of the first match of $Pattern with $Value, leaving the rest of the text untouched
function Set-FirstGroup([string] $Text, [string] $Pattern, [string] $Value) {
    $m = [regex]::Match($Text, $Pattern)

    if (-not $m.Success) {
        return $null
    }

    $g = $m.Groups[1]
    $Text.Substring(0, $g.Index) + $Value + $Text.Substring($g.Index + $g.Length)
}

# Replaces the content of the first <Tag>...</Tag> while keeping the file's formatting and comments
function Set-ElementText([string] $Text, [string] $Tag, [string] $Value) {
    $result = Set-FirstGroup $Text "(?s)<$Tag\b[^>]*>(.*?)</$Tag>" $Value

    if ($null -eq $result) {
        throw "<$Tag> not found"
    }

    $result
}

# --- Version -----------------------------------------------------------------

$manifest = Read-Text $manifestPath

if ($Version) {
    $manifest = Set-ElementText $manifest 'version' $Version
    Write-Text $manifestPath $manifest
    Write-Host "Manifest bumped to $Version"
}

# Select the element explicitly: dot-notation would also pick up the <extension version="..."> attribute
$versionNode = ([xml] $manifest).SelectSingleNode('/extension/version')
$ver         = if ($versionNode) { $versionNode.InnerText.Trim() } else { '' }

if ($ver -notmatch '^\d+\.\d+\.\d+([-.][0-9A-Za-z.]+)?$') {
    throw "Invalid or missing <version> in ${manifestPath}: '$ver'"
}

# Keep the web asset registry version in step with the manifest
$assetJson = Read-Text $assetPath
$assetSync = Set-FirstGroup $assetJson '"version"\s*:\s*"([^"]*)"' $ver

if ($null -ne $assetSync -and $assetSync -ne $assetJson) {
    Write-Text $assetPath $assetSync
    Write-Host "joomla.asset.json version set to $ver"
}

# --- Zip ---------------------------------------------------------------------

Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem

$zipName = "$element-$ver.zip"
$zipPath = Join-Path $distDir $zipName

New-Item -ItemType Directory -Force $distDir | Out-Null

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
$count = 0

try {
    foreach ($item in $include) {
        $full = Join-Path $root $item

        if (-not (Test-Path $full)) {
            if ($item -eq 'LICENSE.txt') {
                Write-Warning 'LICENSE.txt not found; building without it'
                continue
            }

            throw "Missing $item"
        }

        foreach ($file in Get-ChildItem $full -Recurse -File) {
            # Forward slashes: Compress-Archive on PowerShell 5.1 writes backslashes, which break extraction on Linux
            $entry = $file.FullName.Substring($root.Length).TrimStart('\', '/') -replace '\\', '/'
            [void] [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $zip, $file.FullName, $entry, [System.IO.Compression.CompressionLevel]::Optimal
            )
            $count++
        }
    }
} finally {
    $zip.Dispose()
}

$hash = (Get-FileHash $zipPath -Algorithm SHA512).Hash.ToLowerInvariant()

# --- updates.xml -------------------------------------------------------------

$updates = Read-Text $updatesPath
$updates = Set-ElementText $updates 'version' $ver
$updates = Set-ElementText $updates 'downloadurl' "$releaseBaseUrl/v$ver/$zipName"

# Drop the manual-checksum placeholder comments, if still there
$updates = [regex]::Replace($updates, '(?mi)^[ \t]*<!--[^\r\n]*(sha512|checksum)[^\r\n]*-->[ \t]*\r?\n', '')

if ($updates -match '<sha512\b') {
    $updates = Set-ElementText $updates 'sha512' $hash
} else {
    $m = [regex]::Match($updates, '(?m)^([ \t]*)</downloads>[ \t]*(\r?\n)')

    if (-not $m.Success) {
        throw 'Could not insert <sha512> (no </downloads> line found)'
    }

    $end     = $m.Index + $m.Length
    $updates = $updates.Substring(0, $end) + $m.Groups[1].Value + "<sha512>$hash</sha512>" + $m.Groups[2].Value + $updates.Substring($end)
}

[void] [xml] $updates
Write-Text $updatesPath $updates

Write-Host ''
Write-Host "Built   $zipPath ($count files)"
Write-Host "SHA-512 $hash"
Write-Host "Updated updates.xml for version $ver"
Write-Host ''
Write-Host "Next: create GitHub release v$ver, upload $zipName, then commit and push updates.xml."
