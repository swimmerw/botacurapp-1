#!/bin/bash
# BotacurApp (AWS): total del F29 por mes segun la app (solo lectura). 17/10/2026
cd /var/www/html/botacurapp
php -r '
require "vendor/autoload.php"; $app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = app(App\Http\Controllers\ReporteFinancieroController::class);
$m = new ReflectionMethod($c, "calcularUtilidadMes"); $m->setAccessible(true);
$f29 = app(App\Services\F29Service::class);
echo "periodo;iva_debito;iva_credito;remanente_ant;iva_a_pagar(089);ret_honorarios(151);ppm(062);TOTAL_F29;sync\n";
$p = Carbon\Carbon::create(2026, 1, 1);
while ($p->format("Ym") <= now()->subMonth()->format("Ym")) {
  $per = $p->format("Ym");
  try { $u = $m->invoke($c, $p->year, $p->month); } catch (Throwable $e) { $u = []; }
  $iva = $f29->ivaPeriodo($per);
  $ivaPagar = $iva ? (int) $iva["iva_a_pagar"] : null;
  $ret = (int) ($u["honorariosRetencion"] ?? 0); $ppm = (int) ($u["ppm"] ?? 0);
  echo $per.";".($iva["debito"] ?? "-").";".($iva["credito"] ?? "-").";".($iva["remanente_anterior"] ?? "-").";".($ivaPagar ?? "-").";".$ret.";".$ppm.";".(($ivaPagar ?? 0) + $ret + $ppm).";".($iva ? "ok" : "SIN DATOS").(($iva["cadena_incompleta"] ?? false) ? " (remanente incierto)" : "")."\n";
  $p->addMonthNoOverflow();
}
'
