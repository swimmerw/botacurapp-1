<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Envíos de WhatsApp a clientes con seguimiento de entrega (10/11/2026).
 *  - Registra cada mensaje (wa_envios) con su id de Meta y su estado: enviado, entregado, leído o fallido.
 *  - El webhook informa los estados; si Meta no lo entrega porque el cliente escribió hace más de 24 h (131047),
 *    la confirmación o el rechazo se reenvían con plantilla aprobada y lo demás (menú, información) queda en espera.
 *  - Cuando el cliente vuelve a escribir, se envía lo que estaba en espera.
 *  - Guarda la hora del último mensaje de cada cliente (wa_entrantes) para saber si sigue abierta la ventana de 24 h.
 */
class WaEnviosService
{
    const PLANTILLA_CONFIRMACION = 'reserva_confirmada';
    const PLANTILLA_RECHAZO = 'comprobante_rechazado';
    const IDIOMA = 'es';
    const VENTANA_HORAS = 24;

    public static function n($tel): string
    {
        return preg_replace('/\D/', '', (string) $tel);
    }

    public static function listo(): bool
    {
        return Schema::hasTable('wa_envios') && Schema::hasTable('wa_entrantes');
    }

    /** Horas desde el último mensaje del cliente, o null si no hay registro. */
    public static function horasDesdeCliente($tel): ?float
    {
        if (!self::listo()) {
            return null;
        }
        $t = self::n($tel);
        $u = DB::table('wa_entrantes')->where('telefono', $t)->value('ultimo_at');
        if (!$u && strlen($t) >= 9) {
            $u = DB::table('wa_entrantes')->where('telefono', 'like', '%' . substr($t, -9))->max('ultimo_at');
        }
        return $u ? Carbon::parse($u)->diffInMinutes(now()) / 60 : null;
    }

    // ── Envío ───────────────────────────────────────────────────────

    /** $msg: payload de Meta sin "messaging_product" ni "to" (p. ej. ['type'=>'text','text'=>['body'=>...]]) */
    public static function enviar($tel, array $msg, array $meta = []): ?int
    {
        $tel = self::n($tel);
        $id = self::listo() ? DB::table('wa_envios')->insertGetId([
            'telefono' => $tel,
            'reserva_id' => $meta['reserva_id'] ?? null,
            'venta_id' => $meta['venta_id'] ?? null,
            'tipo' => $meta['tipo'] ?? 'mensaje',
            'via' => $msg['type'] ?? 'text',
            'payload_json' => json_encode(['msg' => $msg, 'params' => $meta['params'] ?? null], JSON_UNESCAPED_UNICODE),
            'estado' => 'enviando',
            'created_at' => now(),
            'updated_at' => now(),
        ]) : null;
        self::post($tel, $msg, $id);
        return $id;
    }

