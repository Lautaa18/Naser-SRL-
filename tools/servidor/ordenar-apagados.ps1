# Ejecutar en el SERVIDOR (SRV-GESTION) como administrador.
# 1) Saca el cartel "motivo del apagado inesperado" que aparece al entrar.
# 2) Oculta Apagar/Reiniciar del menu Inicio para TODOS los usuarios (incluido Administrador),
#    para que nadie apague el servidor por error al "cerrar" la sesion remota.
# 3) Horas activas de Windows Update (no reinicia de 7 a 20 hs).
# Para apagar a proposito: shutdown /s /t 0   (o /r para reiniciar)

# 1) Cartel de motivo de apagado
$rel = "HKLM:\SOFTWARE\Policies\Microsoft\Windows NT\Reliability"
New-Item -Path $rel -Force | Out-Null
Set-ItemProperty -Path $rel -Name ShutdownReasonOn -Value 0 -Type DWord
Set-ItemProperty -Path $rel -Name ShutdownReasonUI -Value 0 -Type DWord

# 2) Sin boton de apagar en Inicio
$exp = "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\Explorer"
New-Item -Path $exp -Force | Out-Null
Set-ItemProperty -Path $exp -Name NoClose -Value 1 -Type DWord

# 3) Windows Update: horas activas
$ux = "HKLM:\SOFTWARE\Microsoft\WindowsUpdate\UX\Settings"
Set-ItemProperty -Path $ux -Name ActiveHoursStart -Value 7  -Type DWord
Set-ItemProperty -Path $ux -Name ActiveHoursEnd   -Value 20 -Type DWord
$au = "HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU"
New-Item -Path $au -Force | Out-Null
Set-ItemProperty -Path $au -Name NoAutoRebootWithLoggedOnUsers -Value 1 -Type DWord

Write-Host "Listo. Cerra sesion y volve a entrar para ver los cambios." -ForegroundColor Green
