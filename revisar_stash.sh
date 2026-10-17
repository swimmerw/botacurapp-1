#!/bin/bash
# Que quedo guardado en los stash del 15-17/10 y que falta reinstalar (solo lectura). 17/10/2026
cd /var/www/html/botacurapp
for s in 0 1; do echo "=== stash@{$s}"; git stash show --stat --include-untracked stash@{$s} 2>/dev/null | tail -40 || git stash show --stat stash@{$s} | tail -40; done
echo "=== Funciones del 15/10 en el codigo actual"
grep -c "function revisarUmbral" app/Services/BotInteresFechasService.php | sed 's/^/revisarUmbral (aviso +10 personas): /'
grep -c "interes-fechas" routes/api.php | sed 's/^/ruta api interes-fechas: /'
grep -c "mailto:" resources/views/themes/backoffice/pages/bot-conversaciones/index.blade.php | sed 's/^/boton Correo en dias pedidos: /'
grep -c "HA_ALERTA_WEBHOOK" .env | sed 's/^/HA_ALERTA_WEBHOOK en .env: /'
