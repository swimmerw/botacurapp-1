#!/bin/bash
# BotacurApp (AWS): Gift Cards con fecha de compra y vencimiento a 45 dias. 15/10/2026
# Uso: ... | bash -s <commit>
set -e
C=${1:?falta el commit}
cd /var/www/html/botacurapp
P=/tmp/giftcard_vigencia.patch
curl -fsSL "https://raw.githubusercontent.com/swimmerw/botacurapp-1/$C/giftcard_vigencia.patch" -o $P
if git apply --reverse --check $P 2>/dev/null; then echo "Ya estaba aplicado."; else
  git apply --check $P 2>/tmp/gc_check.txt || { echo "No calza con el codigo del servidor (no se cambio nada):"; cat /tmp/masajes_check.txt; exit 1; }
  T=$(date +%Y%m%d%H%M%S)
  git diff --name-only HEAD > /dev/null
  tar czf ~/respaldo_giftcard_vigencia_$T.tgz $(grep -E '^\+\+\+ b/' $P | sed 's#^+++ b/##' | while read f; do [ -f "$f" ] && echo "$f"; done)
  echo "Respaldo: ~/respaldo_giftcard_vigencia_$T.tgz"
  git apply $P
fi
for f in app/GiftCard.php app/Http/Controllers/GiftCardController.php; do
  php -l $f >/dev/null || { echo "Error de sintaxis en $f: se revierte"; git apply -R $P; exit 1; }
done
php artisan migrate --path=database/migrations/2026_10_15_000002_add_fecha_compra_to_gift_cards.php --force \
  || { echo "Fallo la columna: se revierte"; git apply -R $P; exit 1; }
php -r '
require "vendor/autoload.php"; $app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$t = "gift_cards_respaldo_" . date("YmdHis");
DB::statement("CREATE TABLE $t AS SELECT * FROM gift_cards");
echo "Respaldo de la tabla: $t\n";
$f = function ($w) { return DB::table("gift_cards")->whereRaw($w)->count(); };
echo "Gift Cards: " . DB::table("gift_cards")->count() . "\n";
echo " - ya vencen a 45 dias de la compra: " . $f("validez_hasta = DATE_ADD(fecha_compra, INTERVAL 45 DAY)") . "\n";
$n = DB::update("UPDATE gift_cards SET validez_hasta = DATE_ADD(fecha_compra, INTERVAL 45 DAY) WHERE fecha_compra IS NOT NULL AND (validez_hasta IS NULL OR (fecha_uso IS NOT NULL AND validez_hasta = fecha_uso))");
echo " - corregidas (sin vencimiento o con vencimiento = fecha de uso): $n\n";
$otras = DB::table("gift_cards")->whereRaw("validez_hasta <> DATE_ADD(fecha_compra, INTERVAL 45 DAY)")->orderBy("fecha_compra")->get(["codigo","fecha_compra","validez_hasta","usada"]);
echo " - con otro vencimiento (NO se cambiaron): " . count($otras) . "\n";
foreach ($otras as $o) { echo "     " . $o->codigo . "  compra " . $o->fecha_compra . "  vence " . $o->validez_hasta . "  (45 dias = " . date("Y-m-d", strtotime($o->fecha_compra . " +45 days")) . ")" . ($o->usada ? "  usada" : "") . "\n"; }
'
php artisan view:clear >/dev/null; php artisan route:clear >/dev/null; php artisan config:clear >/dev/null
php artisan queue:restart >/dev/null 2>&1 || true
echo "LISTO: Gift Cards con fecha de compra, vencimiento y vigencia."
