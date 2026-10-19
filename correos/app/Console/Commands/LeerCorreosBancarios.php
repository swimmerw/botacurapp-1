<?php

namespace App\Console\Commands;

use App\Finanzas\Correo\ConciliadorAbonos;
use App\Finanzas\Correo\ImapCliente;
use App\Finanzas\Correo\Lector;
use App\Finanzas\Correo\Mime;
use App\Finanzas\Correo\Seguridad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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
        if (empty($cfg['clave'])) {
            $this->error('Falta FIN_IMAP_PASS en el .env (contraseña de aplicación de ' . $cfg['usuario'] . ').');
            return 1;
        }
        $dias = (int) ($this->option('dias') ?: $cfg['dias']);
        $prueba = (bool) $this->option('prueba');
        $nuevos = ['abono' => 0, 'pago' => 0, 'cuenta' => 0, 'cartola' => 0, 'factura' => 0];

        $imap = new ImapCliente($cfg['host'], $cfg['port']);
        try {
            $imap->login($cfg['usuario'], $cfg['clave']);
            $carpeta = $imap->seleccionar($cfg['carpeta']);
            $uids = $imap->uidsDesde(new \DateTime("-{$dias} days"));
            $cabs = $uids ? $imap->cabeceras($uids) : [];
            $this->line(count($uids) . " correos en {$dias} días en \"{$carpeta}\"; revisando los que pueden traer plata…");

            foreach ($cabs as $uid => $raw) {
                $h = Mime::cabeceras(str_replace("\n", "\r\n", str_replace("\r\n", "\n", $raw)) . "\r\n\r\n");
                $mid = trim($h['message-id'] ?? '') ?: ('uid-' . $uid . '-' . md5($raw));
                $de = Mime::remitente($h['from'] ?? '');
                $asunto = $h['subject'] ?? '';
                if (!$this->interesa($de, $asunto) || Cache::has('fincorreo:' . md5($mid))) {
                    continue;
                }
                if (!$prueba && DB::table('fin_correos')->where('message_id', $mid)->exists()) {
                    continue;
                }
                $completo = $imap->correo($uid);
                if (!$completo) {
                    continue;
                }
                $texto = Mime::texto($completo);
                $fechaCorreo = $h['date'] ?? null;
                $d = Lector::leer($de, $asunto, $texto, $fechaCorreo);
                if (!$d['tipo']) {
                    Cache::put('fincorreo:' . md5($mid), 1, now()->addDays(10));
                    continue;
                }
                $nuevos[$d['tipo']]++;
                $seg = Seguridad::evaluar($de, $completo);
                if ($prueba) {
                    $this->line(sprintf('%-7s %-7s %s %s %-28s %12s %s', $d['tipo'], $seg['seguridad'], $d['fecha'], $d['hora'],
                        mb_substr($d['nombre'] ?: ($d['proveedor'] ?: $d['banco']), 0, 28),
                        $d['monto'] ? '$' . number_format($d['monto'], 0, ',', '.') : '-', $d['operacion']));
                    continue;
                }
                $id = DB::table('fin_correos')->insertGetId([
                    'message_id' => mb_substr($mid, 0, 191), 'uid' => $uid,
                    'recibido_at' => $fechaCorreo && strtotime($fechaCorreo) ? date('Y-m-d H:i:s', strtotime($fechaCorreo)) : now(),
                    'remitente' => mb_substr($de, 0, 120), 'asunto' => mb_substr($asunto, 0, 191),
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
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($d['tipo'] === 'abono' && $seg['seguridad'] !== 'falso') {
                    $c = DB::table('fin_correos')->where('id', $id)->first();
                    $conc->aplicar($c, $conc->conciliar($c));
                }
            }
        } finally {
            $imap->salir();
        }
        $this->info('Nuevos: ' . json_encode($nuevos));
        return 0;
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
