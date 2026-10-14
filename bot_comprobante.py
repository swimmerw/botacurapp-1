import re, sys, shutil, time, os
# Bot: revisar el comprobante ANTES de crear la reserva (13/10/2026).
# Si no es comprobante, no es a la cuenta de Botacura, el monto no alcanza o la fecha no
# corresponde, NO se crea la reserva: el bot le explica al cliente que no calza, le da los
# datos de transferencia y le pide el correcto. Al 2.o intento deriva a una persona.
# Si la lectura automatica falla (API caida), sigue como antes: pasa a revision manual.
B = os.environ.get('BASE', '/var/www/html/botacurapp/')
F = B + 'app/Http/Controllers/Api/WhatsAppWebhookController.php'
s = open(F, encoding='utf-8').read()
if 'motivosRechazoComprobante' in s:
    sys.exit('Ya estaba aplicado.')


def flex(t):
    return r'\s*'.join(re.escape(x) for x in t.split())


def sub1(txt, pat, repl, nombre):
    n = len(re.findall(pat, txt))
    if n != 1:
        sys.exit('ERROR: "%s" aparece %d veces (se esperaba 1). No se cambio nada.' % (nombre, n))
    return re.sub(pat, lambda m: repl(m) if callable(repl) else repl, txt, count=1)


# 1. Camino del cupo apartado (hold): revisar antes de convertirlo en reserva
s = sub1(s, flex("$conversion = app(\\App\\Http\\Controllers\\Api\\BotReservaController::class)->convertirHoldEnReserva($holdPendiente);"), lambda m: (
    "// Revisar el comprobante ANTES de crear la reserva (13/10/2026)\n"
    "            $datosComprobante = $this->extraerDatosComprobante($rutaTemp);\n"
    "            if ($datosComprobante !== null) {\n"
    "                $motivos = $this->motivosRechazoComprobante($datosComprobante, (int) $holdPendiente->abono_50,\n"
    "                    \\Carbon\\Carbon::parse($holdPendiente->created_at)->subDay());\n"
    "                if ($motivos) {\n"
    "                    $this->guardarImagenRechazada($rutaTemp, 'hold_' . $holdPendiente->id);\n"
    "                    if (file_exists($rutaTemp)) { @unlink($rutaTemp); }\n"
    "                    return $this->respuestaComprobanteRechazado($telefono, $motivos, 'hold_' . $holdPendiente->id,\n"
    "                        'El cupo temporal sigue apartado hasta las ' . \\Carbon\\Carbon::parse($holdPendiente->expira_en)->format('H:i') . ' hrs.');\n"
    "                }\n"
    "            }\n\n"
    "            " + m.group(0)), 'convertirHoldEnReserva')
s = sub1(s, flex("$resultadoHold = $this->guardarComprobante($rutaTemp, $ventaPendienteHold);"),
         "$resultadoHold = $this->guardarComprobante($rutaTemp, $ventaPendienteHold, $datosComprobante);", 'guardar hold')

# 2. Camino de la venta pendiente (reserva ya creada)
s = sub1(s, flex("$resultado = $this->guardarComprobante($rutaTemp, $ventaPendiente);"), lambda m: (
    "$datosComprobante = $this->extraerDatosComprobante($rutaTemp);\n"
    "            if ($datosComprobante !== null) {\n"
    "                $minimo = array_filter([(int) $ventaPendiente->abono_programa, (int) $ventaPendiente->diferencia_programa]);\n"
    "                $motivos = $this->motivosRechazoComprobante($datosComprobante, $minimo ? min($minimo) : 0, now()->subDays(3));\n"
    "                if ($motivos) {\n"
    "                    $this->guardarImagenRechazada($rutaTemp, 'venta_' . $ventaPendiente->venta_id);\n"
    "                    if (file_exists($rutaTemp)) { @unlink($rutaTemp); }\n"
    "                    return $this->respuestaComprobanteRechazado($telefono, $motivos, 'venta_' . $ventaPendiente->venta_id,\n"
    "                        'La reserva N°' . $ventaPendiente->reserva_id . ' sigue pendiente de pago.');\n"
    "                }\n"
    "            }\n"
    "            $resultado = $this->guardarComprobante($rutaTemp, $ventaPendiente, $datosComprobante);"), 'guardar venta')

# 3. guardarComprobante reutiliza la lectura ya hecha
s = sub1(s, flex("private function guardarComprobante(string $rutaTemp, $ventaPendiente): array"),
         "private function guardarComprobante(string $rutaTemp, $ventaPendiente, ?array $datosPrevios = null): array", 'firma guardarComprobante')
