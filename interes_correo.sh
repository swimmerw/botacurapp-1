#!/bin/bash
# BotacurApp (AWS): suma al contador de "Dias pedidos sin habilitar" las consultas que llegan por correo. 15/10/2026
# - Columnas nuevas en bot_interes_fechas: correo, origen (whatsapp|correo)
# - API POST /api/cotizador/interes-fechas (token del cotizador) que usa n8n
# - Servicio: las filas de correo no se intentan avisar por WhatsApp (queda la nota con el correo)
set -e
cd /var/www/html/botacurapp
T=$(date +%Y%m%d%H%M%S)
S=app/Services/BotInteresFechasService.php
C=app/Http/Controllers/InteresFechasCorreoController.php
R=routes/api.php
grep -q "cotizador.token" app/Http/Kernel.php || { echo "No encuentro el middleware cotizador.token"; exit 1; }
cp -p $S $S.bak.$T; cp -p $R $R.bak.$T
restaurar(){ echo "ERROR: se restauran los archivos"; cp -p $S.bak.$T $S; cp -p $R.bak.$T $R; rm -f $C; exit 1; }
trap restaurar ERR

# 1) columnas
php -r '
require "vendor/autoload.php"; $app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
Schema::table("bot_interes_fechas", function ($t) {
  if (!Schema::hasColumn("bot_interes_fechas", "correo")) { $t->string("correo", 150)->nullable()->after("nombre"); }
  if (!Schema::hasColumn("bot_interes_fechas", "origen")) { $t->string("origen", 20)->default("whatsapp")->after("correo"); }
});
echo "Columnas correo/origen OK\n";'

# 2) servicio
python3 - "$S" <<'PY'
import re, sys
p = sys.argv[1]; s = open(p, encoding='utf-8').read()
if 'Consulto por correo' not in s:
    pat = r"(private static function enviar\(string \$tel, \?string \$nombre, string \$fecha\): array\s*\{)"
    if len(re.findall(pat, s)) != 1: sys.exit('enviar(): no calza')
    s = re.sub(pat, r"""\1
        if (strpos($tel, 'm') === 0) {
            $c = DB::table(self::TABLA)->where('telefono', $tel)->value('correo');
            return ['ok' => false, 'motivo' => 'Consulto por correo: avisar a ' . ($c ?: 'su correo')];
        }""", s, count=1)
    old = "' (+' . $r->telefono . '): '"
    if s.count(old) == 1:
        s = s.replace(old, "' (' . (!empty($r->correo) ? $r->correo : '+' . $r->telefono) . '): '")
    open(p, 'w', encoding='utf-8').write(s)
print('Servicio OK')
PY

# 3) controlador
cat > $C <<'PHP'
<?php

namespace App\Http\Controllers;

use App\Services\BotInteresFechasService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Consultas por correo de dias no habilitados (las manda n8n desde el borrador
 * "NO ENVIAR - INTERES FECHAS" que deja la tarea de correos). 15/10/2026.
 * Body: {"consultas":[{"mensaje_id","correo","nombre","telefono","fecha":"AAAA-MM-DD","personas","programa_id"}]}
 */
class InteresFechasCorreoController extends Controller
{
    public function store(Request $request)
    {
        $lista = $request->input('consultas', []);
        if (!is_array($lista)) {
            return response()->json(['ok' => false, 'error' => 'consultas debe ser una lista'], 422);
        }
        $res = [];
        foreach (array_slice($lista, 0, 100) as $c) {
            $res[] = $this->una(is_array($c) ? $c : []);
        }
        return response()->json(['ok' => true, 'resultado' => $res]);
    }

