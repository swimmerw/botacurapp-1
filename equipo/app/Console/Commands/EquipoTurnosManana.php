<?php

namespace App\Console\Commands;

use App\Equipo\Turnos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Turnos sugeridos del día siguiente según los clientes reservados. Programado a las 15:00 con --enviar.
 *   php artisan equipo:turnos-manana                 muestra la propuesta de mañana (no envía)
 *   php artisan equipo:turnos-manana --dias=7        próximos 7 días
 *   php artisan equipo:turnos-manana --enviar        guarda y avisa por WhatsApp (una vez por día)
 *   php artisan equipo:turnos-manana --sembrar       carga la configuración inicial del equipo (solo la que falta)
 */
class EquipoTurnosManana extends Command
{
    protected $signature = 'equipo:turnos-manana {--fecha=} {--dias=1} {--enviar} {--forzar} {--sembrar}';
    protected $description = 'Propone el equipo del día siguiente según los clientes y avisa por WhatsApp';

    public function handle(Turnos $t)
    {
        if (!Turnos::hay()) {
            $this->error('Faltan las tablas: php artisan migrate');
            return 1;
        }
        if ($this->option('sembrar')) {
            $this->sembrar($t);
        }
        $desde = $this->option('fecha') ?: now('America/Santiago')->addDay()->toDateString();
        for ($i = 0; $i < max(1, (int) $this->option('dias')); $i++) {
            $f = \Carbon\Carbon::parse($desde)->addDays($i)->toDateString();
            $x = $t->proponer($f);
            if (!$x['personas'] && !$x['habilitada']) {
                $this->line("{$x['dia']}: cerrado y sin reservas");
                continue;
            }
            $prop = $t->guardar($x);
            $this->line($prop->mensaje);
            if ($this->option('enviar')) {
                if ($prop->avisado_at && !$this->option('forzar')) {
                    $this->line('  (ya avisado a las ' . substr($prop->avisado_at, 11, 5) . ')');
                    continue;
                }
                $estados = [];
                foreach (array_filter(array_map('trim', explode(',', Turnos::parametros()['telefonos_aviso']))) as $tel) {
                    $estados[] = substr($tel, -4) . ': ' . Turnos::enviarWhatsApp($tel, $prop->mensaje);
                }
                if (in_array(mb_strtolower(Turnos::parametros()['avisar_trabajadores']), ['si', 'sí', '1', 'true'], true)) {
                    foreach (array_merge($x['atencion'], $x['cocina'], $x['apoyo']) as $u) {
                        if (!empty($u['telefono'])) {
                            $estados[] = $u['corto'] . ': ' . Turnos::enviarWhatsApp($u['telefono'],
                                "Hola {$u['corto']}, mañana {$x['dia']} estás en el turno de Botacura, de 9:30 a 19:30. Si no puedes, avísale a Sebastián.");
                        }
                    }
                }
                DB::table('turno_propuestas')->where('id', $prop->id)->update(['avisado_at' => now(), 'aviso_estado' => mb_substr(implode(' · ', $estados), 0, 191)]);
                $this->line('  Aviso: ' . implode(' · ', $estados));
            }
        }
        return 0;
    }

    /** Configuración inicial con lo que contó Sebastián (19–20/10); lo que no calce queda según el rol. */
    private function sembrar(Turnos $t)
    {
        $reglas = [
            ['Juan Guzm%', 'atencion', '1,2,3,4,5,6,7', 1], ['Catalina Maur%', 'atencion', '1,2,3,4,5,6,7', 1],
            ['Fernando Morales%', 'atencion', '1,2,3,4,5,6,7', 1], ['Pamela%', 'atencion', '4,5,6,7', 2],
            ['David Tapia%', 'atencion', '4,5,6,7', 2], ['Francisca Bahamondes%', 'atencion', '1,2,3,4,5', 3],
            ['Elohim%', 'fijo', '1,2,3,4,5', 5], ['Fabiola%', 'fijo', '1,4', 5], ['Juan Carlos%', 'fijo', '6,7', 5],
            ['Gabriel Villar%', 'no', '', 9], ['Paula Riquelme%', 'no', '', 9], ['Octavio Pe%', 'no', '', 9],
        ];
        foreach ($reglas as [$patron, $area, $dias, $prio]) {
            $us = DB::table('users')->where('name', 'like', $patron)->where('activo', true)->get(['id', 'name']);
            if ($us->count() !== 1) {
                $this->warn("  $patron: " . ($us->count() ? 'varios, no lo toco' : 'no encontrado'));
                continue;
            }
            $u = $us->first();
            if (DB::table('equipo_config')->where('user_id', $u->id)->exists()) {
                continue;
            }
            DB::table('equipo_config')->insert(['user_id' => $u->id, 'area' => $area, 'dias' => $dias, 'prioridad' => $prio,
                'created_at' => now(), 'updated_at' => now()]);
            $this->line("  {$u->name}: " . Turnos::AREAS[$area] . ($dias ? " ($dias)" : ''));
        }
        $this->line('Camila (martes a sábado, turno fijo): asígnala en Equipo › Turnos sugeridos (hay más de una Camila).');
    }
}
