<?php

namespace App\Equipo;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Turnos sugeridos: cuántas personas de atención hacen falta según los clientes reservados del día,
 * quiénes pueden ese día (área, días, prioridad, reparto parejo en la semana), si hay que llamar al
 * copero y quiénes tienen turno fijo. El aviso sale el día anterior a las 15:00.
 */
class Turnos
{
    const AREAS = ['atencion' => 'Atención', 'cocina' => 'Cocina', 'apoyo' => 'Apoyo a llamado', 'fijo' => 'Turno fijo', 'no' => 'No entra en turnos'];
    const DIAS = [1 => 'Lu', 2 => 'Ma', 3 => 'Mi', 4 => 'Ju', 5 => 'Vi', 6 => 'Sá', 7 => 'Do'];
    const ROLES_ASIGNACION = ['anfitriona', 'barman', 'cocina', 'garzon', 'jefe local'];

    public static function hay(): bool
    {
        return Schema::hasTable('equipo_config') && Schema::hasTable('turno_propuestas');
    }

    public static function parametrosDefault(): array
    {
        return [
            'clientes_por_atencion' => ['Clientes por cada persona de atención', '12'],
            'minimo_atencion'       => ['Mínimo de personas de atención en un día abierto', '3'],
            'umbral_copero'         => ['Llamar copero o ayudante de cocina desde (clientes)', '50'],
            'telefonos_aviso'       => ['WhatsApp que reciben el aviso (separados por coma)', '56961910398'],
            'avisar_trabajadores'   => ['Avisar también a cada trabajador (sí/no)', 'no'],
        ];
    }

    public static function parametros(): array
    {
        $out = [];
        foreach (self::parametrosDefault() as $k => $d) {
            $out[$k] = $d[1];
        }
        if (Schema::hasTable('equipo_parametros')) {
            foreach (DB::table('equipo_parametros')->get() as $p) {
                if ($p->valor !== null && $p->valor !== '') {
                    $out[$p->clave] = $p->valor;
                }
            }
        }
        return $out;
    }

    /** Personas del equipo con su configuración (las que no tienen fila quedan según su rol). */
    public function equipo()
    {
        $cfg = DB::table('equipo_config')->get()->keyBy('user_id');
        $users = DB::table('users')->where('activo', true)->orderBy('name')->get(['id', 'name']);
        $roles = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->get(['role_user.user_id', 'roles.name'])->groupBy('user_id');
        $lista = $users->map(function ($u) use ($cfg, $roles) {
            $rs = isset($roles[$u->id]) ? $roles[$u->id]->pluck('name')->map(function ($r) { return mb_strtolower($r); })->all() : [];
            $c = $cfg[$u->id] ?? null;
            $u->roles = $rs;
            $u->area = $c ? $c->area : (in_array('cocina', $rs, true) ? 'cocina' : (array_intersect($rs, ['anfitriona', 'barman', 'garzon', 'jefe local']) ? 'atencion' : 'no'));
            $u->dias = array_map('intval', array_filter(explode(',', $c ? $c->dias : '4,5,6,7')));
            $u->prioridad = $c ? (int) $c->prioridad : 5;
            $u->telefono = $c->telefono ?? null;
            $u->configurado = (bool) $c;
            $u->corto = self::corto($u->name);
            return $u;
        });
        // Nombres cortos repetidos (dos Paula): se agrega la inicial del apellido
        $rep = $lista->countBy('corto');
        return $lista->map(function ($u) use ($rep) {
            if (($rep[$u->corto] ?? 0) > 1) {
                $p = preg_split('/\s+/', trim($u->name));
                $u->corto .= isset($p[1]) ? ' ' . mb_substr($p[count(explode(' ', $u->corto))] ?? $p[1], 0, 1) . '.' : '';
            }
            return $u;
        });
    }

    public static function corto(string $nombre): string
    {
        $p = preg_split('/\s+/', trim($nombre));
        return count($p) > 1 && mb_strlen($p[0]) <= 4 ? $p[0] . ' ' . $p[1] : $p[0];
    }

