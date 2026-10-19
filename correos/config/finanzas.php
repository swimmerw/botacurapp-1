<?php

/*
| Finanzas: lectura de correos bancarios (fase 1) y módulo de egresos (fase 2).
| Solo los usuarios de FINANZAS_USUARIOS (correos separados por coma) ven estas vistas.
*/
return [
    'usuarios' => array_values(array_filter(array_map(function ($c) {
        return strtolower(trim($c));
    }, explode(',', (string) env('FINANZAS_USUARIOS', ''))))),

    // Buzones que se leen. El primero es el de Botacura; el segundo, el personal de Sebastián
    // (cuentas por pagar y avisos de sus bancos). Cada uno con su contraseña de aplicación.
    'imap' => [
        'host'    => env('FIN_IMAP_HOST', 'imap.gmail.com'),
        'port'    => (int) env('FIN_IMAP_PORT', 993),
        'dias'    => (int) env('FIN_IMAP_DIAS', 3),
        'buzones' => array_values(array_filter([
            ['usuario' => env('FIN_IMAP_USER', 'botacura.info@gmail.com'), 'clave' => env('FIN_IMAP_PASS'),
             'carpeta' => env('FIN_IMAP_FOLDER', '[Gmail]/All Mail')],
            ['usuario' => env('FIN_IMAP2_USER'), 'clave' => env('FIN_IMAP2_PASS'),
             'carpeta' => env('FIN_IMAP2_FOLDER', '[Gmail]/All Mail')],
        ], function ($b) {
            return !empty($b['usuario']);
        })),
    ],

    'cuentas' => [
        '36072963894'  => 'BancoEstado Botacura',
        '000216424012' => 'Banco de Chile Botacura',
        '216424012'    => 'Banco de Chile Botacura',
        '00207023120'  => 'Banco de Chile Sebastián',
        '36072581665'  => 'BancoEstado DS Web',
    ],
];
