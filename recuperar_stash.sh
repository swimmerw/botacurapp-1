#!/bin/bash
# Recupera desde los stash los cambios del 14-15/10 que no estan en main (solo archivos que main no toco). 17/10/2026
cd /var/www/html/botacurapp || exit 1
MIOS="app/Console/Commands/ReporteManana.php app/Console/Kernel.php app/GiftCard.php app/Http/Controllers/AdminController.php app/Http/Controllers/GiftCardController.php app/Http/Controllers/InformeController.php app/Http/Controllers/MasajeController.php app/Http/Controllers/MenuController.php app/Http/Controllers/ReporteFinancieroController.php app/Http/Controllers/ReservaController.php app/Http/Controllers/VisitaController.php app/Jobs/ProcesarVentaCerrada.php app/Reserva.php app/ReservaPrograma.php app/Services/DesayunoOnceService.php app/Services/ReservaProgramasService.php app/Services/TurnosService.php config/turnos.php database/migrations/2026_10_15_000001_create_reserva_programas_table.php database/migrations/2026_10_15_000002_add_fecha_compra_to_gift_cards.php database/migrations/2026_10_17_000001_create_turnos_sugeridos_table.php resources/views/emails/reserva.blade.php resources/views/pdf/consumo_separado/viewPDF.blade.php resources/views/pdf/venta/viewPDF.blade.php resources/views/themes/backoffice/pages/admin/finanzas/detalle-dia.blade.php resources/views/themes/backoffice/pages/giftcard/create.blade.php resources/views/themes/backoffice/pages/giftcard/edit.blade.php resources/views/themes/backoffice/pages/giftcard/index.blade.php resources/views/themes/backoffice/pages/giftcard/show.blade.php resources/views/themes/backoffice/pages/masaje/includes/admin_editar.blade.php resources/views/themes/backoffice/pages/masaje/index.blade.php resources/views/themes/backoffice/pages/reserva/create.blade.php resources/views/themes/backoffice/pages/reserva/edit.blade.php resources/views/themes/backoffice/pages/reserva/includes/programas_extra.blade.php resources/views/themes/backoffice/pages/reserva/index_all.blade.php resources/views/themes/backoffice/pages/reserva/index_registro.blade.php resources/views/themes/backoffice/pages/reserva/screens/mobile.blade.php resources/views/themes/backoffice/pages/reserva/screens/principal.blade.php resources/views/themes/backoffice/pages/reserva/show.blade.php resources/views/themes/backoffice/pages/visita/create.blade.php resources/views/themes/backoffice/pages/visita/edit.blade.php resources/views/themes/backoffice/pages/visita/edit_ubicacion.blade.php resources/views/themes/backoffice/pages/visita/registrar.blade.php routes/web.php "
T=$(date +%Y%m%d%H%M%S); BK=~/respaldo_recuperar_stash_$T.tgz; LISTA=/tmp/recuperar_$T.txt; : > $LISTA
es_mio(){ for m in $MIOS; do [ "$m" = "$1" ] && return 0; done; return 1; }
echo "=== Cambios en stash@{1} (archivos ya versionados)"
for f in $(git stash show --name-only stash@{1}); do
  case "$f" in storage/*) continue;; esac
  if es_mio "$f"; then echo "  (ya reinstalado) $f"; continue; fi
  if git diff --quiet 70d6d0e HEAD -- "$f" 2>/dev/null; then echo "  RECUPERAR $f"; echo "T $f" >> $LISTA
  else echo "  ! main tambien lo cambio, NO se toca: $f"; fi
done
echo "=== Archivos nuevos guardados en los stash"
for s in 0 1; do
  git rev-parse -q --verify "stash@{$s}^3" >/dev/null || continue
  for f in $(git ls-tree -r --name-only "stash@{$s}^3"); do
    case "$f" in storage/*) continue;; esac
    if [ -e "$f" ]; then echo "  (ya existe) $f"; else echo "  RECUPERAR $f (stash@{$s})"; echo "U$s $f" >> $LISTA; fi
  done
done
[ -s $LISTA ] || { echo "Nada que recuperar."; exit 0; }
tar czf $BK $(awk '{print $2}' $LISTA | while read f; do [ -f "$f" ] && echo "$f"; done) 2>/dev/null; echo "Respaldo: $BK"
while read tipo f; do
  if [ "$tipo" = "T" ]; then git checkout stash@{1} -- "$f" && git reset -q -- "$f"
  else mkdir -p "$(dirname "$f")"; git show "stash@{${tipo#U}}^3:$f" > "$f"; fi
  case "$f" in *.php) php -l "$f" >/dev/null || echo "  ERROR de sintaxis en $f";; esac
done < $LISTA
sg www-data -c "php artisan route:clear >/dev/null; php artisan view:clear >/dev/null; php artisan config:clear >/dev/null"
echo "=== Verificacion"
grep -c "function revisarUmbral" app/Services/BotInteresFechasService.php | sed 's/^/revisarUmbral: /'
grep -c "interes-fechas" routes/api.php | sed 's/^/ruta interes-fechas: /'
grep -c "mailto:" resources/views/themes/backoffice/pages/bot-conversaciones/index.blade.php | sed 's/^/boton Correo: /'
[ -f app/Http/Controllers/InteresFechasCorreoController.php ] && echo "controlador correo: OK"
sg www-data -c "php artisan route:list 2>/dev/null | grep -c 'interes-fechas\|masaje.admin_update'" | sed 's/^/rutas nuevas registradas: /'
echo "LISTO"
