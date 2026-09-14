$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot
if (!(Test-Path "backups")) { New-Item -ItemType Directory -Path "backups" | Out-Null }
$dbName = "naser_sgi_prueba"
$rootPass = "root2026"
if (Test-Path ".env") {
    Get-Content ".env" | ForEach-Object {
        if ($_ -match '^\s*([^#][^=]*)=(.*)$') {
            $name=$matches[1].Trim(); $value=$matches[2].Trim()
            if ($name -eq "DB_NAME") { $dbName=$value }
            if ($name -eq "DB_ROOT_PASS") { $rootPass=$value }
        }
    }
}
$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$file = "backups\naser_$stamp.sql"
cmd /c "docker compose exec -T db mariadb-dump -uroot -p$rootPass --single-transaction --routines --triggers $dbName > `"$file`""
if ($LASTEXITCODE -ne 0) { throw "No se pudo crear el backup." }
Write-Host "Backup creado: $file"
