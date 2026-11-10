#!/bin/bash
# Solo lectura (10/11/2026): conversaciones con problemas + configuracion del bot, en carpeta temporal para revision.
# Uso: ... | bash -s -- 56979769958,56990908493
cd /var/www/html/botacurapp || exit 1
T=$(openssl rand -hex 10); D=public/rev-$T; mkdir -p $D
set -a; . <(grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' .env | tr -d '"\r'); set +a
Q() { mysql -h"${DB_HOST:-127.0.0.1}" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "$1" 2>/dev/null; }
TELS=$(echo "${1:-}" | tr -cd '0-9,' | sed "s/,/','/g")
{
echo "### modelo"; grep -n "anthropic" -A4 config/services.php | grep -v "key"; grep -E '^ANTHROPIC_MODEL|^BOT_MODEL' .env
echo "### secciones del bot"; Q "SELECT clave, LEFT(contenido, 6000) contenido FROM bot_secciones_textos ORDER BY clave\G" || Q "SELECT * FROM bot_seccion_textos\G"
echo "### conversaciones pedidas"
[ -n "$TELS" ] && Q "SELECT id, usuario_id, nombre_cliente, paso, requiere_humano, humano_hasta, updated_at, historial_json FROM bot_conversaciones WHERE usuario_id IN ('$TELS')\G"
echo "### eventos de esas conversaciones"
[ -n "$TELS" ] && Q "SELECT * FROM bot_eventos WHERE telefono IN ('$TELS') ORDER BY id\G"
echo "### conversaciones de 2 dias donde el bot habla de menu"
Q "SELECT id, usuario_id, updated_at, historial_json FROM bot_conversaciones WHERE updated_at >= NOW() - INTERVAL 2 DAY AND historial_json LIKE '%men_%' AND (historial_json LIKE '%cupo%' OR historial_json LIKE '%disponib%') ORDER BY updated_at DESC LIMIT 12\G"
echo "### reservas del bot 3 dias"; Q "SELECT r.id, r.created_at, r.fecha_visita, r.cantidad_personas, r.estado, r.fuente, c.whatsapp_cliente, v.estado_pago, v.abono_programa FROM reservas r JOIN clientes c ON c.id=r.cliente_id LEFT JOIN ventas v ON v.id_reserva=r.id WHERE r.fuente LIKE 'bot%' AND r.created_at >= NOW() - INTERVAL 3 DAY"
echo "### holds 3 dias"; Q "SELECT id, telefono, nombre, fecha, personas, estado, expira_en, id_reserva, created_at FROM reserva_holds WHERE created_at >= NOW() - INTERVAL 3 DAY"
} > $D/conv.txt
grep -h "" storage/logs/laravel-$(date +%Y-%m-%d).log storage/logs/laravel-$(date -d yesterday +%Y-%m-%d).log | grep -v "WA sync\|Verificación fallida" | grep -i "whatsapp\|bot\|menu\|adjunto\|hold\|reserva" | cut -c1-900 > $D/log.txt
for s in BotTarifasService BotUbicacionesService DisponibilidadService BotCasoService; do [ -f app/Services/$s.php ] && cp app/Services/$s.php $D/$s.txt; done
grep -rln "systemPrompt\s*=" app | head -3 | while read f; do cp "$f" "$D/prompt_$(basename $f .php).txt"; done
chmod 644 $D/*; ls -la $D | tail -n +4 | awk '{print $5, $9}'
echo "URL: https://app.botacura.cl/rev-$T/"
echo "Borrar despues: rm -rf /var/www/html/botacurapp/$D"
