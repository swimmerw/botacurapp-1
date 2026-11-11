"""Bot-Acura (11/11/2026): flujo definitivo de reserva.

1) Personas, fecha y programa -> verificar_disponibilidad (tinajas, espacio y que exista ubicación libre).
2) Si hay: nombre, teléfono, correo, UBICACIÓN (recién aquí, con fotos) y políticas.
3) Extras, medio de pago, resumen -> crear_reserva -> comprobante/pago.
4) Menú: solo con el pago registrado.
Si no hay cupo: el sistema busca en la base fechas cercanas que sí tengan cupo y se las da al bot.
Requiere patch_orden_1011 y patch_pago_confirma_1111.
"""
import sys

BC = 'app/Http/Controllers/Api/BotController.php'
BP = 'app/Services/BotPromptService.php'


def reemplazar(s, viejo, nuevo, nombre):
    n = s.count(viejo)
    if n != 1:
        sys.exit('No encontré el ancla "%s" (%d coincidencias). No se cambió nada.' % (nombre, n))
    return s.replace(viejo, nuevo, 1)


bc = open(BC, encoding='utf-8').read()
bp = open(BP, encoding='utf-8').read()
if 'patch_flujo_1111' in bc:
    print('ya estaba aplicado')
    sys.exit(0)
for req in ('patch_orden_1011', 'function pagoFigura'):
    if req not in bc:
        sys.exit('Falta un parche previo (%s). No se cambió nada.' % req)

# ── 1) Verificación sin fotos ni pregunta de ubicación; solo comprueba que haya lugar ──
i = bc.find('                // Con tinajas y espacio confirmados, el sistema resuelve la ubicación de inmediato.')
j = bc.find("recién con su sí pides los datos personales.';", i)
if i < 0 or j < 0:
    sys.exit('No encontré el bloque de ubicación en verificar_disponibilidad. No se cambió nada.')
j = j + len("recién con su sí pides los datos personales.';")
bc = bc[:i] + """                // patch_flujo_1111: se comprueba que haya ubicación libre, sin fotos ni pregunta (eso va después del correo).
                $pu = \\App\\Services\\BotUbicacionesService::plan((string) $datos['fecha'], (int) $datos['programa_id'], (int) $datos['personas'], $datos['preferencia'] ?? ($datos['ubicacion_preferida'] ?? null));
                if (!empty($pu['categoria']) && empty($pu['ok'])) {
                    return '[Sistema-disponibilidad: ' . json_encode(['disponible' => false, 'motivo' => 'No quedan ubicaciones libres para ese grupo ese día.'], JSON_UNESCAPED_UNICODE) . ']';
                }
                return $ctx . "\\n\\n" . self::marcaVerificado((string) $datos['fecha'], (int) $datos['programa_id'], (int) $datos['personas'])
                    . ' HAY DISPONIBILIDAD. Díselo al cliente en una línea (fecha, personas y programa) y pídele su nombre completo. '
                    . 'Después: teléfono, correo, ubicación (acción "ubicaciones") y políticas. No hables todavía de terraza, reposera ni menú.';""" + bc[j:]

# ── 2) Sin cupo -> fechas cercanas con cupo, consultadas en la base ─────────
bc = reemplazar(bc, """    private function ejecutarAccion(string $accion, array $datos, string $usuarioId, array $contexto, bool $simulacion): ?string
    {
        try {""", """    private function ejecutarAccion(string $accion, array $datos, string $usuarioId, array $contexto, bool $simulacion): ?string
    {
        if ($accion === 'verificar_disponibilidad' && empty($datos['_sin_alt'])) { // patch_flujo_1111
            $r = $this->ejecutarAccion($accion, $datos + ['_sin_alt' => 1], $usuarioId, $contexto, $simulacion);
            if ($r && !empty($datos['fecha']) && !empty($datos['programa_id']) && !empty($datos['personas'])
                && strpos($r, '[Sistema-verificado') === false && strpos($r, 'PRELIMINAR') === false) {
                $r .= "\\n\\n" . $this->textoAlternativas($datos);
            }
            return $r;
        }
        try {""", 'envoltorio alternativas')

