<?php
// Solo lectura (10/11/2026): cruza los comprobantes que llegaron por WhatsApp con las reservas y abonos de la app.
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$dias  = (int) ($argv[1] ?? 14);
$desde = now()->subDays($dias)->toDateTimeString();
$meses = ['enero'=>1,'febrero'=>2,'marzo'=>3,'abril'=>4,'mayo'=>5,'junio'=>6,'julio'=>7,'agosto'=>8,'septiembre'=>9,'setiembre'=>9,'octubre'=>10,'noviembre'=>11,'diciembre'=>12];
function n9($t) { return substr(preg_replace('/\D/', '', (string) $t), -9); }
function fechaDe($txt, $meses) {
    if (preg_match('/(\d{1,2})\s*\\\\?\/\s*(\d{1,2})\s*\\\\?\/\s*(\d{2,4})/', $txt, $m)) {
        $y = strlen($m[3]) == 2 ? 2000 + $m[3] : $m[3]; return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
    }
    if (preg_match('/(\d{1,2})\s+de\s+([a-záéíóú]+)\s+(?:de\s+)?(\d{4})/iu', $txt, $m) && isset($meses[mb_strtolower($m[2])])) {
        return sprintf('%04d-%02d-%02d', $m[3], $meses[mb_strtolower($m[2])], $m[1]);
    }
    if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $txt, $m)) { return $m[0]; }
    return null;
}
function montoDe($txt) {
    if (preg_match('/(?:monto|importe|total|valor|cantidad)[^0-9$]{0,25}\$?\s*([\d][\d\.\,]{2,})/iu', $txt, $m)
        || preg_match('/\$\s*([\d]{1,3}(?:[\.\,]\d{3})+)/u', $txt, $m)) {
        return (int) preg_replace('/[^\d]/', '', preg_replace('/[\,\.]\d{2}$/', '', $m[1]));
    }
    return null;
}

// 1) Comprobantes en las conversaciones del bot (lo que el bot leyó de cada imagen)
$comp = [];
$convs = DB::table('bot_conversaciones')->where('canal', 'whatsapp')->where('updated_at', '>=', $desde)
    ->select('id', 'usuario_id', 'nombre_cliente', 'historial_json', 'updated_at')->get();
foreach ($convs as $c) {
    foreach (json_decode($c->historial_json ?: '[]', true) ?: [] as $t) {
        if (($t['role'] ?? '') !== 'user') { continue; }
        foreach (preg_split('/(?=\[Imagen recibida\])/u', (string) $t['content']) as $bloque) {
            if (strpos($bloque, '[Imagen recibida]') !== 0) { continue; }
            if (!preg_match('/comprobante|transferencia|transacci[oó]n|dep[oó]sito/iu', $bloque)) { continue; }
            $b = str_replace(['\\n', "\n", '**'], ' ', mb_substr($bloque, 0, 1500));
            $comp[] = ['tel' => $c->usuario_id, 'nombre' => $c->nombre_cliente, 'monto' => montoDe($b), 'fecha' => fechaDe($b, $meses),
                'mensaje' => preg_match('/(?:mensaje|glosa|comentario|asunto|descripci[oó]n)\s*:?\s*([^\-\|\]]{3,60})/iu', $b, $mm) ? trim($mm[1]) : ''];
        }
    }
}
// 2) Comprobantes que el bot ya adjuntó a una venta
$adj = DB::table('ventas as v')->join('reservas as r', 'r.id', '=', 'v.id_reserva')->join('clientes as c', 'c.id', '=', 'r.cliente_id')
    ->whereNotNull('v.comprobante_transferencia')->where('v.updated_at', '>=', $desde)
    ->select('c.whatsapp_cliente as tel', 'v.id as venta', 'r.id as reserva', 'v.comprobante_monto as monto')->get();
$ventaPorComp = [];
foreach ($adj as $a) { $ventaPorComp[n9($a->tel) . '|' . (int) $a->monto] = $a; }

