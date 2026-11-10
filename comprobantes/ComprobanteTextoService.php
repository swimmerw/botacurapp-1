<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Comprobantes sin reserva (10/11/2026): lee monto, fecha y glosa del texto con que el bot describe
 * la imagen de un comprobante, y busca reservas candidatas para asociarlo.
 */
class ComprobanteTextoService
{
    const MESES = ['enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7,
        'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12];

    public static function limpiar(string $t): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(['\\n', "\n", '**', '\\/'], [' ', ' ', '', '/'], $t)));
    }

    public static function monto(string $t): ?int
    {
        $t = self::limpiar($t);
        if (preg_match('/(?:monto|importe|total|valor|cantidad)[^0-9$]{0,25}\$?\s*([\d][\d\.\,]{2,})/iu', $t, $m)
            || preg_match('/\$\s*([\d]{1,3}(?:[\.\,]\d{3})+)/u', $t, $m)) {
            return (int) preg_replace('/[^\d]/', '', preg_replace('/[\,\.]\d{2}$/', '', $m[1]));
        }
        return null;
    }

    public static function fecha(string $t): ?string
    {
        $t = self::limpiar($t);
        if (preg_match('/(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*(\d{2,4})/', $t, $m)) {
            $y = strlen($m[3]) == 2 ? 2000 + (int) $m[3] : (int) $m[3];
            return checkdate((int) $m[2], (int) $m[1], $y) ? sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]) : null;
        }
        if (preg_match('/(\d{1,2})\s+de\s+([a-záéíóú]+)\s+(?:de\s+)?(\d{4})/iu', $t, $m) && isset(self::MESES[mb_strtolower($m[2])])) {
            return sprintf('%04d-%02d-%02d', $m[3], self::MESES[mb_strtolower($m[2])], $m[1]);
        }
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $t, $m)) {
            return $m[0];
        }
        return null;
    }

    public static function glosa(string $t): ?string
    {
        $t = str_replace(['\\n', "\n", '**', '\\/'], ['  ', '  ', '', '/'], $t);
        return preg_match('/(?:mensaje|glosa|comentario|asunto)\s*:\s*(.{3,60}?)(?:\s{2,}|\s-\s|\.|\]|$)/iu', $t, $m) ? trim($m[1]) : null;
    }

    public static function n9($tel): string
    {
        return substr(preg_replace('/\D/', '', (string) $tel), -9);
    }

    /** Guarda un comprobante sin reserva (no duplica el mismo teléfono + monto + fecha). */
    public static function registrar(string $telefono, ?string $nombre, string $descripcion, ?string $archivo = null, ?string $phoneNumberId = null, ?string $llegada = null): ?int
    {
        $monto = self::monto($descripcion);
        $fecha = self::fecha($descripcion);
        $tel   = preg_replace('/\D/', '', $telefono);
        $ya = DB::table('comprobantes_sin_reserva')->where('telefono', $tel)->where('monto', $monto)->where('fecha_pago', $fecha)->value('id');
        if ($ya) {
            return (int) $ya;
        }
        return (int) DB::table('comprobantes_sin_reserva')->insertGetId([
            'telefono' => $tel, 'nombre' => $nombre ? mb_substr($nombre, 0, 150) : null, 'monto' => $monto, 'fecha_pago' => $fecha,
            'glosa' => self::glosa($descripcion), 'descripcion' => mb_substr(self::limpiar($descripcion), 0, 2000),
            'archivo' => $archivo, 'phone_number_id' => $phoneNumberId, 'estado' => 'pendiente',
            'created_at' => $llegada ?: now(), 'updated_at' => now(),
        ]);
    }

    /** Reservas candidatas: del mismo teléfono (cliente o chat del cupo apartado) o con el mismo monto. */
    public static function candidatas($c, int $limite = 5): array
    {
        $t = self::n9($c->telefono);
        $base = function () {
            return DB::table('reservas as r')->join('clientes as cl', 'cl.id', '=', 'r.cliente_id')
                ->leftJoin('ventas as v', 'v.id_reserva', '=', 'r.id')->leftJoin('programas as p', 'p.id', '=', 'r.id_programa')
                ->where('r.fecha_visita', '>=', now()->subDays(30)->toDateString())
                ->select('r.id', 'r.fecha_visita', 'r.cantidad_personas', 'r.estado', 'cl.nombre_cliente', 'cl.whatsapp_cliente',
                    'p.nombre_programa', 'v.id as venta_id', 'v.abono_programa', 'v.diferencia_programa', 'v.estado_pago');
        };
        $holds = DB::table('reserva_holds')->whereNotNull('id_reserva')
            ->where(function ($w) use ($t) { $w->whereRaw('RIGHT(telefono,9) = ?', [$t])->orWhere('datos_json', 'like', '%"telefono_chat":"56' . $t . '"%'); })
            ->pluck('id_reserva')->all();
        $porTel = $base()->where(function ($w) use ($t, $holds) {
            $w->whereRaw("RIGHT(REPLACE(REPLACE(REPLACE(cl.whatsapp_cliente,' ',''),'+',''),'-',''),9) = ?", [$t]);
            if ($holds) { $w->orWhereIn('r.id', $holds); }
        })->orderBy('r.fecha_visita')->limit($limite)->get()->all();
        foreach ($porTel as $r) { $r->motivo = 'mismo teléfono'; }
        $porMonto = [];
        if ($c->monto) {
            $m = (int) $c->monto;
            $porMonto = $base()->where(function ($w) use ($m) {
                $w->where('v.abono_programa', $m)->orWhere('v.comprobante_monto', $m)->orWhereRaw('v.abono_programa + IFNULL(v.diferencia_programa,0) = ?', [$m]);
            })->where('r.created_at', '>=', now()->subDays(45)->toDateTimeString())->orderBy('r.id', 'desc')->limit($limite)->get()->all();
            $ids = array_map(function ($r) { return $r->id; }, $porTel);
            $porMonto = array_values(array_filter($porMonto, function ($r) use ($ids) { return !in_array($r->id, $ids); }));
            foreach ($porMonto as $r) { $r->motivo = 'mismo monto'; }
        }
        return array_merge($porTel, $porMonto);
    }
}
