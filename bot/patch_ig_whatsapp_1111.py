"""Instagram (11/11/2026): la reserva se deriva a WhatsApp desde el comienzo. En Instagram el bot no
consulta disponibilidad, no cotiza ni pide datos: responde dudas generales en 1-2 líneas y entrega el link
de WhatsApp (con mensaje prellenado) apenas el cliente muestra intención de reservar."""
import sys
BC = 'app/Http/Controllers/Api/BotController.php'
s = open(BC, encoding='utf-8').read()
if 'patch_ig_whatsapp_1111' in s:
    print('Instagram: ya estaba'); sys.exit(0)
LINK = 'https://wa.me/56974484112?text=Hola%2C%20vengo%20de%20Instagram%20y%20quiero%20reservar'
a = """            if ($canalBot === 'instagram' && in_array($accion, ['crear_reserva', 'reagendar', 'guardar_seleccion_menu'], true)) {
                $respuesta = ['accion' => 'responder', 'datos' => [], 'mensaje' => 'Para dejar tu reserva lista seguimos por WhatsApp, ahí te confirmamos y recibimos el abono: https://wa.me/56974484112 😊'];"""
if s.count(a) != 1:
    sys.exit('Instagram: no encontré el bloque de acciones; no se cambió nada')
s = s.replace(a, """            if ($canalBot === 'instagram' && in_array($accion, ['verificar_disponibilidad', 'cotizar', 'ubicaciones', 'crear_reserva', 'reagendar', 'guardar_seleccion_menu', 'anotar_interes'], true)) { // patch_ig_whatsapp_1111
                $respuesta = ['accion' => 'responder', 'datos' => [], 'mensaje' => 'Las reservas las hacemos por WhatsApp, así revisamos disponibilidad y te confirmamos al tiro 😊 Escríbenos aquí: """ + LINK + """'];""", 1)
b = """        if (($respuesta['accion'] ?? '') === 'escalar_humano' && stripos("""
if s.count(b) != 1:
    sys.exit('Instagram: no encontré dónde agregar el link (¿falta aplicar_bot_ejecutivo_grupos.sh?)')
s = s.replace(b, """        // patch_ig_whatsapp_1111: en Instagram, toda respuesta lleva el link de WhatsApp si no se dio hace poco.
        if ($canalBot === 'instagram' && stripos((string) ($respuesta['mensaje'] ?? ''), 'wa.me') === false) {
            $reciente = false;
            foreach (array_slice($historial, -4) as $hh) {
                if (($hh['role'] ?? '') === 'assistant' && stripos((string) ($hh['content'] ?? ''), 'wa.me') !== false) { $reciente = true; }
            }
            if (!$reciente) {
                $respuesta['mensaje'] = rtrim((string) ($respuesta['mensaje'] ?? '')) . "\\n\\nPara reservar escríbenos por WhatsApp: """ + LINK + """";
            }
        }
""" + b, 1)
open(BC, 'w', encoding='utf-8').write(s)
print('Instagram: deriva a WhatsApp desde el comienzo')