    /** Clientes, reservas y masajes del día (sin canceladas, no-show ni pendientes de pago). */
    public function demanda(string $fecha): array
    {
        $q = DB::table('reservas')->whereDate('fecha_visita', $fecha);
        if (Schema::hasColumn('reservas', 'estado')) {
            $q->where(function ($w) {
                $w->whereNull('estado')->orWhereNotIn('estado', ['cancelada', 'no_show', 'pendiente_pago']);
            });
        }
        $r = $q->selectRaw('COUNT(*) n, COALESCE(SUM(cantidad_personas),0) p, COALESCE(SUM(cantidad_masajes),0) m'
            . (Schema::hasColumn('reservas', 'cantidad_masajes_extra') ? ', COALESCE(SUM(cantidad_masajes_extra),0) mx' : ', 0 mx'))->first();
        $habil = Schema::hasTable('fecha_disponibles')
            ? DB::table('fecha_disponibles')->whereDate('fecha', $fecha)->where('habilitada', true)->exists() : null;
        return ['personas' => (int) $r->p, 'reservas' => (int) $r->n, 'masajes' => (int) $r->m + (int) $r->mx, 'habilitada' => $habil];
    }

    public function proponer(string $fecha): array
    {
        $p = self::parametros();
        $c = Carbon::parse($fecha);
        $dow = $c->dayOfWeekIso;
        $d = $this->demanda($fecha);
        $equipo = $this->equipo();
        $disp = function ($area) use ($equipo, $dow) {
            return $equipo->filter(function ($u) use ($area, $dow) { return $u->area === $area && in_array($dow, $u->dias, true); });
        };

        // Reparto parejo: quien lleva menos días asignados en la semana entra primero (a igual prioridad)
        $semana = DB::table('asignacion_user')->join('asignaciones', 'asignaciones.id', '=', 'asignacion_user.asignacion_id')
            ->whereBetween('asignaciones.fecha', [$c->copy()->startOfWeek()->toDateString(), $c->copy()->endOfWeek()->toDateString()])
            ->where('asignaciones.fecha', '<>', $fecha)
            ->groupBy('asignacion_user.user_id')->selectRaw('asignacion_user.user_id, COUNT(*) n')->pluck('n', 'user_id');

        $porCliente = max(1, (int) $p['clientes_por_atencion']);
        $necesarios = $d['personas'] > 0 ? max((int) $p['minimo_atencion'], (int) ceil($d['personas'] / $porCliente)) : 0;
        $candidatos = $disp('atencion')->sortBy(function ($u) use ($semana) {
            return sprintf('%02d-%02d-%s', $u->prioridad, $semana[$u->id] ?? 0, $u->name);
        })->values();
        $atencion = $candidatos->take($necesarios)->values();
        $falta = max(0, $necesarios - $candidatos->count());

        $cocina = $d['personas'] > 0 ? $disp('cocina')->values() : collect();
        $copero = $d['personas'] >= (int) $p['umbral_copero'];
        $apoyo = $copero ? $disp('apoyo')->sortBy('prioridad')->take(1)->values() : collect();
        $fijos = $disp('fijo')->values();

        $nombres = function ($c) { return $c->map(function ($u) { return ['id' => $u->id, 'nombre' => $u->name, 'corto' => $u->corto, 'telefono' => $u->telefono]; })->all(); };
        return [
            'fecha' => $fecha, 'dia' => $c->locale('es')->isoFormat('dddd D [de] MMMM'), 'dow' => $dow] + $d + [
            'necesarios' => $necesarios, 'falta' => $falta,
            'atencion' => $nombres($atencion), 'quedan_libres' => $nombres($candidatos->slice($necesarios)),
            'cocina' => $nombres($cocina), 'copero' => $copero, 'apoyo' => $nombres($apoyo), 'fijos' => $nombres($fijos),
        ];
    }

    /** Una sola línea (los parámetros de plantilla de WhatsApp no aceptan saltos de línea). */
    public function mensaje(array $x): string
    {
        $n = function ($l) { return implode(', ', array_column($l, 'corto')); };
        $cuando = $x['fecha'] === now('America/Santiago')->addDay()->toDateString() ? 'mañana ' : 'el ';
        if (!$x['personas']) {
            return ucfirst($cuando) . "{$x['dia']}: sin reservas" . ($x['fijos'] ? '. Turnos fijos: ' . $n($x['fijos']) : '') . '.';
        }
        $t = "Turnos para {$cuando}{$x['dia']}: {$x['personas']} clientes en {$x['reservas']} reservas"
            . ($x['masajes'] ? ", {$x['masajes']} masajes" : '') . '. '
            . 'Atención (' . count($x['atencion']) . '): ' . ($x['atencion'] ? $n($x['atencion']) : 'nadie disponible')
            . ($x['falta'] ? " · faltan {$x['falta']}" : '') . '. ';
        if ($x['cocina']) {
            $t .= 'Cocina: ' . $n($x['cocina']) . ($x['copero'] ? ' + ' . ($x['apoyo'] ? $n($x['apoyo']) . ' de apoyo' : 'llamar copero') : '') . '. ';
        } elseif ($x['copero']) {
            $t .= 'Llamar copero o ayudante de cocina. ';
        }
        if ($x['fijos']) {
            $t .= 'Fijos: ' . $n($x['fijos']) . '. ';
        }
        return trim($t . 'Revisar y aceptar: ' . rtrim(config('app.url'), '/') . '/equipo/turnos');
    }

