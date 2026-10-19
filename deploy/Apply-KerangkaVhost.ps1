param(
    [string]$ManagedConfig = 'D:/DATA/SERVER_DEVARON/Devaron/etc/nginx/vhosts/managed-sites.conf',
    [string]$MainConfig = 'D:/DATA/SERVER_DEVARON/Devaron/etc/nginx/nginx.conf',
    [string]$NginxExe = 'D:/DATA/SERVER_DEVARON/Devaron/bin/nginx/nginx-1.28.2/nginx.exe',
    [switch]$Reload
)
$ErrorActionPreference = 'Stop'
$template = [IO.File]::ReadAllText((Join-Path $PSScriptRoot 'nginx/kerangkaweb.conf'))
$original = [IO.File]::ReadAllText($ManagedConfig)
$pattern = '(?ms)^server\s*\{.*?^\}'
$blocks = [regex]::Matches($original, $pattern)
$targets = @($blocks | Where-Object { $_.Value -match 'server_name kerangkaweb\.test;' })
if ($targets.Count -lt 1 -or $targets.Count -gt 2) { throw 'Expected one HTTP block or HTTP+HTTPS blocks for kerangkaweb.test.' }
$firstIndex = $targets[0].Index
$modified = [regex]::Replace($original, $pattern, [System.Text.RegularExpressions.MatchEvaluator]{
    param($match)
    if ($match.Value -notmatch 'server_name kerangkaweb\.test;') { return $match.Value }
    if ($match.Index -eq $firstIndex) { return $template.Trim() }
    return ''
})
$beforeOthers = @($blocks | Where-Object { $_.Value -notmatch 'server_name kerangkaweb\.test;' } | ForEach-Object Value)
$afterOthers = @([regex]::Matches($modified, $pattern) | Where-Object { $_.Value -notmatch 'server_name kerangkaweb\.test;' } | ForEach-Object Value)
if (($beforeOthers -join "`n") -cne ($afterOthers -join "`n")) { throw 'Other vhosts changed; refusing write.' }
$backup = "$ManagedConfig.kerangka-backup-$(Get-Date -Format yyyyMMddHHmmss)"
[IO.File]::WriteAllText($backup, $original)
[IO.File]::WriteAllText($ManagedConfig, $modified)
$prefix = (Split-Path $NginxExe -Parent).Replace('\','/') + '/'
& $NginxExe -p $prefix -c $MainConfig -t
if ($LASTEXITCODE -ne 0) { [IO.File]::WriteAllText($ManagedConfig, $original); throw 'Nginx check failed; restored original.' }
if ($Reload) {
    & $NginxExe -p $prefix -c $MainConfig -s reload
    if ($LASTEXITCODE -ne 0) { throw 'Reload requires the same Windows administrator privilege as Nginx.' }
}
'Kerangka vhost applied; other vhosts unchanged. Run HTTP/TLS smoke checks after reload.'
