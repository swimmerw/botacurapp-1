import re, sys, shutil, time, os, glob
# Bot: revisar el comprobante ANTES de crear la reserva (v2, 13/10/2026).
#  - RECHAZO (no se crea nada, se le explica al cliente): no es comprobante, la plata fue a otra
#    cuenta, o el monto es menor al abono.
#  - DUDA (queda para que una persona del equipo lo apruebe en Finanzas > Reservas del dia):
#    no se lee el destinatario, el monto, la fecha o la hora; la fecha y hora de la transferencia
#    no calzan con la conversacion; el N. de operacion ya se uso; o la lectura automatica fallo.
#    El cupo queda apartado 24 h mientras se revisa.
#  - OK: igual que antes (se crea la reserva y queda en revision manual del pago).
B = os.environ.get('BASE', '/var/www/html/botacurapp/')
F = B + 'app/Http/Controllers/Api/WhatsAppWebhookController.php'
s = open(F, encoding='utf-8').read()
if 'clasificarComprobante' in s:
    sys.exit('Ya estaba aplicado.')
if 'motivosRechazoComprobante' in s:
    # Tenia la version 1: volver al respaldo anterior a ella
    previos = [b for b in sorted(glob.glob(F + '.bak.*'), reverse=True)
               if 'motivosRechazoComprobante' not in open(b, encoding='utf-8').read()]
    if not previos:
        sys.exit('ERROR: tiene la version 1 y no encontre su respaldo. No se cambio nada.')
    s = open(previos[0], encoding='utf-8').read()
    print('Version 1 reemplazada (base: %s)' % os.path.basename(previos[0]))


def flex(t):
    return r'\s*'.join(re.escape(x) for x in t.split())


def sub1(txt, pat, repl, nombre):
    n = len(re.findall(pat, txt))
    if n != 1:
        sys.exit('ERROR: "%s" aparece %d veces (se esperaba 1). No se cambio nada.' % (nombre, n))
    return re.sub(pat, lambda m: repl(m) if callable(repl) else repl, txt, count=1)


# 1. Cupo apartado (hold): revisar antes de convertirlo en reserva
s = sub1(s, flex("$conversion = app(\\App\\Http\\Controllers\\Api\\BotReservaController::class)->convertirHoldEnReserva($holdPendiente);"), lambda m: (
    "// Revisar el comprobante ANTES de crear la reserva (13/10/2026)\n"
    "            $datosComprobante = $this->extraerDatosComprobante($rutaTemp);\n"
    "            $revision = $datosComprobante === null\n"
    "                ? ['rechazo' => [], 'duda' => ['no se pudo leer el comprobante automaticamente']]\n"
    "                : $this->clasificarComprobante($datosComprobante, (int) $holdPendiente->abono_50,\n"
    "                    \\Carbon\\Carbon::parse($holdPendiente->created_at)->subMinutes(30));\n"
    "            if ($revision['rechazo']) {\n"
    "                $this->guardarImagenComprobante($rutaTemp, 'rechazado_hold_' . $holdPendiente->id);\n"
    "                if (file_exists($rutaTemp)) { @unlink($rutaTemp); }\n"
    "                return $this->respuestaComprobanteRechazado($telefono, $revision['rechazo'], 'hold_' . $holdPendiente->id,\n"
    "                    'El cupo temporal sigue apartado hasta las ' . \\Carbon\\Carbon::parse($holdPendiente->expira_en)->format('H:i') . ' hrs.');\n"
    "            }\n"
    "            if ($revision['duda']) {\n"
    "                $archivo = $this->guardarImagenComprobante($rutaTemp, 'revision_hold_' . $holdPendiente->id);\n"
    "                if (file_exists($rutaTemp)) { @unlink($rutaTemp); }\n"
    "                return $this->comprobanteARevision($telefono, $holdPendiente, $archivo, $datosComprobante ?? [], $revision['duda']);\n"
    "            }\n\n"
    "            " + m.group(0)), 'convertirHoldEnReserva')
s = sub1(s, flex("$resultadoHold = $this->guardarComprobante($rutaTemp, $ventaPendienteHold);"),
         "$resultadoHold = $this->guardarComprobante($rutaTemp, $ventaPendienteHold, $datosComprobante);", 'guardar hold')