    public function guardar(array $x): object
    {
        $msg = $this->mensaje($x);
        $prev = DB::table('turno_propuestas')->where('fecha', $x['fecha'])->first();
        $fila = ['personas' => $x['personas'], 'reservas' => $x['reservas'], 'propuesta' => json_encode($x, JSON_UNESCAPED_UNICODE),
                 'mensaje' => $msg, 'updated_at' => now()];
        if ($prev) {
            DB::table('turno_propuestas')->where('id', $prev->id)->update($fila);
        } else {
            DB::table('turno_propuestas')->insert($fila + ['fecha' => $x['fecha'], 'created_at' => now()]);
        }
        return DB::table('turno_propuestas')->where('fecha', $x['fecha'])->first();
    }

    /** Pasa la propuesta a Asignación de equipo (la misma que usa Turnos y Remuneraciones). */
    public function aceptar(string $fecha, ?int $userId = null): int
    {
        $prop = DB::table('turno_propuestas')->where('fecha', $fecha)->first();
        $x = $prop ? json_decode($prop->propuesta, true) : $this->proponer($fecha);
        $ids = array_merge(array_column($x['atencion'], 'id'), array_column($x['cocina'], 'id'), array_column($x['apoyo'], 'id'));
        $validos = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('role_user.user_id', $ids ?: [0])->get(['role_user.user_id', 'roles.name'])
            ->filter(function ($r) { return in_array(mb_strtolower($r->name), self::ROLES_ASIGNACION, true); })
            ->pluck('user_id')->unique()->values()->all();
        $a = \App\Asignacion::where('fecha', $fecha)->first() ?: \App\Asignacion::create(['fecha' => $fecha]);
        $a->users()->sync($validos);
        DB::table('turno_propuestas')->where('fecha', $fecha)->update(['aceptada_at' => now(), 'aceptada_por' => $userId, 'updated_at' => now()]);
        return count($validos);
    }

    /**
     * WhatsApp desde el número de avisos (el de Home Assistant). Con plantilla aprobada (EQUIPO_PLANTILLA, un
     * parámetro de texto) llega siempre; sin plantilla, solo si la persona escribió a ese número en las últimas 24 h.
     */
    public static function enviarWhatsApp(string $tel, string $texto): string
    {
        $tel = preg_replace('/\D/', '', $tel);
        $phoneId = env('EQUIPO_PHONE_NUMBER_ID') ?: (env('HA_PHONE_NUMBER_ID') ?: env('META_PHONE_NUMBER_ID'));
        $token = env('META_WHATSAPP_TOKEN');
        $ver = env('META_API_VERSION', 'v19.0');
        if (!$phoneId || !$token || !$tel) {
            return 'sin configuración de WhatsApp';
        }
        $texto = mb_substr(preg_replace('/\s+/u', ' ', $texto), 0, 1000);
        $post = function (array $cuerpo) use ($phoneId, $token, $ver) {
            $cl = new \GuzzleHttp\Client(['timeout' => 15, 'http_errors' => false]);
            $r = $cl->post("https://graph.facebook.com/{$ver}/{$phoneId}/messages", [
                'headers' => ['Authorization' => 'Bearer ' . $token], 'json' => $cuerpo + ['messaging_product' => 'whatsapp'],
            ]);
            return [$r->getStatusCode(), (string) $r->getBody()];
        };
        try {
            $plantilla = env('EQUIPO_PLANTILLA');
            if ($plantilla) {
                [$st, $b] = $post(['to' => $tel, 'type' => 'template', 'template' => ['name' => $plantilla,
                    'language' => ['code' => env('EQUIPO_PLANTILLA_IDIOMA', 'es')],
                    'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $texto]]]]]]);
                if ($st < 300) {
                    return 'ok (plantilla)';
                }
                Log::warning('[Turnos] Plantilla rechazada', ['status' => $st, 'body' => mb_substr($b, 0, 400)]);
            }
            [$st, $b] = $post(['to' => $tel, 'type' => 'text', 'text' => ['body' => $texto]]);
            return $st < 300 ? 'ok (texto)' : 'error ' . $st . ': ' . mb_substr(json_decode($b, true)['error']['message'] ?? $b, 0, 140);
        } catch (\Throwable $e) {
            return 'error: ' . mb_substr($e->getMessage(), 0, 140);
        }
    }
}
