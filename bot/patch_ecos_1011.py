# 10/11/2026: el mensaje de ausencia de la app WhatsApp Business no cuenta como respuesta del equipo,
# y el bot ya no responde a avisos de sistema / tipos no soportados.
import sys, shutil, time
W = '/var/www/html/botacurapp/app/Http/Controllers/Api/WhatsAppWebhookController.php'
s = open(W, encoding='utf-8').read()
if 'esRespuestaAutomatica' in s:
    print('YA_APLICADO'); sys.exit(0)
def rep(viejo, nuevo):
    global s
    if s.count(viejo) != 1:
        print('NO_ENCONTRADO: ' + viejo[:80]); sys.exit(1)
    s = s.replace(viejo, nuevo)
rep("""                $texto = $tipo === 'text' ? trim((string) ($eco['text']['body'] ?? '')) : '[' . $tipo . ' enviado por el equipo]';
                if ($texto === '') {
                    continue;
                }
""", """                $texto = $tipo === 'text' ? trim((string) ($eco['text']['body'] ?? '')) : '[' . $tipo . ' enviado por el equipo]';
                if ($texto === '') {
                    continue;
                }
                if ($this->esRespuestaAutomatica($texto)) {
                    Log::info('[WhatsApp] Mensaje automatico de la app (ausencia/bienvenida): no silencia al bot', ['telefono' => $para]);
                    continue;
                }
""")
rep("""    private function registrarEcosEquipo(array $ecos)""", """    /** Mensajes automáticos de la app WhatsApp Business (ausencia, bienvenida): no son respuesta del equipo — 10/11/2026 */
    private function esRespuestaAutomatica(string $texto): bool
    {
        $t = mb_strtolower(preg_replace('/\\s+/u', ' ', $texto));
        $frases = ['en este momento no podemos responder', 'feliz la responderemos', 'lo haremos lo antes posible', 'déjanos tu duda o consulta'];
        try {
            $extra = (string) \\App\\BotSeccionTexto::where('clave', 'ecos_automaticos')->value('contenido');
            foreach (array_filter(array_map('trim', explode("\\n", $extra))) as $f) {
                $frases[] = mb_strtolower($f);
            }
        } catch (\\Throwable $e) {
        }
        foreach ($frases as $f) {
            if ($f !== '' && mb_strpos($t, $f) !== false) {
                return true;
            }
        }
        return false;
    }

    private function registrarEcosEquipo(array $ecos)""")
rep("""            } else {
                Log::info("[WhatsApp] Tipo no soportado: {$tipo} de {$telefono}");""", """            } elseif (in_array($tipo, ['unsupported', 'system', 'reaction', 'ephemeral', 'request_welcome', 'revoke', 'unknown'], true)) {
                // Avisos de Meta, reacciones, etc.: no se responden (10/11/2026)
                Log::info("[WhatsApp] Tipo ignorado: {$tipo} de {$telefono}");
            } else {
                Log::info("[WhatsApp] Tipo no soportado: {$tipo} de {$telefono}");""")
shutil.copy(W, W + '.bak.' + time.strftime('%Y%m%d%H%M%S'))
open(W, 'w', encoding='utf-8').write(s)
print('PARCHE_OK')
