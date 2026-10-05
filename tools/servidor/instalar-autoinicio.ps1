# Ejecutar UNA vez en el SERVIDOR como administrador.
# - Evita suspension/hibernacion
# - Crea tarea programada que levanta Docker + el sistema al iniciar sesion
$script = Join-Path $PSScriptRoot "iniciar-naser.ps1"

powercfg /change standby-timeout-ac 0
powercfg /change hibernate-timeout-ac 0
powercfg /change monitor-timeout-ac 0
powercfg /hibernate off

$accion = New-ScheduledTaskAction -Execute "powershell.exe" `
  -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$script`""
$trigger   = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
$config    = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
  -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest

Register-ScheduledTask -TaskName "Naser-Docker-Autoinicio" -Action $accion `
  -Trigger $trigger -Settings $config -Principal $principal -Force
Write-Host "Listo. Falta (a mano): BIOS 'Restore on AC Power Loss' = Power On, y Autologon de Sysinternals." -ForegroundColor Green
