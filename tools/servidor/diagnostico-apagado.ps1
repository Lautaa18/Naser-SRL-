# Diagnostico de apagados inesperados. Ejecutar en el SERVIDOR como administrador.
# Genera diagnostico-apagado.txt al lado del script. Solo LEE, no cambia nada.
$salida = Join-Path $PSScriptRoot "diagnostico-apagado.txt"
$null = Start-Transcript -Path $salida -Force

Write-Host "=== Fecha: $(Get-Date) | Equipo: $env:COMPUTERNAME ===" -ForegroundColor Cyan
Write-Host "Ultimo arranque: $((Get-CimInstance Win32_OperatingSystem).LastBootUpTime)"

Write-Host "`n=== Apagados y reinicios (ultimos 25) ===" -ForegroundColor Cyan
Get-WinEvent -FilterHashtable @{LogName='System'; Id=41,1074,1076,6005,6006,6008} -MaxEvents 25 -ErrorAction SilentlyContinue |
  Select-Object TimeCreated, Id, ProviderName, @{n='Mensaje';e={$_.Message.Split("`n")[0]}} |
  Format-Table -AutoSize -Wrap

Write-Host "`n=== Detalle Kernel-Power 41 (ultimos 3) ===" -ForegroundColor Cyan
Get-WinEvent -FilterHashtable @{LogName='System'; Id=41} -MaxEvents 3 -ErrorAction SilentlyContinue | ForEach-Object {
  $x = [xml]$_.ToXml()
  $d = @{}; $x.Event.EventData.Data | ForEach-Object { $d[$_.Name] = $_.'#text' }
  "{0}  BugcheckCode={1}  PowerButtonTimestamp={2}" -f $_.TimeCreated, $d['BugcheckCode'], $d['PowerButtonTimestamp']
}

Write-Host "`n=== Quien pidio el apagado (1074, ultimos 5) ===" -ForegroundColor Cyan
Get-WinEvent -FilterHashtable @{LogName='System'; Id=1074} -MaxEvents 5 -ErrorAction SilentlyContinue |
  Select-Object TimeCreated, Message | Format-List

Write-Host "`n=== Errores criticos de hardware/disco (ultimos 10) ===" -ForegroundColor Cyan
Get-WinEvent -FilterHashtable @{LogName='System'; Level=1,2; ProviderName='disk','Microsoft-Windows-WHEA-Logger','volmgr'} -MaxEvents 10 -ErrorAction SilentlyContinue |
  Select-Object TimeCreated, ProviderName, Id, @{n='Mensaje';e={$_.Message.Split("`n")[0]}} | Format-Table -AutoSize -Wrap

Write-Host "`n=== Pantallas azules ===" -ForegroundColor Cyan
Get-ChildItem C:\Windows\Minidump -ErrorAction SilentlyContinue | Select-Object Name, LastWriteTime
"MEMORY.DMP existe: $(Test-Path C:\Windows\MEMORY.DMP)"

Write-Host "`n=== Windows Update: reinicios pendientes / horas activas ===" -ForegroundColor Cyan
Test-Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\WindowsUpdate\Auto Update\RebootRequired'
Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\WindowsUpdate\UX\Settings' -ErrorAction SilentlyContinue |
  Select-Object ActiveHoursStart, ActiveHoursEnd

Write-Host "`n=== Estados de energia ===" -ForegroundColor Cyan
powercfg /a
Write-Host "`n--- Plan activo ---"
powercfg /query SCHEME_CURRENT SUB_SLEEP

Write-Host "`n=== Bateria / UPS conectado ===" -ForegroundColor Cyan
Get-CimInstance Win32_Battery -ErrorAction SilentlyContinue | Select-Object Name, BatteryStatus, EstimatedChargeRemaining
if (-not (Get-CimInstance Win32_Battery -ErrorAction SilentlyContinue)) { "No se detecta UPS/bateria por USB" }

Write-Host "`n=== Temperatura (si el equipo la expone) ===" -ForegroundColor Cyan
Get-CimInstance -Namespace root/wmi -ClassName MSAcpi_ThermalZoneTemperature -ErrorAction SilentlyContinue |
  Select-Object InstanceName, @{n='C';e={[math]::Round($_.CurrentTemperature/10-273.15,1)}}

Write-Host "`n=== Docker ===" -ForegroundColor Cyan
docker ps -a 2>&1

$null = Stop-Transcript
Write-Host "`nListo. Reporte guardado en: $salida" -ForegroundColor Green
