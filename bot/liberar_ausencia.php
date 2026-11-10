<?php
// Libera los chats que el bot dejó en pausa porque el mensaje de ausencia de la app contó como respuesta del equipo.
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$n = 0;
foreach (DB::table('bot_conversaciones')->where('requiere_humano', 1)->whereNotNull('humano_hasta')->where('humano_hasta', '>', now())->get() as $c) {
    $ultimoEquipo = null;
    foreach (json_decode($c->historial_json ?: '[]', true) ?: [] as $t) {
        foreach (explode("\n", (string) $t['content']) as $l) {
            if (strpos($l, '[Equipo Botacura respondió]') !== false) { $ultimoEquipo = mb_strtolower($l); }
        }
    }
    if ($ultimoEquipo && (mb_strpos($ultimoEquipo, 'no podemos responder') !== false || mb_strpos($ultimoEquipo, 'feliz la responderemos') !== false)) {
        DB::table('bot_conversaciones')->where('id', $c->id)->update(['humano_hasta' => null, 'updated_at' => now()]);
        $n++;
    }
}
echo "Chats liberados (estaban en pausa por el mensaje de ausencia): {$n}\n";
echo "Chats todavía en pausa porque el equipo respondió: " . DB::table('bot_conversaciones')->where('requiere_humano', 1)->where('humano_hasta', '>', now())->count() . "\n";
