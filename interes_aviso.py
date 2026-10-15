import re, sys, shutil, time, os
# Dias pedidos sin habilitar: aviso al equipo (correo + WhatsApp via Home Assistant) cuando una fecha
# pasa el umbral de personas (BOT_INTERES_UMBRAL, 10). Una sola vez por fecha.
B = os.environ.get('BASE', '/var/www/html/botacurapp/')
F = B + 'app/Services/BotInteresFechasService.php'
s = open(F, encoding='utf-8').read()
if 'revisarUmbral' in s:
    sys.exit('Ya estaba aplicado.')


def flex(t):
    return r'\s*'.join(re.escape(x) for x in t.split())


def sub1(txt, pat, repl, nombre):
    n = len(re.findall(pat, txt))
    if n != 1:
        sys.exit('ERROR: "%s" aparece %d veces (se esperaba 1). No se cambio nada.' % (nombre, n))
    return re.sub(pat, lambda m: repl(m), txt, count=1)


s = sub1(s, flex("'consultas' => $reciente ? $fila->consultas : DB::raw('consultas + 1'), 'updated_at' => now(), ]);"),
         lambda m: m.group(0) + "\n                self::revisarUmbral($fecha);", 'update consulta')
s = sub1(s, flex("'estado' => 'consulta', 'created_at' => now(), 'updated_at' => now(), ]);"),
         lambda m: m.group(0) + "\n            self::revisarUmbral($fecha);", 'insert consulta')
s = sub1(s, flex("try { BotCasoService::registrar('interes_dia_cerrado',"),
         lambda m: "self::revisarUmbral($fecha);\n        " + m.group(0), 'anotar')

METODOS = r'''/**
     * Si la fecha pasa el umbral de personas, avisa al equipo una sola vez (correo de alertas del bot
     * y WhatsApp al administrador a traves de Home Assistant, HA_ALERTA_WEBHOOK). 15/10/2026.
     */
    public static function revisarUmbral(string $fecha): void
    {
        try {
            $clave = 'interes_umbral_avisado_' . $fecha;
            if (\Cache::has($clave)) {
                return;
            }
            $filas = DB::table(self::TABLA)->where('fecha', $fecha)->where('estado', '<>', 'descartado')->get();
            $personas = $filas->sum(function ($r) {
                return max(1, (int) $r->personas);
            });
            if ($personas <= self::umbral()) {
                return;
            }
            \Cache::forever($clave, now()->toDateTimeString());
            $programas = self::nombresProgramas();
            $dia = Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM');
            $anotados = $filas->whereIn('estado', ['interesado', 'avisado'])->count();
            $corto = 'Dia pedido sin habilitar: ' . $dia . ' ya suma ' . $personas . ' personas en ' . $filas->count()
                . ' consultas (' . $anotados . ' pidieron aviso). Evalua habilitarlo: Conversaciones del bot > Dias pedidos sin habilitar.';
            Log::info('[BotInteres] Fecha sobre el umbral', ['fecha' => $fecha, 'personas' => $personas]);

            $correo = \App\BotSeccionTexto::where('clave', 'alerta_correo')->value('contenido');
            if ($correo) {
                $lineas = $filas->map(function ($r) use ($programas) {
                    return '- ' . ($r->nombre ?: 'Sin nombre') . ' (+' . $r->telefono . '): ' . max(1, (int) $r->personas) . ' persona(s)'
                        . ($r->programa_id ? ', ' . ($programas[$r->programa_id] ?? 'programa #' . $r->programa_id) : '')
                        . ', ' . ($r->estado === 'consulta' ? 'solo consulto' : ($r->estado === 'interesado' ? 'pidio aviso' : $r->estado));
                })->implode("\n");
                try {
                    \Mail::raw($corto . "\n\n" . $lineas . "\n\n" . url('/bot-conversaciones'), function ($m) use ($correo, $dia, $personas) {
                        $m->to($correo)->subject('Botacura: ' . $dia . ' ya suma ' . $personas . ' personas interesadas');
                    });
                } catch (\Throwable $e) {
                    Log::warning('[BotInteres] Aviso por correo no enviado: ' . $e->getMessage());
                }
            }

            $url = trim((string) env('HA_ALERTA_WEBHOOK', ''));
            if ($url !== '') {
                try {
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                        CURLOPT_POSTFIELDS => json_encode(['mensaje' => $corto], JSON_UNESCAPED_UNICODE),
                    ]);
                    curl_exec($ch);
                    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($code >= 300 || $code === 0) {
                        Log::warning('[BotInteres] Aviso por WhatsApp (Home Assistant) respondio HTTP ' . $code);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[BotInteres] Aviso por WhatsApp no enviado: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BotInteres] revisarUmbral: ' . $e->getMessage());
        }
    }

    '''
s = sub1(s, flex("public static function textoAviso(?string $nombre, string $fecha): string"),
         lambda m: METODOS + m.group(0), 'textoAviso')
shutil.copy(F, F + '.bak.' + time.strftime('%Y%m%d%H%M%S'))
open(F, 'w', encoding='utf-8').write(s)
print('Aviso de umbral agregado (correo + WhatsApp via Home Assistant)')
