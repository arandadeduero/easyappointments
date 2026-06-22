$ErrorActionPreference = 'Stop'

$configPath = Join-Path $PSScriptRoot 'config.json'
if (-not (Test-Path -LiteralPath $configPath)) {
    throw "No se encuentra config.json en $PSScriptRoot"
}

$config = Get-Content -LiteralPath $configPath -Raw -Encoding UTF8 | ConvertFrom-Json
$baseUrl = "http://$($config.host):$($config.port)"

Write-Host "Comprobando servicio: $baseUrl/health"
$health = Invoke-RestMethod -Uri "$baseUrl/health" -Method Get
$health | ConvertTo-Json -Depth 5

Write-Host "`nImpresoras detectadas por el servicio:"
$printers = Invoke-RestMethod -Uri "$baseUrl/printers" -Method Get
$printers | ConvertTo-Json -Depth 5

Write-Host "`nEnviando ticket de prueba..."
$result = Invoke-RestMethod `
    -Uri "$baseUrl/test-print" `
    -Method Post `
    -Headers @{ 'X-Print-Token' = [string]$config.token } `
    -ContentType 'application/json; charset=utf-8' `
    -Body '{}'

$result | ConvertTo-Json -Depth 5
Write-Host "`nRevisa también: $(Join-Path $PSScriptRoot 'print-service.log')"
