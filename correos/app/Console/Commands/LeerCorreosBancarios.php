<?php

namespace App\Console\Commands;

use App\Finanzas\Correo\ConciliadorAbonos;
use App\Finanzas\Correo\ImapCliente;
use App\Finanzas\Correo\Lector;
use App\Finanzas\Correo\Mime;
use App\Finanzas\Correo\Seguridad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lee los correos de bancos y cuentas por pagar (solo lectura, no los marca),
 * guarda los datos en fin_correos y cruza los abonos con reservas y comprobantes.
 *
 *   php artisan finanzas:leer-correos                 últimos N días (FIN_IMAP_DIAS, 3)
 *   php artisan finanzas:leer-correos --dias=30       carga inicial
 *   php artisan finanzas:leer-correos --prueba        muestra lo que leería, sin guardar
 *   php artisan finanzas:leer-correos --recruzar      vuelve a cruzar los abonos por revisar
 */
class LeerCorreosBancarios extends Command
{
    protected $signature = 'finanzas:leer-correos {--dias=} {--prueba} {--recruzar}';
    protected $description = 'Lee avisos de transferencias, cartolas y cuentas desde el correo y los cruza con reservas';

    public function handle(ConciliadorAbonos $conc)
    {
        if ($this->option('recruzar')) {
            return $this->recruzar($conc);
        }
        $cfg = config('finanzas.imap');
        $dias = (int) ($this->option('dias') ?: $cfg['dias']);
        $error = 0;
        foreach ($cfg['buzones'] as $b) {
            if (empty($b['clave'])) {
                $this->warn("{$b['usuario']}: falta su contraseña de aplicación en el .env; no se lee.");
                continue;
            }
            try {
                $this->leerBuzon($b, $cfg, $dias, $conc);
            } catch (\Throwable $e) {
                $error = 1;
                $this->error("{$b['usuario']}: " . $e->getMessage());
                \Illuminate\Support\Facades\Log::warning('[FinCorreos] ' . $b['usuario'] . ': ' . $e->getMessage());
            }
        }
        return $error;
    }

    private function leerBuzon(array $b, array $cfg, int $dias, ConciliadorAbonos $conc): void
    {
        $prueba = (bool) $this->option('prueba');
        $nuevos = ['abono' => 0, 'pago' => 0, 'traspaso' => 0, 'cuenta' => 0, 'cartola' => 0, 'factura' => 0];
        $hayBuzon = \Illuminate\Support\Facades\Schema::hasColumn('fin_correos', 'buzon');

        $imap = new ImapCliente($cfg['host'], $cfg['port']);
        try {
            $imap->login($b['usuario'], $b['clave']);
            $carpeta = $imap->seleccionar($b['carpeta']);
            $uids = $imap->uidsDesde(new \DateTime("-{$dias} days"));
            $cabs = $uids ? $imap->cabeceras($uids) : [];
            $this->line("{$b['usuario']}: " . count($uids) . " correos en {$dias} días en \"{$carpeta}\"…");

            foreach ($cabs as $uid => $raw) {
                $h = Mime::cabeceras(str_replace("\n", "\r\n", str_replace("\r\n", "\n", $raw)) . "\r\n\r\n");
                $mid = mb_substr(trim($h['message-id'] ?? '') ?: ('uid-' . $b['usuario'] . '-' . $uid), 0, 191);
                $de = Mime::remitente($h['from'] ?? '');
                $asunto = $h['subject'] ?? '';
                if (!$this->interesa($de, $asunto)) {
                    continue;
                }
                if (DB::table('fin_correos')->where('message_id', $mid)->exists()) {
                    continue; // ya leído (también los que no traían plata)
                }
                $completo = $imap->correo($uid);
                if (!$completo) {
                    continue;
                }
                $texto = Mime::texto($completo);
                $fechaCorreo = $h['date'] ?? null;
                $recibido = $fechaCorreo && strtotime($fechaCorreo) ? date('Y-m-d H:i:s', strtotime($fechaCorreo)) : now();
                $d = Lector::leer($de, $asunto, $texto, $fechaCorreo);
                $base = ['message_id' => $mid, 'uid' => $uid, 'recibido_at' => $recibido,
                         'remitente' => mb_substr($de, 0, 120), 'asunto' => mb_substr($asunto, 0, 191),
                         'created_at' => now(), 'updated_at' => now()];
                if ($hayBuzon) {
                    $base['buzon'] = mb_substr($b['usuario'], 0, 60);
                }
                if (!$d['tipo']) {
                    if (!$prueba) { // se anota para no volver a descargarlo
                        DB::table('fin_correos')->insert($base + ['tipo' => 'otro', 'estado' => 'ignorado', 'banco' => $d['banco']]);
                    }
                    continue;
                }
                $nuevos[$d['tipo']]++;
                $seg = Seguridad::evaluar($de, $completo);
                if ($prueba) {
                    $this->line(sprintf('%-8s %-7s %s %s %-30s %12s %s', $d['tipo'], $seg['seguridad'], $d['fecha'], $d['hora'],
                        mb_substr($d['nombre'] ?: ($d['proveedor'] ?: $d['banco']), 0, 30),
                        $d['monto'] ? '$' . number_format($d['monto'], 0, ',', '.') : '-', $d['operacion']));
                    continue;
                }
                $id = DB::table('fin_correos')->insertGetId($base + [
                    'tipo' => $d['tipo'], 'banco' => $d['banco'], 'proveedor' => $d['proveedor'],
                    'nombre' => $d['nombre'] ? mb_substr($d['nombre'], 0, 120) : null, 'rut' => $d['rut'],
                    'monto' => $d['monto'], 'fecha' => $d['fecha'], 'hora' => $d['hora'],
                    'operacion' => $d['operacion'] ? mb_substr($d['operacion'], 0, 60) : null,
                    'comentario' => $d['comentario'] ? mb_substr($d['comentario'], 0, 191) : null,
                    'cuenta' => $d['cuenta'], 'vencimiento' => $d['vencimiento'],
                    'estado' => $seg['seguridad'] === 'falso' ? 'sospechoso'
                        : (in_array($d['tipo'], ['cartola', 'factura'], true) ? 'ignorado' : 'nuevo'),
                    'seguridad' => $seg['seguridad'], 'seguridad_detalle' => mb_substr($seg['detalle'], 0, 191),
                    'texto' => mb_substr($texto, 0, 8000),
                ]);
                if ($d['tipo'] === 'abono' && $seg['seguridad'] !== 'falso') {
                    $c = DB::table('fin_correos')->where('id', $id)->first();
                    $conc->aplicar($c, $conc->conciliar($c));
                }
            }
        } finally {
            $imap->salir();
        }
        $this->info("{$b['usuario']} nuevos: " . json_encode($nuevos));
    }

    private function interesa(string $de, string $asunto): bool
    {
        if (Lector::bancoDeDominio(Lector::dominio($de))) {
            return true;
        }
        foreach (array_keys(Lector::PROVEEDORES) as $k) {
            if (strpos($de, $k) !== false) {
                return true;
            }
        }
        return (bool) preg_match('/(factura electr|documento tributario)/i', $asunto);
    }

    private function recruzar(ConciliadorAbonos $conc)
    {
        $n = 0;
        foreach (DB::table('fin_correos')->where('tipo', 'abono')->whereIn('estado', ['nuevo', 'revisar'])->get() as $c) {
            $r = $conc->conciliar($c);
            $conc->aplicar($c, $r);
            $n += ($r['estado'] === 'calzado') ? 1 : 0;
        }
        $this->info("Abonos calzados ahora: {$n}");
        return 0;
    }
}
