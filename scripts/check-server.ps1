# Why is pick-sell.shop down? Run in PowerShell (admin not needed):
#   powershell -ExecutionPolicy Bypass -File scripts\check-server.ps1
# Optional: -Minutes 30 to look further back (default 10).
param([int]$Minutes = 10)

$apacheLogs = 'C:\laragon\bin\apache\httpd-2.4.68-260617-Win64-VS18\logs'
$laravelLog = Join-Path $PSScriptRoot '..\storage\logs\laravel.log'
$since = (Get-Date).AddMinutes(-$Minutes)

function Section($title) { Write-Host "`n=== $title ===" -ForegroundColor Cyan }

Section '1. Services (all must be Running)'
Get-Service MySQL80, PicksellApache, Cloudflared | Format-Table Name, Status -AutoSize

Section '2. Is the site answering?'
foreach ($u in 'http://127.0.0.1/', 'https://pick-sell.shop/') {
    try {
        $r = Invoke-WebRequest $u -UseBasicParsing -TimeoutSec 20 -ErrorAction Stop
        Write-Host "$u -> $($r.StatusCode)" -ForegroundColor Green
    } catch { Write-Host "$u -> $($_.Exception.Message)" -ForegroundColor Red }
}
Write-Host "Local OK but public 502  = tunnel/Cloudflare problem, or Apache is overloaded."
Write-Host "Local fails too           = Apache or PHP problem (see 3 and 5)."

Section '3. Busy PHP workers (limit 50; near 50 = overloaded)'
(Get-Process php-cgi -ErrorAction SilentlyContinue | Measure-Object).Count

Section "4. Who sent the most requests in the last $Minutes min (a flood shows up here)"
$stamp = { param($line) if ($line -match '\[(\d{2}/\w{3}/\d{4}:\d{2}:\d{2}:\d{2})') { [datetime]::ParseExact($matches[1], 'dd/MMM/yyyy:HH:mm:ss', [Globalization.CultureInfo]::InvariantCulture) } }
$recent = Get-Content "$apacheLogs\picksell-access.log" -Tail 50000 | Where-Object { $t = & $stamp $_; $t -and $t -ge $since }
Write-Host "Total requests: $($recent.Count)"
$recent | ForEach-Object { if ($_ -match '"([^"]*)"$') { $matches[1] } } | Group-Object | Sort-Object Count -Descending |
    Select-Object -First 5 Count, @{n='User agent'; e={$_.Name}} | Format-Table -AutoSize -Wrap
Write-Host 'Status codes:'
$recent | ForEach-Object { if ($_ -match '" (\d{3}) ') { $matches[1] } } | Group-Object | Sort-Object Name | Format-Table Count, Name -AutoSize

Section '5. Apache errors (last 8 lines)'
Get-Content "$apacheLogs\picksell-error.log" -Tail 8
Write-Host '"ran out of threads" = too many requests at once (flood).' -ForegroundColor Yellow

Section '6. Laravel errors (last 5)'
Select-String -Path $laravelLog -Pattern '\.(ERROR|CRITICAL|EMERGENCY):' | Select-Object -Last 5 |
    ForEach-Object { $_.Line.Substring(0, [Math]::Min(250, $_.Line.Length)) }
