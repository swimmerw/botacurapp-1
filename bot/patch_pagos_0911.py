# Arreglos del bot 09/11/2026: comprobantes, cupos apartados y mensajes atrasados.
import sys, shutil, time
B = '/var/www/html/botacurapp/app/Http/Controllers/Api/'
W, R, C = B + 'WhatsAppWebhookController.php', B + 'BotReservaController.php', B + 'BotController.php'
src = {f: open(f, encoding='utf-8').read() for f in (W, R, C)}
if 'telefono_chat' in src[W] or 'telefono_chat' in src[C]:
    print('YA_APLICADO'); sys.exit(0)

def rep(f, viejo, nuevo):
    n = src[f].count(viejo)
    if n != 1:
        print('NO_ENCONTRADO en %s (%d): %s' % (f.split('/')[-1], n, viejo[:70])); sys.exit(1)
    src[f] = src[f].replace(viejo, nuevo)

# 1) Webhook: no procesar dos veces el mismo mensaje y no contestar mensajes atrasados
rep(W, """            $nombre   = $value['contacts'][0]['profile']['name'] ?? 'Cliente';
""", """            $nombre   = $value['contacts'][0]['profile']['name'] ?? 'Cliente';

            // Mismo mensaje dos veces (Meta reintenta si el bot demora): se procesa una sola vez — 09/11/2026
            if (!empty($msg['id']) && !\\Cache::add('wa_msg_' . $msg['id'], 1, now()->addDay())) {
                return response()->json(['ok' => true]);
            }
            // Mensajes atrasados (p. ej. los que Meta reenvía al conectar la coexistencia): el bot no los contesta,
            // quedan en pendientes para el equipo — 09/11/2026
            if (!empty($msg['timestamp']) && (int) $msg['timestamp'] < time() - 1800 && $tipo !== 'sticker') {
                try {
                    $textoAtr = $tipo === 'text' ? ($msg['text']['body'] ?? '') : '[' . ($tipo ?: 'mensaje') . '] (revisar en WhatsApp)';
                    \\App\\BotMensajePendiente::create([
                        'telefono' => $telefono, 'nombre' => $nombre, 'phone_number_id' => $this->phoneNumberId,
                        'tipo' => $tipo ?: 'text', 'mensaje' => '[atrasado ' . date('d/m H:i', (int) $msg['timestamp']) . '] ' . $textoAtr,
                        'estado' => 'pendiente',
                    ]);
                } catch (\\Throwable $e) {
                    Log::warning('[WhatsApp] No se pudo guardar mensaje atrasado: ' . $e->getMessage());
                }
                Log::info('[WhatsApp] Mensaje atrasado: no lo responde el bot', ['telefono' => $telefono, 'enviado' => date('Y-m-d H:i', (int) $msg['timestamp'])]);
                return response()->json(['ok' => true]);
            }
""")

# 2) Webhook: el cupo apartado se busca tambien por el numero del chat y aunque haya vencido hace poco
rep(W, """        return DB::table('reserva_holds')
            ->where('telefono', $telefonoNorm)
            ->where('estado', 'activo')
            ->orderBy('id', 'desc')
            ->first();
    }""", """        // Por el teléfono que dio el cliente y también por el número del chat (puede dar otro al reservar) — 09/11/2026
        $base = function () use ($telefonoNorm) {
            return DB::table('reserva_holds')->where(function ($w) use ($telefonoNorm) {
                $w->where('telefono', $telefonoNorm)
                  ->orWhere('datos_json', 'like', '%"telefono_chat":"' . $telefonoNorm . '"%');
            });
        };
        $activo = $base()->where('estado', 'activo')->orderBy('id', 'desc')->first();
        if ($activo) {
            return $activo;
        }
        // Venció hace poco mientras el cliente transfería: igual se recibe el comprobante (la conversión revalida el cupo).
        return $base()->where('estado', 'expirado')->whereNull('id_reserva')
            ->where('expira_en', '>', now()->subHours(6))->orderBy('id', 'desc')->first();
    }""")

# 3) Webhook: comprobante de un cupo vencido ya no se descarta: se reactiva y sigue el flujo normal
i = src[W].find("            if (\\Carbon\\Carbon::parse($holdPendiente->expira_en)->isPast()) {")
j = src[W].find("            // Revisar el comprobante ANTES de crear la reserva (13/10/2026)")
if i < 0 or j < 0 or j < i:
    print('NO_ENCONTRADO bloque hold vencido'); sys.exit(1)
src[W] = src[W][:i] + """            if (\\Carbon\\Carbon::parse($holdPendiente->expira_en)->isPast() || $holdPendiente->estado !== 'activo') {
                // Antes se descartaba el comprobante. Ahora se reactiva el cupo y sigue el flujo normal:
                // convertirHoldEnReserva revalida que todavía haya cupo — 09/11/2026
                DB::table('reserva_holds')->where('id', $holdPendiente->id)->update([
                    'estado' => 'activo', 'expira_en' => now()->addMinutes(30), 'updated_at' => now(),
                ]);
                $holdPendiente = DB::table('reserva_holds')->find($holdPendiente->id);
                Log::info('[WhatsApp] Comprobante para un cupo apartado vencido: se reactiva', ['hold_id' => $holdPendiente->id]);
            }

""" + src[W][j:]

