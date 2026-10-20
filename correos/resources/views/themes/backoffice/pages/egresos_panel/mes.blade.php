@extends('themes.backoffice.layouts.admin')

@section('title', 'Egresos · ' . $nombreMes)

@section('breadcrumbs')
<li><a href="{{ route('backoffice.egreso.index') }}">Egresos</a></li><li>{{ $nombreMes }}</li>
@endsection

@php
    $peso = function ($n) { return '$' . number_format((int) $n, 0, ',', '.'); };
    $max = max(1, collect($d['arbol'])->max('total'));
    $var = $prev['total'] ? round(($d['total'] - $prev['total']) * 100 / $prev['total']) : null;
    $saldoDeudas = $deudas->sum('saldo');
    $mesCorto = mb_strtolower(explode(' ', $nombreMes)[0]);
    $grupos = [];
    foreach ($categoriasNuevas as $sid => $x) { $grupos[$x['cat']][$sid] = $x['sub']; }
@endphp

@section('content')
@include('themes.backoffice.pages.egresos_panel._estilo')
<div class="section eg">
    @if(session('success'))<div class="aviso ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="aviso falta">{{ session('error') }}</div>@endif

    <div class="top">
        <div>
            <div class="eyebrow">Finanzas · Egresos</div>
            <h1>Egresos de {{ $mesCorto }}</h1>
            <p class="sub">Se llena solo con las facturas del SII, los avisos del banco, las remuneraciones y lo que llega al correo.</p>
        </div>
        <nav class="pills" aria-label="Mes y vistas">
            <a class="pill" href="{{ route('backoffice.egreso.mes', [$anterior->year, $anterior->month]) }}" aria-label="Mes anterior">‹ {{ ucfirst($anterior->locale('es')->isoFormat('MMM')) }}</a>
            <span class="pill on">{{ $nombreMes }}</span>
            <a class="pill" href="{{ route('backoffice.egreso.mes', [$siguiente->year, $siguiente->month]) }}" aria-label="Mes siguiente">{{ ucfirst($siguiente->locale('es')->isoFormat('MMM')) }} ›</a>
            <a class="pill" href="{{ route('backoffice.finanzas.deudas.index') }}">Por pagar</a>
            <a class="pill" href="{{ route('backoffice.finanzas.clasificar.index') }}">Clasificar</a>
        </nav>
    </div>

    @if(!$faltan)
        <div class="aviso ok" role="status">
            <span class="ms" aria-hidden="true">task_alt</span>
            <div><strong>{{ ucfirst($mesCorto) }} está cuadrado.</strong> Cada factura tiene su pago, no hay movimientos del banco por clasificar y no hay cuentas vencidas.</div>
        </div>
    @else
        <div class="aviso falta" role="status">
            <span class="ms" aria-hidden="true">pending_actions</span>
            <div><strong>Para cuadrar {{ $mesCorto }} falta:</strong>
                @foreach($faltan as $f)<a href="{{ $f['href'] }}">{{ $f['n'] }} {{ $f['txt'] }}</a>@if(!$loop->last) · @endif @endforeach
            </div>
        </div>
    @endif

    <section class="kpis" aria-label="Resumen">
        <div class="card-e kpi">
            <div class="l">Egresos del mes</div>
            <div class="v">{{ $peso($d['total']) }}</div>
            <div class="l">@if($var !== null){{ $var >= 0 ? '+' : '' }}{{ $var }} % vs {{ $anterior->locale('es')->isoFormat('MMMM') }}@else sin mes anterior @endif</div>
        </div>
        <div class="card-e kpi">
            <div class="l">Facturas con pago</div>
            <div class="v {{ $d['egresos_n'] && $d['cuadrados'] === $d['egresos_n'] ? 'verde' : '' }}">{{ $d['egresos_n'] ? round($d['cuadrados'] * 100 / $d['egresos_n']) : 0 }} %</div>
            <div class="l">{{ $d['cuadrados'] }} de {{ $d['egresos_n'] }} egresos registrados</div>
        </div>
        <div class="card-e kpi">
            <div class="l">Por pagar</div>
            <div class="v">{{ $peso($saldoDeudas) }}</div>
            <div class="l">{{ $deudas->count() }} pendientes @if($deudas->where('vencida', true)->count())· <span class="rojo">{{ $deudas->where('vencida', true)->count() }} vencidas</span>@endif</div>
        </div>
        <div class="card-e kpi">
            <div class="l">Fuera de egresos</div>
            <div class="v">{{ $peso($d['fuera']['retiros']) }}</div>
            <div class="l">retiros de socios · traspasos {{ $peso($d['fuera']['traspasos']) }}</div>
        </div>
    </section>

    <div class="cols">
        <div class="col-a">
            <section class="card-e" aria-label="Por categoría">
                <h2>Por categoría</h2>
                @forelse($d['arbol'] as $cat => $c)
                    <details class="cat" {{ $loop->first ? 'open' : '' }} @if($cat === 'Sin categoría') id="sin-categoria" @endif>
                        <summary>
                            <span style="font-weight:500">{{ $cat }}</span>
                            <span class="barra"><span style="width: {{ round($c['total'] * 100 / $max) }}%"></span></span>
                            <span class="num">{{ $peso($c['total']) }}</span>
                            <span class="mas" aria-hidden="true">+</span>
                        </summary>
                        <div class="subs">
                            @foreach($c['subs'] as $sub => $s)
                                <div class="s">
                                    <span>{{ $sub }}</span>
                                    <span class="gris">{{ implode(', ', array_slice(array_keys($s['quien']), 0, 3)) }}@if(count($s['quien']) > 3) y {{ count($s['quien']) - 3 }} más @endif</span>
                                    <span class="num">{{ $peso($s['total']) }}</span>
                                </div>
                            @endforeach
                            @if($cat === 'Sin categoría' && count($d['sin_categoria']['proveedores']))
                                <div class="eyebrow" style="margin-top:8px">Asigna una categoría a cada proveedor (queda para siempre)</div>
                                @foreach(collect($d['sin_categoria']['proveedores'])->sortByDesc('total')->take(15) as $p)
                                    @if($p['proveedor_id'])
                                    <form method="POST" action="{{ route('backoffice.finanzas.egresos.categorizar') }}" class="form-l" style="margin-top:2px">@csrf
                                        <input type="hidden" name="proveedor_id" value="{{ $p['proveedor_id'] }}">
                                        <span style="flex:1 1 220px;font-size:.9rem">{{ $p['nombre'] }} <span class="gris">· {{ $p['n'] }} · {{ $peso($p['total']) }}</span></span>
                                        <select name="subcategoria_id" class="browser-default" required aria-label="Categoría para {{ $p['nombre'] }}">
                                            <option value="">Categoría…</option>
                                            @foreach($grupos as $nc => $ss)
                                                <optgroup label="{{ $nc }}">@foreach($ss as $sid => $nombreSub)<option value="{{ $sid }}">{{ $nombreSub }}</option>@endforeach</optgroup>
                                            @endforeach
                                        </select>
                                        <button class="btn-c ghost">Asignar</button>
                                    </form>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </details>
                @empty
                    <p class="gris">Sin egresos este mes.</p>
                @endforelse
                <p class="gris" style="font-size:.82rem;margin:10px 0 0">Retiros de socios, traspasos y gastos personales no son egresos: se muestran aparte.</p>
            </section>

            <section class="card-e" id="cuadre" aria-label="Cuadre">
                <h2>Cuadre</h2>
                <div class="eyebrow">Facturas sin pago encontrado · {{ $d['facturas_sin_pago']->count() }}</div>
                @forelse($d['facturas_sin_pago']->take(8) as $e)
                    <div class="fila">
                        <a href="{{ route('backoffice.egreso.show', $e->id) }}">{{ $e->proveedor_nombre ?: $e->descripcion }} <span class="gris">· {{ \Carbon\Carbon::parse($e->fecha_egreso)->format('d-m') }}</span></a>
                        <span class="num">{{ $peso($e->total) }}</span>
                    </div>
                @empty
                    <p class="verde" style="margin:4px 0 8px">Todas las facturas del mes tienen pago.</p>
                @endforelse
                @if($d['facturas_sin_pago']->count() > 8)<p class="gris" style="font-size:.82rem">y {{ $d['facturas_sin_pago']->count() - 8 }} más ({{ $peso($d['facturas_sin_pago']->slice(8)->sum('total')) }})</p>@endif

                <div class="eyebrow" style="margin-top:12px">Pagos sin factura · {{ $d['sin_factura']->count() }}</div>
                @forelse($d['sin_factura']->take(8) as $m)
                    <div class="fila">
                        <a href="{{ route('backoffice.finanzas.correos.show', $m->id) }}">{{ $m->nombre ?: $m->proveedor }} <span class="gris">· {{ \Carbon\Carbon::parse($m->fecha)->format('d-m') }} · {{ $m->banco }}</span></a>
                        <span class="num">{{ $peso($m->monto) }}</span>
                    </div>
                @empty
                    <p class="gris" style="margin:4px 0">Ninguno.</p>
                @endforelse
                @if($d['por_clasificar']['n'])
                    <div class="fila" style="margin-top:8px"><a href="{{ route('backoffice.finanzas.clasificar.index', ['tab' => 'salidas']) }}"><strong>{{ $d['por_clasificar']['n'] }} movimientos del banco por clasificar</strong></a><span class="num">{{ $peso($d['por_clasificar']['total']) }}</span></div>
                @endif
            </section>
        </div>

        <div class="col-b">
            <section class="card-e" aria-label="Por pagar">
                <div style="display:flex;justify-content:space-between;align-items:baseline"><h2>Por pagar</h2><a href="{{ route('backoffice.finanzas.deudas.index') }}" style="font-size:.88rem">Ver todo</a></div>
                @forelse($deudas->take(6) as $x)
                    @include('themes.backoffice.pages.egresos_panel._deuda', ['x' => $x, 'compacta' => true])
                @empty
                    <p class="verde">No hay nada pendiente.</p>
                @endforelse
            </section>

            <section class="card-e" aria-label="Fuentes">
                <h2>De dónde llega cada gasto</h2>
                <div class="fila"><span>Facturas y boletas del SII</span><span class="gris">lunes, automático</span></div>
                <div class="fila"><span>Avisos del banco</span><span class="gris">cada 5 minutos</span></div>
                <div class="fila"><span>Remuneraciones</span><span class="gris">{{ $peso($d['sueldos']) }} este mes</span></div>
                <div class="fila"><span>Cuentas que llegan al correo</span><span class="gris">pasan a Por pagar</span></div>
                <div class="fila"><span>Efectivo y gastos hormiga</span><span class="gris"><a href="{{ route('backoffice.egreso.create') }}">registrar a mano</a></span></div>
                <p class="gris" style="font-size:.82rem;margin:10px 0 0"><a href="{{ route('backoffice.egreso.mes.anterior', [$anio, $mes]) }}">Vista anterior de egresos</a></p>
            </section>
        </div>
    </div>
</div>
@endsection
