"""Bot-Acura (11/11/2026):
1) EJECUTIVO: solo cuando el cliente escribe la palabra, el chat queda en la app como "pide ejecutivo" y el bot
   se pausa. Las demás derivaciones del bot ya no crean pendientes ni pausan: el bot ofrece escribir EJECUTIVO.
2) Grupos de 9 a 24 personas: se reservan directo por el chat, también en días no habilitados (salvo días
   cerrados con nota en el calendario). 25 o más: cotización por correo.
3) Bebestibles: no están incluidos; se piden aparte de la carta (www.botacura.cl/carta).
4) Tuteo chileno: sin formas de España ("necesitáis", "queréis").
"""
import sys

BC = 'app/Http/Controllers/Api/BotController.php'
BP = 'app/Services/BotPromptService.php'
BT = 'app/Services/BotTarifasService.php'
MARCA = 'patch_ejecutivo_1111'


def reemplazar(s, viejo, nuevo, nombre):
    n = s.count(viejo)
    if n != 1:
        sys.exit('No encontré el ancla "%s" (%d coincidencias). No se cambió nada.' % (nombre, n))
    return s.replace(viejo, nuevo, 1)


bc = open(BC, encoding='utf-8').read()
bp = open(BP, encoding='utf-8').read()
bt = open(BT, encoding='utf-8').read()
if MARCA in bc:
    print('ya estaba aplicado')
    sys.exit(0)

FRASE = 'Si quieres hablar con una persona del equipo, escribe *EJECUTIVO* 🙌'

# ── 1a) Palabra EJECUTIVO, antes de llamar a la IA ───────────────────────────
bc = reemplazar(bc, """        $contenidoParaHistorial = $mensaje;
        if (empty($historial)) {""", """        // patch_ejecutivo_1111: el cliente pide un ejecutivo -> queda en la app y el bot se pausa.
        if (preg_match('/\\bejecutiv[oa]s?\\b/iu', $mensaje)) {
            $resp = 'Listo 🙌 Le avisé al equipo: una persona te escribe por este mismo chat a la brevedad.';
            if (!$simulacion) {
                try {
                    \\App\\BotMensajePendiente::create([
                        'telefono' => $usuarioId, 'nombre' => $nombre . ($canalBot === 'instagram' ? ' (Instagram)' : ''), 'tipo' => 'ejecutivo',
                        'mensaje'  => 'PIDE EJECUTIVO. Mensaje: ' . mb_substr($mensaje, 0, 500), 'estado' => 'pendiente',
                    ]);
                    DB::table('bot_conversaciones')->where('id', $conv->id)->update([
                        'requiere_humano' => 1, 'humano_hasta' => now()->addHours(\\App\\Services\\BotTarifasService::horasSilencioHumano()), 'updated_at' => now(),
                    ]);
                    \\App\\Services\\BotAprendizajeService::evento('pide_ejecutivo', $usuarioId, ['mensaje' => mb_substr($mensaje, 0, 300)]);
                } catch (\\Throwable $e) {
                    Log::error('[Bot] No se pudo registrar el pedido de ejecutivo: ' . $e->getMessage());
                }
            }
            if ($historial && ($historial[count($historial) - 1]['role'] ?? '') === 'user') {
                $historial[count($historial) - 1]['content'] .= "\n\n" . $mensaje;
            } else {
                $historial[] = ['role' => 'user', 'content' => $mensaje];
            }
            $historial[] = ['role' => 'assistant', 'content' => $resp];
            $this->persistirHistorial($conv->id, $mensaje, $resp, $historial, []);
            return response()->json(['ok' => true, 'accion' => 'responder', 'mensaje' => $resp, 'datos' => [], 'acciones_ejecutadas' => [], 'imagenes' => []]);
        }

        $contenidoParaHistorial = $mensaje;
        if (empty($historial)) {""", 'palabra EJECUTIVO')

# ── 1b) escalar(): sin pendiente, sin pausa, sin correo ──────────────────────
i = bc.find('    private function escalar(string $tel, string $nombre, string $motivo, string $ultimoMensaje)')
j = bc.find('    // ─────', i)
if i < 0 or j < 0:
    sys.exit('No encontré escalar(). No se cambió nada.')
