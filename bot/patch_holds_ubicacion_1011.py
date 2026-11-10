"""Cupos apartados y texto de ubicación (10/11/2026).

a) El texto que ve el cliente sale de la ubicación que el sistema realmente asigna (antes: solo de la
   cantidad de personas, y a 5 personas siempre les decía "Níspero" aunque estuvieran ocupados).
b) Los cupos apartados por el bot (reserva_holds activos y sin vencer) cuentan en turnos de tinaja,
   en la capacidad por categoría y en el plan de ubicaciones, desde que se apartan.
"""
import sys

BU = 'app/Services/BotUbicacionesService.php'
DS = 'app/Services/DisponibilidadService.php'
MARCA = 'patch_holds_ubicacion_1011'


def reemplazar(s, viejo, nuevo, nombre):
    n = s.count(viejo)
    if n != 1:
        sys.exit('No encontré el ancla "%s" (%d coincidencias). No se cambió nada.' % (nombre, n))
    return s.replace(viejo, nuevo, 1)


bu = open(BU, encoding='utf-8').read()
ds = open(DS, encoding='utf-8').read()
if MARCA in bu and MARCA in ds:
    print('ya estaba aplicado')
    sys.exit(0)

# ── DisponibilidadService: turnos de tinaja ───────────────────────────────────
ds = reemplazar(ds, """        $usados = $usadosReservas + $usadosOrdenes;
""", """        // Cupos apartados por el bot (transferencia en curso) — patch_holds_ubicacion_1011
        $usadosHolds = 0;
        try {
            $usadosHolds = (int) DB::table('reserva_holds')
                ->where('estado', 'activo')->where('fecha', $fecha)->where('expira_en', '>', now())
                ->selectRaw('COALESCE(SUM(CEIL(personas / 5)), 0) as slots')->value('slots');
        } catch (\\Throwable $e) {
        }

        $usados = $usadosReservas + $usadosOrdenes + $usadosHolds;
""", 'slotsTinaja')

# ── DisponibilidadService: capacidad por categoría ───────────────────────────
ds = reemplazar(ds, """        return [
            'estacion' => (int) $ordenes->whereIn('espacio_tipo', self::ESPACIO_TIPOS_ESTACION)->sum('cantidad_personas'),
            'wellness' => (int) $ordenes->whereIn('espacio_tipo', self::ESPACIO_TIPOS_WELLNESS)->sum('cantidad_personas'),
        ];""", """        $holds = collect();
        try {
            $holds = DB::table('reserva_holds')
                ->join('programas', 'reserva_holds.programa_id', '=', 'programas.id')
                ->where('reserva_holds.estado', 'activo')->where('reserva_holds.fecha', $fecha)->where('reserva_holds.expira_en', '>', now())
                ->select('programas.espacio_tipo', 'reserva_holds.personas as cantidad_personas')->get();
        } catch (\\Throwable $e) {
        }
        $ordenes = $ordenes->concat($holds); // patch_holds_ubicacion_1011

        return [
            'estacion' => (int) $ordenes->whereIn('espacio_tipo', self::ESPACIO_TIPOS_ESTACION)->sum('cantidad_personas'),
            'wellness' => (int) $ordenes->whereIn('espacio_tipo', self::ESPACIO_TIPOS_WELLNESS)->sum('cantidad_personas'),
        ];""", 'pendientes por categoría')

# ── BotUbicacionesService: holds en el plan ──────────────────────────────────
bu = reemplazar(bu, """        $unidades = self::unidades();
        list($fijas, $auto) = self::ocupacion($fecha, $reservaId);
""", """        $unidades = self::unidades();
        list($fijas, $auto) = self::ocupacion($fecha, $reservaId);
        // Cupos apartados por el bot: también ocupan lugar — patch_holds_ubicacion_1011
        foreach (self::holdsActivos($fecha, $reservaId) as $h) {
            if ($h['cat'] === 'estacion') {
                $auto['h' . $h['id']] = $h['personas'];
            } elseif ($h['cat'] === 'wellness') {
                $fijas = array_merge($fijas, self::lugaresHoldWellness($h, $unidades, $fijas));
            }
        }
""", 'plan holds')

bu = reemplazar(bu, """        $base['texto_cliente'] = self::textoEstacion($personas);
        foreach ($opt as $k => $ids) {
            if ($k === 'nuevo') {
                continue;
            }""", """        $base['texto_cliente'] = self::textoAsignacion($opt['nuevo'], $N, $personas);
        foreach ($opt as $k => $ids) {
            if ($k === 'nuevo' || strpos((string) $k, 'h') === 0) {
                continue;
            }""", 'texto y reasignar')

