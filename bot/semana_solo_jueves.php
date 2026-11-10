<?php
// Programas S: al cliente se le dice "solo jueves no festivos" (nunca "jueves a miércoles" ni "lunes a jueves"). 10/11/2026
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

// 1) Etiqueta de cada programa S en la lista del prompt
$P = base_path('app/Services/BotPromptService.php');
$s = file_get_contents($P);
$viejo = "solo lunes a jueves que no sean feriado y para reservas de 10+ personas (mira en la lista de fechas si aplica ese día), solo transferencia, no reagendable";
$nuevo = "al cliente: SOLO LOS JUEVES que no sean feriado (nunca digas 'jueves a miércoles' ni 'lunes a jueves'; mira en la lista de fechas si aplica ese día), solo transferencia, no reagendable";
if (strpos($s, $nuevo) !== false) {
    echo "Prompt: ya estaba\n";
} elseif (substr_count($s, $viejo) === 1) {
    copy($P, $P . '.bak.' . date('YmdHi'));
    file_put_contents($P, str_replace($viejo, $nuevo, $s));
    echo "Prompt: etiqueta de programas S = solo jueves\n";
} else {
    echo "Prompt: NO encontré la etiqueta; no se cambió\n";
}

// 2) Texto regla_programas_semana (Configuración del bot)
$fila = DB::table('bot_secciones_texto')->where('clave', 'regla_programas_semana')->first();
$t = (string) ($fila->contenido ?? '');
$v2 = 'DESCUENTO DE SEMANA (noviembre): los jueves no festivos, y los lunes a miércoles que estén abiertos por un grupo, los programas tienen 10% de descuento.';
$n2 = 'DESCUENTO DE SEMANA: los programas S (10% de descuento) son SOLO LOS JUEVES que no sean festivo. Al cliente dilo así: "los jueves (no festivos), con 10% de descuento pagando por transferencia". Nunca escribas "jueves a miércoles" ni "lunes a jueves". (Interno, no se le dice al cliente: un lunes a miércoles abierto por un grupo de 10+ también lleva el 10%; solo coméntalo si ese grupo pide ese día.)';
if (strpos($t, 'SOLO LOS JUEVES') !== false) {
    echo "Configuración del bot: ya estaba\n";
} elseif (strpos($t, $v2) !== false) {
    file_put_contents(storage_path('app/regla_programas_semana_' . date('YmdHi') . '.txt'), $t);
    DB::table('bot_secciones_texto')->where('clave', 'regla_programas_semana')->update(['contenido' => str_replace($v2, $n2, $t), 'updated_at' => now()]);
    echo "Configuración del bot: regla_programas_semana = solo jueves (respaldo en storage/app)\n";
} else {
    echo "Configuración del bot: NO encontré el párrafo; no se cambió\n";
}
