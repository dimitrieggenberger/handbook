$ErrorActionPreference = 'Stop'

$pluginFolder = 'handbook'
$component = 'local_handbook'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$dist = Join-Path $root 'dist'
$build = Join-Path $root 'build\zip'
$stagePlugin = Join-Path $build $pluginFolder

$excludedDirectories = @(
    '.git',
    '.github',
    '.claude',
    '.vscode',
    '.idea',
    'build',
    'dist',
    'node_modules',
    'vendor',
    'logs',
    'docs',
    'dev',
    'tmp',
    'temp',
    '.cache'
)

$excludedFilePatterns = @(
    '*.zip',
    '*.tar',
    '*.tar.gz',
    '*.tgz',
    '*.7z',
    '*.log',
    '*.tmp',
    '*.bak',
    '*.swp',
    '*~',
    '.DS_Store',
    'Thumbs.db',
    'desktop.ini',
    '.gitignore',
    '.gitattributes',
    'AGENTS.md',
    'README.md',
    'build-zip.ps1'
)

if (-not (Test-Path -LiteralPath (Join-Path $root 'version.php'))) {
    throw 'version.php was not found. Run this script from the Moodle plugin repository root.'
}

if (Test-Path -LiteralPath $build) {
    Remove-Item -LiteralPath $build -Recurse -Force
}

if (Test-Path -LiteralPath $dist) {
    Remove-Item -LiteralPath $dist -Recurse -Force
}

New-Item -ItemType Directory -Path $stagePlugin -Force | Out-Null
New-Item -ItemType Directory -Path $dist -Force | Out-Null

$rootPath = (Resolve-Path -LiteralPath $root).Path.TrimEnd('\')

Get-ChildItem -LiteralPath $root -Force -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Substring($rootPath.Length + 1)
    $parts = $relativePath -split '[\\/]'

    foreach ($directory in $excludedDirectories) {
        if ($parts -contains $directory) {
            return
        }
    }

    foreach ($pattern in $excludedFilePatterns) {
        if ($_.Name -like $pattern) {
            return
        }
    }

    $target = Join-Path $stagePlugin $relativePath
    $targetDirectory = Split-Path -Parent $target

    if (-not (Test-Path -LiteralPath $targetDirectory)) {
        New-Item -ItemType Directory -Path $targetDirectory -Force | Out-Null
    }

    Copy-Item -LiteralPath $_.FullName -Destination $target -Force
}

$versionFile = Get-Content -LiteralPath (Join-Path $root 'version.php') -Raw
$version = if ($versionFile -match '\$plugin->version\s*=\s*([0-9]+)') { $Matches[1] } else { 'unknown' }
$zipName = "$component-$version.zip"
$zipPath = Join-Path $dist $zipName

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

if (Test-Path -LiteralPath $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}

$zipArchive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $stageRootPath = (Resolve-Path -LiteralPath $build).Path.TrimEnd('\')
    Get-ChildItem -LiteralPath $stagePlugin -Recurse -File | ForEach-Object {
        $entryName = $_.FullName.Substring($stageRootPath.Length + 1) -replace '\\', '/'
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zipArchive, $_.FullName, $entryName) | Out-Null
    }
} finally {
    $zipArchive.Dispose()
}

Write-Host 'Created Moodle plugin ZIP:'
Write-Host $zipPath
