"""Bot-Acura: orden de la reserva (10/11/2026).

Orden obligatorio: personas + programa + fecha -> verificar_disponibilidad (turnos de tinaja primero,
luego ubicación) -> confirmar al cliente -> recién ahí datos personales.

- verificar_disponibilidad sin programa o sin personas = chequeo preliminar (no confirma nada).
- Con programa y personas: tinajas (slotsTinaja) + espacio + ubicación; si todo OK deja la marca
  [Sistema-verificado: ...] en el historial.
- Traba en código: el bot no puede afirmar disponibilidad ni pedir nombre/correo/políticas sin esa
  marca para la misma fecha, programa y personas; crear_reserva sin marca se convierte en verificación.
- Chequeo general (sin programa) cuenta turnos de tinaja reales (CEIL(personas/5), web y exclusivos),
  no reservas.
- Fechas relativas ("este sábado", "mañana", "sábado 21") se resuelven en código y se le entregan a la
  IA con el día correcto y si está habilitado.
- Ubicación: solo con texto_cliente, sin adornos ("vista a la cordillera").
"""
import sys

BC = 'app/Http/Controllers/Api/BotController.php'
BP = 'app/Services/BotPromptService.php'
MARCA = 'patch_orden_1011'


def reemplazar(s, viejo, nuevo, nombre):
    n = s.count(viejo)
    if n != 1:
        sys.exit('No encontré el ancla "%s" (%d coincidencias). No se cambió nada.' % (nombre, n))
    return s.replace(viejo, nuevo, 1)


bc = open(BC, encoding='utf-8').read()
bp = open(BP, encoding='utf-8').read()
if MARCA in bc:
    print('BotController: ya estaba aplicado')
    sys.exit(0)

# ── 1) Chequeo general por turnos de tinaja reales ───────────────────────────
bc = reemplazar(bc, """        // Sin programa_id: contar reservas totales del día
        $cuposMax       = 16; // slots de tinaja
        $reservasActual = Reserva::whereDate('fecha_visita', $fecha)
            ->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', '<>', 'cancelada');
            })
            ->count();
        $cuposDisp      = max(0, $cuposMax - $reservasActual);

        return response()->json([
            'disponible'        => $cuposDisp > 0,
            'fecha'             => $fecha,
            'dia'               => $fechaCarbon->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY'),
            'cupos_maximos'     => $cuposMax,
            'reservas_actuales' => $reservasActual,
            'cupos_disponibles' => $cuposDisp,
        ]);""", """        // Sin programa_id: turnos de tinaja reales (5 personas por turno, web y exclusivos) — patch_orden_1011
        $slots = app(\\App\\Services\\DisponibilidadService::class)->slotsTinaja($fecha, (int) $request->query('personas', 0));

        return response()->json([
            'disponible'        => !empty($slots['ok']) && $slots['disponibles'] > 0,
            'fecha'             => $fecha,
            'dia'               => $fechaCarbon->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY'),
            'turnos_tinaja'     => $slots,
            'cupos_disponibles' => $slots['disponibles'],
        ]);""", 'chequeo general')

