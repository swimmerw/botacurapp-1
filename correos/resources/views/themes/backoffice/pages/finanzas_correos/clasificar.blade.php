@extends('themes.backoffice.layouts.admin')

@section('title', 'Clasificar movimientos')

@section('breadcrumbs')
<li>Egresos</li><li>Clasificar</li>
@endsection

@php
    $peso = function ($n) { return '$' . number_format((int) $n, 0, ',', '.'); };
    $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $clasif = \App\Http\Controllers\FinanzasClasificarController::ENTRADAS + \App\Http\Controllers\FinanzasClasificarController::SALIDAS
        + ['reserva' => 'Reserva', 'egreso' => 'Egreso'];
    $tipoTxt = ['abono' => 'Entrada', 'pago' => 'Pago', 'compra' => 'Compra', 'personal' => 'Desde tu cuenta', 'cuenta' => 'Cuenta'];
@endphp

@section('content')
<style>
    .clf { max-width: 1080px; margin: 0 auto; color: #1f2a26; }
    .clf h1 { font-size: 1.6rem; font-weight: 500; margin: 8px 0 2px; }
    .clf .sub { color: #6b756f; font-size: .9rem; margin: 0 0 18px; }
    .clf .tabs-c { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
    .clf a, .clf a:hover { text-decoration: none; }
    .clf .tab-c { display: inline-flex; align-items: baseline; gap: 8px; padding: 8px 14px; border: 1px solid #dfe3e0; border-radius: 999px; color: #3b4541; background: #fff; font-size: .9rem; }
    .clf .tab-c.on { background: #1f2a26; border-color: #1f2a26; color: #fff; }
    .clf .tab-c .n { font-weight: 600; }
    .clf .tab-c .m { font-size: .78rem; opacity: .7; }
    .clf .item { background: #fff; border: 1px solid #e6e9e7; border-radius: 12px; margin-bottom: 10px; }
    .clf .item > summary { list-style: none; cursor: pointer; display: grid; grid-template-columns: 74px 1fr auto; gap: 14px; align-items: center; padding: 14px 18px; }
    .clf .item > summary::-webkit-details-marker { display: none; }
    .clf .item[open] > summary { border-bottom: 1px solid #eef0ee; }
    .clf .fecha { color: #6b756f; font-size: .8rem; line-height: 1.25; }
    .clf .fecha b { display: block; color: #1f2a26; font-size: 1rem; font-weight: 500; }
    .clf .quien { font-weight: 500; font-size: .98rem; }
    .clf .meta { color: #6b756f; font-size: .82rem; margin-top: 2px; }
    .clf .monto { font-size: 1.05rem; font-weight: 600; white-space: nowrap; text-align: right; }
    .clf .monto.in { color: #1b7f5b; }
    .clf .cuerpo { padding: 14px 18px 18px; display: grid; gap: 16px; }
    .clf .bloque-t { font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: #6b756f; margin-bottom: 8px; }
    .clf .opc { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid #e6e9e7; border-radius: 8px; margin-bottom: 6px; font-size: .88rem; }
    .clf .opc.fuerte { border-color: #9fd3bd; background: #f3faf6; }
    .clf .opc form { margin: 0; }
    .clf .btn-c { border: 1px solid #1f2a26; background: #1f2a26; color: #fff; border-radius: 8px; padding: 6px 12px; font-size: .85rem; cursor: pointer; line-height: 1.2; }
    .clf .btn-c.ghost { background: #fff; color: #1f2a26; border-color: #cfd5d1; }
    .clf .btn-c:hover { opacity: .88; }
    .clf .fila { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .clf select.browser-default, .clf input.campo { height: 36px; border: 1px solid #cfd5d1; border-radius: 8px; padding: 0 10px; background: #fff; font-size: .88rem; margin: 0; box-sizing: border-box; }
    .clf input.campo { width: 220px; }
    .clf .pista { color: #1b7f5b; font-size: .8rem; }
    .clf .vacio { text-align: center; color: #6b756f; padding: 48px 0; border: 1px dashed #dfe3e0; border-radius: 12px; }
    .clf .tag { display: inline-block; font-size: .75rem; padding: 2px 8px; border-radius: 999px; background: #eef3f0; color: #2f5d4b; margin-right: 6px; }
    @media (max-width: 600px) {
        .clf .item > summary { grid-template-columns: 1fr auto; }
        .clf .item > summary .fecha { grid-column: 1 / -1; }
        .clf input.campo { width: 100%; }
    }
</style>

<div class="section clf">
    @if(session('success'))<div class="card-panel green lighten-5" style="border-radius:10px">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="card-panel red lighten-5" style="border-radius:10px">{{ session('error') }}</div>@endif

    <h1>Clasificar</h1>
    <p class="sub">Movimientos del banco que no se cruzaron solos. Asócialos, regístralos como egreso o clasifícalos; todo se puede deshacer.
        <a href="{{ route('backoffice.finanzas.correos.movimientos') }}">Ver movimientos</a></p>

    <nav class="tabs-c" aria-label="Bandejas">
        @foreach($tabs as $k => $v)
            <a class="tab-c {{ $tab === $k ? 'on' : '' }}" href="{{ route('backoffice.finanzas.clasificar.index', ['tab' => $k]) }}">
                {{ $v }}
                @if(isset($conteos[$k]))<span class="n">{{ $conteos[$k]['n'] }}</span>@if($conteos[$k]['monto'])<span class="m">{{ $peso($conteos[$k]['monto']) }}</span>@endif @endif
            </a>
        @endforeach
    </nav>

    @forelse($items as $c)
        @php
            $f = $c->fecha ? \Carbon\Carbon::parse($c->fecha) : null;
            $entra = $c->tipo === 'abono';
        @endphp
        <details class="item" {{ $loop->first && $tab !== 'listos' ? 'open' : '' }}>
            <summary>
                <div class="fecha">@if($f)<b>{{ $f->format('d') }} {{ $meses[$f->month] }}</b>@endif{{ $c->hora }}</div>
                <div>
                    <div class="quien">{{ $c->nombre ?: ($c->proveedor ?: $c->banco) }}</div>
                    <div class="meta">
                        {{ $tipoTxt[$c->tipo] ?? $c->tipo }}@if($c->banco) · {{ $c->banco }}@endif @if($c->comentario) · {{ $c->comentario }}@endif
                        @if($c->vencimiento) · vence {{ \Carbon\Carbon::parse($c->vencimiento)->format('d-m') }}@endif
                        @if($tab === 'listos')
                            <br><span class="tag">{{ $clasif[$c->clasificacion] ?? ($c->vinculo ?: $c->estado) }}</span>
                            @if($c->categoria_id){{ $nombresCat[$c->categoria_id] ?? '' }}{{ $c->subcategoria_id ? ' › ' . ($nombresSub[$c->subcategoria_id] ?? '') : '' }} · @endif{{ $c->nota }}
                        @elseif($c->nota)
                            <br>{{ $c->nota }}
                        @endif
                    </div>
                </div>
                <div class="monto {{ $entra ? 'in' : '' }}">{{ $entra ? '+' : '−' }}{{ $peso($c->monto) }}</div>
            </summary>

            <div class="cuerpo">
            @if($tab === 'listos')
                <div class="fila">
                    <form method="POST" action="{{ route('backoffice.finanzas.clasificar.deshacer', $c->id) }}">@csrf
                        <button class="btn-c ghost" title="Vuelve a la bandeja{{ $c->egreso_id ? ' y borra el egreso creado aquí' : '' }}">Deshacer</button>
                    </form>
                    <a href="{{ route('backoffice.finanzas.correos.show', $c->id) }}" class="btn-c ghost">Ver correo</a>
                </div>

            @elseif($entra)
                <div>
                    <div class="bloque-t">¿De qué reserva es?</div>
                    @forelse($c->opciones as $o)
                        @if(!empty($o['venta_id']))
                        <div class="opc {{ $o['puntos'] >= 2 ? 'fuerte' : '' }}">
                            <form method="POST" action="{{ route('backoffice.finanzas.clasificar.venta', $c->id) }}">@csrf
                                <input type="hidden" name="venta_id" value="{{ $o['venta_id'] }}"><input type="hidden" name="campo" value="{{ $o['campo'] }}">
                                <button class="btn-c">Es esta</button>
                            </form>
                            <span>{{ ucfirst($o['motivo']) }}</span>
                        </div>
                        @endif
                    @empty
                        <p class="meta" style="margin:0 0 6px">No encontré reservas por este monto ni de este nombre.</p>
                    @endforelse
                    <form method="POST" action="{{ route('backoffice.finanzas.correos.asociar', $c->id) }}" class="fila" style="margin-top:6px">@csrf
                        <select name="que" class="browser-default"><option value="reserva">Reserva N°</option><option value="venta">Venta N°</option></select>
                        <input type="number" name="numero" class="campo" style="width:120px" required aria-label="Número">
                        <button class="btn-c ghost">Asociar</button>
                    </form>
                </div>
                <div>
                    <div class="bloque-t">O no es de una reserva</div>
                    <div class="fila">
                        @foreach(\App\Http\Controllers\FinanzasClasificarController::ENTRADAS as $k => $v)
                            <form method="POST" action="{{ route('backoffice.finanzas.clasificar.marcar', $c->id) }}">@csrf
                                <input type="hidden" name="clasificacion" value="{{ $k }}"><button class="btn-c ghost">{{ $v }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>

            @else
                @if(count($c->semanas))
                <div>
                    <div class="bloque-t">Remuneración de {{ $c->persona->name }}</div>
                    @foreach($c->semanas as $w)
                        <div class="opc {{ (int) $w['total'] === (int) $c->monto ? 'fuerte' : '' }}">
                            <form method="POST" action="{{ route('backoffice.finanzas.correos.pagar_semana', $c->id) }}">@csrf
                                <input type="hidden" name="inicio" value="{{ $w['inicio'] }}"><input type="hidden" name="fin" value="{{ $w['fin'] }}">
                                <button class="btn-c">Marcar pagada</button>
                            </form>
                            <span>{{ $w['etiqueta'] }} · {{ $peso($w['total']) }}@if((int) $w['total'] === (int) $c->monto) <span class="pista">· mismo monto</span>@endif</span>
                        </div>
                    @endforeach
                </div>
                @endif

                @if(count($c->opciones))
                <div>
                    <div class="bloque-t">Ya registrado en BotacurApp</div>
                    @foreach($c->opciones as $o)
                        <div class="opc {{ $o['puntos'] >= 3 ? 'fuerte' : '' }}">
                            <form method="POST" action="{{ route('backoffice.finanzas.correos.vincular', $c->id) }}">@csrf
                                <input type="hidden" name="clase" value="{{ $o['clase'] }}"><input type="hidden" name="ref" value="{{ $o['id'] }}"><input type="hidden" name="label" value="{{ $o['label'] }}">
                                <button class="btn-c">Es este</button>
                            </form>
                            <span>{{ $o['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                @endif

                <div>
                    <div class="bloque-t">{{ $c->tipo === 'personal' ? 'Si es de Botacura, regístralo como egreso' : ($c->tipo === 'cuenta' ? 'Registrar como egreso por pagar' : 'Registrar como egreso') }}</div>
                    <form method="POST" action="{{ route('backoffice.finanzas.clasificar.egreso', $c->id) }}" class="fila js-egreso">@csrf
                        <select name="categoria_id" class="browser-default js-cat" required aria-label="Categoría">
                            <option value="">Categoría…</option>
                            @foreach($categorias as $cat)
                                <option value="{{ $cat->id }}" {{ ($c->sugerida['categoria_id'] ?? null) == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                            @endforeach
                        </select>
                        <select name="subcategoria_id" class="browser-default js-sub" aria-label="Subcategoría" data-sel="{{ $c->sugerida['subcategoria_id'] ?? '' }}">
                            <option value="">Subcategoría…</option>
                            @foreach($subcategorias as $s)
                                <option value="{{ $s->id }}" data-cat="{{ $s->categoria_id }}" {{ ($c->sugerida['subcategoria_id'] ?? null) == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="proveedor" class="campo" value="{{ $c->proveedor ?: $c->nombre }}" placeholder="Proveedor" aria-label="Proveedor">
                        <button class="btn-c">Registrar egreso</button>
                        @if($c->sugerida)<span class="pista">Categoría sugerida por {{ $c->sugerida['por'] }}</span>@endif
                    </form>
                </div>

                @if($c->tipo !== 'cuenta')
                <div>
                    <div class="bloque-t">O clasificar sin egreso</div>
                    <div class="fila">
                        @foreach(\App\Http\Controllers\FinanzasClasificarController::SALIDAS as $k => $v)
                            @if($c->tipo === 'personal' && in_array($k, ['retiro', 'prestamo'])) @continue @endif
                            <form method="POST" action="{{ route('backoffice.finanzas.clasificar.marcar', $c->id) }}">@csrf
                                <input type="hidden" name="clasificacion" value="{{ $k }}"><button class="btn-c ghost">{{ $v }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
                @else
                <div class="fila">
                    <form method="POST" action="{{ route('backoffice.finanzas.correos.estado', $c->id) }}">@csrf
                        <input type="hidden" name="estado" value="pagado"><button class="btn-c ghost">Ya está pagada</button>
                    </form>
                </div>
                @endif
            @endif
            </div>
        </details>
    @empty
        <div class="vacio">{{ $tab === 'listos' ? 'Todavía no has clasificado nada.' : 'Nada pendiente aquí.' }}</div>
    @endforelse
</div>
@endsection

@section('foot')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Subcategorías según la categoría elegida
    document.querySelectorAll('.js-egreso').forEach(function (f) {
        var cat = f.querySelector('.js-cat'), sub = f.querySelector('.js-sub');
        function filtrar() {
            sub.querySelectorAll('option[data-cat]').forEach(function (o) {
                var ok = o.getAttribute('data-cat') === cat.value;
                o.hidden = !ok;
                if (!ok && o.selected) { sub.value = ''; }
            });
        }
        cat.addEventListener('change', filtrar);
        filtrar();
    });
});
</script>
@endsection
