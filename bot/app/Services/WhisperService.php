<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * WhisperService
 *
 * Transcribe audios de WhatsApp (OGG/Opus) con Whisper. Dos proveedores (misma API):
 *  - Groq  (GROQ_API_KEY, modelo whisper-large-v3-turbo; tiene capa gratuita y es muy rápido)
 *  - OpenAI (OPENAI_API_KEY, modelo whisper-1; ~US$0,006 por minuto)
 * TRANSCRIPCION_PROVEEDOR=groq|openai elige; si no se indica, usa Groq si hay llave y si no OpenAI.
 * El archivo ya debe estar descargado localmente antes de llamar a transcribir().
 *
 * Compatible PHP 7.2 / Laravel 6.
 */
class WhisperService
{
    /** @var string */
    private $apiKey;
    /** @var string */
    private $url;
    /** @var string */
    private $modelo;
    /** @var string */
    private $proveedor;

    public function __construct()
    {
        $groq = (string) config('services.groq.key', '');
        $openai = (string) config('services.openai.key', '');
        $pref = strtolower((string) config('services.transcripcion.proveedor', ''));
        $this->proveedor = $pref === 'openai' || ($pref !== 'groq' && !$groq) ? 'openai' : 'groq';
        if ($this->proveedor === 'groq') {
            $this->apiKey = $groq;
            $this->url = 'https://api.groq.com/openai/v1/audio/transcriptions';
            $this->modelo = (string) config('services.groq.modelo_audio', 'whisper-large-v3-turbo');
        } else {
            $this->apiKey = $openai;
            $this->url = 'https://api.openai.com/v1/audio/transcriptions';
            $this->modelo = 'whisper-1';
        }
    }

    public function proveedor(): string
    {
        return $this->proveedor;
    }

    public function configurado(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Transcribe un archivo de audio local y devuelve el texto.
     *
     * @param  string $rutaLocal  Ruta absoluta al archivo temporal
     * @param  string $extension  Extensión del archivo (ogg, mp4, webm, m4a, mp3, wav)
     * @return string|null        Texto transcrito, o null si falló
     */
    public function transcribir(string $rutaLocal, string $extension = 'ogg', bool $borrar = true): ?string
    {
        if (!$this->configurado()) {
            Log::warning('[Whisper] Sin llave para transcribir (GROQ_API_KEY u OPENAI_API_KEY) — audio ignorado');
            return null;
        }

        if (!file_exists($rutaLocal) || filesize($rutaLocal) === 0) {
            Log::warning('[Whisper] Archivo de audio vacío o inexistente: ' . $rutaLocal);
            return null;
        }

        try {
            $client   = new Client(['timeout' => 60, 'http_errors' => true]);
            $response = $client->post($this->url, [
                'headers'   => ['Authorization' => 'Bearer ' . $this->apiKey],
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => fopen($rutaLocal, 'r'),
                        'filename' => 'audio.' . $extension,
                    ],
                    ['name' => 'model',           'contents' => $this->modelo],
                    ['name' => 'prompt',          'contents' => 'Conversación por WhatsApp con Botacura, centro de tinajas y spa en el Cajón del Maipo, Chile. Reservas, abonos, programas Full Day, masajes, gift cards.'],
                    ['name' => 'language',        'contents' => 'es'],
                    ['name' => 'response_format', 'contents' => 'text'],
                ],
            ]);

            $texto = trim((string) $response->getBody());
            Log::info('[Whisper] Transcripción OK', ['proveedor' => $this->proveedor, 'chars' => strlen($texto)]);
            return $texto ?: null;

        } catch (\Exception $e) {
            Log::error('[Whisper] Error: ' . $e->getMessage());
            return null;
        } finally {
            // Limpiar archivo temporal
            if ($borrar && file_exists($rutaLocal)) {
                @unlink($rutaLocal);
            }
        }
    }
}
