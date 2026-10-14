import re, sys, shutil, time, os
# Frase clave "#reiniciar" (solo numeros del equipo): el bot empieza de cero.
# Archiva la conversacion (activo = 0), cancela el cupo apartado y los comprobantes en revision,
# y el historial del caso solo considera lo ocurrido despues del reinicio. No se borra nada.
B = os.environ.get('BASE', '/var/www/html/botacurapp/')
W = B + 'app/Http/Controllers/Api/WhatsAppWebhookController.php'
C = B + 'app/Services/BotCasoService.php'
w = open(W, encoding='utf-8').read()
c = open(C, encoding='utf-8').read()
ts = time.strftime('%Y%m%d%H%M%S')


def flex(t):
    return r'\s*'.join(re.escape(x) for x in t.split())


def sub1(txt, pat, repl, nombre):
    n = len(re.findall(pat, txt))
    if n != 1:
        sys.exit('ERROR: "%s" aparece %d veces (se esperaba 1). No se cambio nada.' % (nombre, n))
    return re.sub(pat, lambda m: repl(m) if callable(repl) else repl, txt, count=1)


if 'reiniciarConversacion' not in w:
    w = sub1(w, flex("$nombre = $value['contacts'][0]['profile']['name'] ?? 'Cliente';"), lambda m: m.group(0) + """

            // Frase clave del equipo para empezar la conversacion de cero (13/10/2026)
            if ($tipo === 'text' && $this->esReinicioDelEquipo($telefono, $msg['text']['body'] ?? '')) {
                $this->reiniciarConversacion($telefono);
                return response()->json(['ok' => true]);
            }""", 'nombre del contacto')
    METODOS = r'''/** "#reiniciar" (o BOT_FRASE_REINICIO) enviado desde un numero del equipo (BOT_TELEFONOS_EQUIPO). */
    private function esReinicioDelEquipo(string $telefono, string $texto): bool
    {
        $frase = mb_strtolower(trim((string) env('BOT_FRASE_REINICIO', '#reiniciar')));
        if ($frase === '' || mb_strtolower(trim($texto)) !== $frase) {
            return false;
        }
        $equipo = array_filter(array_map(function ($t) {
            return substr(preg_replace('/\D/', '', $t), -9);
        }, explode(',', (string) env('BOT_TELEFONOS_EQUIPO', '56961910398,56940014282,56961249012'))));
        return in_array(substr(preg_replace('/\D/', '', $telefono), -9), $equipo, true);
    }

    /** Deja al numero como cliente nuevo para el bot. Lo anterior queda archivado (no se borra). */
    private function reiniciarConversacion(string $telefono)
    {
        $tel = preg_replace('/\D/', '', $telefono);
        $corto = substr($tel, -9);
        $variantes = array_values(array_unique([$tel, $corto, '56' . $corto]));
        $res = ['conversaciones' => 0, 'cupos' => 0, 'comprobantes' => 0];
        try {
            $res['conversaciones'] = DB::table('bot_conversaciones')->whereIn('usuario_id', $variantes)->where('activo', 1)
                ->update(array_merge(['activo' => 0, 'updated_at' => now()],
                    \Schema::hasColumn('bot_conversaciones', 'motivo_cierre') ? ['motivo_cierre' => 'Reiniciada con frase clave del equipo'] : []));
            $res['cupos'] = DB::table('reserva_holds')->whereIn('telefono', $variantes)->where('estado', 'activo')
                ->update(['estado' => 'cancelado', 'updated_at' => now()]);
            if (\Schema::hasTable('comprobantes_revision')) {
                $res['comprobantes'] = DB::table('comprobantes_revision')->whereIn('telefono', $variantes)->where('estado', 'pendiente')
                    ->update(['estado' => 'cancelado', 'nota' => 'Conversacion reiniciada', 'updated_at' => now()]);
            }
            \App\BotMensajePendiente::whereIn('telefono', $variantes)->where('estado', 'pendiente')
                ->update(['estado' => 'atendido', 'updated_at' => now()]);
            \App\Services\BotCasoService::registrar('conversacion_reiniciada', $tel, $res);
            Log::info('[WhatsApp] Conversacion reiniciada con frase clave', ['telefono' => $tel] + $res);
            $this->enviarMensaje($telefono, "Conversación reiniciada. El bot empieza de cero"
                . ($res['cupos'] ? " (se cancelaron {$res['cupos']} cupo(s) apartado(s))" : '')
                . ". Lo anterior quedó archivado. Escribe tu primer mensaje como cliente nuevo.");
        } catch (\Throwable $e) {
            Log::error('[WhatsApp] No se pudo reiniciar la conversacion: ' . $e->getMessage());
            $this->enviarMensaje($telefono, 'No se pudo reiniciar la conversación: ' . mb_substr($e->getMessage(), 0, 150));
        }
    }

    '''
    w = sub1(w, flex("private function normalizarTelefono(string $telefono)"), lambda m: METODOS + m.group(0), 'normalizarTelefono')
    shutil.copy(W, W + '.bak.' + ts)
    open(W, 'w', encoding='utf-8').write(w)
    print('Webhook: frase #reiniciar agregada')
else:
    print('Webhook: ya estaba')

if 'ultimoReinicio' not in c:
    c = sub1(c, flex("->where('created_at', '>=', now()->subDays($dias))"),
             lambda m: m.group(0) + "\n                ->where('id', '>', self::ultimoReinicio($tel))", 'filtro eventos')
    c = sub1(c, flex("/** Eventos del caso (más antiguo primero). */") if "/** Eventos del caso (más antiguo primero). */" in c
             else flex("public static function eventos(string $tel, int $dias = 60, int $max = 20)"),
             lambda m: '''/** Id del ultimo reinicio con frase clave: lo anterior ya no cuenta como historial del caso. */
    private static function ultimoReinicio(string $tel): int
    {
        try {
            return (int) DB::table('bot_eventos')->whereIn('telefono', self::variantes($tel))
                ->where('tipo', 'conversacion_reiniciada')->max('id');
        } catch (\\Throwable $e) {
            return 0;
        }
    }

    ''' + m.group(0), 'inicio de eventos')
    shutil.copy(C, C + '.bak.' + ts)
    open(C, 'w', encoding='utf-8').write(c)
    print('Historial del caso: ignora lo anterior al reinicio')
else:
    print('Historial del caso: ya estaba')