# 2. Venta pendiente (reserva ya creada): rechazo directo o alerta para el equipo
s = sub1(s, flex("$resultado = $this->guardarComprobante($rutaTemp, $ventaPendiente);"), lambda m: (
    "$datosComprobante = $this->extraerDatosComprobante($rutaTemp);\n"
    "            $dudas = ['no se pudo leer el comprobante automaticamente'];\n"
    "            if ($datosComprobante !== null) {\n"
    "                $minimo = array_filter([(int) $ventaPendiente->abono_programa, (int) $ventaPendiente->diferencia_programa]);\n"
    "                $revision = $this->clasificarComprobante($datosComprobante, $minimo ? min($minimo) : 0, now()->subHours(3));\n"
    "                if ($revision['rechazo']) {\n"
    "                    $this->guardarImagenComprobante($rutaTemp, 'rechazado_venta_' . $ventaPendiente->venta_id);\n"
    "                    if (file_exists($rutaTemp)) { @unlink($rutaTemp); }\n"
    "                    return $this->respuestaComprobanteRechazado($telefono, $revision['rechazo'], 'venta_' . $ventaPendiente->venta_id,\n"
    "                        'La reserva N°' . $ventaPendiente->reserva_id . ' sigue pendiente de pago.');\n"
    "                }\n"
    "                $dudas = $revision['duda'];\n"
    "            }\n"
    "            $resultado = $this->guardarComprobante($rutaTemp, $ventaPendiente, $datosComprobante, $dudas);"), 'guardar venta')

# 3. guardarComprobante reutiliza la lectura y suma las dudas a la alerta
s = sub1(s, flex("private function guardarComprobante(string $rutaTemp, $ventaPendiente): array"),
         "private function guardarComprobante(string $rutaTemp, $ventaPendiente, ?array $datosPrevios = null, array $alertasPrevias = []): array",
         'firma guardarComprobante')
s = sub1(s, flex("$datos = $this->extraerDatosComprobante($rutaTemp) ?? [];"),
         "$datos = $datosPrevios ?? ($this->extraerDatosComprobante($rutaTemp) ?? []);", 'datos guardarComprobante')
s = sub1(s, flex("$tipoDetectado = 'no_detectado'; $alertas = [];"),
         "$tipoDetectado = 'no_detectado';\n            $alertas = $alertasPrevias;", 'alertas iniciales')

