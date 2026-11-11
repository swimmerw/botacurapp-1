<?php
// Gift Card y masajes (11/11/2026): revisa qué servicios ve el bot por programa y corrige el texto de la Gift Card.
//   sin argumento: muestra y corrige el texto   |   El servicio de masaje de cada programa se muestra, no se cambia.
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
echo "Servicios que ve el bot en cada programa (busca 'Masaje' con 60):\n";
foreach (\App\Programa::whereIn('id', \App\Services\BotTarifasService::idsProgramasBot() ?: [0])->with('servicios')->orderBy('valor_programa')->get() as $p) {
    $s = $p->servicios->pluck('nombre_servicio')->implode(', ');
    echo '  #' . $p->id . ' ' . $p->nombre_programa . ': ' . $s . (preg_match('/60/', $s) ? '   <-- 60' : '') . "\n";
}
$gc = (string) DB::table('bot_secciones_texto')->where('clave', 'gift_cards')->value('contenido');
$extra = "\nREGLAS FIJAS DE GIFT CARD (no las cambies):\n- El masaje de la Gift Card y del Botacura Full es de 30 minutos. Nunca digas 60 minutos.\n- La fecha abierta de 45 días es SOLO de la Gift Card. Ningún otro programa se compra para usarlo después: los demás se reservan con fecha. Si quieren regalar otro programa, se reserva con la fecha que elijan; nunca ofrezcas comprar otro programa \"para usarlo dentro de 45 días\".";
if (strpos($gc, 'REGLAS FIJAS DE GIFT CARD') === false) {
    file_put_contents(storage_path('app/gift_cards_respaldo_' . date('YmdHi') . '.txt'), $gc);
    DB::table('bot_secciones_texto')->where('clave', 'gift_cards')->update(['contenido' => rtrim($gc) . "\n" . $extra, 'updated_at' => now()]);
    echo "\nTexto Gift Card: agregadas las reglas (masaje 30 min; 45 días solo Gift Card).\n";
} else {
    echo "\nTexto Gift Card: ya tenía las reglas.\n";
}
\Artisan::call('cache:clear');
