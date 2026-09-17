$ErrorActionPreference = 'Stop'

$baseUrl = 'http://127.0.0.1:8000'
$outputDir = Join-Path $PSScriptRoot '..\docs'
$outputDir = [System.IO.Path]::GetFullPath($outputDir)

New-Item -ItemType Directory -Force -Path $outputDir | Out-Null
if (Test-Path (Join-Path $outputDir 'build')) {
    Remove-Item (Join-Path $outputDir 'build') -Recurse -Force
}
if (Test-Path (Join-Path $outputDir 'images')) {
    Remove-Item (Join-Path $outputDir 'images') -Recurse -Force
}

Copy-Item (Join-Path $PSScriptRoot '..\public\build') (Join-Path $outputDir 'build') -Recurse -Force
Copy-Item (Join-Path $PSScriptRoot '..\public\images') (Join-Path $outputDir 'images') -Recurse -Force

$pages = @{
    '/' = 'index.html'
    '/about' = 'about.html'
    '/contact' = 'contact.html'
    '/shop' = 'shop.html'
}

foreach ($route in $pages.Keys) {
    $response = Invoke-WebRequest -Uri "$baseUrl$route" -UseBasicParsing -TimeoutSec 30
    $html = $response.Content
    $html = $html -replace 'href="/about"', 'href="about.html"'
    $html = $html -replace 'href="/contact"', 'href="contact.html"'
    $html = $html -replace 'href="/shop"', 'href="shop.html"'
    $html = $html -replace 'href="/"', 'href="index.html"'
    $html = $html -replace 'src="/build/', 'src="build/'
    $html = $html -replace 'href="/build/', 'href="build/'
    $html = $html -replace 'src="/images/', 'src="images/'
    $html = $html -replace 'href="/images/', 'href="images/'
    Set-Content -Path (Join-Path $outputDir $pages[$route]) -Value $html -Encoding UTF8
}

Write-Output "Exported $($pages.Count) public pages to $outputDir"