# ── 2) verificar_disponibilidad: preliminar vs verificado ────────────────────
bc = reemplazar(bc, """                $res = $this->disponibilidad(new Request($query));
                $ctx = '[Sistema-disponibilidad: ' . $res->getContent() . ']';
                // Orden: con disponibilidad confirmada, el sistema resuelve la ubicación de inmediato.
                $disp = json_decode($res->getContent(), true) ?: [];
                if ($conPrograma && !empty($datos['personas']) && !empty($disp['disponible'])) {
                    $ub = $this->ejecutarAccion('ubicaciones', [
                        'programa_id' => $datos['programa_id'], 'fecha' => $datos['fecha'], 'personas' => $datos['personas'],
                        'preferencia' => $datos['preferencia'] ?? null,
                    ], $usuarioId, $contexto, $simulacion);
                    if ($ub && strpos($ub, '"categoria":null') === false) {
                        $ctx .= "\\n\\n" . $ub . ' Resuelve la ubicación ahora (antes de pedir los datos personales).';
                        $this->ubicacionAuto = true;
                    }
                }
                return $ctx;""", """                $res = $this->disponibilidad(new Request($query));
                $disp = json_decode($res->getContent(), true) ?: [];
                $completo = $conPrograma && !empty($datos['personas']);
                if (!$completo) {
                    // patch_orden_1011: sin programa o sin personas el chequeo es solo preliminar.
                    $falta = $conPrograma ? 'la cantidad de personas' : (empty($datos['personas']) ? 'la cantidad de personas y el programa' : 'el programa');
                    return '[Sistema-disponibilidad PRELIMINAR (falta ' . $falta . '): ' . $res->getContent() . ' — esto NO confirma disponibilidad. '
                        . (!empty($disp['disponible']) ? 'No digas que hay cupo: pregunta ' . $falta . ' y vuelve a usar verificar_disponibilidad con fecha, programa_id y personas.' : 'Explica el motivo y pregunta qué otra fecha le acomoda.') . ']';
                }
                // Turnos de tinaja primero: sin tinaja no hay reserva ese día.
                $tinaja = app(\\App\\Services\\DisponibilidadService::class)->slotsTinaja((string) $datos['fecha'], (int) $datos['personas']);
                if (empty($tinaja['ok'])) {
                    return '[Sistema-disponibilidad: ' . json_encode(['disponible' => false, 'motivo' => 'Los turnos de tinaja están completos para ese día y esa cantidad de personas.', 'turnos_tinaja' => $tinaja], JSON_UNESCAPED_UNICODE)
                        . ' — dile que ese día no queda cupo y pregunta qué otra fecha le acomoda (verifícala antes de ofrecerla).]';
                }
                $ctx = '[Sistema-disponibilidad: ' . $res->getContent() . ']';
                if (empty($disp['disponible'])) {
                    return $ctx . ' — no hay disponibilidad: explica el motivo y pregunta qué otra fecha le acomoda.';
                }
                // Con tinajas y espacio confirmados, el sistema resuelve la ubicación de inmediato.
                $ub = $this->ejecutarAccion('ubicaciones', [
                    'programa_id' => $datos['programa_id'], 'fecha' => $datos['fecha'], 'personas' => $datos['personas'],
                    'preferencia' => $datos['preferencia'] ?? ($datos['ubicacion_preferida'] ?? null),
                ], $usuarioId, $contexto, $simulacion);
                $ubOk = $ub && strpos($ub, '"ok":false') === false;
                if ($ub && strpos($ub, '"categoria":null') === false) {
                    $ctx .= "\\n\\n" . $ub;
                    $this->ubicacionAuto = true;
                }
                if (!$ubOk) {
                    return $ctx . ' — no se pudo asignar ubicación: usa escalar_humano.';
                }
                return $ctx . "\\n\\n" . self::marcaVerificado((string) $datos['fecha'], (int) $datos['programa_id'], (int) $datos['personas'])
                    . ' Ahora confirma al cliente en UN mensaje: fecha, personas, programa y dónde estarán (solo con texto_cliente, sin agregar descripciones). '
                    . 'Si requiere_eleccion es true, pregunta terraza o reposera. Después pregunta si avanzamos con la reserva; recién con su sí pides los datos personales.';""", 'verificar_disponibilidad')

