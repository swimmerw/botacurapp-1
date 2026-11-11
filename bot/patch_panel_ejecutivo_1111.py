# Atención de ejecutivo (11/11/2026): los chats donde el cliente escribe EJECUTIVO aparecen en el panel.
import sys, glob
C = 'app/Http/Controllers/BotEjecutivoController.php'
s = open(C, encoding='utf-8').read()
if 'patch_panel_ejecutivo_1111' not in s:
    a = "|| in_array(strtolower((string) $p->tipo), ['derivado', 'escalado', 'escalar_humano', 'humano'], true);"
    if s.count(a) != 1:
        sys.exit('Panel: no encontré esDerivado; no se cambió nada')
    s = s.replace(a, "|| in_array(strtolower((string) $p->tipo), ['derivado', 'escalado', 'escalar_humano', 'humano', 'ejecutivo'], true); // patch_panel_ejecutivo_1111", 1)
    b = "    private static function motivo(string $m): ?string\n    {\n"
    if s.count(b) != 1:
        sys.exit('Panel: no encontré motivo(); no se cambió nada')
    s = s.replace(b, b + "        if (stripos($m, 'PIDE EJECUTIVO') === 0) {\n            return 'El cliente pidió hablar con un ejecutivo';\n        }\n", 1)
    c = "        $m = preg_replace('/^Derivado por el bot"
    s = s.replace(c, "        $m = preg_replace('/^PIDE EJECUTIVO\\.\\s*Mensaje:\\s*/u', '', $m);\n" + c, 1)
    open(C, 'w', encoding='utf-8').write(s)
    print('Panel: los pedidos de EJECUTIVO ya aparecen')
else:
    print('Panel: ya estaba')
for v in glob.glob('resources/views/themes/backoffice/pages/bot-ejecutivo/index.blade.php'):
    t = open(v, encoding='utf-8').read()
    t2 = t.replace('derivadas por el bot', 'piden ejecutivo').replace('Derivadas por el bot', 'Piden ejecutivo')
    t2 = t2.replace('El bot no podía resolverlas solo: pagos, grupos, excepciones o errores.', 'El cliente escribió EJECUTIVO para hablar con una persona del equipo.')
    if t2 != t:
        open(v, 'w', encoding='utf-8').write(t2)
        print('Vista: textos actualizados')
