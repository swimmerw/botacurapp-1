<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$d = $argv[1] ?? '';
if (!filter_var($d, FILTER_VALIDATE_EMAIL)) { echo "Sin correo de prueba\n"; exit; }
$h = date('H:i');
try {
    Illuminate\Support\Facades\Mail::raw("Prueba directa de BotacurApp ($h). Si te llega, el servidor de correo funciona.", function ($m) use ($d, $h) { $m->to($d)->subject("Prueba BotacurApp directa $h"); });
    echo "Envío directo: aceptado por " . config('mail.host') . "\n";
} catch (Throwable $e) { echo 'Envío directo: ERROR ' . mb_substr($e->getMessage(), 0, 300) . "\n"; }
Illuminate\Support\Facades\Mail::to($d)->queue(new App\Mail\PruebaMailable($h));
echo "Envío por cola: encolado (la cola lo manda en segundos)\n";