bc = bc[:i] + """    private function escalar(string $tel, string $nombre, string $motivo, string $ultimoMensaje)
    {
        // patch_ejecutivo_1111: solo se registra para aprendizaje; el cliente pide un ejecutivo con la palabra EJECUTIVO.
        \\App\\Services\\BotAprendizajeService::evento('derivado', $tel, ['motivo' => $motivo, 'ultimo_mensaje' => mb_substr($ultimoMensaje, 0, 300)]);
    }

""" + bc[j:]

# ── 1c) Todo mensaje de derivación ofrece la palabra ─────────────────────────
bc = reemplazar(bc, """        if (($respuesta['accion'] ?? '') === 'escalar_humano' && !$simulacion) {""", """        if (($respuesta['accion'] ?? '') === 'escalar_humano' && stripos((string) ($respuesta['mensaje'] ?? ''), 'EJECUTIVO') === false) {
            $respuesta['mensaje'] = rtrim((string) ($respuesta['mensaje'] ?? '')) . "\\n\\n" . '""" + FRASE + """';
        }
        if (($respuesta['accion'] ?? '') === 'escalar_humano' && !$simulacion) {""", 'frase en derivación')
for viejo, nuevo in [
    ("Estamos teniendo problemas técnicos. Una persona del equipo te responde por este chat a la brevedad, o si prefieres escríbenos a hola@botacura.cl 🙏",
     "Estamos teniendo problemas técnicos 🙏 Si quieres que una persona del equipo te ayude, escribe *EJECUTIVO*."),
    ("Déjame revisarlo con el equipo y te escribimos en breve 🙏", "No pude resolverlo por aquí 🙏"),
    ("Déjame confirmar el valor exacto con el equipo y te escribimos en breve 🙏", "No pude confirmar el valor exacto por aquí 🙏"),
]:
    if viejo in bc:
        bc = bc.replace(viejo, nuevo)

# ── 2a) Disponibilidad: grupos de 9 a 24 pasan aunque el día no esté habilitado ──
bc = reemplazar(bc, """        $esOperativo = in_array($diaSemana, [0, 4, 5, 6])""", """        $grupoDirecto = \\App\\Services\\BotTarifasService::grupoAbreDia($fecha, (int) $request->query('personas', 0)); // patch_ejecutivo_1111
        $esOperativo = $grupoDirecto || in_array($diaSemana, [0, 4, 5, 6])""", 'disponibilidad operativo')
bc = reemplazar(bc, """        $habilitada = \\App\\FechaDisponible::where('fecha', $fecha)->where('habilitada', true)->exists();
        if (!$habilitada) {""", """        $habilitada = $grupoDirecto || \\App\\FechaDisponible::where('fecha', $fecha)->where('habilitada', true)->exists();
        if (!$habilitada) {""", 'disponibilidad habilitada')

# ── 2b) Cotizar: idem (también lo usa la creación de la reserva) ─────────────
bt = reemplazar(bt, """                        if (!$habilitada) {
                            $err('fecha_no_habilitada', 'Ese día no está habilitado para reservas.');""", """                        if (!$habilitada && self::grupoAbreDia($fecha->toDateString(), $personas)) {
                            $habilitada = true; // patch_ejecutivo_1111: grupo de 9 a 24 reserva directo
                        }
                        if (!$habilitada) {
                            $err('fecha_no_habilitada', 'Ese día no está habilitado para reservas.');""", 'cotizar habilitada')
bt = reemplazar(bt, """    public static function permiteReservaChica($fecha, int $personas): bool
    {""", """    /** Grupo de 9 a 24 personas: puede reservar un día no habilitado (no pasado, sin cierre con nota). */
    public static function grupoAbreDia(string $fecha, int $personas): bool
    {
        if ($personas < 9 || $personas > 24) {
            return false;
        }
        try {
            $f = Carbon::parse($fecha, self::TZ)->startOfDay();
            if ($f->lt(Carbon::now(self::TZ)->startOfDay())) {
                return false;
            }
            $fila = \\App\\FechaDisponible::where('fecha', $f->toDateString())->first();
            return !($fila && !$fila->habilitada && trim((string) ($fila->nota ?? '')) !== '');
        } catch (\\Throwable $e) {
            return false;
        }
    }

    public static function permiteReservaChica($fecha, int $personas): bool
    {""", 'grupoAbreDia')