# 4) Webhook: comprobante sin cupo ni reserva pendiente -> se guarda la imagen, queda en pendientes y el bot no inventa
rep(W, """        $descripcion = $this->describirConClaude($rutaTemp, $caption);
""", """        $descripcion = $this->describirConClaude($rutaTemp, $caption);
        $esComprobanteSinReserva = $descripcion && preg_match('/comprobante|transferencia|transacci[oó]n|dep[oó]sito/iu', $descripcion);
        $archivoSinReserva = $esComprobanteSinReserva
            ? $this->guardarImagenComprobante($rutaTemp, 'sin_reserva_' . preg_replace('/\\D/', '', $telefono)) : null;
""")
rep(W, """        $texto = "[Imagen recibida]: {$descripcion}";
        if ($caption) {
            $texto .= " | Caption del cliente: \\"{$caption}\\"";
        }

        return $texto;""", """        $texto = "[Imagen recibida]: {$descripcion}";
        if ($caption) {
            $texto .= " | Caption del cliente: \\"{$caption}\\"";
        }

        if ($esComprobanteSinReserva) {
            try {
                \\App\\BotMensajePendiente::create([
                    'telefono' => $telefono, 'phone_number_id' => $this->phoneNumberId, 'tipo' => 'comprobante',
                    'mensaje' => 'Comprobante SIN cupo apartado ni reserva pendiente para este chat. Imagen: '
                        . ($archivoSinReserva ?: 'no guardada') . '. ' . mb_substr($descripcion, 0, 900),
                    'estado' => 'pendiente',
                ]);
                \\App\\Services\\BotCasoService::registrar('comprobante_sin_reserva', $telefono, ['archivo' => $archivoSinReserva]);
                $correo = \\App\\BotSeccionTexto::where('clave', 'alerta_correo')->value('contenido');
                if ($correo) {
                    \\Mail::raw("Llegó un comprobante sin cupo apartado ni reserva pendiente para +{$telefono}.\\n\\n{$descripcion}\\n\\nImagen: "
                        . ($archivoSinReserva ?: 'no guardada') . "\\nRevisa el chat y regístralo en la reserva que corresponda.",
                        function ($m) use ($correo, $telefono) { $m->to($correo)->subject('Bot: comprobante sin reserva de +' . $telefono); });
                }
            } catch (\\Throwable $e) {
                Log::warning('[WhatsApp] Comprobante sin reserva: no se pudo registrar: ' . $e->getMessage());
            }
            Log::info('[WhatsApp] Comprobante sin reserva asociada', ['telefono' => $telefono, 'archivo' => $archivoSinReserva]);
            $texto .= "\\n\\n[Sistema-comprobante-sin-reserva: La imagen parece un comprobante de pago, pero NO hay un cupo apartado ni "
                . "una reserva pendiente de pago asociada a este chat (quizás reservó con otro teléfono o por otro canal). NO digas que "
                . "recibiste, registraste o confirmaste el pago ni la reserva. Agradece el comprobante, explica en 1 a 2 líneas que una "
                . "persona del equipo lo revisará y le confirmará por este chat, y si no lo sabes pregunta a nombre de quién y para qué "
                . "fecha era la reserva. Usa escalar_humano.]";
        }

        return $texto;""")

# 5) Bot: al crear el cupo apartado se guarda tambien el numero del chat
rep(C, """                    'telefono'      => $this->telefonoReserva($datos['telefono'] ?? null, $usuarioId),
""", """                    'telefono'      => $this->telefonoReserva($datos['telefono'] ?? null, $usuarioId),
                    'telefono_chat' => $usuarioId,
""")
rep(R, """            return $this->crearHold([
                'nombre'        => $nombre,
                'telefono'      => $telefono,
""", """            return $this->crearHold([
                'nombre'        => $nombre,
                'telefono'      => $telefono,
                'telefono_chat' => $request->telefono_chat ? $this->normalizarTelefono((string) $request->telefono_chat) : null,
""")
# 6) Bot: reconocer el cupo apartado del chat aunque el cliente haya dado otro telefono (evita duplicados)
rep(C, """$h = DB::table('reserva_holds')->whereIn('telefono', $tels)->where('fecha', $datos['fecha'])""",
    """$h = DB::table('reserva_holds')->where(function ($w) use ($tels, $tel) { $w->whereIn('telefono', $tels)->orWhere('datos_json', 'like', '%"telefono_chat":"' . $tel . '"%'); })->where('fecha', $datos['fecha'])""")
rep(C, """                ->whereIn('telefono', array_unique([$tel, $corto]))""",
    """                ->where(function ($w) use ($tel, $corto) { $w->whereIn('telefono', array_unique([$tel, $corto]))->orWhere('datos_json', 'like', '%"telefono_chat":"' . $tel . '"%'); })""")

st = time.strftime('%Y%m%d%H%M%S')
for f in (W, R, C):
    shutil.copy(f, f + '.bak.' + st)
    open(f, 'w', encoding='utf-8').write(src[f])
print('PARCHE_OK ' + st)