# 4. Metodos nuevos
NUEVOS = r'''/**
     * Clasifica un comprobante: 'rechazo' = claramente no sirve; 'duda' = lo revisa una persona.
     * Sin motivos en ninguna lista = se acepta y sigue el flujo normal (revision manual del pago).
     */
    private function clasificarComprobante(array $datos, int $minimo, $desde): array
    {
        if (($datos['es_comprobante'] ?? true) === false) {
            return ['rechazo' => ['la imagen no parece un comprobante de transferencia'], 'duda' => []];
        }
        $rechazo = [];
        $duda = [];
        $banco = config('botacura_pago.datos_bancarios_transferencia', []);
        $alfa = function ($v) { return ltrim(strtoupper(preg_replace('/[^0-9K]/i', '', (string) $v)), '0'); };
        $digitos = function ($v) { return ltrim(preg_replace('/\D/', '', (string) $v), '0'); };
        $texto = function ($v) { return strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $v)); };

        // Destinatario
        $rut = $datos['destinatario_rut'] ?? null;
        $nombre = $datos['destinatario_nombre'] ?? null;
        $cuentaBotacura = $digitos($banco['numero_cuenta'] ?? '');
        $cuentaLeida = $digitos($datos['destinatario_cuenta'] ?? null);
        $rutMal = $rut && $alfa($banco['rut'] ?? '') !== '' && $alfa($rut) !== $alfa($banco['rut'] ?? '');
        // Cuentas enmascaradas (****3128): basta que calcen los ultimos digitos leidos
        $cuentaMal = strlen($cuentaLeida) >= 4 && $cuentaBotacura !== ''
            && substr($cuentaBotacura, -strlen($cuentaLeida)) !== $cuentaLeida
            && substr($cuentaLeida, -strlen($cuentaBotacura)) !== $cuentaBotacura;
        $nombreMal = $nombre && strpos($texto($nombre), 'BOTACUR') === false
            && ($texto($banco['titular'] ?? '') === '' || strpos($texto($nombre), $texto($banco['titular'] ?? '')) === false);
        if ($rutMal || $cuentaMal || $nombreMal) {
            $rechazo[] = 'la transferencia no fue hecha a la cuenta de Botacura' . ($nombre ? ' (destinatario: ' . mb_substr($nombre, 0, 60) . ')' : '');
        } elseif (!$rut && $cuentaLeida === '' && !$nombre) {
            $duda[] = 'no se ve a quien se hizo la transferencia (nombre, RUT o cuenta de destino)';
        }

        // Monto
        $monto = (isset($datos['monto']) && is_numeric($datos['monto'])) ? (int) $datos['monto'] : null;
        if ($monto === null) {
            $duda[] = 'no se lee el monto transferido';
        } elseif ($minimo > 0 && $monto < $minimo * 0.97) {
            $rechazo[] = 'el monto transferido ($' . number_format($monto, 0, ',', '.') . ') es menor al abono que corresponde ($'
                . number_format($minimo, 0, ',', '.') . ')';
        }

        // Fecha y hora: deben calzar con la conversacion (desde que se pidio el pago hasta ahora)
        $fecha = (!empty($datos['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $datos['fecha'])) ? $datos['fecha'] : null;
        $hora = (!empty($datos['hora']) && preg_match('/^(\d{1,2}):(\d{2})/', $datos['hora'], $h)) ? sprintf('%02d:%02d', $h[1], $h[2]) : null;
        $desdeTxt = $desde->copy()->timezone('America/Santiago')->format('d/m H:i');
        if ($fecha === null) {
            $duda[] = 'no se lee la fecha de la transferencia';
        } elseif ($hora === null) {
            $duda[] = 'no se lee la hora de la transferencia';
            $hoy = now()->timezone('America/Santiago')->toDateString();
            if ($fecha < $desde->copy()->timezone('America/Santiago')->toDateString() || $fecha > $hoy) {
                $duda[] = 'la fecha del comprobante (' . date('d/m/Y', strtotime($fecha)) . ') no coincide con la conversacion';
            }
        } else {
            try {
                $momento = \Carbon\Carbon::parse($fecha . ' ' . $hora, 'America/Santiago');
                if ($momento->lt($desde) || $momento->gt(now()->addMinutes(10))) {
                    $duda[] = 'la fecha y hora del comprobante (' . $momento->format('d/m H:i') . ') no coinciden con la conversacion (desde '
                        . $desdeTxt . ' hasta la recepcion)';
                }
            } catch (\Exception $e) {
                $duda[] = 'no se entiende la fecha u hora del comprobante';
            }
        }

        // N. de operacion repetido
        $op = trim((string) ($datos['numero_operacion'] ?? ''));
        if (strlen(preg_replace('/\W/', '', $op)) >= 4) {
            $usada = DB::table('ventas')->where('comprobante_numero_operacion', $op)->value('id_reserva');
            if ($usada) {
                $duda[] = 'el N° de operacion ' . $op . ' ya se uso en la reserva N°' . $usada;
            }
        }

        return ['rechazo' => $rechazo, 'duda' => $duda];
    }

    private function guardarImagenComprobante(string $rutaTemp, string $prefijo): ?string
    {
        try {
            $nombre = $prefijo . '_' . now()->format('Ymd_His') . '_' . uniqid() . '.jpg';
            Storage::disk('comprobante_transferencia')->put($nombre, file_get_contents($rutaTemp));
            return $nombre;
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp] No se pudo guardar la imagen del comprobante: ' . $e->getMessage());
            return null;
        }
    }

    /** Comprobante con dudas: queda para que el equipo lo apruebe en Finanzas > Reservas del dia. */
    private function comprobanteARevision(string $telefono, $hold, ?string $archivo, array $datos, array $dudas): string
    {
        try {
            DB::table('comprobantes_revision')->insert([
                'hold_id' => $hold->id, 'telefono' => preg_replace('/\D/', '', $telefono),
                'phone_number_id' => $this->phoneNumberId, 'archivo' => $archivo,
                'datos_json' => json_encode($datos, JSON_UNESCAPED_UNICODE), 'motivos' => implode(' | ', $dudas),
                'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
            ]);
            // El cupo queda apartado mientras el equipo revisa
            DB::table('reserva_holds')->where('id', $hold->id)->update(['expira_en' => now()->addHours(24), 'updated_at' => now()]);
            \App\Services\BotCasoService::registrar('comprobante_en_revision', $telefono, ['motivos' => implode('; ', $dudas)]);
        } catch (\Throwable $e) {
            Log::error('[WhatsApp] No se pudo dejar el comprobante en revision: ' . $e->getMessage());
        }
        Log::info('[WhatsApp] Comprobante en revision humana', ['hold_id' => $hold->id, 'dudas' => $dudas]);
        $correo = \App\BotSeccionTexto::where('clave', 'alerta_correo')->value('contenido');
        if ($correo) {
            try {
                \Mail::raw("Un comprobante quedo esperando revision antes de crear la reserva.\n\nCliente: {$hold->nombre} (+{$telefono})\n"
                    . "Motivos: " . implode('; ', $dudas) . "\n\nApruebalo o rechazalo en " . url('/finanzas/reservas-del-dia'),
                    function ($m) use ($correo, $hold) { $m->to($correo)->subject('Bot: comprobante por revisar de ' . $hold->nombre); });
            } catch (\Throwable $e) {
            }
        }

        return "[Sistema-comprobante-en-revision: Se recibio el comprobante, pero hay datos que una persona del equipo debe comprobar. "
            . "Todavia NO se creo la reserva. El cupo queda apartado mientras se revisa. Dile al cliente con amabilidad, en 1 a 2 lineas, "
            . "que recibimos su comprobante y que una persona del equipo lo revisara y le confirmara la reserva por este mismo chat a la "
            . "brevedad. Si quiere, puede mandar una captura mas clara o completa. NO le digas que la reserva esta confirmada ni le "
            . "cuentes los motivos internos de la revision.]";
    }

    private function respuestaComprobanteRechazado(string $telefono, array $motivos, string $clave, string $estado): string
    {
        $k = 'comprobante_rechazos_' . $clave;
        \Cache::add($k, 0, now()->addDays(2));
        $intento = (int) \Cache::increment($k);
        $lista = implode('; ', $motivos);
        Log::info('[WhatsApp] Comprobante rechazado', ['telefono' => $telefono, 'clave' => $clave, 'intento' => $intento, 'motivos' => $motivos]);
        try {
            \App\Services\BotCasoService::registrar('comprobante_rechazado', $telefono, ['motivos' => $lista, 'intento' => $intento]);
        } catch (\Throwable $e) {
        }
        $correo = \App\BotSeccionTexto::where('clave', 'alerta_correo')->value('contenido');
        if ($correo) {
            try {
                \Mail::raw("El bot NO acepto un comprobante (intento {$intento}).\n\nCliente: +{$telefono}\nMotivos: {$lista}\n{$estado}",
                    function ($m) use ($correo, $telefono) { $m->to($correo)->subject('Bot: comprobante rechazado de +' . $telefono); });
            } catch (\Throwable $e) {
            }
        }
        $b = config('botacura_pago.datos_bancarios_transferencia', []);
        $datosBanco = trim(($b['titular'] ?? '') . ', RUT ' . ($b['rut'] ?? '') . ', ' . ($b['banco'] ?? '') . ', '
            . ($b['tipo_cuenta'] ?? '') . ' N° ' . ($b['numero_cuenta'] ?? '') . ', correo ' . ($b['correo_comprobante'] ?? ''));

        return "[Sistema-comprobante-rechazado: El comprobante NO se acepto y NO se creo ni se confirmo ninguna reserva. "
            . "Motivos: {$lista}. {$estado} Explicale al cliente con amabilidad, en 2 a 3 lineas, que el comprobante no calza "
            . "y por que (puedes decirle los motivos), recuerdale los datos para transferir ({$datosBanco}) y pidele que envie "
            . "el comprobante correcto, completo y legible. NO digas que la reserva quedo creada, confirmada ni en revision."
            . ($intento >= 2
                ? " Es el intento N°{$intento}: ademas dile que una persona del equipo lo revisara por este mismo chat y deriva a humano."
                : '')
            . "]";
    }

    '''
s = sub1(s, flex("private function extraerDatosComprobante(string $rutaLocal): ?array"),
         lambda m: NUEVOS + m.group(0), 'extraerDatosComprobante')

shutil.copy(F, F + '.bak.' + time.strftime('%Y%m%d%H%M'))
open(F, 'w', encoding='utf-8').write(s)
print('OK')