    public static function encolar($tel, array $msg, array $meta = []): ?int
    {
        if (!self::listo()) {
            return null;
        }
        return DB::table('wa_envios')->insertGetId([
            'telefono' => self::n($tel),
            'reserva_id' => $meta['reserva_id'] ?? null,
            'venta_id' => $meta['venta_id'] ?? null,
            'tipo' => $meta['tipo'] ?? 'mensaje',
            'via' => $msg['type'] ?? 'text',
            'payload_json' => json_encode(['msg' => $msg, 'params' => $meta['params'] ?? null], JSON_UNESCAPED_UNICODE),
            'estado' => 'en_espera',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function plantilla(string $nombre, array $parametros): array
    {
        $params = array_map(function ($p) {
            // Meta no acepta saltos de línea ni tabulaciones en los parámetros
            $p = trim(preg_replace('/[\r\n\t]+/', ' ', (string) $p));
            return ['type' => 'text', 'text' => $p !== '' ? mb_substr($p, 0, 200) : '-'];
        }, $parametros);
        return ['type' => 'template', 'template' => [
            'name' => $nombre,
            'language' => ['code' => env('WA_PLANTILLAS_IDIOMA', self::IDIOMA)],
            'components' => [['type' => 'body', 'parameters' => $params]],
        ]];
    }

    private static function post(string $tel, array $msg, ?int $id): bool
    {
        $phoneId = env('META_PHONE_NUMBER_ID');
        $token = env('META_WHATSAPP_TOKEN');
        $version = env('META_API_VERSION', 'v26.0');
        $estado = 'error'; $wamid = null; $code = null; $err = null;
        try {
            $res = (new \GuzzleHttp\Client(['timeout' => 15, 'http_errors' => false]))->post(
                "https://graph.facebook.com/{$version}/{$phoneId}/messages",
                ['headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                 'json' => ['messaging_product' => 'whatsapp', 'to' => $tel] + $msg]
            );
            $body = json_decode((string) $res->getBody(), true) ?: [];
            if ($res->getStatusCode() < 300 && !empty($body['messages'][0]['id'])) {
                $estado = 'enviado';
                $wamid = $body['messages'][0]['id'];
            } else {
                $code = (string) ($body['error']['code'] ?? $res->getStatusCode());
                $err = mb_substr((string) ($body['error']['error_data']['details'] ?? $body['error']['message'] ?? $res->getBody()), 0, 480);
                Log::error('[WaEnvios] Meta rechazó el envío', ['telefono' => $tel, 'code' => $code, 'error' => $err]);
            }
        } catch (\Throwable $e) {
            $err = mb_substr($e->getMessage(), 0, 480);
            Log::error('[WaEnvios] Excepción enviando: ' . $err);
        }
        if ($id) {
            DB::table('wa_envios')->where('id', $id)->update([
                'estado' => $estado, 'wamid' => $wamid, 'error_code' => $code, 'error_text' => $err, 'updated_at' => now(),
            ]);
        }
        return $estado === 'enviado';
    }

    // ── Webhook ─────────────────────────────────────────────────────

    /** Recibe el "value" de cada cambio del webhook de WhatsApp. No interrumpe el flujo normal. */
    public static function webhook(array $value): void
    {
        if (!self::listo()) {
            return;
        }
        foreach (($value['messages'] ?? []) as $m) {
            if (!empty($m['from'])) {
                self::registrarEntrante($m['from'], $m['timestamp'] ?? null);
            }
        }
        foreach (($value['statuses'] ?? []) as $s) {
            self::registrarEstado($s);
        }
    }

    public static function registrarEntrante($tel, $timestamp = null): void
    {
        $tel = self::n($tel);
        $at = $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : now();
        if ($at->lt(now()->subHours(self::VENTANA_HORAS))) {
            return; // mensaje atrasado (historial): no abre la ventana
        }
        DB::table('wa_entrantes')->updateOrInsert(['telefono' => $tel], ['ultimo_at' => $at, 'updated_at' => now()]);
        self::despacharCola($tel);
    }

    private static function registrarEstado(array $s): void
    {
        if (empty($s['id'])) {
            return;
        }
        $fila = DB::table('wa_envios')->where('wamid', $s['id'])->first();
        if (!$fila) {
            return; // no es un mensaje enviado por esta vía
        }
        $orden = ['enviando' => 0, 'enviado' => 1, 'entregado' => 2, 'leido' => 3];
        $nuevo = ['sent' => 'enviado', 'delivered' => 'entregado', 'read' => 'leido', 'failed' => 'fallido'][$s['status'] ?? ''] ?? null;
        if (!$nuevo) {
            return;
        }
        if ($nuevo !== 'fallido') {
            if (($orden[$nuevo] ?? 0) > ($orden[$fila->estado] ?? -1)) {
                DB::table('wa_envios')->where('id', $fila->id)->update(['estado' => $nuevo, 'updated_at' => now()]);
            }
            return;
        }
        $code = (string) ($s['errors'][0]['code'] ?? '');
        $err = mb_substr((string) ($s['errors'][0]['error_data']['details'] ?? $s['errors'][0]['title'] ?? $s['errors'][0]['message'] ?? ''), 0, 480);
        DB::table('wa_envios')->where('id', $fila->id)->update(['estado' => 'fallido', 'error_code' => $code, 'error_text' => $err, 'updated_at' => now()]);
        Log::warning('[WaEnvios] No entregado', ['telefono' => $fila->telefono, 'tipo' => $fila->tipo, 'code' => $code, 'error' => $err]);

        if ($code === '131047') { // fuera de la ventana de 24 h
            if (in_array($fila->tipo, ['confirmacion', 'rechazo'], true) && $fila->via !== 'template') {
                self::reenviarConPlantilla($fila);
            } elseif ($fila->via !== 'template') {
                DB::table('wa_envios')->where('id', $fila->id)->update(['estado' => 'en_espera', 'updated_at' => now()]);
            }
        }
    }

    private static function reenviarConPlantilla($fila): void
    {
        $ya = DB::table('wa_envios')->where('telefono', $fila->telefono)->where('reserva_id', $fila->reserva_id)
            ->where('tipo', $fila->tipo)->where('via', 'template')->where('created_at', '>=', now()->subDays(2))->exists();
        if ($ya) {
            return;
        }
        $p = json_decode($fila->payload_json ?: '{}', true)['params'] ?? null;
        if (!$p) {
            return;
        }
        $nombre = $fila->tipo === 'confirmacion' ? env('WA_PLANTILLA_CONFIRMACION', self::PLANTILLA_CONFIRMACION) : env('WA_PLANTILLA_RECHAZO', self::PLANTILLA_RECHAZO);
        self::enviar($fila->telefono, self::plantilla($nombre, $p), [
            'reserva_id' => $fila->reserva_id, 'venta_id' => $fila->venta_id, 'tipo' => $fila->tipo, 'params' => $p,
        ]);
        Log::info('[WaEnvios] Reenviado con plantilla', ['telefono' => $fila->telefono, 'tipo' => $fila->tipo, 'reserva' => $fila->reserva_id]);
    }

    public static function despacharCola(string $tel): int
    {
        $n = 0;
        $filas = DB::table('wa_envios')->where('telefono', $tel)->where('estado', 'en_espera')
            ->where('created_at', '>=', now()->subDays(14))->orderBy('id')->get();
        foreach ($filas as $f) {
            $msg = json_decode($f->payload_json ?: '{}', true)['msg'] ?? null;
            if (!$msg) {
                continue;
            }
            DB::table('wa_envios')->where('id', $f->id)->update(['estado' => 'enviando', 'updated_at' => now()]);
            if (self::post($tel, $msg, $f->id)) {
                $n++;
            }
        }
        if ($n) {
            Log::info('[WaEnvios] Enviados los mensajes en espera', ['telefono' => $tel, 'mensajes' => $n]);
        }
        return $n;
    }

    // ── Para la pantalla de Verificación de pagos ───────────────────

    public static function recientes(int $dias = 3)
    {
        if (!self::listo()) {
            return collect();
        }
        $filas = DB::table('wa_envios as e')
            ->leftJoin('reservas as r', 'r.id', '=', 'e.reserva_id')
            ->leftJoin('clientes as c', 'c.id', '=', 'r.cliente_id')
            ->whereNotNull('e.reserva_id')->where('e.created_at', '>=', now()->subDays($dias))
            ->orderBy('e.id')
            ->get(['e.*', 'c.nombre_cliente', 'r.fecha_visita']);
        return $filas->groupBy('reserva_id')->map(function ($g) {
            $porTipo = [];
            foreach ($g as $f) {
                $porTipo[$f->tipo] = $f; // el último de cada tipo (el reenvío con plantilla reemplaza al fallido)
            }
            $primero = $g->first();
            return (object) [
                'reserva_id' => $primero->reserva_id,
                'telefono' => $primero->telefono,
                'nombre' => $primero->nombre_cliente,
                'fecha_visita' => $primero->fecha_visita,
                'enviado_at' => Carbon::parse($primero->created_at),
                'tipos' => $porTipo,
            ];
        })->sortByDesc('enviado_at')->values();
    }

    /** venta_id => horas desde el último mensaje del cliente, para las ventas por verificar. */
    public static function horasPorVenta(): array
    {
        if (!self::listo()) {
            return [];
        }
        $out = [];
        $ventas = DB::table('ventas as v')->join('reservas as r', 'r.id', '=', 'v.id_reserva')->join('clientes as c', 'c.id', '=', 'r.cliente_id')
            ->whereNotIn('v.estado_pago', ['pagado', 'rechazado'])->whereNotNull('v.comprobante_transferencia')
            ->orderBy('v.id', 'desc')->limit(300)->get(['v.id', 'c.whatsapp_cliente']);
        foreach ($ventas as $v) {
            $h = $v->whatsapp_cliente ? self::horasDesdeCliente($v->whatsapp_cliente) : null;
            if ($h !== null) {
                $out[$v->id] = round($h, 1);
            }
        }
        return $out;
    }
}