# ── 3) crear_reserva sin verificación => se verifica primero ─────────────────
bc = reemplazar(bc, """            $firma = $accion . ':' . md5(json_encode($respuesta['datos'] ?? []));
            if (isset($ejecutadas[$firma]) || ($accion === 'crear_reserva' && isset($ejecutadas['crear_reserva']))) {""", """            if ($accion === 'crear_reserva' && !isset($ejecutadas['orden_forzado'])) {
                // patch_orden_1011: no se crea una reserva sin tinajas y ubicación verificadas para esos datos.
                $dr = $respuesta['datos'] ?? [];
                if (!$this->verificadoPara($historial, $dr)) {
                    $ejecutadas['orden_forzado'] = true;
                    \\App\\Services\\BotAprendizajeService::evento('orden_forzado', $usuarioId, ['donde' => 'crear_reserva']);
                    $respuesta = ['accion' => 'verificar_disponibilidad', 'mensaje' => '', 'datos' => [
                        'fecha' => $dr['fecha'] ?? null, 'programa_id' => $dr['programa_id'] ?? null, 'personas' => $dr['personas'] ?? null,
                        'preferencia' => $dr['ubicacion_preferida'] ?? null,
                    ]];
                    $accion = 'verificar_disponibilidad';
                }
            }
            $firma = $accion . ':' . md5(json_encode($respuesta['datos'] ?? []));
            if (isset($ejecutadas[$firma]) || ($accion === 'crear_reserva' && isset($ejecutadas['crear_reserva']))) {""", 'crear_reserva')

# ── 4) Validador de orden (antes del control de montos) ─────────────────────
bc = reemplazar(bc, """        // Control de montos: todo "$" del mensaje debe venir del sistema.""", """        // Control de orden (patch_orden_1011): sin tinajas y ubicación verificadas no se afirma
        // disponibilidad ni se piden datos personales.
        $faltaOrden = $this->ordenNoRespetado($respuesta, $historial);
        if ($faltaOrden) {
            \\App\\Services\\BotAprendizajeService::evento('orden_forzado', $usuarioId, ['motivo' => $faltaOrden, 'mensaje' => mb_substr((string) ($respuesta['mensaje'] ?? ''), 0, 500)]);
            $h = $historial;
            $h[] = ['role' => 'assistant', 'content' => json_encode($respuesta, JSON_UNESCAPED_UNICODE)];
            $h[] = ['role' => 'user', 'content' => '[Sistema-validador: ' . $faltaOrden . ' El orden es: personas, programa y fecha -> verificar_disponibilidad con fecha, programa_id y personas (turnos de tinaja y ubicación) -> confirmar al cliente -> datos personales. Si ya tienes los tres datos, usa ahora verificar_disponibilidad; si falta alguno, pregunta solo ese. Responde de nuevo en el formato JSON.]'];
            $reintento = $this->llamarClaude($systemPrompt, $h, $nombre);
            if ($reintento && ($reintento['accion'] ?? '') === 'verificar_disponibilidad' && !empty($reintento['datos']['fecha'])) {
                $contexto['historial'] = $historial;
                $ctx = $this->ejecutarAccion('verificar_disponibilidad', $reintento['datos'], $usuarioId, $contexto, $simulacion);
                if ($ctx) {
                    $acciones[] = 'verificar_disponibilidad';
                    if ($this->ubicacionAuto && !in_array('ubicaciones', $acciones, true)) {
                        $acciones[] = 'ubicaciones';
                    }
                    $historial[count($historial) - 1]['content'] .= "\\n\\n" . $ctx;
                    $reintento = $this->llamarClaude($systemPrompt, $historial, $nombre);
                }
            }
            if ($reintento && trim((string) ($reintento['mensaje'] ?? '')) !== '' && !in_array($reintento['accion'] ?? '', ['crear_reserva', 'verificar_disponibilidad', 'cotizar', 'ubicaciones'], true)
                && !$this->ordenNoRespetado($reintento, $historial)) {
                $respuesta = $reintento;
            } else {
                $respuesta = ['accion' => 'responder', 'datos' => [], 'mensaje' => $this->preguntaFaltante($historial)];
            }
        }

        // Control de montos: todo "$" del mensaje debe venir del sistema.""", 'validador de orden')

# ── 5) Fechas relativas resueltas en código ──────────────────────────────────
bc = reemplazar(bc, """        $contenidoParaHistorial .= \\App\\Services\\BotInteresFechasService::notaParaMensaje($usuarioId, $mensaje, $conv, $simulacion);""",
"""        $contenidoParaHistorial .= \\App\\Services\\BotInteresFechasService::notaParaMensaje($usuarioId, $mensaje, $conv, $simulacion);
        $contenidoParaHistorial .= $this->notaFechasDelMensaje($mensaje); // patch_orden_1011""", 'nota de fechas')