s = sub1(s, flex("$datos = $this->extraerDatosComprobante($rutaTemp) ?? [];"),
         "$datos = $datosPrevios ?? ($this->extraerDatosComprobante($rutaTemp) ?? []);", 'datos guardarComprobante')

# 4. Metodos nuevos
NUEVOS = r'''/**
     * Revisa un comprobante antes de aceptarlo. Devuelve los motivos de rechazo;
     * vacio = se acepta y queda en revision manual del equipo, como siempre.
     */
    private function motivosRechazoComprobante(array $datos, int $minimo, $desde): array
    {
        if (($datos['es_comprobante'] ?? true) === false) {
            return ['la imagen no parece un comprobante de transferencia'];
        }
        $motivos = [];
        $banco = config('botacura_pago.datos_bancarios_transferencia', []);
        $alfa = function ($v) { return ltrim(strtoupper(preg_replace('/[^0-9K]/i', '', (string) $v)), '0'); };
        $digitos = function ($v) { return ltrim(preg_replace('/\D/', '', (string) $v), '0'); };
        $texto = function ($v) { return strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $v)); };

        $rut = $datos['destinatario_rut'] ?? null;
        $cuenta = $datos['destinatario_cuenta'] ?? null;
        $nombre = $datos['destinatario_nombre'] ?? null;
        $cuentaBotacura = $digitos($banco['numero_cuenta'] ?? '');
        $cuentaLeida = $digitos($cuenta);

        $rutMal = $rut && $alfa($banco['rut'] ?? '') !== '' && $alfa($rut) !== $alfa($banco['rut'] ?? '');
        // Cuentas enmascaradas (****3128): basta que calcen los ultimos digitos leidos
        $cuentaMal = $cuentaLeida !== '' && strlen($cuentaLeida) >= 4 && $cuentaBotacura !== ''
            && substr($cuentaBotacura, -strlen($cuentaLeida)) !== $cuentaLeida
            && substr($cuentaLeida, -strlen($cuentaBotacura)) !== $cuentaBotacura;
        $nombreMal = $nombre && strpos($texto($nombre), 'BOTACUR') === false
            && ($texto($banco['titular'] ?? '') === '' || strpos($texto($nombre), $texto($banco['titular'] ?? '')) === false);

        if (!$rut && $cuentaLeida === '' && !$nombre) {
            $motivos[] = 'no se ve a quien se hizo la transferencia (nombre, RUT o cuenta de destino)';
        } elseif ($rutMal || $cuentaMal || $nombreMal) {
            $motivos[] = 'la transferencia no fue hecha a la cuenta de Botacura'
                . ($nombre ? ' (destinatario: ' . mb_substr($nombre, 0, 60) . ')' : '');
        }

        $monto = (isset($datos['monto']) && is_numeric($datos['monto'])) ? (int) $datos['monto'] : null;
        if ($monto === null) {
            $motivos[] = 'no se lee el monto transferido';
        } elseif ($minimo > 0 && $monto < $minimo * 0.97) {
            $motivos[] = 'el monto transferido ($' . number_format($monto, 0, ',', '.') . ') es menor al abono que corresponde ($'
                . number_format($minimo, 0, ',', '.') . ')';
        }

        $fecha = (!empty($datos['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $datos['fecha'])) ? $datos['fecha'] : null;
        if ($fecha === null) {
            $motivos[] = 'no se lee la fecha de la transferencia';
        } elseif ($fecha < $desde->toDateString() || $fecha > now()->addDay()->toDateString()) {
            $motivos[] = 'la fecha del comprobante (' . date('d/m/Y', strtotime($fecha)) . ') no corresponde a esta reserva';
        }

        return $motivos;
    }

    private function guardarImagenRechazada(string $rutaTemp, string $clave)
    {
        try {
            Storage::disk('comprobante_transferencia')->put(
                'rechazado_' . $clave . '_' . now()->format('Ymd_His') . '_' . uniqid() . '.jpg', file_get_contents($rutaTemp));
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp] No se pudo guardar el comprobante rechazado: ' . $e->getMessage());
        }
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
                \Mail::raw("El bot NO acepto un comprobante (intento {$intento}).\n\nCliente: +{$telefono}\nMotivos: {$lista}\n{$estado}\n\n"
                    . "La imagen quedo guardada como rechazado_{$clave}_*.jpg.", function ($m) use ($correo, $telefono) {
                    $m->to($correo)->subject('Bot: comprobante rechazado de +' . $telefono);
                });
            } catch (\Throwable $e) {
                Log::warning('[WhatsApp] Aviso de comprobante rechazado no enviado: ' . $e->getMessage());
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
