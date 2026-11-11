<?php
// Solo lectura: estado de imágenes, audios e Instagram en el bot (11/11/2026).
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
function t($l, $fn) { echo "\n== $l\n"; try { $fn(); } catch (\Throwable $e) { echo '(no se pudo: ' . mb_substr($e->getMessage(), 0, 150) . ")\n"; } }
t('Claves configuradas (solo si existen, sin mostrarlas)', function () {
    foreach (['GROQ_API_KEY', 'OPENAI_API_KEY', 'WHISPER_PROVIDER', 'ANTHROPIC_API_KEY', 'IG_BOT_ACTIVO', 'IG_ACCESS_TOKEN', 'IG_API_BASE', 'FB_PAGE_ACCESS_TOKEN', 'FB_PAGE_ID'] as $k) {
        $v = env($k); echo "  $k: " . ($v === null || $v === '' ? 'no' : ($k === 'IG_BOT_ACTIVO' || $k === 'IG_API_BASE' || $k === 'WHISPER_PROVIDER' ? $v : 'sí')) . "\n";
    }
    echo '  Interruptor bot Instagram (app): ' . var_export(\Cache::get('ig_bot_activo'), true) . "\n";
});
t('Mensajes recibidos por WhatsApp por tipo (últimos 7 días)', function () {
    foreach (DB::table('wa_historial')->where('desde_negocio', 0)->where('enviado_at', '>=', now()->subDays(7))->select('tipo', DB::raw('COUNT(*) n'))->groupBy('tipo')->get() as $r) { echo "  {$r->tipo}: {$r->n}\n"; }
});
t('Audios e imágenes en los registros (últimos 3 días)', function () {
    $c = ['audio_ok' => 0, 'audio_error' => 0, 'imagen' => 0, 'no_soportado' => 0];
    foreach (glob(storage_path('logs/laravel-2026-11-*.log')) as $f) {
        if (filemtime($f) < time() - 3 * 86400) continue;
        foreach (file($f) as $l) {
            if (preg_match('/Whisper|[Aa]udio/', $l)) { preg_match('/ERROR|WARNING/', $l) ? $c['audio_error']++ : $c['audio_ok']++; }
            if (strpos($l, 'Vision') !== false || strpos($l, 'Imagen') !== false) $c['imagen']++;
            if (stripos($l, 'no soportado') !== false || stripos($l, 'solo puedo procesar') !== false) $c['no_soportado']++;
        }
    }
    foreach ($c as $k => $v) echo "  $k: $v\n";
    foreach (glob(storage_path('logs/laravel-' . date('Y-m-d') . '.log')) as $f) {
        foreach (array_slice(preg_grep('/(Whisper|[Aa]udio).*(ERROR|WARNING)|(ERROR|WARNING).*(Whisper|[Aa]udio)/', file($f)), -3) as $l) echo '  ' . mb_substr(trim($l), 0, 300) . "\n";
    }
});
t('Instagram (últimos 7 días)', function () {
    echo '  conversaciones: ' . DB::table('bot_conversaciones')->where('canal', 'instagram')->where('updated_at', '>=', now()->subDays(7))->count() . "\n";
    echo '  pendientes IG: ' . \App\BotMensajePendiente::where('tipo', 'instagram')->where('created_at', '>=', now()->subDays(7))->count() . "\n";
});
