<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Bot-Acura en Instagram (mensajes directos).
 *
 * Meta → POST /api/instagram/webhook (object = "instagram") → mismo bot que WhatsApp
 * (/api/bot-ai/message con canal = instagram) → respuesta por la API de mensajes de Instagram.
 *
 * Variables en .env:
 *   IG_VERIFY_TOKEN        token de verificación del webhook (el mismo que se escribe en Meta)
 *   IG_ACCESS_TOKEN        token de la cuenta (Instagram Login) o de la página de Facebook (Messenger)
 *   IG_ACCOUNT_ID          id de la cuenta de Instagram de Botacura (para ignorar sus propios mensajes)
 *   IG_SEND_ID             id desde el que se envía: el IG user id (Instagram Login) o el id de la página
 *   IG_API_BASE            https://graph.instagram.com (Instagram Login, por defecto) o https://graph.facebook.com
 *   IG_API_VERSION         v23.0 por defecto
 *   IG_APP_SECRET          (opcional) para verificar la firma X-Hub-Signature-256
 *   IG_APP_ID              (opcional) id de la app, para reconocer los ecos de lo que envía el bot
 *   IG_BOT_ACTIVO          1 para que el bot responda; 0 para solo guardar los mensajes (por defecto 0)
 */
class InstagramWebhookController extends Controller
{
    public function verify(Request $request)
    {
        if ($request->query('hub_mode') === 'subscribe'
            && hash_equals((string) env('IG_VERIFY_TOKEN', ''), (string) $request->query('hub_verify_token'))) {
            return response($request->query('hub_challenge'), 200);
        }
        return response('Forbidden', 403);
    }

    public function handle(Request $request)
    {
        if (!$this->firmaValida($request)) {
            Log::warning('[Instagram] Firma inválida');
            return response()->json(['ok' => false], 403);
        }
        $body = $request->all();
        if (($body['object'] ?? '') !== 'instagram') {
            return response()->json(['ok' => true]);
        }
        foreach ($body['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $ev) {
                try {
                    $this->procesarMensaje($ev);
                } catch (\Throwable $e) {
                    Log::error('[Instagram] Error procesando mensaje: ' . $e->getMessage());
                }
            }
        }
        return response()->json(['ok' => true]);
    }

