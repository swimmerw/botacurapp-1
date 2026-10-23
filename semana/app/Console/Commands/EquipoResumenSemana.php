<?php

namespace App\Console\Commands;

use App\Equipo\Turnos;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Resumen de la semana por el WhatsApp de avisos (Home Assistant), el día antes del primer día con reservas.
 *   php artisan equipo:resumen-semana                  muestra el resumen desde mañana hasta el domingo (no envía)
 *   php artisan equipo:resumen-semana --enviar         envía a los administradores SOLO si mañana es el primer día con reservas de su semana
 *   php artisan equipo:resumen-semana --enviar --forzar   envía igual (una vez más)
 *   php artisan equipo:resumen-semana --trabajadores   envía a cada persona sus turnos de esos días (asignados o, si no hay, propuestos)
 */
class EquipoResumenSemana extends Command
{
    protected $signature = 'equipo:resumen-semana {--desde=} {--enviar} {--forzar} {--trabajadores}';
    protected $description = 'Resumen semanal de reservas y turnos por WhatsApp';

    public function handle(Turnos $t)
    {
        if (!Turnos::hay()) {
            $this->error('Faltan las tablas de turnos: corre aplicar_turnos.sh');
            return 1;
        }
        $desde = $this->option('desde') ?: now('America/Santiago')->addDay()->toDateString();
        $dias = $t->semana($desde);
        $msgs = $t->mensajesSemana($dias);
        foreach ($msgs as $m) {
            $this->line($m);
        }
        $clave = 'resumen_semana_' . Carbon::parse($desde)->startOfWeek()->toDateString();

        if ($this->option('enviar')) {
            $esPrimero = $t->esPrimerDiaDeLaSemana($desde);
            $ya = DB::table('equipo_parametros')->where('clave', $clave)->value('valor');
            if (!$this->option('forzar') && (!$esPrimero || $ya)) {
                $this->line($ya ? "(ya enviado: $ya)" : '(mañana no es el primer día con reservas de la semana: no se envía)');
            } else {
                $est = [];
                foreach (array_filter(array_map('trim', explode(',', Turnos::parametros()['telefonos_aviso']))) as $tel) {
                    $ok = 0;
                    foreach ($msgs as $m) {
                        $ok += strpos(Turnos::enviarWhatsApp($tel, $m), 'ok') === 0 ? 1 : 0;
                    }
                    $est[] = substr($tel, -4) . ": $ok/" . count($msgs);
                }
                DB::table('equipo_parametros')->updateOrInsert(['clave' => $clave], ['valor' => now('America/Santiago')->format('d-m H:i') . ' ' . implode(' ', $est), 'updated_at' => now()]);
                $this->line('Enviado: ' . implode(' · ', $est));
            }
        }

        if ($this->option('trabajadores')) {
            $res = [];
            foreach ($t->turnosPorPersona($dias) as $p) {
                if (empty($p['telefono'])) {
                    $res[] = $p['corto'] . ': sin WhatsApp';
                    continue;
                }
                $r = Turnos::enviarWhatsApp($p['telefono'], Turnos::mensajeTrabajador($p));
                $res[] = $p['corto'] . ': ' . (strpos($r, 'ok') === 0 ? 'ok' : $r) . ($p['propuesto'] ? ' (propuesto)' : '');
            }
            $this->line('Equipo: ' . ($res ? implode(' · ', $res) : 'nadie con turnos esos días'));
        }
        return 0;
    }
}
