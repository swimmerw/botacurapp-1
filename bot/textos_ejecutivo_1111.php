<?php
// Textos y parámetros del bot (11/11/2026): grupos de 9 a 24 reservan directo; 25+ por correo; EJECUTIVO.
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$T = 'bot_secciones_texto';
$resp = [];
foreach (['param_grupo_max_bot', 'param_min_personas_lun_jue', 'regla_eventos', 'regla_programas_semana', 'regla_apertura_agenda'] as $k) {
    $resp[$k] = DB::table($T)->where('clave', $k)->value('contenido');
}
file_put_contents(storage_path('app/textos_bot_respaldo_' . date('YmdHi') . '.json'), json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$set = function ($k, $v) use ($T) { DB::table($T)->where('clave', $k)->update(['contenido' => $v, 'updated_at' => now()]); };
$set('param_grupo_max_bot', '24');
$set('param_min_personas_lun_jue', '9');
echo "Grupo máximo por chat: 24 (antes {$resp['param_grupo_max_bot']}) · lunes a miércoles se abre con 9+ (antes {$resp['param_min_personas_lun_jue']})\n";

$set('regla_eventos', 'GRUPOS Y EVENTOS:
- Grupos de 9 a 24 personas: se reservan DIRECTO por este chat, con el mismo flujo de siempre (fecha, personas y programa -> verificar_disponibilidad -> ubicación -> confirmar -> datos). Sirve también para días que no aparecen habilitados: el sistema lo valida. No los mandes al correo.
- Grupos de 25 o más personas, o eventos de empresa con requerimientos especiales (factura, montaje, horarios distintos): la cotización se hace por correo a hola@botacura.cl (nombre de la empresa u organización, fecha tentativa, cantidad de personas, programa de interés y si necesita factura). Si prefieren hablar con una persona, que escriban EJECUTIVO.
- Cambios: los grupos de más de 15 personas no pueden modificar la reserva con 72 horas; los cambios de fecha o de cantidad de personas se piden con al menos 10 días de anticipación.');
echo "regla_eventos: actualizada\n";

foreach (['regla_programas_semana', 'regla_apertura_agenda'] as $k) {
    $t = (string) $resp[$k]; $n = 0;
    $t = preg_replace('/- Grupo de 10 o más que pide un lunes, martes o miércoles:[^\n]*/u',
        '- Grupo de 9 a 24 personas que pide un lunes, martes o miércoles (aunque no esté habilitado): SÍ se puede, reserva directo por este chat con el flujo normal; el sistema lo valida. Si es lunes a miércoles no festivo puede tomar el programa S (10% de descuento, solo transferencia).', -1, $c); $n += $c;
    $t = str_replace(['grupo de menos de 10', 'grupos de menos de 10', 'grupos de 10 o más', 'grupo de 10 o más', 'un grupo de 10+'], ['grupo de menos de 9', 'grupos de menos de 9', 'grupos de 9 o más', 'grupo de 9 o más', 'un grupo de 9+'], $t, $c); $n += $c;
    $set($k, $t);
    echo "$k: $n cambio(s)\n";
}
\Artisan::call('cache:clear');
echo "Respaldo de los textos anteriores en storage/app/textos_bot_respaldo_*.json\n";