# ── 2c) verificar_disponibilidad respeta el mínimo de lunes a miércoles ─────
bc = reemplazar(bc, """$codigos = ['grupo_grande', 'agenda_no_abierta', 'fecha_pasada', 'fecha_no_habilitada', 'fecha_invalida', 'sin_cupo_masajes'];""",
                """$codigos = ['grupo_grande', 'agenda_no_abierta', 'fecha_pasada', 'fecha_no_habilitada', 'fecha_invalida', 'sin_cupo_masajes', 'dia_solo_grupos'];""", 'verificar lun-mié')

# ── 2d) No decir "recibimos tu comprobante" sin comprobante ─────────────────
bc = reemplazar(bc, """        // Control de montos: todo "$" del mensaje debe venir del sistema.""", """        // patch_ejecutivo_1111: solo se dice que llegó el comprobante si el sistema lo recibió en este turno.
        $ultU = '';
        for ($k = count($historial) - 1; $k >= 0; $k--) {
            if (($historial[$k]['role'] ?? '') === 'user') { $ultU = (string) $historial[$k]['content']; break; }
        }
        if (strpos($ultU, '[Sistema-comprobante') === false
            && preg_match('/recib(imos|í|i)\\s+(tu|el|su)\\s+comprobante|verificando (el|tu) pago|est[aá] verificando el pago/iu', (string) ($respuesta['mensaje'] ?? ''))) {
            \\App\\Services\\BotAprendizajeService::evento('comprobante_inventado', $usuarioId, ['mensaje' => mb_substr((string) $respuesta['mensaje'], 0, 300)]);
            $respuesta = ['accion' => 'responder', 'datos' => [], 'mensaje' => 'Para confirmar tu reserva necesito la foto del comprobante de transferencia 📸 ¿Me la envías por este chat?'];
        }

        // Control de montos: todo "$" del mensaje debe venir del sistema.""", 'comprobante inventado')

# ── Prompt ───────────────────────────────────────────────────────────────────
bp = reemplazar(bp, """  → Dile al cliente que una persona del equipo le responderá por este mismo chat a la brevedad.
    El sistema avisa al equipo y tú quedas en pausa en este chat.""", """  → NO prometas que alguien le escribirá. Dile en una línea que eso no lo puedes resolver por aquí y que,
    si quiere hablar con una persona del equipo, escriba la palabra EJECUTIVO. Tú sigues atendiendo el chat.
  → Si el cliente escribe EJECUTIVO, el sistema avisa al equipo solo; tú no haces nada.""", 'prompt escalar')
bp = reemplazar(bp, """Di que la política es esa y que una persona del equipo le
  responderá por este chat. No le preguntes si quiere que lo contactes: escalar_humano ya lo deriva.""", """Di que la política es esa y que, si quiere
  hablar con una persona del equipo, escriba EJECUTIVO.""", 'prompt excepción')
bp = reemplazar(bp, """- Al escalar: "Una persona del equipo te responde por este mismo chat. Si prefieres, también puedes escribir a hola@botacura.cl\"""",
                """- Al escalar: "Eso no lo puedo resolver por aquí. Si quieres hablar con una persona del equipo, escribe EJECUTIVO.\"""", 'prompt al escalar')
bp = reemplazar(bp, """✅ Bebestibles variados (sin y con alcohol según programa)""",
                """🥤 BEBESTIBLES: NO están incluidos en los programas (la única excepción son las 2 copas de espumante por pareja del
   Botacura Full). Se piden aparte de nuestra carta: www.botacura.cl/carta. No se pueden ingresar bebidas externas.""", 'bebestibles')
bp = reemplazar(bp, """- Tuteo chileno: nunca voseo ("querés", "tenés", "podés"); usa "quieres", "tienes", "puedes".""",
                """- Tuteo chileno: nunca voseo ("querés", "tenés", "podés") ni formas de España ("necesitáis", "queréis",
  "vosotros"); usa "quieres", "tienes", "puedes", "necesitan", "ustedes".""", 'tuteo')

open(BC, 'w', encoding='utf-8').write(bc)
open(BP, 'w', encoding='utf-8').write(bp)
open(BT, 'w', encoding='utf-8').write(bt)
print('Código: EJECUTIVO, grupos de 9 a 24 directo, bebestibles aparte, tuteo chileno')