# ── 6) Métodos auxiliares ────────────────────────────────────────────────────
metodos = r'''
    // ── patch_orden_1011: orden de la reserva ────────────────────────────────
    private static function marcaVerificado(string $fecha, int $programaId, int $personas): string
    {
        return '[Sistema-verificado: fecha=' . $fecha . ' programa_id=' . $programaId . ' personas=' . $personas . ' · turnos de tinaja y ubicación OK]';
    }

    /** Última verificación completa del historial (fecha, programa_id, personas) o null. */
    private function ultimaVerificacion(array $historial): ?array
    {
        for ($i = count($historial) - 1; $i >= 0; $i--) {
            $c = is_string($historial[$i]['content'] ?? null) ? $historial[$i]['content'] : '';
            if (preg_match_all('/\[Sistema-verificado: fecha=(\d{4}-\d{2}-\d{2}) programa_id=(\d+) personas=(\d+)/', $c, $m, PREG_SET_ORDER)) {
                $x = end($m);
                return ['fecha' => $x[1], 'programa_id' => (int) $x[2], 'personas' => (int) $x[3]];
            }
        }
        return null;
    }

    /** ¿Hay verificación completa y coincide con los datos que trae la IA (si trae alguno)? */
    private function verificadoPara(array $historial, array $datos): bool
    {
        $v = $this->ultimaVerificacion($historial);
        if (!$v) {
            return false;
        }
        if (!empty($datos['fecha']) && (string) $datos['fecha'] !== $v['fecha']) {
            return false;
        }
        if (!empty($datos['programa_id']) && (int) $datos['programa_id'] !== $v['programa_id']) {
            return false;
        }
        if (!empty($datos['personas']) && (int) $datos['personas'] !== $v['personas']) {
            return false;
        }
        return true;
    }

    /** Devuelve el problema si la respuesta se salta el orden; null si está bien. */
    private function ordenNoRespetado(array $respuesta, array $historial): ?string
    {
        $msg = (string) ($respuesta['mensaje'] ?? '');
        if (in_array($respuesta['accion'] ?? '', ['escalar_humano', 'reagendar', 'guardar_seleccion_menu', 'anotar_interes'], true)) {
            return null;
        }
        // Clientes con una reserva ya hecha (menú, pago, reagendar) no pasan por aquí.
        $ultimoUser = '';
        for ($i = count($historial) - 1; $i >= 0; $i--) {
            if (($historial[$i]['role'] ?? '') === 'user') {
                $ultimoUser = (string) $historial[$i]['content'];
                break;
            }
        }
        if (strpos($ultimoUser, '[Sistema-reserva') !== false) {
            return null;
        }
        $datos = (array) ($respuesta['datos'] ?? []);
        if ($this->verificadoPara($historial, $datos)) {
            return null;
        }
        $pideDatos = preg_match('/nombre completo|tu nombre|su nombre|nombre (para|de) la reserva|tu correo|su correo|correo electr[oó]nico|tu e-?mail|\bRUT\b|botacura\.cl\/politicas/iu', $msg);
        $afirma = preg_match('/(?<!no )\b(tenemos|hay|queda|quedan)\s+(cupos?|disponibilidad)\b|\bcon cupo\b|(?<!no )\best[aá] disponible\b|\bs[ií] tenemos\b/iu', $msg);
        if ($pideDatos) {
            return 'Estás pidiendo datos personales o las políticas, pero el sistema todavía no verificó turnos de tinaja y ubicación para esta fecha, programa y cantidad de personas.';
        }
        if ($afirma) {
            return 'Tu mensaje afirma que hay disponibilidad, pero el sistema no verificó turnos de tinaja y ubicación para esta fecha, programa y cantidad de personas.';
        }
        return null;
    }

    /** Respaldo si la IA no corrige el orden: pregunta por el primer dato que falte. */
    private function preguntaFaltante(array $historial): string
    {
        $v = $this->ultimaVerificacion($historial);
        if ($v) {
            return 'Antes de seguir, ¿me confirmas la fecha, cuántas personas y el programa? Así reviso los horarios de tinaja y la ubicación 😊';
        }
        return 'Para revisar la disponibilidad necesito tres datos: la fecha, cuántas personas y el programa. ¿Me confirmas los que falten? 😊';
    }

    /** Resuelve fechas del mensaje del cliente ("este sábado", "mañana", "sábado 21", "21 de noviembre"). */
    private function notaFechasDelMensaje(string $mensaje): string
    {
        try {
            $tz  = 'America/Santiago';
            $hoy = Carbon::now($tz)->startOfDay();
            $t   = mb_strtolower($mensaje);
            $t   = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
            $dias  = ['lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sabado' => 6, 'domingo' => 7];
            $meses = ['enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
                      'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12];
            $out = [];
            $add = function (string $frase, Carbon $f, string $extra = '') use (&$out, $hoy) {
                if ($f->lt($hoy) || $f->gt($hoy->copy()->addDays(180))) {
                    return;
                }
                $iso = $f->toDateString();
                $hab = \App\FechaDisponible::where('fecha', $iso)->where('habilitada', true)->exists();
                $out[$frase] = '"' . $frase . '" = ' . $f->copy()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') . ' (' . $iso . ')'
                    . ($hab ? ', habilitado en el calendario' : ', NO habilitado en el calendario') . $extra;
            };
            if (preg_match('/\bpasado manana\b/', $t)) {
                $add('pasado mañana', $hoy->copy()->addDays(2));
            } elseif (preg_match('/\bmanana\b/', $t)) {
                $add('mañana', $hoy->copy()->addDay());
            }
            if (preg_match('/\bhoy\b/', $t)) {
                $add('hoy', $hoy->copy());
            }
            $reDias = implode('|', array_keys($dias));
            $reMes  = implode('|', array_keys($meses));
            // "sábado 21 de noviembre", "sábado 21", "este sábado", "el próximo domingo"
            if (preg_match_all('/\b(?:(este|esta|el|proximo|este proximo|el proximo)\s+)?(' . $reDias . ')(?:\s+(\d{1,2}))?(?:\s+de\s+(' . $reMes . '))?\b/u', $t, $m, PREG_SET_ORDER)) {
                foreach ($m as $x) {
                    $dow = $dias[$x[2]];
                    if (!empty($x[3])) {
                        $num = (int) $x[3];
                        $mes = !empty($x[4]) ? $meses[$x[4]] : null;
                        $cand = null;
                        foreach ([0, 1, 2] as $k) {
                            $base = $hoy->copy()->startOfMonth()->addMonthsNoOverflow($k);
                            if ($mes && $base->month !== $mes) {
                                continue;
                            }
                            if ($num <= $base->daysInMonth) {
                                $c = $base->copy()->day($num);
                                if ($c->gte($hoy)) {
                                    $cand = $c;
                                    break;
                                }
                            }
                        }
                        if ($cand) {
                            $extra = $cand->dayOfWeekIso !== $dow ? ' — OJO: el ' . $num . ' NO es ' . $x[2] . '; confirma con el cliente qué día quiso decir' : '';
                            $add(trim($x[2] . ' ' . $num . (!empty($x[4]) ? ' de ' . $x[4] : '')), $cand, $extra);
                        }
                        continue;
                    }
                    if (!empty($x[4])) {
                        continue;
                    }
                    $f = $hoy->copy();
                    while ($f->dayOfWeekIso !== $dow) {
                        $f->addDay();
                    }
                    $add(trim(($x[1] ?? '') . ' ' . $x[2]), $f);
                }
            }
            // "21 de noviembre", "21/11"
            if (preg_match_all('/\b(\d{1,2})\s+de\s+(' . $reMes . ')\b|\b(\d{1,2})\/(\d{1,2})\b/u', $t, $m, PREG_SET_ORDER)) {
                foreach ($m as $x) {
                    $d = (int) (!empty($x[1]) ? $x[1] : $x[3]);
                    $mo = !empty($x[2]) ? $meses[$x[2]] : (int) ($x[4] ?? 0);
                    if ($d < 1 || $mo < 1 || $mo > 12 || !checkdate($mo, $d, (int) $hoy->year)) {
                        continue;
                    }
                    $f = Carbon::create($hoy->year, $mo, $d, 0, 0, 0, $tz);
                    if ($f->lt($hoy)) {
                        $f->addYear();
                    }
                    $clave = $d . ' de ' . array_search($mo, $meses, true);
                    $ya = false;
                    foreach (array_keys($out) as $k) {
                        if (strpos($k, ' ' . $d) !== false) {
                            $ya = true;
                        }
                    }
                    if (!$ya) {
                        $add($clave, $f);
                    }
                }
            }
            if (!$out) {
                return '';
            }
            return "\n\n[Sistema-fecha: " . implode(' · ', $out) . '. Usa EXACTAMENTE este día y fecha; no los recalcules. Si NO está habilitado, díselo y pregunta qué otro día le acomoda (verifícalo antes de ofrecerlo).]';
        } catch (\Throwable $e) {
            Log::warning('[Bot] notaFechasDelMensaje: ' . $e->getMessage());
            return '';
        }
    }
'''
ancla_fin = '    private function persistirHistorial(int $convId'
bc = reemplazar(bc, ancla_fin, metodos.lstrip('\n') + '\n' + ancla_fin, 'métodos')

