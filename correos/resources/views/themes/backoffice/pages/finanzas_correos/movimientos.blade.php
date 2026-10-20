@extends('themes.backoffice.layouts.admin')

@section('title', 'Movimientos')

@section('breadcrumbs')
<li>Finanzas</li><li>Movimientos</li>
@endsection

@php
    $peso = function ($n) { return '$' . number_format((int) $n, 0, ',', '.'); };
    $color = ['calzado' => 'green', 'revisar' => 'orange', 'nuevo' => 'blue', 'pagado' => 'teal'];
    $estados = ['calzado' => 'Cruzado', 'revisar' => 'Por revisar', 'nuevo' => 'Nuevo', 'pagado' => 'Pagado'];
    $vinculos = ['sueldo' => 'Sueldo', 'egreso' => 'Egreso', 'reembolso' => 'Botacura te debe', 'cuenta' => 'Pago de cuenta', 'retiro' => 'Retiro', 'traspaso' => 'Traspaso'];
    $tipoTxt = ['abono' => 'Abono', 'pago' => 'Pago', 'retiro' => 'Retiro', 'traspaso' => 'Traspaso', 'compra' => 'Compra', 'personal' => 'Gasto'];
    $verPersonal = \App\Http\Controllers\FinanzasCorreoController::puedeVerPersonal();
    $diasSemana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
@endphp

