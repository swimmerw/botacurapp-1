#!/bin/bash
# Diagnostico: ¿siguen instalados los cambios de varios programas? (solo lectura) 17/10/2026
cd /var/www/html/botacurapp
echo "--- git"; git log --oneline -3; git status --short | head -30
echo "--- archivos"
for f in app/ReservaPrograma.php app/Services/ReservaProgramasService.php app/Services/TurnosService.php config/turnos.php resources/views/themes/backoffice/pages/reserva/includes/programas_extra.blade.php resources/views/themes/backoffice/pages/masaje/includes/admin_editar.blade.php; do [ -f "$f" ] && echo "OK  $f" || echo "FALTA $f"; done
for p in detalleProgramas personasConMasaje personasDesayunoOnce scopeConPrograma tieneEstacionExtra; do echo "$p en Reserva.php: $(grep -c "function $p" app/Reserva.php)"; done
echo "fecha_compra en GiftCard.php: $(grep -c fecha_compra app/GiftCard.php)"
echo "--- logs"; ls -l storage/logs | tail -3; id
ls -la ~/respaldo_*multi* ~/respaldo_*boleta* ~/respaldo_*ubic* ~/respaldo_*gift* 2>/dev/null