bc = reemplazar(bc, """    /** Devuelve el problema si la respuesta se salta el orden; null si está bien. */""", r"""    /** Fechas cercanas con cupo real para el mismo programa y personas (patch_flujo_1111). */
    private function fechasAlternativas(array $datos, int $max = 3): array
    {
        $out = [];
        try {
            $tz = 'America/Santiago';
            $base = Carbon::parse((string) $datos['fecha'], $tz)->startOfDay();
            $hoy = Carbon::now($tz)->startOfDay();
            $pid = (int) $datos['programa_id'];
            $per = (int) $datos['personas'];
            $prog = DB::table('programas')->where('id', $pid)->first();
            $codigos = ['grupo_grande', 'agenda_no_abierta', 'fecha_pasada', 'fecha_no_habilitada', 'fecha_invalida', 'sin_cupo_masajes', 'dia_solo_grupos',
                        'programa_no_disponible', 'minimo_personas', 'semana_solo_lunes_viernes', 'semana_solo_lunes_jueves', 'semana_solo_jueves_viernes', 'semana_feriado', 'semana_fuera_de_temporada'];
            $svc = app(\App\Services\DisponibilidadService::class);
            for ($d = 1; $d <= 21 && count($out) < $max; $d++) {
                foreach ([$base->copy()->addDays($d), $base->copy()->subDays($d)] as $f) {
                    if ($f->lt($hoy) || count($out) >= $max) {
                        continue;
                    }
                    $iso = $f->toDateString();
                    $cot = \App\Services\BotTarifasService::cotizar(['programa_id' => $pid, 'fecha' => $iso, 'personas' => $per]);
                    if (array_intersect(array_column($cot['errores'] ?? [], 'codigo'), $codigos)) {
                        continue;
                    }
                    $disp = $svc->disponible($iso, $prog->espacio_tipo ?? null, $per);
                    if (empty($disp['disponible']) && !empty($disp['categoria'])) {
                        continue;
                    }
                    if (empty($disp['categoria']) && empty($svc->slotsTinaja($iso, $per)['ok'])) {
                        continue;
                    }
                    $pu = \App\Services\BotUbicacionesService::plan($iso, $pid, $per, null);
                    if (!empty($pu['categoria']) && empty($pu['ok'])) {
                        continue;
                    }
                    $out[$iso] = \App\Services\BotPromptService::etiquetaFecha($iso);
                }
            }
            ksort($out);
        } catch (\Throwable $e) {
            Log::warning('[Bot] fechasAlternativas: ' . $e->getMessage());
        }
        return $out;
    }

    private function textoAlternativas(array $datos): string
    {
        $alt = $this->fechasAlternativas($datos);
        if (!$alt) {
            return '[Sistema-alternativas: no hay fechas con cupo para ese programa y cantidad de personas en las 3 semanas cercanas. Díselo con amabilidad y pregunta si le sirve otro programa u otra fecha más adelante (verifícala antes de ofrecerla).]';
        }
        return '[Sistema-alternativas: fechas cercanas CON CUPO verificado en la base para el mismo programa y personas: ' . implode(' | ', $alt)
            . '. Ofrécele estas (máximo 3, con día y fecha tal cual) y pregunta cuál prefiere. Cuando elija, vuelve a usar verificar_disponibilidad con esa fecha antes de pedir datos.]';
    }

    /** Devuelve el problema si la respuesta se salta el orden; null si está bien. */""", 'métodos alternativas')

# ── 3) Validador: ubicación y menú también cuentan como "datos" ─────────────
bc = reemplazar(bc, """        $pideDatos = preg_match('/nombre completo|tu nombre|su nombre|nombre (para|de) la reserva|tu correo|su correo|correo electr[oó]nico|tu e-?mail|\\bRUT\\b|botacura\\.cl\\/politicas/iu', $msg);""",
"""        $pideDatos = preg_match('/nombre completo|tu nombre|su nombre|nombre (para|de) la reserva|tu correo|su correo|correo electr[oó]nico|tu e-?mail|\\bRUT\\b|botacura\\.cl\\/politicas|terraza o (la |una )?reposera|prefieren (la )?(terraza|reposera)|opciones de men[uú]|elegir (el |su )?men[uú]|entrada y (el )?fondo/iu', $msg);
        if (strpos($ultimoUser, '[Sistema-alternativas') !== false && !$pideDatos) {
            return null; // ofrece fechas que el sistema ya verificó
        }""", 'validador datos')
bc = reemplazar(bc, """        // Clientes con una reserva ya hecha (menú, pago, reagendar) no pasan por aquí.""", """        // Menú: solo con el pago registrado (patch_flujo_1111).
        if (preg_match('/opciones de men[uú]|elegir (el |su )?men[uú]|entrada y (el )?fondo|qu[eé] (quieren|van a) comer/iu', $msg)) {
            $telV = '';
            foreach ($historial as $hh) {
                if (preg_match('/WhatsApp (\\d{9,15})/', (string) ($hh['content'] ?? ''), $mm)) { $telV = $mm[1]; break; }
            }
            if ($telV !== '' && !$this->pagoFigura($telV)) {
                return 'Estás pidiendo elegir el menú, pero el pago todavía no figura. El menú se elige recién cuando el pago está registrado.';
            }
        }
        // Clientes con una reserva ya hecha (menú, pago, reagendar) no pasan por aquí.""", 'validador menú')

# ── 4) Guardar menú solo con pago ───────────────────────────────────────────
bc = reemplazar(bc, """            if ($accion === 'guardar_seleccion_menu') {""", """            if ($accion === 'guardar_seleccion_menu' && !$this->pagoFigura($usuarioId)) { // patch_flujo_1111
                return '[Sistema-menu: {"ok":false,"motivo":"El menú se elige recién cuando el pago está registrado."} — no guardes el menú; si falta pagar, recuérdale cómo hacerlo.]';
            }
            if ($accion === 'guardar_seleccion_menu') {""", 'guardar menú')

# ── Prompt ───────────────────────────────────────────────────────────────────
bp = reemplazar(bp, """    Ahí confirmas al cliente fecha, personas, programa y dónde estarán, y preguntas si avanzamos.
    Recién con su sí pides nombre, correo y políticas. Nunca pidas datos personales antes.""", """    Ahí le dices que hay disponibilidad y pides, de a uno: nombre, teléfono, correo, ubicación (acción
    "ubicaciones") y políticas. Nunca pidas datos, ubicación ni menú antes de esa verificación.
  → Si NO hay cupo, el sistema te entrega [Sistema-alternativas] con fechas cercanas que sí tienen cupo:
    ofrécelas tal cual. Nunca ofrezcas fechas que no vengan ahí o que no hayas verificado.
  → MENÚ: se elige recién cuando el pago está registrado (después del comprobante o del pago con tarjeta).""", 'prompt flujo')
bp = reemplazar(bp, """  → Úsala apenas tengas programa, fecha (con disponibilidad confirmada) y cantidad de personas.""",
                """  → Úsala en el paso de ubicación: después de [Sistema-verificado] y de tener nombre, teléfono y correo.""", 'prompt ubicaciones')

open(BC, 'w', encoding='utf-8').write(bc)
open(BP, 'w', encoding='utf-8').write(bp)
print('Flujo: disponibilidad -> datos -> ubicación -> políticas -> pago -> menú; sin cupo = fechas cercanas con cupo')