@section('content')
<div class="section">
    <div class="row valign-wrapper" style="margin-bottom:0">
        <div class="col s12 m7">
            <h5 class="grey-text text-darken-2" style="margin:0 0 4px"><i class="material-icons left">swap_vert</i>Movimientos</h5>
            <p class="grey-text" style="margin:0;font-size:.85rem">Lo que entró y salió según los avisos de los bancos, cruzado con reservas, sueldos y egresos.</p>
        </div>
        <div class="col s12 m5 right-align">
            <a href="{{ route('backoffice.finanzas.correos.index') }}" class="btn-flat"><i class="material-icons left">mark_email_read</i>Correos</a>
            <form method="POST" action="{{ route('backoffice.finanzas.correos.leer') }}" style="display:inline">@csrf
                <button class="btn waves-effect blue darken-1"><i class="material-icons left">refresh</i>Leer y cruzar</button>
            </form>
        </div>
    </div>

    <div class="card-panel" style="padding:12px 16px">
        <form method="GET" class="row" style="margin:0">
            @if($verPersonal)
            <div class="col s12 m4" style="padding-top:8px">
                <a href="{{ route('backoffice.finanzas.correos.movimientos', ['ambito' => 'botacura', 'desde' => $desde, 'hasta' => $hasta]) }}" class="btn-small {{ $ambito === 'botacura' ? 'blue darken-1' : 'btn-flat' }}">Botacura</a>
                <a href="{{ route('backoffice.finanzas.correos.movimientos', ['ambito' => 'personal', 'desde' => $desde, 'hasta' => $hasta]) }}" class="btn-small {{ $ambito === 'personal' ? 'blue darken-1' : 'btn-flat' }}">Mi cuenta personal</a>
            </div>
            @endif
            <input type="hidden" name="ambito" value="{{ $ambito }}">
            <div class="input-field col s6 m4" style="margin:0"><input type="date" name="desde" id="desde" value="{{ $desde }}" onchange="this.form.submit()"><label for="desde" class="active">Desde</label></div>
            <div class="input-field col s6 m4" style="margin:0"><input type="date" name="hasta" id="hasta" value="{{ $hasta }}" onchange="this.form.submit()"><label for="hasta" class="active">Hasta</label></div>
        </form>
    </div>

    <div class="row">
        <div class="col s12 m3"><div class="card-panel" style="padding:14px">
            <div class="grey-text" style="font-size:.8rem">Entró</div>
            <div style="font-size:1.5rem" class="green-text text-darken-2">{{ $peso($res['entradas']) }}</div>
            <div class="grey-text" style="font-size:.8rem">{{ $res['abonos_ok'] }} abonos cruzados · {{ $res['abonos_rev'] }} por revisar</div>
        </div></div>
        <div class="col s12 m3"><div class="card-panel" style="padding:14px">
            <div class="grey-text" style="font-size:.8rem">Salió (sin traspasos)</div>
            <div style="font-size:1.5rem" class="red-text text-darken-2">{{ $peso($res['salidas']) }}</div>
            <div class="grey-text" style="font-size:.8rem">Neto {{ $peso($res['entradas'] - $res['salidas']) }}</div>
        </div></div>
        @if($ambito === 'botacura')
        <div class="col s12 m3"><div class="card-panel" style="padding:14px">
            <div class="grey-text" style="font-size:.8rem">Pagos sin registrar</div>
            <div style="font-size:1.5rem" class="{{ $res['sin_reg']->count() ? 'orange-text text-darken-2' : '' }}">{{ $res['sin_reg']->count() }} · {{ $peso($res['sin_reg']->sum('monto')) }}</div>
            <div class="grey-text" style="font-size:.8rem">Sin sueldo ni egreso en BotacurApp</div>
        </div></div>
        <div class="col s12 m3"><div class="card-panel" style="padding:14px">
            <div class="grey-text" style="font-size:.8rem">Retiros de socios</div>
            @forelse($res['retiros'] as $quien => $total)
                <div style="font-size:1.05rem">{{ $quien }}: <strong>{{ $peso($total) }}</strong></div>
            @empty
                <div style="font-size:1.05rem" class="grey-text">Sin retiros</div>
            @endforelse
        </div></div>
        @else
        <div class="col s12 m6"><div class="card-panel" style="padding:14px">
            <div class="grey-text" style="font-size:.8rem">Gastos de Botacura pagados desde tu cuenta (Botacura te debe)</div>
            <div style="font-size:1.5rem" class="blue-text text-darken-2">{{ $peso($res['reembolsos']->sum('monto')) }}</div>
            <div class="grey-text" style="font-size:.8rem">{{ $res['reembolsos']->count() }} pagos en el período</div>
        </div></div>
        @endif
    </div>

    @if($ambito === 'botacura' && $cuentas->count())
    <div class="card"><div class="card-content" style="padding:12px 16px">
        <span class="card-title" style="font-size:1.05rem">Cuentas por pagar que llegaron al correo</span>
        <table style="font-size:.9rem"><tbody>
        @foreach($cuentas as $k)
            @php $vencida = $k->vencimiento && $k->vencimiento < date('Y-m-d'); @endphp
            <tr>
                <td>{{ $k->proveedor }}</td>
                <td class="right-align">{{ $k->monto ? $peso($k->monto) : '–' }}</td>
                <td class="{{ $vencida ? 'red-text' : '' }}">{{ $k->vencimiento ? ($vencida ? 'Venció ' : 'Vence ') . \Carbon\Carbon::parse($k->vencimiento)->format('d-m') : '' }}</td>
                <td class="grey-text">{{ $k->nota }}</td>
                <td><a href="{{ route('backoffice.finanzas.correos.show', $k->id) }}" class="btn-flat btn-small"><i class="material-icons">visibility</i></a></td>
            </tr>
        @endforeach
        </tbody></table>
    </div></div>
    @endif

    @forelse($dias as $fecha => $movs)
        @php
            $d = \Carbon\Carbon::parse($fecha);
            $inD = $movs->where('tipo', 'abono')->sum('monto');
            $outD = $movs->where('tipo', '!=', 'abono')->where('tipo', '!=', 'traspaso')->sum('monto');
        @endphp
        <div class="card"><div class="card-content" style="padding:8px 12px">
            <div class="row" style="margin:4px 0 6px">
                <div class="col s12 m6"><strong style="font-size:1.05rem">{{ ucfirst($diasSemana[$d->dayOfWeek]) }} {{ $d->format('d-m') }}</strong></div>
                <div class="col s12 m6 right-align" style="font-size:.9rem">
                    <span class="green-text text-darken-2">+{{ $peso($inD) }}</span> ·
                    <span class="red-text text-darken-2">−{{ $peso($outD) }}</span>
                    @if($ambito === 'botacura')
                    · <a href="{{ route('backoffice.finanzas.reservas_dia', ['fecha' => $fecha]) }}">Reservas del día</a>
                    @endif
                </div>
            </div>
            <table class="striped highlight responsive-table" style="font-size:.88rem">
                <tbody>
                @foreach($movs as $m)
                    @php $entra = $m->tipo === 'abono'; @endphp
                    <tr>
                        <td style="width:52px" class="grey-text">{{ $m->hora }}</td>
                        <td style="width:90px"><i class="material-icons tiny {{ $entra ? 'green-text' : 'red-text' }}">{{ $entra ? 'south_west' : 'north_east' }}</i> {{ $tipoTxt[$m->tipo] ?? $m->tipo }}</td>
                        <td>{{ $m->nombre ?: $m->proveedor }} <span class="grey-text">· {{ $m->banco }}</span></td>
                        <td class="right-align {{ $entra ? 'green-text text-darken-2' : '' }}" style="white-space:nowrap">{{ $entra ? '+' : '−' }}{{ $peso($m->monto) }}</td>
                        <td style="max-width:360px">
                            @if($entra && $m->reserva_id)
                                <a href="{{ route('backoffice.reserva.show', $m->reserva_id) }}">Reserva #{{ $m->reserva_id }}</a>
                                <span class="grey-text">{{ $m->nombre_cliente }} · {{ $m->campo }}{{ $m->fecha_visita ? ' · visita ' . \Carbon\Carbon::parse($m->fecha_visita)->format('d-m') : '' }}</span>
                            @elseif($entra && $m->comprobante_id)
                                <span class="grey-text">Comprobante del bot #{{ $m->comprobante_id }}</span>
                            @else
                                @if(!empty($m->vinculo))<span class="chip green lighten-4" style="height:24px;line-height:24px;font-size:.8rem">{{ $vinculos[$m->vinculo] ?? $m->vinculo }}</span>@endif
                                <span class="grey-text">{{ $m->nota }}</span>
                            @endif
                        </td>
                        <td>@if(isset($estados[$m->estado]))<span class="chip {{ $color[$m->estado] }} lighten-4" style="height:24px;line-height:24px;font-size:.8rem">{{ $estados[$m->estado] }}</span>@endif</td>
                        <td><a href="{{ route('backoffice.finanzas.correos.show', $m->id) }}" class="btn-flat btn-small" title="Ver y asociar"><i class="material-icons">visibility</i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div></div>
    @empty
        <div class="card-panel grey-text center">Sin movimientos en estas fechas.</div>
    @endforelse
</div>
@endsection
