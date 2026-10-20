<?php

namespace App\Console\Commands;

use App\Services\WhisperService;
use Illuminate\Console\Command;

/**
 * php artisan bot:probar-audio [archivo]
 * Prueba la transcripción de audios del bot. Sin archivo, genera uno de prueba con espeak/ffmpeg si están instalados.
 */
class BotProbarAudio extends Command
{
    protected $signature = 'bot:probar-audio {archivo?}';
    protected $description = 'Prueba la transcripción de audios (Whisper) que usa Bot-Acura';

    public function handle()
    {
        $w = new WhisperService();
        if (!$w->configurado()) {
            $this->error('Falta la llave: agrega GROQ_API_KEY (o OPENAI_API_KEY) al .env y corre php artisan config:clear');
            return 1;
        }
        $this->line('Proveedor: ' . $w->proveedor());
        $f = $this->argument('archivo');
        if (!$f) {
            $f = sys_get_temp_dir() . '/bot_audio_prueba.ogg';
            $wav = sys_get_temp_dir() . '/bot_audio_prueba.wav';
            @exec('espeak -v es -w ' . escapeshellarg($wav) . ' ' . escapeshellarg('Hola, quiero reservar para cuatro personas el sábado') . ' 2>/dev/null', $o, $c1);
            @exec('ffmpeg -y -loglevel error -i ' . escapeshellarg($wav) . ' -c:a libopus ' . escapeshellarg($f) . ' 2>/dev/null', $o, $c2);
            if (!file_exists($f)) {
                $this->error('No pude generar un audio de prueba (falta espeak o ffmpeg). Pasa la ruta de un audio: php artisan bot:probar-audio /ruta/audio.ogg');
                return 1;
            }
            $this->line('Audio de prueba: "Hola, quiero reservar para cuatro personas el sábado"');
        }
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION)) ?: 'ogg';
        $t0 = microtime(true);
        $txt = $w->transcribir($f, $ext, false);
        if (!$txt) {
            $this->error('No se pudo transcribir (revisa storage/logs/laravel.log, línea [Whisper]).');
            return 1;
        }
        $this->info('Transcripción (' . round(microtime(true) - $t0, 1) . ' s): ' . $txt);
        return 0;
    }
}