# ── 7) Prompt ────────────────────────────────────────────────────────────────
bp = reemplazar(bp, """  → Dispara esta acción en cuanto tengas una FECHA concreta, aunque el cliente todavía no haya
    elegido programa. Si NO sabes el programa_id, envíalo como null — el sistema hará un chequeo
    general de cupos para esa fecha. Cuando ya sepas el programa, puedes volver a verificar con
    programa_id para un chequeo más específico, pero no es obligatorio: la creación de la reserva
    (accion "crear_reserva") vuelve a validar la disponibilidad automáticamente antes de confirmar.""",
"""  → ORDEN OBLIGATORIO: primero reúne fecha, cantidad de personas y programa (pregunta solo lo que
    falte, uno a la vez). Con los tres, usa esta acción con fecha, programa_id y personas. El sistema
    revisa PRIMERO los turnos de tinaja (sin tinaja no hay reserva ese día) y después la ubicación.
  → Solo cuando el resultado traiga [Sistema-verificado: ...] puedes decir que hay disponibilidad.
    Ahí confirmas al cliente fecha, personas, programa y dónde estarán, y preguntas si avanzamos.
    Recién con su sí pides nombre, correo y políticas. Nunca pidas datos personales antes.
  → Si te falta el programa o las personas, el resultado será PRELIMINAR: no afirmes cupo, pregunta
    lo que falta y vuelve a verificar.""", 'prompt verificar_disponibilidad')
bp = reemplazar(bp, """  → No inventes nombres de estaciones ni terrazas: usa solo "texto_cliente".""",
"""  → No inventes nombres de estaciones ni terrazas: usa solo "texto_cliente". Tampoco agregues
    descripciones que no estén ahí (vistas, "con vista a la cordillera", sombra, tamaño, etc.).
  → Si se enviaron fotos, di "te mandé unas fotos" (nunca "el sistema te envió").""", 'prompt ubicaciones')
bp = bp.replace('(ver PASO 3C)', '(ver PASO 3B)').replace('la que eligió en PASO 3C;', 'la que eligió en PASO 3B;')
bp = reemplazar(bp, """- "El próximo [día]" = la ocurrencia de ese día en la semana siguiente a la actual.""",
"""- "El próximo [día]" = la próxima ocurrencia de ese día (igual que "este"). Si el cliente da un número
  ("sábado 21"), manda el número. Cuando el mensaje traiga [Sistema-fecha: ...], usa ESE día y fecha.""", 'prompt próximo')

open(BC, 'w', encoding='utf-8').write(bc)
open(BP, 'w', encoding='utf-8').write(bp)
print('Código y prompt: orden tinaja → ubicación → confirmar → datos; fechas resueltas en código; ubicación sin adornos')
