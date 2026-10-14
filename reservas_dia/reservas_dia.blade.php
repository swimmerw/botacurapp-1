@extends('themes.backoffice.layouts.admin')

@section('title', 'Reservas del día')

@section('content')
@php
    $f = function ($n) { return '$' . number_format((int) $n, 0, ',', '.'); };
    $anterior = $fecha->copy()->subDay()->toDateString();
    $siguiente = $fecha->copy()->addDay()->toDateString();
@endphp
<div class="section">
    <div class="row valign-wrapper" style="margin-bottom:8px">
        <div class="col s12 m7">
            <h5 class="grey-text text-darken-2" style="margin:0 0 4px">
                <i class="material-icons left" style="font-size:1.5rem">event_available</i>
                Reservas del día — {{ ucfirst($fecha->locale('es')->isoFormat('dddd D [de] MMMM YYYY')) }}
            </h5>
            <p class="grey-text" style="margin:0;font-size:.85rem">
                Reservas creadas este día y abonos recibidos. Las pagadas con Gift Card se muestran, pero no suman en lo recibido (esa plata entró cuando se vendió la Gift Card).
            </p>
        </div>
        <div class="col s12 m5" style="text-align:right">
            <form method="GET" action="{{ route('backoffice.finanzas.reservas_dia') }}" style="display:inline-flex;gap:6px;align-items:center">
                <a class="btn-flat waves-effect" href="{{ route('backoffice.finanzas.reservas_dia', ['fecha' => $anterior]) }}" title="Día anterior"><i class="material-icons">chevron_left</i></a>
                <input type="date" name="fecha" value="{{ $fecha->toDateString() }}" class="browser-default" style="border:1px solid #ccc;border-radius:4px;padding:4px 8px" onchange="this.form.submit()">
                <a class="btn-flat waves-effect" href="{{ route('backoffice.finanzas.reservas_dia', ['fecha' => $siguiente]) }}" title="Día siguiente"><i class="material-icons">chevron_right</i></a>
                <a class="btn-flat waves-effect" href="{{ route('backoffice.finanzas.reservas_dia') }}">Hoy</a>
            </form>
        </div>
    </div>

    <div class="row" style="margin-bottom:0">
        <div class="col s6 m3">
            <div class="card-panel" style="padding:14px 12px;margin:4px 0;border-left:4px solid #039B7B;background:#e0f2f1">
                <span class="grey-text" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;display:block">Reservas</span>
                <span style="font-size:1.4rem;font-weight:700;color:#00695C">{{ $resumen['reservas'] }}</span>
                <span class="grey-text" style="font-size:.75rem;display:block">{{ $resumen['personas'] }} personas</span>
            </div>
        </div>
        <div class="col s6 m3">
            <div class="card-panel" style="padding:14px 12px;margin:4px 0;border-left:4px solid #2E7D32;background:#f1f8e9">
                <span class="grey-text" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;display:block">Abonos recibidos</span>
                <span style="font-size:1.4rem;font-weight:700;color:#2E7D32">{{ $f($resumen['recibido']) }}</span>
                <span class="grey-text" style="font-size:.75rem;display:block">Reservas {{ $f($resumen['abonos']) }} + abonos extra {{ $f($resumen['extra']) }}</span>
            </div>
        </div>
        <div class="col s6 m3">
            <div class="card-panel" style="padding:14px 12px;margin:4px 0;border-left:4px solid #1565C0;background:#e8f0fe">
                <span class="grey-text" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;display:block">Valor de lo reservado</span>
                <span style="font-size:1.4rem;font-weight:700;color:#1565C0">{{ $f($resumen['valor']) }}</span>
                <span class="grey-text" style="font-size:.75rem;display:block">Por cobrar en la visita {{ $f($resumen['por_cobrar']) }}</span>
            </div>
        </div>
        <div class="col s6 m3">
            <div class="card-panel" style="padding:14px 12px;margin:4px 0;border-left:4px solid #6A1B9A;background:#f5f0ff">
                <span class="grey-text" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;display:block">Pagadas con Gift Card</span>
                <span style="font-size:1.4rem;font-weight:700;color:#6A1B9A">{{ $f($resumen['giftcard']) }}</span>
                <span class="grey-text" style="font-size:.75rem;display:block">No suman en lo recibido</span>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top:8px">
        <div class="col s12 l9">
            <div class="card" style="border-radius:8px">
                <div class="card-content" style="padding:16px 20px;overflow-x:auto">
                    <p style="font-weight:700;margin:0 0 12px;color:#00695C">Reservas creadas</p>
                    @if($reservas->isEmpty())
                        <p class="grey-text">No se crearon reservas este día.</p>
                    @else
                    <table class="striped" style="font-size:.85rem;min-width:760px">
                        <thead>
                            <tr>
                                <th>Hora</th><th>N°</th><th>Cliente</th><th>Programa</th><th class="center-align">Pers.</th>
                                <th>Visita</th><th class="right-align">Abono</th><th>Medio</th><th class="right-align">Por cobrar</th><th>Origen</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($reservas as $r)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($r->created_at)->format('H:i') }}</td>
                                <td>{{ $r->id }}</td>
                                <td>{{ $r->nombre_cliente }}</td>
                                <td>{{ $r->nombre_programa ?? '—' }}</td>
                                <td class="center-align">{{ $r->cantidad_personas }}</td>
                                <td>{{ $r->fecha_visita ? \Carbon\Carbon::parse($r->fecha_visita)->format('d/m/Y') : '—' }}</td>
                                <td class="right-align">{{ $f($r->abono_programa) }}</td>
                                <td>
                                    @if($r->con_giftcard)
                                        <span class="purple-text">Gift Card</span>
                                    @else
                                        {{ $r->medio_abono ?? '—' }}
                                    @endif
                                    @if($r->estado_pago === 'pendiente_verificacion')
                                        <span class="orange-text" style="font-size:.75rem;display:block">por verificar</span>
                                    @endif
                                </td>
                                <td class="right-align">{{ $f($r->total_pagar ?? $r->diferencia_programa) }}</td>
                                <td class="grey-text">{{ strpos((string) $r->fuente, 'bot') === 0 ? 'Bot WhatsApp' : ($r->usuario ?? ($r->fuente ?: '—')) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="font-weight:700;background:#f0faf7">
                                <td colspan="4">Total ({{ $resumen['reservas'] }} reservas)</td>
                                <td class="center-align">{{ $resumen['personas'] }}</td>
                                <td></td>
                                <td class="right-align">{{ $f($resumen['abonos'] + $resumen['giftcard']) }}</td>
                                <td></td>
                                <td class="right-align">{{ $f($resumen['por_cobrar']) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    @endif

                    @if($abonosExtra->isNotEmpty())
                    <p style="font-weight:700;margin:20px 0 8px;color:#00695C">Abonos extra registrados este día</p>
                    <table class="striped" style="font-size:.85rem">
                        <thead><tr><th>Reserva</th><th>Cliente</th><th>Visita</th><th class="right-align">Monto</th><th>Medio</th></tr></thead>
                        <tbody>
                        @foreach($abonosExtra as $a)
                            <tr>
                                <td>{{ $a->reserva_id ?? '—' }}</td>
                                <td>{{ $a->nombre_cliente ?? '—' }}</td>
                                <td>{{ $a->fecha_visita ? \Carbon\Carbon::parse($a->fecha_visita)->format('d/m/Y') : '—' }}</td>
                                <td class="right-align">{{ $f($a->monto) }}</td>
                                <td>{{ $a->medio ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @endif
                </div>
            </div>
        </div>

        <div class="col s12 l3">
            <div class="card" style="border-radius:8px">
                <div class="card-content" style="padding:16px 20px">
                    <p style="font-weight:700;margin:0 0 12px;color:#2E7D32">Abonos por medio de pago</p>
                    <table style="font-size:.88rem;width:100%">
                        <tbody>
                        @forelse($porMedio as $medio => $monto)
                            <tr><td>{{ $medio }}</td><td class="right-align">{{ $f($monto) }}</td></tr>
                        @empty
                            <tr><td class="grey-text" colspan="2">Sin abonos</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                            <tr style="font-weight:700;border-top:2px solid #c8e6c9"><td>Total</td><td class="right-align">{{ $f($resumen['recibido']) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
