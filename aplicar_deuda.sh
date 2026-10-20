#!/bin/bash
# Por pagar: cuadro "Deuda pendiente total" (por tipo, mes a mes y lo que se suma cada mes), tipo Impuesto,
# pagos a mano de cuentas e impuestos en Egresos del mes, y el IVA de julio ($755.146) registrado como pagado hoy.
cd /var/www/html/botacurapp || exit 1
SHA=$(curl -fsSL https://api.github.com/repos/swimmerw/botacurapp-1/commits/parches-finanzas 2>/dev/null | grep -m1 '"sha"' | cut -d'"' -f4)
[ -n "$SHA" ] || SHA=parches-finanzas
U=https://raw.githubusercontent.com/swimmerw/botacurapp-1/$SHA/deuda
echo "Version: ${SHA:0:7}"
TS=$(date +%Y%m%d%H%M)
V=resources/views/themes/backoffice/pages/egresos_panel
FILES="app/Finanzas/Egresos/Deudas.php app/Http/Controllers/FinanzasDeudasController.php app/Http/Controllers/EgresosPanelController.php $V/deudas.blade.php $V/_deuda_form.blade.php $V/_deuda.blade.php"
for f in $FILES; do cp $f $f.bak.$TS; done
restaurar() { for f in $FILES; do cp $f.bak.$TS $f; done; rm -f $V/_deuda_total.blade.php metodo_resumen.tmp; echo "ERROR: $1 (restaurado)"; exit 1; }
curl -fsSL "$U/_deuda_total.blade.php" -o $V/_deuda_total.blade.php || restaurar "bajando la vista"
curl -fsSL "$U/metodo_resumen.php" -o metodo_resumen.tmp || restaurar "bajando el resumen"
curl -fsSL "$U/patch_deuda.py" | python3 - || restaurar "parche"
rm -f metodo_resumen.tmp
for f in app/Finanzas/Egresos/Deudas.php app/Http/Controllers/FinanzasDeudasController.php app/Http/Controllers/EgresosPanelController.php; do php -l $f >/dev/null || restaurar "sintaxis en $f"; done
php artisan view:clear >/dev/null
curl -fsSL "$U/iva_julio.php" -o iva_tmp_$TS.php && php iva_tmp_$TS.php; rm -f iva_tmp_$TS.php
echo "Listo: Finanzas › Por pagar"
echo "Si algo sale mal: for f in $FILES; do cp \$f.bak.$TS \$f; done; rm $V/_deuda_total.blade.php"