    private function una(array $c): array
    {
        $correo = mb_strtolower(trim((string) ($c['correo'] ?? '')));
        try {
            $fecha = Carbon::parse((string) ($c['fecha'] ?? ''))->toDateString();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'fecha_invalida', 'correo' => $correo];
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'correo_invalido', 'fecha' => $fecha];
        }
        if ($fecha <= now()->toDateString()) {
            return ['ok' => false, 'error' => 'fecha_pasada', 'fecha' => $fecha];
        }
        if (\App\FechaDisponible::where('fecha', $fecha)->where('habilitada', true)->exists()) {
            return ['ok' => false, 'error' => 'ya_habilitada', 'fecha' => $fecha];
        }
        $msg = trim((string) ($c['mensaje_id'] ?? ''));
        $claveMsg = $msg !== '' ? 'interes_correo_' . md5($msg . '|' . $fecha) : null;
        if ($claveMsg && Cache::has($claveMsg)) {
            return ['ok' => true, 'fecha' => $fecha, 'repetido' => true];
        }
        // Si el cliente dejo su telefono, se junta con la fila de WhatsApp; si no, clave propia del correo.
        $tel = preg_replace('/\D/', '', (string) ($c['telefono'] ?? ''));
        if (strlen($tel) === 9) {
            $tel = '56' . $tel;
        }
        if (strlen($tel) < 11 || strlen($tel) > 15) {
            $tel = 'm' . substr(sha1($correo), 0, 19);
        }
        $nombre = mb_substr(trim((string) ($c['nombre'] ?? '')), 0, 110) ?: null;
        $extra = [];
        if (!empty($c['personas']) && (int) $c['personas'] > 0) {
            $extra['personas'] = min(500, (int) $c['personas']);
        }
        if (!empty($c['programa_id']) && (int) $c['programa_id'] > 0) {
            $extra['programa_id'] = (int) $c['programa_id'];
        }
        $fila = DB::table('bot_interes_fechas')->where('telefono', $tel)->where('fecha', $fecha)->first();
        if ($fila) {
            DB::table('bot_interes_fechas')->where('id', $fila->id)->update($extra + [
                'consultas'  => DB::raw('consultas + 1'),
                'correo'     => $fila->correo ?: $correo,
                'nombre'     => $fila->nombre ?: ($nombre ? '✉ ' . $nombre : null),
                'estado'     => in_array($fila->estado, ['avisado', 'descartado'], true) ? $fila->estado : 'interesado',
                'anotado_at' => $fila->anotado_at ?: now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('bot_interes_fechas')->insert($extra + [
                'telefono'   => $tel,
                'nombre'     => '✉ ' . ($nombre ?: $correo),
                'correo'     => $correo,
                'origen'     => 'correo',
                'fecha'      => $fecha,
                'consultas'  => 1,
                'estado'     => 'interesado',
                'anotado_at' => now(),
                'nota'       => 'Consulto por correo: ' . $correo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if ($claveMsg) {
            Cache::put($claveMsg, 1, now()->addDays(120));
        }
        BotInteresFechasService::revisarUmbral($fecha);
        return ['ok' => true, 'fecha' => $fecha];
    }
}
PHP

# 4) ruta
grep -q "InteresFechasCorreoController" $R || printf "\n// Consultas por correo de dias no habilitados (n8n). 15/10/2026\nRoute::middleware('cotizador.token')->post('cotizador/interes-fechas', '\\\\App\\\\Http\\\\Controllers\\\\InteresFechasCorreoController@store');\n" >> $R

php -l $S >/dev/null && php -l $C >/dev/null && php -l $R >/dev/null
php artisan route:clear >/dev/null; php artisan config:clear >/dev/null
php artisan route:list 2>/dev/null | grep -q "cotizador/interes-fechas" || { echo "La ruta no aparece"; false; }
trap - ERR
echo "Ruta OK: POST /api/cotizador/interes-fechas"
echo "--- Vista (para revisar los enlaces de WhatsApp) ---"
grep -rn "wa.me\|telefono" resources/views --include=*.blade.php | grep -i "interes\|personas_detalle\|dias" | head -8 || true
grep -rln "personas_detalle" resources/views | head -3
echo "LISTO"