    private function procesarMensaje(array $ev)
    {
        $propio = (string) env('IG_ACCOUNT_ID', '');
        $de     = (string) ($ev['sender']['id'] ?? '');
        $para   = (string) ($ev['recipient']['id'] ?? '');
        $msg    = $ev['message'] ?? null;
        if (!$msg || $de === '') {
            return; // lecturas, reacciones, postbacks: nada que responder
        }

        // Mensaje enviado por Botacura (desde la app de Instagram o por el propio bot)
        if (!empty($msg['is_echo']) || ($propio !== '' && $de === $propio)) {
            $txt = trim((string) ($msg['text'] ?? ''));
            $delBot = ($txt !== '' && \Cache::has('ig_enviado_' . md5($txt)))
                || (!empty($msg['app_id']) && (string) $msg['app_id'] === (string) env('IG_APP_ID', '-'));
            if (!$delBot) {
                $this->registrarRespuestaEquipo($para, $txt);
            }
            return;
        }

        $usuario = 'ig' . preg_replace('/\D/', '', $de);
        $nombre  = $this->nombreUsuario($de);
        $texto   = trim((string) ($msg['text'] ?? ''));
        if ($texto === '' && !empty($msg['attachments'])) {
            $tipos = implode(', ', array_unique(array_map(function ($a) { return $a['type'] ?? 'archivo'; }, $msg['attachments'])));
            $texto = "[El cliente envió por Instagram: {$tipos}. Por este canal solo lees texto: pídele que lo escriba, o que mande comprobantes por WhatsApp.]";
        }
        if ($texto === '') {
            return;
        }
        Log::info("[Instagram] Mensaje de {$usuario}");

        // Bot apagado (general o en esta conversación) o chat en manos del equipo: guardar y avisar
        $pausado = Schema::hasTable('bot_chats_pausados')
            && DB::table('bot_chats_pausados')->where('telefono', preg_replace('/\D/', '', $de))->exists();
        $humano = DB::table('bot_conversaciones')->where('usuario_id', $usuario)->where('activo', 1)
            ->whereNotNull('humano_hasta')->where('humano_hasta', '>', now())->exists();
        $apagado = env('IG_BOT_ACTIVO', '0') !== '1'
            || \App\BotSeccionTexto::where('clave', 'bot_activo')->value('contenido') === '0';
        if ($pausado || $humano || $apagado) {
            $this->guardarPendiente($usuario, $nombre, $texto);
            return;
        }

        $client = new GuzzleClient(['timeout' => 35, 'http_errors' => false]);
        $res = $client->post(url('/api/bot-ai/message'), [
            'headers' => [
                'X-Bot-Secret' => config('services.bot.secret'),
                'X-Bot-Token'  => env('BOT_API_TOKEN'),
                'Content-Type' => 'application/json',
            ],
            'json' => ['telefono' => $de, 'mensaje' => $texto, 'nombre' => $nombre, 'canal' => 'instagram'],
        ]);
        $data = json_decode((string) $res->getBody(), true) ?? [];
        Log::info('[Instagram] Respuesta del bot', ['status' => $res->getStatusCode()]);

        if (!empty($data['mensaje'])) {
            $this->enviarTexto($de, $data['mensaje']);
        }
        foreach (array_slice($data['imagenes'] ?? [], 0, 3) as $img) {
            if (!empty($img['url'])) {
                $this->enviar($de, ['attachment' => ['type' => 'image', 'payload' => ['url' => $img['url']]]]);
            }
        }
        if (!empty($data['adjunto_url'])) {
            $this->enviarTexto($de, trim(($data['adjunto_texto'] ?? 'Aquí va el documento') . ': ' . $data['adjunto_url']));
        }
    }

    /** Respuesta del equipo desde la app de Instagram: queda en el historial y el bot se calla un rato. */
    private function registrarRespuestaEquipo(string $cliente, string $texto)
    {
        if ($cliente === '' || $texto === '') {
            return;
        }
        $usuario = 'ig' . preg_replace('/\D/', '', $cliente);
        $conv = DB::table('bot_conversaciones')->where('usuario_id', $usuario)->where('activo', 1)->orderBy('id', 'desc')->first();
        if (!$conv) {
            return;
        }
        $historial = json_decode($conv->historial_json ?? '[]', true) ?: [];
        $ultimo = end($historial);
        if ($ultimo && ($ultimo['role'] ?? '') === 'assistant' && strpos((string) $ultimo['content'], $texto) !== false) {
            return; // eco de lo que respondió el bot
        }
        $linea = '[Equipo Botacura respondió por Instagram]: ' . $texto;
        if ($ultimo && ($ultimo['role'] ?? '') === 'assistant') {
            $historial[count($historial) - 1]['content'] .= "\n\n" . $linea;
        } else {
            $historial[] = ['role' => 'assistant', 'content' => $linea];
        }
        DB::table('bot_conversaciones')->where('id', $conv->id)->update([
            'historial_json'  => json_encode(array_slice($historial, -24), JSON_UNESCAPED_UNICODE),
            'requiere_humano' => 1,
            'humano_hasta'    => now()->addHours(\App\Services\BotTarifasService::horasSilencioHumano()),
            'updated_at'      => now(),
        ]);
        \App\BotMensajePendiente::where('telefono', $usuario)->where('estado', 'pendiente')->update(['estado' => 'atendido', 'updated_at' => now()]);
    }

