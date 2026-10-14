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


    @if(session('success'))
        <div class="card-panel green lighten-5 green-text text-darken-3" style="padding:10px 16px">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="card-panel red lighten-5 red-text text-darken-3" style="padding:10px 16px">{{ session('error') }}</div>
    @endif

    @if($porRevisar->isNotEmpty())
    <div class="card" style="border-radius:8px;border-left:4px solid #FF8F00">
        <div class="card-content" style="padding:16px 20px;overflow-x:auto">
            <p style="font-weight:700;margin:0 0 4px;color:#E65100">
                <i class="material-icons tiny">warning</i> Comprobantes por revisar ({{ $porRevisar->count() }})
            </p>
            <p class="grey-text" style="margin:0 0 12px;font-size:.8rem">
                El bot no creó estas reservas porque algo del comprobante quedó en duda. Revisa la imagen y da el pase:
                <strong>Aprobar</strong> crea la reserva, la confirma y le avisa al cliente con el menú; <strong>Rechazar</strong> le pide el comprobante correcto (el cupo queda apartado 1 hora más).
            </p>
            <table class="striped" style="font-size:.85rem;min-width:900px">
                <thead>
                    <tr><th>Recibido</th><th>Cliente</th><th>Reserva pedida</th><th class="right-align">Abono esperado</th><th>Lo que se leyó</th><th>Por qué quedó en duda</th><th style="width:230px"></th></tr>
                </thead>
                <tbody>
                @foreach($porRevisar as $c)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($c->created_at)->format('d/m H:i') }}</td>
                        <td>{{ $c->nombre ?? '—' }}<span class="grey-text" style="display:block;font-size:.75rem">+{{ $c->telefono }}</span></td>
                        <td>
                            {{ $c->nombre_programa ?? '—' }} · {{ $c->personas }} pers.
                            <span class="grey-text" style="display:block;font-size:.75rem">Visita {{ $c->fecha_visita ? \Carbon\Carbon::parse($c->fecha_visita)->format('d/m/Y') : '—' }}
                            @if($c->estado_hold !== 'activo') · <span class="red-text">cupo {{ $c->estado_hold }}</span>@endif</span>
                        </td>
                        <td class="right-align">{{ $f($c->abono_50) }}<span class="grey-text" style="display:block;font-size:.75rem">total {{ $f($c->valor_total) }}</span></td>
                        <td style="font-size:.8rem">
                            Monto: <strong>{{ isset($c->datos['monto']) && is_numeric($c->datos['monto']) ? $f($c->datos['monto']) : 'no legible' }}</strong><br>
                            Fecha: {{ $c->datos['fecha'] ?? '—' }} {{ $c->datos['hora'] ?? '' }}<br>
                            A: {{ $c->datos['destinatario_nombre'] ?? '—' }} {{ $c->datos['destinatario_cuenta'] ?? '' }}<br>
                            Desde: {{ $c->datos['nombre_origen'] ?? '—' }} · Op. {{ $c->datos['numero_operacion'] ?? '—' }}
                        </td>
                        <td class="orange-text text-darken-3" style="font-size:.8rem">{{ $c->motivos }}</td>
                        <td>
                            @if($c->archivo)
                                <a class="btn-flat waves-effect" style="padding:0 8px" target="_blank" href="{{ route('backoffice.finanzas.reservas_dia.comprobante', $c->id) }}"><i class="material-icons left" style="margin-right:4px">image</i>Ver</a>
                            @endif
                            <form method="POST" action="{{ route('backoffice.finanzas.reservas_dia.aprobar', $c->id) }}" style="display:inline" onsubmit="return confirm('¿Aprobar el comprobante y crear la reserva? Se le avisará al cliente.')">
                                @csrf
                                <button class="btn-small green waves-effect" type="submit">Aprobar</button>
                            </form>
                            <form method="POST" action="{{ route('backoffice.finanzas.reservas_dia.rechazar', $c->id) }}" style="margin-top:6px;display:flex;gap:4px;align-items:center" onsubmit="return confirm('¿Rechazar el comprobante? Se le pedirá al cliente el correcto.')">
                                @csrf
                                <input type="text" name="motivo" placeholder="Motivo para el cliente (opcional)" class="browser-default" style="border:1px solid #ddd;border-radius:4px;padding:3px 6px;font-size:.75rem;width:140px">
                                <button class="btn-small red lighten-1 waves-effect" type="submit">Rechazar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

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
                                <td>
                                    @if(Route::has('backoffice.reserva.show'))
                                        <a href="{{ route('backoffice.reserva.show', $r->id) }}" title="Ver la reserva" style="font-weight:600">{{ $r->id }}</a>
                                    @else
                                        {{ $r->id }}
                                    @endif
                                </td>
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
                                        <a class="orange-text" style="font-size:.75rem;display:block" href="{{ route('backoffice.verificacion-pago.index') }}">por verificar</a>
                                    @endif
                                    @if(!empty($r->comprobante_alerta))
                                        <i class="material-icons orange-text tooltipped" style="font-size:1rem;vertical-align:middle;cursor:help"
                                           title="{{ $r->comprobante_alerta }}" data-position="left" data-tooltip="{{ $r->comprobante_alerta }}">warning</i>
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
                                <td>
                                    @if($a->reserva_id && Route::has('backoffice.reserva.show'))
                                        <a href="{{ route('backoffice.reserva.show', $a->reserva_id) }}" style="font-weight:600">{{ $a->reserva_id }}</a>
                                    @else
                                        {{ $a->reserva_id ?? '—' }}
                                    @endif
                                </td>
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

@section('foot')
<script>
    $(function () { if ($.fn.tooltip) { $('.tooltipped').tooltip({delay: 50}); } });
</script>
@endsection
