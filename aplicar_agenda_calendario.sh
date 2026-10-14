#!/bin/bash
# Agenda solo por calendario + textos sin "dia 20" + arreglo del error al guardar Configuracion del bot (13/10/2026)
cd /var/www/html/botacurapp || exit 1
rm -rf public/rev-a39a2c2b568b   # copia temporal de lectura
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/parches-finanzas
curl -fsSL "$U/agenda_calendario.py?v=$(date +%s)" -o /tmp/agenda_calendario.py || exit 1
python3 /tmp/agenda_calendario.py || exit 1
for f in app/Services/BotTarifasService.php app/Services/BotPromptService.php app/Http/Controllers/BotConfiguracionController.php; do
  if ! php -l $f >/dev/null; then
    cp "$(ls $f.bak.* | sort | tail -1)" $f; echo "ERROR de sintaxis en $f: restaurado"; exit 1
  fi
done
echo "Sintaxis OK"
# Textos guardados en la base (los que usa el bot)
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"'); set +a
M() { mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" "$@" 2>/dev/null; }
M -e "SELECT clave, contenido FROM bot_secciones_texto WHERE clave='regla_apertura_agenda'; SELECT id, instrucciones FROM bot_pasos_flujo WHERE id=3;" > ~/respaldo_textos_agenda_$(date +%Y%m%d%H%M).txt
M <<'SQL'
UPDATE bot_secciones_texto SET contenido = 'APERTURA DE AGENDA (parejas y grupos de menos de 10 personas):
- No hay una fecha fija de apertura: solo se pueden reservar los días habilitados en el calendario (lo verifican las acciones de disponibilidad y cotización).
- Si una pareja o un grupo de menos de 10 personas pide un día que no está habilitado, NO prometas una fecha de apertura ni hables del día 20. Dile con amabilidad que esa fecha aún no está habilitada y ofrécele los días habilitados más cercanos; si quiere esa fecha igual, que nos vuelva a escribir más adelante.', updated_at = NOW()
WHERE clave = 'regla_apertura_agenda';
UPDATE bot_pasos_flujo SET instrucciones = REPLACE(instrucciones, 'Solo confirmar disponibilidad para el mes con agenda abierta.', 'Solo confirmar disponibilidad para días habilitados en el calendario.') WHERE id = 3;
SQL
M -e "SELECT clave, LEFT(contenido,120) texto FROM bot_secciones_texto WHERE clave='regla_apertura_agenda'; SELECT id, RIGHT(instrucciones,90) texto FROM bot_pasos_flujo WHERE id=3;"
php artisan cache:clear >/dev/null 2>&1; php artisan config:clear >/dev/null
echo "LISTO: agenda por calendario; Configuracion del bot ya guarda con campos vacios"
