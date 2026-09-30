# Mueve los HTML de formularios a formularios/<sector>/<codigo>.html (no borra nada)
import os, shutil, sys
root = sys.argv[1]
M = [
 ("HSQ/DECLARACIÓN ANTE INCIDENTE.html","hseq/declaracion-incidente.html"),
 ("HSQ/IDENTIFICACIÓN DE ASPECTOS Y EVALUACIÓN DE IMPACTOS AMBIENTALES.html","hseq/aspectos-impactos-ambientales.html"),
 ("HSQ/Identificación de Peligros y Control de Riesgos.html","hseq/peligros-riesgos.html"),
 ("HSQ/IndicadoresdeGestion.html","hseq/indicadores-gestion.html"),
 ("HSQ/Informe_Auditoria.html","hseq/informe-auditoria.html"),
 ("HSQ/Listado de Incidentes.html","hseq/listado-incidentes.html"),
 ("HSQ/ListadodeNoConformidad.html","hseq/listado-no-conformidades.html"),
 ("HSQ/MATRIZDEREQUISITOSLEGALES.html","hseq/matriz-requisitos-legales.html"),
 ("HSQ/MinutadeReunion.html","hseq/minuta-reunion.html"),
 ("HSQ/NoConformidad.html","hseq/no-conformidad.html"),
 ("HSQ/Planificación e Informe de Simulacro.html","hseq/simulacro.html"),
 ("HSQ/Programa de Excelencia Operacional.html","hseq/excelencia-operacional.html"),
 ("HSQ/ProgramadeAuditoria.html","hseq/programa-auditoria.html"),
 ("HSQ/RevisionporlaDireccion.html","hseq/revision-direccion.html"),
 ("HSQ/Roles ante emergencia.html","hseq/roles-emergencia.html"),
 ("HSQ/VisitaGeneral.html","hseq/visita-gerencial.html"),
 ("HSQ/diagrama,causa,efecto.html","hseq/causa-efecto.html"),
 ("HSQ/planAnualdeAuditoria.html","hseq/plan-anual-auditorias.html"),
 ("RRHH/Comunicación de LicenciasyVacaciones.html","rrhh/licencias-vacaciones.html"),
 ("RRHH/EntrevistaPersonalIgresante.html","rrhh/entrevista-ingresante.html"),
 ("RRHH/Evaluación de DesempeñodelPersonal.html","rrhh/evaluacion-desempeno.html"),
 ("RRHH/Inducción al Personal Ingresante.html","rrhh/induccion-ingresante.html"),
 ("RRHH/InformeMedico.html","rrhh/informe-medico.html"),
 ("RRHH/ListadodeTrabajadores.html","rrhh/listado-trabajadores.html"),
 ("RRHH/PlandeCarrera.html","rrhh/plan-carrera.html"),
 ("RRHH/RegistroFormacion.html","rrhh/registro-formacion.html"),
 ("RRHH/RegistrodeEntregaEPP.html","rrhh/entrega-epp.html"),
 ("RRHH/checklistPerfilPersonal.html","rrhh/perfil-puesto.html"),
 ("RRHH/ingreso_Naser.html","rrhh/ingreso.html"),
 ("RRHH/sancion-disciplinaria.html","rrhh/sancion-disciplinaria.html"),
 ("compras/ALTADEPROVEEDORES.html","compras/alta-proveedores.html"),
 ("compras/EntregadeMateriales.html","compras/entrega-materiales.html"),
 ("compras/Listado de Proveedores, ProductosyServicios.html","compras/listado-proveedores.html"),
 ("compras/Pedido de MaterialesyServicios.html","compras/pedido-materiales.html"),
 ("compras/SeguimientoProvedor.html","compras/evaluacion-proveedores.html"),
 ("compras/EvaluaciondeProvedores.html","operaciones/control-slickline.html"),
 ("Ventas/PROPUESTA ECONOMICA DE SERVICIO.html","ventas/propuesta-economica.html"),
 # duplicados / versiones alternativas -> se guardan aparte
 ("HSQ/informedeAudiotoria.html","_otros/hseq-informedeAudiotoria-DUPLICADO-de-ProgramadeAuditoria.html"),
 ("HSQ/Planificación e informe de simulacr.html","_otros/hseq-simulacro-version-anterior.html"),
 ("HSQ/PlandeMejora.html","_otros/hseq-PlandeMejora-en-realidad-es-Visita-Gerencial.html"),
 ("HSQ/php","_otros/hseq-php"),("RRHH/php","_otros/rrhh-php"),("compras/php","_otros/compras-php"),
]
dst_root = os.path.join(root, "formularios")
for src, dst in M:
    s = os.path.join(root, src); d = os.path.join(dst_root, dst)
    if not os.path.exists(s): print("NO EXISTE", src); continue
    if os.path.exists(d): print("YA EXISTE", dst); continue
    os.makedirs(os.path.dirname(d), exist_ok=True)
    shutil.move(s, d)
for folder in ["HSQ","RRHH","compras","Ventas"]:
    p = os.path.join(root, folder)
    if os.path.isdir(p):
        left = os.listdir(p)
        if left: print("QUEDAN archivos en", folder, left)
        else: os.rmdir(p)
print("listo")
