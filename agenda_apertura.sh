#!/bin/bash
# Agenda: el bot deja de bloquear el mes siguiente antes del dia 20 (param_dia_apertura_agenda = 1).
# Manda el calendario (dias habilitados). Ademas: causa del error al guardar parametros y textos del bot que hablen de la apertura.
cd /var/www/html/botacurapp || exit 1
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
M() { mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "$1" 2>/dev/null; }
echo "== Antes"
M "SELECT clave, contenido FROM bot_secciones_texto WHERE clave='param_dia_apertura_agenda';"
M "UPDATE bot_secciones_texto SET contenido='1', updated_at=NOW() WHERE clave='param_dia_apertura_agenda';"
php artisan cache:clear >/dev/null 2>&1; php artisan config:clear >/dev/null
echo "== Despues"
M "SELECT clave, contenido FROM bot_secciones_texto WHERE clave='param_dia_apertura_agenda';"
echo "== Como usa el codigo ese parametro"
grep -rn "dia_apertura_agenda\|agenda_no_abierta" app --include=*.php | cut -c1-220 | head -12
echo "== Textos del bot que mencionan la apertura de la agenda"
M "SELECT clave, LEFT(REPLACE(contenido,'\n',' '),300) texto FROM bot_secciones_texto WHERE contenido REGEXP 'agenda|se abre|habilita' AND clave<>'param_dia_apertura_agenda'\G"
M "SELECT id, numero_paso, LEFT(REPLACE(instrucciones,'\n',' '),300) texto FROM bot_pasos_flujo WHERE instrucciones REGEXP 'agenda|se abre|habilita'\G"
grep -rn -i "dia 20\|día 20\|20 de cada mes\|20 del mes" app/Services app/Http/Controllers/Api --include=*.php | cut -c1-220 | head -10
echo "== Error al guardar parametros (ultimas lineas del log)"
grep -h "local.ERROR" storage/logs/laravel*.log 2>/dev/null | grep -i "secciones\|BotConfiguracion\|bot_secciones" | tail -3 | cut -c1-500
grep -h "local.ERROR" storage/logs/laravel-$(date +%Y-%m-%d).log storage/logs/laravel.log 2>/dev/null | tail -2 | cut -c1-500
echo LISTO
