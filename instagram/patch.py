import re, sys, os
# Bot-Acura en Instagram: ruta del webhook y canal "instagram" en el bot (sin teléfono, sin crear reservas: lleva a WhatsApp).
base = sys.argv[1]
hechos = []

def editar(rel, fn):
    p = os.path.join(base, rel)
    s = open(p, encoding='utf-8').read()
    n = fn(s)
    if n != s:
        open(p, 'w', encoding='utf-8').write(n)
        hechos.append(rel.split('/')[-1])

def rutas(s):
    if 'InstagramWebhookController' in s:
        return s
    return s.rstrip() + """

// -------------------------------------------------------------------------
// Instagram — Bot-Acura por mensaje directo
// GET  /api/instagram/webhook  → verificación
// POST /api/instagram/webhook  → mensajes entrantes
// -------------------------------------------------------------------------
Route::prefix('instagram')->namespace('Api')->middleware('auditoria:omitir')->group(function () {
    Route::get('webhook',  'InstagramWebhookController@verify')->name('instagram.webhook.verify');
    Route::post('webhook', 'InstagramWebhookController@handle')->name('instagram.webhook.handle');
});
"""

NOTA_IG = ('[Sistema: El cliente te escribe por Instagram (mensaje directo), no por WhatsApp: NO tienes su teléfono. '
           'Responde dudas, precios y disponibilidad aquí mismo, en mensajes muy breves (1 a 3 líneas). '
           'No crees reservas ni pidas comprobantes por Instagram: cuando quiera reservar o pagar, invítalo a seguir por WhatsApp '
           'en https://wa.me/56974484112 , donde se confirma la reserva y se envía el abono.]')

def bot(s):
    if "'canal' === 'instagram'" in s or "$canalBot" in s:
        return s
    q = 'BotController'
    m = re.search(r"\$usuarioId\s*=\s*\$this->normalizarTelefono\(\$request->telefono\);", s)
    if not m:
        sys.exit(q + ': no encontré $usuarioId = normalizarTelefono(...)')
    s = s[:m.start()] + ("$canalBot   = $request->input('canal') === 'instagram' ? 'instagram' : 'whatsapp';\n"
                         "        $usuarioId  = $canalBot === 'instagram' ? 'ig' . preg_replace('/\\D/', '', $request->telefono) : $this->normalizarTelefono($request->telefono);") + s[m.end():]
    m = re.search(r"\$conv\s*=\s*\$this->obtenerConversacion\(\$usuarioId, \$nombre\);", s)
    if not m:
        sys.exit(q + ': no encontré obtenerConversacion')
    s = s[:m.end()] + ("\n        if ($canalBot === 'instagram' && ($conv->canal ?? '') !== 'instagram') {\n"
                       "            DB::table('bot_conversaciones')->where('id', $conv->id)->update(['canal' => 'instagram']);\n"
                       "        }") + s[m.end():]
    m = re.search(r"\$ctxTelefono = \"\[Sistema: El cliente te escribe desde el WhatsApp.*?\]\";", s, re.S)
    if not m:
        sys.exit(q + ': no encontré la nota del teléfono de WhatsApp')
    s = s[:m.end()] + ("\n            if ($canalBot === 'instagram') {\n"
                       "                $ctxTelefono = " + "'" + NOTA_IG + "'" + ";\n"
                       "            }") + s[m.end():]
    m = re.search(r"(for \(\$i = 0; \$i < 4; \$i\+\+\) \{\s*\$accion = \$respuesta\['accion'\] \?\? 'responder';)", s)
    if m:
        s = s[:m.end()] + ("\n            if ($canalBot === 'instagram' && in_array($accion, ['crear_reserva', 'reagendar', 'guardar_seleccion_menu'], true)) {\n"
                           "                $respuesta = ['accion' => 'responder', 'datos' => [], 'mensaje' => 'Para dejar tu reserva lista seguimos por WhatsApp, ahí te confirmamos y recibimos el abono: https://wa.me/56974484112 😊'];\n"
                           "                break;\n"
                           "            }") + s[m.end():]
    else:
        print('AVISO: no encontré el ciclo de acciones; queda solo la instrucción de no reservar por Instagram')
    return s

editar('routes/api.php', rutas)
editar('app/Http/Controllers/Api/BotController.php', bot)
print('Instagram: ' + (', '.join(hechos) if hechos else 'ya estaba'))
