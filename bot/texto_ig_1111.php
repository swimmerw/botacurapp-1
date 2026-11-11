<?php
require '/var/www/html/botacurapp/vendor/autoload.php';
$app = require '/var/www/html/botacurapp/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$k = 'regla_solo_instagram';
$antes = (string) DB::table('bot_secciones_texto')->where('clave', $k)->value('contenido');
file_put_contents(storage_path('app/' . $k . '_respaldo_' . date('YmdHi') . '.txt'), $antes);
DB::table('bot_secciones_texto')->where('clave', $k)->update(['updated_at' => now(), 'contenido' => 'SOLO INSTAGRAM (mensaje directo): aquí NO se hace el proceso de reserva.
- Las reservas se hacen por WhatsApp desde el comienzo. Apenas el cliente quiera reservar o pregunte por disponibilidad, una fecha, cantidad de personas, cupos o cómo reservar: NO le hagas ninguna pregunta ni pidas datos; responde en una línea que las reservas se hacen por WhatsApp y entrega el link: https://wa.me/56974484112?text=Hola%2C%20vengo%20de%20Instagram%20y%20quiero%20reservar
- Solo respondes aquí dudas generales (qué incluye cada programa, precios de lista, horarios, cómo llegar, qué llevar), en 1 a 2 líneas, y cierras con el link de WhatsApp.
- No consultas disponibilidad, no cotizas totales, no pides nombre, teléfono ni correo, no ofreces ubicaciones ni menú.
- Si manda fotos, audios o un comprobante: pídele que lo envíe por WhatsApp con el mismo link.']);
\Artisan::call('cache:clear');
echo "Texto de Instagram actualizado (respaldo en storage/app).\n";
