# Enlaza Finanzas > Reservas del dia con los correos bancarios (19/10/2026).
# Cambios puntuales (idempotentes) en el controlador y la vista que ya estan en el servidor.
import re, sys
C = 'app/Http/Controllers/ReservasDelDiaController.php'
V = 'resources/views/themes/backoffice/pages/reporte/reservas_dia.blade.php'
c = open(C).read(); v = open(V).read(); cambios = []

if 'fin_correos' not in c:
    anc = "        $abonosExtra = Schema::hasTable('abonos_extra')"
    if anc not in c: sys.exit('No encontre $abonosExtra en el controlador')
    c = c.replace(anc, """        // Correos bancarios: que abonos de estas reservas ya llegaron al banco
        $finB = Schema::hasTable('fin_correos');
        $bancoVentas = $finB ? DB::table('fin_correos')->where('tipo', 'abono')
            ->whereIn('venta_id', $reservas->pluck('venta_id')->filter()->values())
            ->whereIn('estado', ['calzado', 'revisar'])->orderByDesc('id')->get()->groupBy('venta_id') : collect();
        foreach ($reservas as $r) {
            $r->banco = $r->venta_id ? optional($bancoVentas->get($r->venta_id))->first() : null;
        }

""" + anc, 1)
    anc2 = "$c->datos = json_decode($c->datos_json ?? '{}', true) ?: [];"
    if anc2 not in c: sys.exit('No encontre el map de comprobantes')
    c = c.replace(anc2, anc2 + "\n                $c->banco = Schema::hasTable('fin_correos') ? DB::table('fin_correos')->where('comprobante_id', $c->id)->orderByDesc('id')->first() : null;", 1)
    m = re.search(r"\n(\s*)return view\('themes\.backoffice\.pages\.reporte\.reservas_dia', compact\(([^)]*)\)\);", c)
    if not m: sys.exit('No encontre el return view')
    ind = m.group(1)
    bloque = (f"\n{ind}$bancoDia = $finB ? DB::table('fin_correos as fc')\n"
              f"{ind}    ->leftJoin('ventas as fv', 'fv.id', '=', 'fc.venta_id')\n"
              f"{ind}    ->where('fc.tipo', 'abono')->whereDate('fc.fecha', $dia)->where('fc.estado', '<>', 'sospechoso')\n"
              f"{ind}    ->orderBy('fc.hora')->select('fc.*', 'fv.id_reserva as reserva_id')->get() : collect();\n")
    nuevo = bloque + f"\n{ind}return view('themes.backoffice.pages.reporte.reservas_dia', compact({m.group(2)}, 'bancoDia'));"
    c = c[:m.start()] + nuevo + c[m.end():]
    cambios.append('controlador')

if 'finanzas_correos._badge' not in v:
    anc = "Desde: {{ $c->datos['nombre_origen'] ?? '—' }} · Op. {{ $c->datos['numero_operacion'] ?? '—' }}"
    if anc not in v: sys.exit('No encontre los datos del comprobante en la vista')
    v = v.replace(anc, anc + "\n                            @include('themes.backoffice.pages.finanzas_correos._badge', ['b' => $c->banco ?? null])", 1)
    m = re.search(r"(\n\s*)</td>(\s*<td class=\"right-align\">\{\{ \$f\(\$r->total_pagar \?\? \$r->diferencia_programa\) \}\}</td>)", v)
    if not m: sys.exit('No encontre la columna Medio de la reserva')
    v = v[:m.start()] + m.group(1) + "    @include('themes.backoffice.pages.finanzas_correos._badge', ['b' => $r->banco ?? null])" + m.group(1) + "</td>" + m.group(2) + v[m.end():]
    anc3 = "@if($abonosExtra->isNotEmpty())"
    if anc3 not in v: sys.exit('No encontre la seccion de abonos extra')
    v = v.replace(anc3, "@include('themes.backoffice.pages.finanzas_correos._dia', ['bancoDia' => $bancoDia ?? collect()])\n\n                    " + anc3, 1)
    cambios.append('vista')

open(C, 'w').write(c); open(V, 'w').write(v)
print('Reservas del dia: ' + (', '.join(cambios) if cambios else 'ya estaba enlazada'))