    private function guardarPendiente(string $usuario, string $nombre, string $texto)
    {
        try {
            \App\BotMensajePendiente::create([
                'telefono' => $usuario, 'nombre' => $nombre . ' (Instagram)', 'tipo' => 'instagram',
                'mensaje' => $texto, 'estado' => 'pendiente',
            ]);
            $correo = \App\BotSeccionTexto::where('clave', 'alerta_correo')->value('contenido');
            if ($correo && \Cache::add('ig_pendiente_' . $usuario, 1, now()->addMinutes(30))) {
                \Mail::raw("Llegó un mensaje por Instagram que el bot no respondió.\n\nCliente: {$nombre}\nMensaje: {$texto}\n\nRespóndelo desde la app de Instagram.",
                    function ($m) use ($correo, $nombre) { $m->to($correo)->subject('Instagram: mensaje de ' . $nombre); });
            }
        } catch (\Throwable $e) {
            Log::error('[Instagram] No se pudo guardar el pendiente: ' . $e->getMessage());
        }
    }

    private function nombreUsuario(string $igsid): string
    {
        return \Cache::remember('ig_nombre_' . $igsid, now()->addDays(7), function () use ($igsid) {
            try {
                $client = new GuzzleClient(['timeout' => 8, 'http_errors' => false]);
                $res = $client->get($this->base() . '/' . $igsid, ['query' => ['fields' => 'name,username', 'access_token' => env('IG_ACCESS_TOKEN')]]);
                $j = json_decode((string) $res->getBody(), true) ?? [];
                return $j['name'] ?? (isset($j['username']) ? '@' . $j['username'] : 'Cliente');
            } catch (\Throwable $e) {
                return 'Cliente';
            }
        });
    }

    /** Instagram corta en 1000 caracteres: se manda en partes por párrafo. */
    private function enviarTexto(string $igsid, string $texto)
    {
        $partes = [];
        $actual = '';
        foreach (preg_split("/\n{2,}/", trim($texto)) as $p) {
            if (mb_strlen($actual . "\n\n" . $p) > 950 && $actual !== '') {
                $partes[] = $actual;
                $actual = $p;
            } else {
                $actual = $actual === '' ? $p : $actual . "\n\n" . $p;
            }
        }
        if ($actual !== '') {
            $partes[] = $actual;
        }
        foreach ($partes as $p) {
            $this->enviar($igsid, ['text' => mb_substr($p, 0, 1000)]);
        }
    }

    private function enviar(string $igsid, array $message)
    {
        if (!empty($message['text'])) {
            \Cache::put('ig_enviado_' . md5(trim($message['text'])), 1, now()->addMinutes(15)); // para no confundir su eco con el equipo
        }
        try {
            $client = new GuzzleClient(['timeout' => 10, 'http_errors' => false]);
            $res = $client->post($this->base() . '/' . env('IG_SEND_ID', 'me') . '/messages', [
                'headers' => ['Authorization' => 'Bearer ' . env('IG_ACCESS_TOKEN'), 'Content-Type' => 'application/json'],
                'json' => ['recipient' => ['id' => $igsid], 'message' => $message],
            ]);
            if ($res->getStatusCode() >= 300) {
                Log::error('[Instagram] Error enviando', ['status' => $res->getStatusCode(), 'body' => mb_substr((string) $res->getBody(), 0, 500)]);
            }
        } catch (\Throwable $e) {
            Log::error('[Instagram] Excepción enviando: ' . $e->getMessage());
        }
    }

    private function base(): string
    {
        return rtrim(env('IG_API_BASE', 'https://graph.instagram.com'), '/') . '/' . env('IG_API_VERSION', 'v23.0');
    }

    private function firmaValida(Request $request): bool
    {
        $secreto = (string) env('IG_APP_SECRET', '');
        if ($secreto === '') {
            return true;
        }
        $firma = (string) $request->header('X-Hub-Signature-256', '');
        return $firma !== '' && hash_equals('sha256=' . hash_hmac('sha256', $request->getContent(), $secreto), $firma);
    }
}