// Reservas por teléfono (cliente o teléfono del chat del cupo apartado)
function reservasDe($tel) {
    $t = n9($tel);
    $ids = DB::table('reservas as r')->join('clientes as c', 'c.id', '=', 'r.cliente_id')
        ->whereRaw("RIGHT(REPLACE(REPLACE(REPLACE(c.whatsapp_cliente,' ',''),'+',''),'-',''),9) = ?", [$t])->pluck('r.id')->all();
    $ids = array_merge($ids, DB::table('reserva_holds')->whereNotNull('id_reserva')
        ->where(function ($w) use ($t) { $w->whereRaw('RIGHT(telefono,9) = ?', [$t])->orWhere('datos_json', 'like', '%"telefono_chat":"56' . $t . '"%'); })
        ->pluck('id_reserva')->all());
    if (!$ids) { return []; }
    return DB::table('reservas as r')->join('clientes as c', 'c.id', '=', 'r.cliente_id')->leftJoin('ventas as v', 'v.id_reserva', '=', 'r.id')
        ->whereIn('r.id', array_unique($ids))->where('r.fecha_visita', '>=', now()->subDays(30)->toDateString())
        ->select('r.id', 'r.fecha_visita', 'r.cantidad_personas', 'r.estado', 'c.nombre_cliente', 'v.id as venta', 'v.abono_programa', 'v.diferencia_programa', 'v.estado_pago', 'v.comprobante_monto')
        ->orderBy('r.fecha_visita')->get()->all();
}

$filas = []; $vistos = [];
foreach ($comp as $k) {
    $clave = n9($k['tel']) . '|' . $k['monto'] . '|' . $k['fecha'];
    if (isset($vistos[$clave])) { continue; } $vistos[$clave] = 1;
    $res = reservasDe($k['tel']);
    $estado = 'FALTA: no hay reserva para este teléfono';
    $detalle = '';
    if ($res) {
        $estado = 'REVISAR: hay reserva, pero el abono no calza';
        foreach ($res as $r) {
            $detalle .= " | R{$r->id} {$r->fecha_visita} {$r->cantidad_personas}p {$r->estado} abono " . (int) $r->abono_programa . ' dif ' . (int) $r->diferencia_programa . ' (' . ($r->estado_pago ?: '-') . ')';
            $pag = (int) $r->abono_programa; $tot = $pag + (int) $r->diferencia_programa;
            if ($k['monto'] && ((int) $r->comprobante_monto === $k['monto'] || abs($pag - $k['monto']) <= max(1000, $k['monto'] * 0.03) || abs($tot - $k['monto']) <= max(1000, $k['monto'] * 0.03))) {
                $estado = 'OK';
            }
        }
    }
    $filas[] = [$estado, $k, $detalle];
}
usort($filas, function ($a, $b) { return strcmp($a[0], $b[0]) ?: strcmp((string) $a[1]['fecha'], (string) $b[1]['fecha']); });

$cuenta = ['OK' => 0, 'REVISAR' => 0, 'FALTA' => 0];
echo "==== COMPROBANTES LEIDOS POR EL BOT (ultimos {$dias} dias) ====\n";
foreach ($filas as $f) {
    list($estado, $k, $det) = $f; $cuenta[strtok($estado, ':')]++;
    printf("%-8s +%s %-22s pago %s %s %s%s\n", strtok($estado, ':'), $k['tel'], mb_substr((string) $k['nombre'], 0, 22),
        $k['fecha'] ?: '¿fecha?', $k['monto'] ? '$' . number_format($k['monto'], 0, ',', '.') : '¿monto?', $k['mensaje'] ? '«' . $k['mensaje'] . '»' : '', $estado === 'OK' ? '' : $det);
}

// 3) Imágenes o documentos que los clientes mandaron al celular (historial) y que no tienen reserva
echo "\n==== IMAGENES/DOCUMENTOS ENVIADOS AL CELULAR SIN RESERVA (revisar en el chat) ====\n";
$conComp = array_flip(array_map(function ($k) { return n9($k['tel']); }, $comp));
$img = DB::table('wa_historial')->where('desde_negocio', 0)->whereIn('tipo', ['image', 'document'])->where('enviado_at', '>=', $desde)
    ->select('cliente', DB::raw('MAX(enviado_at) ultimo'), DB::raw('COUNT(*) n'))->groupBy('cliente')->orderBy('ultimo')->get();
$sinRes = 0;
foreach ($img as $i) {
    if (isset($conComp[n9($i->cliente)]) || reservasDe($i->cliente)) { continue; }
    $sinRes++;
    $ult = DB::table('wa_historial')->where('cliente', $i->cliente)->where('desde_negocio', 0)->where('tipo', 'text')->orderBy('enviado_at', 'desc')->value('texto');
    printf("+%s  %d archivo(s), último %s  · último texto: %s\n", $i->cliente, $i->n, substr($i->ultimo, 0, 16), mb_substr(str_replace("\n", ' ', (string) $ult), 0, 110));
}
echo "\nRESUMEN: comprobantes OK {$cuenta['OK']} · REVISAR {$cuenta['REVISAR']} · FALTA {$cuenta['FALTA']} · imágenes sin reserva {$sinRes}\n";
