# 10/11/2026: (1) leer todos los bloques de texto de la respuesta del modelo (con Sonnet el primero puede venir vacio);
# (2) registrar en el log los mensajes que Meta NO pudo entregar al cliente.
import sys, shutil, time
B = '/var/www/html/botacurapp/app/Http/Controllers/Api/'
C, W = B + 'BotController.php', B + 'WhatsAppWebhookController.php'
c = open(C, encoding='utf-8').read(); w = open(W, encoding='utf-8').read()
cambios = 0
viejo = "            $content = $body['content'][0]['text'] ?? '';"
if 'bloques de texto de la respuesta' not in c:
    if c.count(viejo) != 1: sys.exit('NO_ENCONTRADO en BotController')
    c = c.replace(viejo, """            // Todos los bloques de texto de la respuesta (el primero puede no ser texto) — 10/11/2026
            $content = '';
            foreach (($body['content'] ?? []) as $bloque) {
                if (($bloque['type'] ?? '') === 'text') {
                    $content .= (string) ($bloque['text'] ?? '');
                }
            }
            if (trim($content) === '') {
                Log::warning('[Bot] Respuesta del modelo sin texto', ['stop_reason' => $body['stop_reason'] ?? null, 'tipos' => array_column($body['content'] ?? [], 'type')]);
            }""")
    cambios += 1
viejo2 = "            $messages = $value['messages'] ?? [];"
if 'Mensaje NO entregado' not in w:
    if w.count(viejo2) != 1: sys.exit('NO_ENCONTRADO en webhook')
    w = w.replace(viejo2, """            // Estados de entrega: dejar en el log los mensajes que Meta no pudo entregar — 10/11/2026
            if (!empty($value['statuses'])) {
                foreach ($value['statuses'] as $st) {
                    if (($st['status'] ?? '') === 'failed') {
                        Log::error('[WhatsApp] Mensaje NO entregado al cliente', [
                            'telefono' => $st['recipient_id'] ?? null,
                            'errores'  => $st['errors'] ?? null,
                        ]);
                    }
                }
                if (empty($value['messages'])) {
                    return response()->json(['ok' => true]);
                }
            }

""" + viejo2)
    cambios += 1
st = time.strftime('%Y%m%d%H%M%S')
if cambios:
    for f, t in ((C, c), (W, w)):
        shutil.copy(f, f + '.bak.' + st); open(f, 'w', encoding='utf-8').write(t)
print('PARCHE_OK ' + st if cambios else 'YA_APLICADO')
