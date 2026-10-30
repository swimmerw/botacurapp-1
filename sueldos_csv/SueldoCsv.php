<?php

namespace App\Finanzas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de los CSV de sueldos enviados al banco (BancoEstado, transferencias a terceros).
 *
 * Cada vez que se descarga el CSV se guarda qué semana y qué monto exacto va para cada persona.
 * Cuando llega el aviso de la transferencia, se calza contra esa línea (persona + monto exacto,
 * o el total del lote si el banco manda un solo aviso por todo el archivo) y la semana queda
 * "Pagado" en Remuneraciones, aunque el monto incluya bono o la semana esté partida en dos meses.
 */
class SueldoCsv
{
    const TABLA = 'sueldo_csv_lineas';
    const DIAS = 20; // un aviso calza con líneas exportadas hasta 20 días antes

    public static function hay(): bool
    {
        return Schema::hasTable(self::TABLA);
    }

    /** ¿Esta semana de esta persona ya está pagada en Remuneraciones? (misma regla que la vista) */
    public static function pagada(int $userId, string $inicio, string $fin): bool
    {
        return DB::table('sueldos_pagados')->where('user_id', $userId)
            ->where('semana_inicio', $inicio)->where('semana_fin', $fin)->exists();
    }

    /** Guarda las líneas de un CSV descargado. $lineas: [['user_id','inicio','fin','monto'], ...] */
    public static function registrar(array $lineas): ?string
    {
        if (!self::hay() || !$lineas) {
            return null;
        }
        $lote = date('YmdHis') . '-' . substr(md5(uniqid('', true)), 0, 6);
        foreach ($lineas as $l) {
            // Si la misma semana ya estaba en un CSV anterior sin pagar, esa línea queda reemplazada
            DB::table(self::TABLA)->where('user_id', $l['user_id'])->where('semana_inicio', $l['inicio'])
                ->where('semana_fin', $l['fin'])->whereNull('pagado_at')->update(['reemplazada' => true, 'updated_at' => now()]);
            DB::table(self::TABLA)->insert([
                'lote' => $lote, 'user_id' => $l['user_id'], 'semana_inicio' => $l['inicio'], 'semana_fin' => $l['fin'],
                'monto' => (int) $l['monto'], 'id_user_exporta' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return $lote;
    }

    private static function pendientes(string $fecha)
    {
        return DB::table(self::TABLA)->whereNull('pagado_at')->where('reemplazada', false)
            ->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime($fecha . ' -' . self::DIAS . ' days')))
            ->where('created_at', '<=', date('Y-m-d 23:59:59', strtotime($fecha . ' +1 day')));
    }

    /**
     * Líneas que explican esta transferencia:
     *  - de esa persona, por el monto exacto (una línea, o la suma de todas sus líneas pendientes);
     *  - o, sin persona, un lote completo cuyo total es el monto (un solo aviso por el archivo).
     * @return array|null ['ids' => [...], 'nota' => '...']
     */
    public static function buscar(?int $userId, int $monto, string $fecha): ?array
    {
        if (!self::hay() || $monto <= 0) {
            return null;
        }
        if ($userId) {
            $suyas = self::pendientes($fecha)->where('user_id', $userId)->orderBy('semana_inicio')->get();
            $exactas = $suyas->where('monto', $monto);
            if ($exactas->count() >= 1) {
                // si hay varias iguales (dos CSV con la misma semana) se toma la más reciente
                $l = $exactas->sortByDesc('id')->first();
                return ['ids' => [$l->id], 'nota' => self::nota([$l])];
            }
            $porSemana = $suyas->sortByDesc('id')->unique(function ($l) { return $l->semana_inicio . $l->semana_fin; });
            if ($porSemana->count() > 1 && (int) $porSemana->sum('monto') === $monto) {
                return ['ids' => $porSemana->pluck('id')->all(), 'nota' => self::nota($porSemana->all())];
            }
            return null;
        }
        foreach (self::pendientes($fecha)->get()->groupBy('lote') as $lote => $ls) {
            if ($ls->count() > 1 && (int) $ls->sum('monto') === $monto) {
                return ['ids' => $ls->pluck('id')->all(), 'nota' => 'Pago del CSV de sueldos (' . $ls->count() . ' personas): marcados pagados'];
            }
        }
        return null;
    }

    /** Marca "Pagado" cada semana de esas líneas. Devuelve el id de sueldos_pagados de la primera. */
    public static function marcar(array $ids, string $fechaPago, ?int $correoId = null): ?int
    {
        $primero = null;
        foreach (DB::table(self::TABLA)->whereIn('id', $ids)->get() as $l) {
            $id = app(SueldoSemanas::class)->marcarPagada((int) $l->user_id,
                ['inicio' => $l->semana_inicio, 'fin' => $l->semana_fin, 'total' => (int) $l->monto], $fechaPago);
            if ($id && method_exists(SueldoSemanas::class, 'confirmar')) {
                SueldoSemanas::confirmar($id);
            }
            DB::table(self::TABLA)->where('id', $l->id)->update(['pagado_at' => now(), 'sueldo_pagado_id' => $id ?: null,
                'fin_correo_id' => $correoId, 'updated_at' => now()]);
            $primero = $primero ?: $id;
        }
        return $primero;
    }

    private static function nota(array $ls): string
    {
        $u = DB::table('users')->where('id', $ls[0]->user_id)->value('name');
        $sem = implode(' y ', array_map(function ($l) {
            return date('d-m', strtotime($l->semana_inicio)) . ' al ' . date('d-m', strtotime($l->semana_fin));
        }, $ls));
        return "Remuneración de {$u} (CSV), semana {$sem}: marcada pagada";
    }
}
