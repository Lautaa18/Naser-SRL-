#!/bin/bash
# ==========================================
# NASER SGI - Prueba rapida antes de hacer push
# Uso (desde la carpeta del proyecto, con Docker levantado):
#   bash tools/smoke_test.sh correo@gruponaser.com.ar 'contraseña' [http://localhost:8080]
# Entra con ese usuario, abre todas las paginas principales y avisa si alguna da error.
# ==========================================
EMAIL="$1"; PASS="$2"; BASE="${3:-http://localhost:8080}"
if [ -z "$EMAIL" ] || [ -z "$PASS" ]; then echo "Uso: bash tools/smoke_test.sh correo contraseña [url]"; exit 1; fi
JAR=$(mktemp); trap 'rm -f "$JAR" /tmp/naser_smoke.html' EXIT
ROJO='\033[31m'; VERDE='\033[32m'; NADA='\033[0m'; fallas=0

tok=$(curl -s -c "$JAR" -b "$JAR" "$BASE/php/login.php" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
dest=$(curl -s -c "$JAR" -b "$JAR" -o /dev/null -w "%{redirect_url}" -X POST --data-urlencode "csrf=$tok" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASS" "$BASE/php/login.php")
if [[ "$dest" != *dashboard* && "$dest" != *perfil* ]]; then echo -e "${ROJO}No se pudo iniciar sesión con $EMAIL${NADA}"; exit 1; fi
echo "Sesión iniciada como $EMAIL"

probar() { # url codigo_esperado
  local code; code=$(curl -s -b "$JAR" -o /tmp/naser_smoke.html -w "%{http_code}" "$BASE$1")
  local errores; errores=$(grep -ciE "fatal error|warning:|notice:|deprecated:|uncaught|parse error" /tmp/naser_smoke.html)
  if [ "$code" != "$2" ] || [ "$errores" != "0" ]; then echo -e "${ROJO}✖ $1  (HTTP $code, $errores error/es de PHP)${NADA}"; fallas=$((fallas+1));
  else echo -e "${VERDE}✔${NADA} $1"; fi
}
for p in dashboard sgi buscar operaciones rrhh compras finanzas ventas hseq perfil notificaciones formularios/index "sector.php?sector=hseq" "sector.php?sector=rrhh" "formularios/index.php?sector=rrhh" "formularios/index.php?vista=aprobar"; do
  [[ $p == *.php* ]] && probar "/php/$p" 200 || probar "/php/$p.php" 200
done
echo "Archivos privados (tienen que dar 404):"
for p in /.env /.git/config /base_naser_compartir.sql /compose.yaml /backups/ /formularios/rrhh/ingreso.html; do probar "$p" 404; done

if [ $fallas -eq 0 ]; then echo -e "${VERDE}Todo OK${NADA}"; else echo -e "${ROJO}$fallas problema(s). Revisalos antes de hacer push.${NADA}"; exit 1; fi
