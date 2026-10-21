@extends('themes.backoffice.layouts.admin')

@section('title', 'Turnos sugeridos')

@section('breadcrumbs')
<li>Equipo</li><li>Turnos sugeridos</li>
@endsection

@section('content')
@include('themes.backoffice.pages.egresos_panel._estilo')
<style>
    .eg .dia { border-top: 1px solid #eef0ee; padding: 14px 0; display: grid; grid-template-columns: 170px minmax(0, 1fr) auto; gap: 16px; align-items: start; }
    .eg .dia:first-of-type { border-top: 0; }
    .eg .dia .n { font-size: 1.5rem; font-weight: 500; font-variant-numeric: tabular-nums; }
    .eg .chip-p { display: inline-block; padding: 3px 10px; border-radius: 999px; background: #eaf4ef; color: #0e5f49; font-size: .85rem; margin: 2px 4px 2px 0; }
    .eg .chip-p.libre { background: #f3f4f3; color: #6b756f; }
    .eg .chip-p.alerta { background: #fff4e0; color: #8a5a0b; }
    .eg .linea { font-size: .88rem; margin: 3px 0; }
    .eg .linea b { font-weight: 500; color: #3b4541; display: inline-block; min-width: 74px; }
    .eg table.eq { width: 100%; border-collapse: collapse; font-size: .88rem; }
    .eg table.eq th { text-align: left; font-weight: 500; color: #6b756f; font-size: .78rem; padding: 6px 4px; border-bottom: 1px solid #eef0ee; }
    .eg table.eq td { padding: 6px 4px; border-bottom: 1px solid #f3f4f3; vertical-align: middle; }
    .eg .dsem label { display: inline-flex; align-items: center; gap: 2px; margin-right: 6px; font-size: .8rem; color: #3b4541; }
    .eg .dsem input[type=checkbox] { position: static; opacity: 1; pointer-events: auto; width: 14px; height: 14px; margin: 0; }
    .eg .dsem input[type=checkbox] + span { display: none; }
    @media (max-width: 760px) {
        .eg .dia { grid-template-columns: 1fr; gap: 6px; }
        .eg table.eq thead { display: none; }
        .eg table.eq tr { display: block; border-bottom: 1px solid #eef0ee; padding: 8px 0; }
        .eg table.eq td { display: block; border: 0; padding: 3px 0; }
    }
</style>
@php
    $dl = \App\Equipo\Turnos::DIAS;
    $nombres = function ($l) { return collect($l)->pluck('corto')->implode(', '); };
@endphp
<div class="section eg">
    @if(session('success'))<div class="aviso ok">{{ session('success') }}</div>@endif

    <div class="top">
        <div>
            <div class="eyebrow">Equipo</div>
            <h1>Turnos sugeridos</h1>
            <p class="sub">Según los clientes reservados de cada día. El aviso del día siguiente sale a las 15:00 por WhatsApp.</p>
        </div>
        <nav class="pills">
            <a class="pill" href="{{ route('backoffice.asignacion.index') }}">Turnos asignados</a>
            <form method="POST" action="{{ route('backoffice.equipo.turnos.avisar') }}" style="display:inline">@csrf<button class="pill" style="cursor:pointer">Enviar aviso de mañana ahora</button></form>
        </nav>
    </div>

    <section class="card-e" aria-label="Próximos días" style="margin-bottom:18px">
        <h2>Próximos 7 días</h2>
        @forelse($dias as $x)
            <div class="dia">
                <div>
                    <div style="font-weight:500">{{ ucfirst($x['dia']) }}</div>
                    <div class="n">{{ $x['personas'] }} <span class="gris" style="font-size:.85rem;font-weight:400">clientes</span></div>
                    <div class="gris" style="font-size:.8rem">{{ $x['reservas'] }} reservas @if($x['masajes'])· {{ $x['masajes'] }} masajes @endif</div>
                </div>
                <div>
                    @if(!$x['personas'])
                        <div class="linea gris">Sin reservas todavía.</div>
                    @else
                        <div class="linea">
                            @if($x['bajo_minimo'])<span class="chip-p alerta">bajo el mínimo de {{ $params['minimo_abrir'] }}: día a pérdida</span>
                            @elseif($x['rentable'])<span class="chip-p">rentable</span>
                            @else<span class="chip-p libre">no pierde, aún no cubre los fijos (rentable desde {{ $params['rentable_desde'] }})</span>@endif
                            <span class="gris" style="font-size:.8rem">tramo desde {{ $x['tramo']['desde'] }}: {{ $x['tramo']['frente'] }} frente · {{ $x['tramo']['cocina'] }} cocina · {{ $x['tramo']['aseo'] }} aseo</span></div>
                        <div class="linea"><b>Frente</b>@foreach($x['atencion'] as $p)<span class="chip-p">{{ $p['corto'] }}</span>@endforeach
                            @if($x['falta'])<span class="chip-p alerta">faltan {{ $x['falta'] }}</span>@endif</div>
                        @if($x['quedan_libres'])<div class="linea"><b>Libres</b>@foreach($x['quedan_libres'] as $p)<span class="chip-p libre">{{ $p['corto'] }}</span>@endforeach</div>@endif
                        <div class="linea"><b>Cocina</b>{{ $nombres($x['cocina']) ?: '—' }}
                            @if($x['falta_cocina'])<span class="chip-p alerta">faltan {{ $x['falta_cocina'] }}</span>@endif
                            @if($x['copero'])<span class="chip-p alerta">{{ $x['apoyo'] ? '+ ' . $nombres($x['apoyo']) : '+ llamar copero' }}</span>@endif</div>
                    @endif
                    @if($x['personas'] && ($x['aseo'] || $x['falta_aseo']))<div class="linea"><b>Aseo</b>{{ $nombres($x['aseo']) }}@if($x['falta_aseo'])<span class="chip-p alerta">falta {{ $x['falta_aseo'] }}</span>@endif</div>@endif
                    @if($x['fijos'])<div class="linea"><b>Fijos</b><span class="gris">{{ $nombres($x['fijos']) }}</span></div>@endif
                    @if($x['asignados'])<div class="linea"><b>Asignados</b><span class="gris">{{ collect($x['asignados'])->map(function ($n) { return \App\Equipo\Turnos::corto($n); })->implode(', ') }}</span></div>@endif
                    @if($x['guardada'] && $x['guardada']->avisado_at)<div class="linea gris" style="font-size:.8rem">Avisado {{ \Carbon\Carbon::parse($x['guardada']->avisado_at)->format('d-m H:i') }} · {{ $x['guardada']->aviso_estado }}</div>@endif
                </div>
                <div>
                    @if($x['personas'])
                    <form method="POST" action="{{ route('backoffice.equipo.turnos.aceptar', $x['fecha']) }}">@csrf
                        <button class="btn-c {{ $x['asignados'] ? 'ghost' : '' }}">{{ $x['asignados'] ? 'Reemplazar asignación' : 'Aceptar y asignar' }}</button>
                    </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="gris">No hay días habilitados ni reservas en los próximos 7 días.</p>
        @endforelse
    </section>

    <div class="cols">
        <div class="col-a">
            <section class="card-e" aria-label="Equipo">
                <h2>Quién entra en los turnos</h2>
                <p class="gris" style="font-size:.85rem;margin:0 0 8px">Área, días que puede trabajar y prioridad (1 entra primero; a igual prioridad, entra quien lleva menos días en la semana). El teléfono sirve para avisarle directo.</p>
                <form method="POST" action="{{ route('backoffice.equipo.turnos.equipo') }}">@csrf
                    <table class="eq">
                        <thead><tr><th>Persona</th><th>Área</th><th>Días</th><th>Prior.</th><th>WhatsApp</th></tr></thead>
                        <tbody>
                        @foreach($equipo as $u)
                            <tr>
                                <td>{{ $u->name }}@if(!$u->configurado)<br><span class="gris" style="font-size:.75rem">según su rol</span>@endif</td>
                                <td><select name="p[{{ $u->id }}][area]" class="browser-default" aria-label="Área de {{ $u->name }}">
                                    @foreach(\App\Equipo\Turnos::AREAS as $k => $v)<option value="{{ $k }}" {{ $u->area === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach
                                </select></td>
                                <td class="dsem">@foreach($dl as $n => $d)<label><input type="checkbox" name="p[{{ $u->id }}][dias][]" value="{{ $n }}" {{ in_array($n, $u->dias, true) ? 'checked' : '' }}><span></span>{{ $d }}</label>@endforeach</td>
                                <td><input type="number" min="1" max="9" name="p[{{ $u->id }}][prioridad]" value="{{ $u->prioridad }}" class="campo" style="width:58px" aria-label="Prioridad"></td>
                                <td><input type="text" name="p[{{ $u->id }}][telefono]" value="{{ $u->telefono }}" class="campo" style="width:130px" placeholder="569…" inputmode="tel" aria-label="WhatsApp"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div style="margin-top:10px"><button class="btn-c">Guardar equipo</button></div>
                </form>
            </section>
        </div>
        <div class="col-b">
            <section class="card-e" aria-label="Reglas">
                <h2>Reglas</h2>
                <form method="POST" action="{{ route('backoffice.equipo.turnos.reglas') }}">@csrf
                    @foreach(\App\Equipo\Turnos::parametrosDefault() as $k => $d)
                        <label style="display:block;font-size:.85rem;color:#3b4541;margin:8px 0 3px">{{ $d[0] }}</label>
                        <input type="text" name="{{ $k }}" value="{{ $params[$k] }}" class="campo" style="width:100%">
                    @endforeach
                    <div style="margin-top:12px"><button class="btn-c">Guardar reglas</button></div>
                </form>
                <p class="gris" style="font-size:.8rem;margin:10px 0 0">Tramos: "13:3,2,1" = desde 13 clientes, 3 en frente, 2 en cocina y 1 en aseo. Con el equipo mínimo un día no pierde desde 7 clientes y es rentable (cubre también los gastos fijos) desde unos 20. El copero o la persona de "Apoyo a llamado" se suma desde el umbral.</p>
            </section>
        </div>
    </div>
</div>
@endsection
