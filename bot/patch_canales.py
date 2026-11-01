import re, sys, os
# Reglas por canal (solo WhatsApp / solo Instagram) y almuerzo de 3 tiempos, editables en Configuración del bot › Reglas comerciales.
# Lo que comparten los dos canales sigue en las demás reglas; cada conversación recibe solo la regla de su canal.
base = sys.argv[1]
hechos = []

DEF = {
    'regla_solo_whatsapp': """SOLO WHATSAPP:
- Ya tienes el teléfono del cliente: es el número desde el que escribe. No lo pidas; solo confírmalo si hace falta.
- Aquí sí se cotiza, se crea la reserva, se reagenda y se guarda el menú del almuerzo.
- Aquí se recibe el comprobante del abono: cuando lo envíe, queda en revisión del equipo. Nunca confirmes un pago tú solo.
- El menú y los documentos se envían como archivo PDF.""",
    'regla_solo_instagram': """SOLO INSTAGRAM (mensaje directo):
- No tienes el teléfono del cliente y no lo pides por aquí.
- Responde dudas, precios y disponibilidad aquí mismo, en mensajes muy breves (1 a 3 líneas).
- No crees reservas, no reagendes, no guardes menú ni pidas comprobantes por Instagram. Cuando quiera reservar o pagar, invítalo a seguir por WhatsApp en https://wa.me/56974484112 , donde se confirma la reserva y se envía el abono.
- Solo lees texto: si manda fotos, audios o un comprobante, pídele que lo escriba, o que mande el comprobante por WhatsApp.
- El menú y los documentos van como enlace, no como archivo.""",
    'regla_almuerzo': """ALMUERZO DE 3 TIEMPOS: el almuerzo de los programas es plato de fondo, ensalada o crema, y postre del día. El postre no se elige.""",
}
ETQ = {
    'regla_solo_whatsapp': 'Solo WhatsApp (lo que no comparte con Instagram)',
    'regla_solo_instagram': 'Solo Instagram (lo que no comparte con WhatsApp)',
    'regla_almuerzo': 'Almuerzo de 3 tiempos',
}

def php_str(t):
    return "'" + t.replace('\\', '\\\\').replace("'", "\\'") + "'"

def prompt(s):
    q = 'BotPromptService'
    if "'regla_solo_instagram'" not in s:
        a = "public static function reglasDefault(): array\n    {\n        return ["
        if a not in s:
            sys.exit(q + ': no encontré reglasDefault')
        s = s.replace(a, a + ''.join("\n            '%s' => %s," % (k, php_str(v)) for k, v in DEF.items()), 1)
        b = "public static function reglasEtiquetas(): array\n    {\n        return ["
        if b not in s:
            sys.exit(q + ': no encontré reglasEtiquetas')
        s = s.replace(b, b + ''.join("\n            '%s' => %s," % (k, php_str(v)) for k, v in ETQ.items()), 1)
    if 'string $canal' not in s:
        a = "public function getSystemPrompt(array $programas = [], array $menuOpciones = [], string $contextoCliente = '')"
        if a not in s:
            sys.exit(q + ': no encontré getSystemPrompt')
        s = s.replace(a, a[:-1] + ", string $canal = 'whatsapp')", 1)
        m = re.search(r"if \(trim\(\$contextoCliente\) === ''\) \{\s*\$contextoCliente = '- \(sin datos\)';\s*\}", s)
        if not m:
            sys.exit(q + ': no encontré el contexto del cliente')
        s = s[:m.end()] + """
        $bloqueCanal = $canal === 'instagram'
            ? "═══════════════════════════════════════════════════════\\nCANAL DE ESTA CONVERSACIÓN: INSTAGRAM\\n═══════════════════════════════════════════════════════\\n" . $__reglasCom['regla_solo_instagram']
            : "═══════════════════════════════════════════════════════\\nCANAL DE ESTA CONVERSACIÓN: WHATSAPP\\n═══════════════════════════════════════════════════════\\n" . $__reglasCom['regla_solo_whatsapp'];""" + s[m.end():]
        m = re.search(r"═+\nCONTEXTO DEL CLIENTE \(desde el sistema", s)
        if not m:
            sys.exit(q + ': no encontré el bloque CONTEXTO DEL CLIENTE')
        s = s[:m.start()] + "{$bloqueCanal}\n\n" + s[m.start():]
    if "$__reglasCom['regla_almuerzo']" not in s:
        a = "{$__reglasCom['regla_notas']}"
        if a not in s:
            sys.exit(q + ': no encontré la regla de notas en el prompt')
        s = s.replace(a, a + "\n\n{$__reglasCom['regla_almuerzo']}", 1)
    return s

def bot(s):
    q = 'BotController'
    a = "getSystemPrompt($programas, $menuOpciones, $contexto['texto'])"
    if a in s:
        s = s.replace(a, "getSystemPrompt($programas, $menuOpciones, $contexto['texto'], isset($canalBot) ? $canalBot : 'whatsapp')", 1)
    elif "isset($canalBot) ? $canalBot : 'whatsapp')" not in s:
        sys.exit(q + ': no encontré la llamada a getSystemPrompt')
    # La nota fija de Instagram pasa a la regla editable "Solo Instagram"
    s = re.sub(r"\$ctxTelefono = '\[Sistema: El cliente te escribe por Instagram \(mensaje directo\), no por WhatsApp:[^']*\]';",
               "$ctxTelefono = '[Sistema: El cliente te escribe por Instagram (mensaje directo): no tienes su teléfono. Sigue la sección CANAL DE ESTA CONVERSACIÓN.]';", s)
    return s

def editar(rel, fn):
    p = os.path.join(base, rel)
    s = open(p, encoding='utf-8').read()
    n = fn(s)
    if n != s:
        open(p, 'w', encoding='utf-8').write(n)
        hechos.append(rel.split('/')[-1])

editar('app/Services/BotPromptService.php', prompt)
editar('app/Http/Controllers/Api/BotController.php', bot)
print('Reglas por canal: ' + (', '.join(hechos) if hechos else 'ya estaba'))