metodos = r'''
    // ── patch_holds_ubicacion_1011 ───────────────────────────────────────────
    /** Texto para el cliente según lo que realmente se asignó (Nísperos y estaciones). */
    public static function textoAsignacion(array $ids, array $nisperos, int $personas): string
    {
        $nN = count(array_intersect($ids, $nisperos));
        $nE = count($ids) - $nN;
        $num = [1 => 'una', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco'];
        $est = $nE === 1 ? 'una estación de descanso' : (($num[$nE] ?? $nE) . ' estaciones de descanso');
        if ($nN === 0) {
            return $nE <= 1 ? 'una estación de descanso' : $est . ' (el grupo se reparte entre ellas)';
        }
        $nis = $nN === 1 ? 'una estación grupal con terraza (Níspero)' : 'las dos estaciones grupales con terraza (los dos Nísperos)';
        return $nE === 0 ? $nis : $nis . ' y ' . $est;
    }

    /** Holds activos y sin vencer del día (excluye el que se está convirtiendo en la reserva $reservaId). */
    private static function holdsActivos(string $fecha, ?int $reservaId = null): array
    {
        try {
            $hs = DB::table('reserva_holds')->where('estado', 'activo')->where('fecha', $fecha)
                ->where('expira_en', '>', now())->orderBy('id')->get();
            $excluir = null;
            if ($reservaId) {
                $r = DB::table('reservas')->where('id', $reservaId)->first();
                if ($r) {
                    $m = $hs->first(function ($h) use ($r) {
                        return (int) $h->programa_id === (int) $r->id_programa && (int) $h->personas === (int) $r->cantidad_personas;
                    });
                    $excluir = $m ? $m->id : null;
                }
            }
            $out = [];
            foreach ($hs as $h) {
                if ($excluir && $h->id === $excluir) {
                    continue;
                }
                $p = DB::table('programas')->where('id', $h->programa_id)->first();
                $d = json_decode((string) ($h->datos_json ?? ''), true) ?: [];
                $out[] = ['id' => $h->id, 'personas' => (int) $h->personas, 'cat' => self::categoria($p), 'pref' => $d['ubicacion_preferida'] ?? null];
            }
            return $out;
        } catch (\Throwable $e) {
            \Log::warning('[BotUbicaciones] holdsActivos: ' . $e->getMessage());
            return [];
        }
    }

    /** Lugares que tomaría un hold de Wellness (misma regla que el plan: terraza chica, grande o reposeras). */
    private static function lugaresHoldWellness(array $h, $unidades, array $fijas): array
    {
        $libres = $unidades->reject(function ($u) use ($fijas) {
            return in_array((int) $u->id, $fijas, true);
        });
        $grandes = $unidades->filter(function ($u) {
            return $u->sub_tipo === 'terraza' && preg_match('/quillay\s*[01]\b/i', $u->nombre);
        })->keys()->map(function ($i) { return (int) $i; })->all();
        $terr  = $libres->where('sub_tipo', 'terraza')->keys()->map(function ($i) { return (int) $i; })->all();
        $repos = $libres->where('sub_tipo', 'reposera')->keys()->map(function ($i) { return (int) $i; })->all();
        $p = $h['personas'];
        if ($h['pref'] === 'reposera' && in_array($p, [2, 4], true) && count($repos) >= $p / 2) {
            return array_slice($repos, 0, (int) ($p / 2));
        }
        if ($p <= 6) {
            $orden = array_merge(array_values(array_diff($terr, $grandes)), array_values(array_intersect($terr, $grandes)));
            if ($orden) {
                return [$orden[0]];
            }
            return in_array($p, [2, 4], true) && count($repos) >= $p / 2 ? array_slice($repos, 0, (int) ($p / 2)) : [];
        }
        return !array_diff($grandes, $terr) ? $grandes : [];
    }

'''
ancla = '    public static function plan(string $fecha'
bu = reemplazar(bu, ancla, metodos.lstrip('\n') + ancla, 'métodos')

open(BU, 'w', encoding='utf-8').write(bu)
open(DS, 'w', encoding='utf-8').write(ds)
print('Cupos apartados cuentan en tinajas y ubicaciones; texto de ubicación según lo asignado')
