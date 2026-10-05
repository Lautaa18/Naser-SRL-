# Abre Docker Desktop si hace falta, espera a que responda y levanta el sistema.
$proyecto = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent   # raiz del repo (tools\servidor\..\..)
if (-not (Get-Process "Docker Desktop" -ErrorAction SilentlyContinue)) {
    Start-Process "C:\Program Files\Docker\Docker\Docker Desktop.exe"
}
for ($i = 0; $i -lt 60; $i++) {
    docker info *> $null
    if ($LASTEXITCODE -eq 0) { break }
    Start-Sleep -Seconds 5
}
Set-Location $proyecto
docker compose up -d
