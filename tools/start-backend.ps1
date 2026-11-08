param(
    [string]$PhpPath,
    [int]$Port = 8000,
    [switch]$PrepareOnly
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$backendRoot = Join-Path $projectRoot 'backend'
$bundlePath = Join-Path $backendRoot 'storage/app/private/local-ca-bundle.pem'

# PHP's Windows builds do not automatically use the Windows trusted root store.
# Export public certificates only; no private keys or account credentials.
$certificates = Get-ChildItem Cert:/CurrentUser/Root, Cert:/LocalMachine/Root |
    Sort-Object -Property Thumbprint -Unique
$pem = foreach ($certificate in $certificates) {
    "-----BEGIN CERTIFICATE-----`n" +
        [Convert]::ToBase64String($certificate.RawData, [Base64FormattingOptions]::InsertLineBreaks) +
        "`n-----END CERTIFICATE-----`n"
}
if (!$pem) { throw 'No trusted Windows root certificates are available.' }
New-Item -ItemType Directory -Path (Split-Path -Parent $bundlePath) -Force | Out-Null
[System.IO.File]::WriteAllText($bundlePath, ($pem -join "`n"), [System.Text.Encoding]::ASCII)
Write-Output 'Prepared the local trusted CA bundle for Google sign-in and email.'
if ($PrepareOnly) { return }

if (!$PhpPath) {
    $candidates = @(
        (Join-Path $backendRoot 'storage/app/private/php84/php.exe'),
        (Join-Path $env:TEMP 'capstone-exam-php84/php.exe'),
        (Get-Command php -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source -First 1)
    )
    foreach ($candidate in $candidates) {
        if (!$candidate -or !(Test-Path -LiteralPath $candidate)) { continue }
        $version = & $candidate -n -r 'echo PHP_VERSION_ID;'
        if ($LASTEXITCODE -eq 0 -and [int]$version -ge 80400) { $PhpPath = $candidate; break }
    }
}
if (!$PhpPath -or !(Test-Path -LiteralPath $PhpPath)) {
    throw 'PHP 8.4+ is required. Run this script with -PhpPath pointing to php.exe.'
}
$phpDirectory = Split-Path -Parent $PhpPath
$iniPath = Join-Path $backendRoot 'storage/app/private/local-development.ini'
# Keep paths in a quoted INI file. PHP -d misparses unquoted parentheses
# in workspace paths and can silently skip the database/HTTPS extensions.
$phpConfiguration = @("extension_dir=`"$($phpDirectory.Replace('\', '/'))/ext`"")
foreach ($extension in @('pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'curl')) {
    $phpConfiguration += "extension=$extension"
}
$phpConfiguration += @(
    "curl.cainfo=`"$($bundlePath.Replace('\', '/'))`"",
    "openssl.cafile=`"$($bundlePath.Replace('\', '/'))`""
)
[System.IO.File]::WriteAllLines($iniPath, $phpConfiguration, [System.Text.Encoding]::ASCII)
$phpOptions = @('-c', $iniPath)

Push-Location $backendRoot
try {
    & $PhpPath @phpOptions artisan config:clear
    if ($LASTEXITCODE -ne 0) { throw 'Unable to load the backend with this PHP runtime.' }
    Set-Location (Join-Path $backendRoot 'public')
    Write-Output "Backend: http://localhost:$Port (keep this terminal open)"
    & $PhpPath @phpOptions -S "localhost:$Port" '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
} finally {
    Pop-Location
}
