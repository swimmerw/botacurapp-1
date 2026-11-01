<?php
// Crea las reglas nuevas (Solo WhatsApp, Solo Instagram, Almuerzo de 3 tiempos) en Reglas comerciales si no existen.
// Si ya existen, no las toca: así no se pierde lo que el equipo haya editado.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$def = App\Services\BotPromptService::reglasDefault();
$etq = App\Services\BotPromptService::reglasEtiquetas();
$orden = (int) DB::table('bot_secciones_texto')->where('categoria', 'reglas')->max('orden');
foreach (['regla_solo_whatsapp', 'regla_solo_instagram', 'regla_almuerzo'] as $k) {
    if (DB::table('bot_secciones_texto')->where('clave', $k)->exists()) {
        echo "  {$etq[$k]}: ya existía (no se tocó)\n";
        continue;
    }
    DB::table('bot_secciones_texto')->insert([
        'categoria' => 'reglas', 'clave' => $k, 'etiqueta' => $etq[$k], 'contenido' => $def[$k],
        'orden' => ++$orden, 'created_at' => now(), 'updated_at' => now(),
    ]);
    echo "  {$etq[$k]}: creada\n";
}
// Prueba: el prompt de cada canal lleva solo su regla
$p = new App\Services\BotPromptService();
$wa = $p->getSystemPrompt([], [], '', 'whatsapp');
$ig = $p->getSystemPrompt([], [], '', 'instagram');
$ok = strpos($wa, 'CANAL DE ESTA CONVERSACIÓN: WHATSAPP') !== false && strpos($wa, 'SOLO INSTAGRAM') === false
   && strpos($ig, 'CANAL DE ESTA CONVERSACIÓN: INSTAGRAM') !== false && strpos($ig, 'SOLO WHATSAPP') === false
   && strpos($wa, 'ALMUERZO DE 3 TIEMPOS') !== false;
echo $ok ? "Prueba del prompt: OK (cada canal recibe solo su regla, y el almuerzo de 3 tiempos está)\n"
         : "OJO: la prueba del prompt no calzó; revisa las reglas en Configuración del bot\n";
