# 10/11/2026: (1) el bot no da datos para transferir ni pide comprobante sin un cupo apartado o reserva;
# (2) comprobantes con dia y mes invertidos (10/11 leido como 11/10) ya no quedan en duda.
import sys, shutil, time
B = '/var/www/html/botacurapp/app/Http/Controllers/Api/'
C, W = B + 'BotController.php', B + 'WhatsAppWebhookController.php'
c = open(C, encoding='utf-8').read(); w = open(W, encoding='utf-8').read()
hecho = 0
if 'pideTransferenciaSinCupo' not in c:
    a1 = "        // Menú en PDF cuando el cliente lo pide\n"
    a2 = "    /**\n     * Ejecuta una acción pedida por la IA y devuelve el texto \"[Sistema-...]\" con el resultado.\n     */"
    if c.count(a1) != 1 or c.count(a2) != 1: sys.exit('NO_ENCONTRADO en BotController')
    c = c.replace(a1, """        // Control de pago: no se dan datos para transferir ni se pide el comprobante sin cupo apartado o reserva — 10/11/2026
        if (!$simulacion && $canalBot === 'whatsapp' && $this->pideTransferenciaSinCupo((string) ($respuesta['mensaje'] ?? ''), $usuarioId, $ejecutadas)) {
            Log::warning('[Bot] Pidió transferencia sin cupo apartado', ['telefono' => $usuarioId]);
            \\App\\Services\\BotAprendizajeService::evento('pago_sin_cupo', $usuarioId, ['mensaje' => mb_substr((string) ($respuesta['mensaje'] ?? ''), 0, 500)]);
            $h = $historial;
            $h[] = ['role' => 'assistant', 'content' => json_encode($respuesta, JSON_UNESCAPED_UNICODE)];
            $h[] = ['role' => 'user', 'content' => '[Sistema-validador: diste datos para transferir o pediste el comprobante, pero NO existe un cupo apartado ni una reserva para este cliente: si paga ahora, el pago no queda asociado a nada. Si ya tienes nombre, correo, programa, fecha, personas y medio de pago, y el cliente confirmó el resumen, usa AHORA la acción crear_reserva. Si falta algo, pídelo y NO des todavía los datos bancarios. Responde en el formato JSON.]'];
            $reintento = $this->llamarClaude($systemPrompt, $h, $nombre);
            if ($reintento && ($reintento['accion'] ?? '') === 'crear_reserva' && !isset($ejecutadas['crear_reserva'])) {
                $ejecutadas['crear_reserva'] = true;
                $contexto['historial'] = $historial;
                $ctx = $this->ejecutarAccion('crear_reserva', $reintento['datos'] ?? [], $usuarioId, $contexto, $simulacion);
                if ($ctx) {
                    $acciones[] = 'crear_reserva';
                    $historial[count($historial) - 1]['content'] .= "\\n\\n" . $ctx;
                    $reintento = $this->llamarClaude($systemPrompt, $historial, $nombre);
                }
            }
            if ($reintento && trim((string) ($reintento['mensaje'] ?? '')) !== ''
                && !$this->montosNoVerificados($reintento['mensaje'] ?? '', $systemPrompt, $historial)
                && !$this->pideTransferenciaSinCupo((string) $reintento['mensaje'], $usuarioId, $ejecutadas)) {
                $respuesta = $reintento;
            } else {
                $respuesta = ['accion' => 'responder', 'datos' => [], 'mensaje' => 'Antes de transferir, dejemos tu cupo apartado para que el pago quede asociado a tu reserva 🙌 ¿Me confirmas el resumen (programa, fecha, personas, nombre, correo y medio de pago) para apartarlo?'];
            }
        }

""" + a1)
    c = c.replace(a2, """    /** ¿El mensaje da los datos para transferir o pide el comprobante sin que exista cupo apartado ni reserva por pagar? — 10/11/2026 */
    private function pideTransferenciaSinCupo(string $msg, string $tel, array $ejecutadas): bool
    {
        if (isset($ejecutadas['crear_reserva']) || trim($msg) === '') {
            return false;
        }
        $banco  = config('botacura_pago.datos_bancarios_transferencia', []);
        $cuenta = ltrim(preg_replace('/\\D/', '', (string) ($banco['numero_cuenta'] ?? '')), '0');
        $daDatos  = $cuenta !== '' && strpos(preg_replace('/\\D/', '', $msg), $cuenta) !== false;
        $pideComp = (bool) preg_match('/(env[ií]a|manda|adjunta|m[aá]ndanos|env[ií]anos)[^.\\n]{0,40}comprobante|foto del comprobante/iu', $msg);
        if (!$daDatos && !$pideComp) {
            return false;
        }
        $corto = substr($tel, -9);
        try {
            $hold = DB::table('reserva_holds')->where(function ($w) use ($tel, $corto) {
                $w->whereIn('telefono', [$tel, $corto])->orWhere('datos_json', 'like', '%"telefono_chat":"' . $tel . '"%');
            })->where('estado', 'activo')->exists();
            if ($hold) {
                return false;
            }
            $venta = DB::table('ventas as v')->join('reservas as r', 'r.id', '=', 'v.id_reserva')->join('clientes as c', 'c.id', '=', 'r.cliente_id')
                ->whereIn('c.whatsapp_cliente', [$tel, $corto, '56' . $corto])->whereIn('v.estado_pago', ['pendiente', 'pendiente_verificacion'])
                ->where('r.fecha_visita', '>=', now()->toDateString())->exists();
            return !$venta;
        } catch (\\Throwable $e) {
            return false;
        }
    }

""" + a2)
    hecho += 1
if 'dia y mes invertidos' not in w:
    a3 = "        $hora = (!empty($datos['hora']) && preg_match('/^(\\d{1,2}):(\\d{2})/', $datos['hora'], $h)) ? sprintf('%02d:%02d', $h[1], $h[2]) : null;\n"
    if w.count(a3) != 1: sys.exit('NO_ENCONTRADO en webhook (fecha)')
    w = w.replace(a3, a3 + """        // Lectura con dia y mes invertidos (10/11 leido como 11/10): si invertidos calzan con la conversacion, se corrige — 10/11/2026
        if ($fecha) {
            list($yy, $mm, $dd) = explode('-', $fecha);
            $inv = $yy . '-' . $dd . '-' . $mm;
            $hoyS = now()->timezone('America/Santiago')->toDateString();
            $desdeS = $desde->copy()->timezone('America/Santiago')->toDateString();
            if (($fecha < $desdeS || $fecha > $hoyS) && (int) $dd <= 12 && checkdate((int) $dd, (int) $mm, (int) $yy) && $inv >= $desdeS && $inv <= $hoyS) {
                $fecha = $inv;
            }
        }
""")
    hecho += 1
st = time.strftime('%Y%m%d%H%M%S')
if hecho:
    for f, t in ((C, c), (W, w)):
        shutil.copy(f, f + '.bak.' + st); open(f, 'w', encoding='utf-8').write(t)
print(('PARCHE_OK ' + st) if hecho else 'YA_APLICADO')
