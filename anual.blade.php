@extends('themes.backoffice.layouts.admin')

@section('content')
<div class="section">
    <p class="caption">Resumen Anual.</p>
    <div class="divider"></div>
    <div id="basic-form" class="section">
        <div class="row">
            <div class="col s12 m10 offset-m1 ">
                <div class="card-panel">

                    <div class="container">
                        <h4>Resumen Anual de Finanzas {{$anio}}</h4>
                        <p class="grey-text" style="font-size:.85rem">
                            Mismo cálculo que <a href="{{ route('backoffice.finanzas.utilidad', ['anio' => $anio]) }}">Finanzas → Utilidad</a>:
                            resultado por mes de la visita (abonos, abonos extra, diferencias, consumos, servicios y ventas directas, sin Poro Poro). La plata recibida día a día está en Ingresos percibidos;
                            egresos = compras netas + gas y otros sin factura + IVA débito + retención de honorarios + sueldos (sin propinas) + PPM.
                            Las inversiones y remodelaciones van aparte.
                        </p>
                        <table class="highlight">
                            <thead>
                                <tr>
                                    <th>Mes</th>
                                    <th>Ingresos</th>
                                    <th>Egresos</th>
                                    <th>Utilidad</th>
                                    <th>Inversión (aparte)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $ingresoAnual = 0;
                                    $egresoAnual = 0;
                                    $totalAnual = 0;
                                    $inversionAnual = 0;
                                @endphp
                                @for ($i = 1; $i <= 12; $i++)
                                    @php $d = $filas[$i] ?? null; @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ route('backoffice.finanzas.utilidad', ['anio' => $anio, 'mes' => $i]) }}">{{ ucfirst(\Carbon\Carbon::create()->month($i)->locale('es')->isoFormat('MMMM')) }}</a>
                                            @if($d !== null && $d['ivaEstimado'])
                                                <span class="orange-text" title="IVA y PPM estimados: mes sin sincronizar con el SII">*</span>
                                            @endif
                                        </td>
                                        @if($d === null)
                                            <td class="grey-text" colspan="4">—</td>
                                        @else
                                            @php
                                                $ingresoAnual += $d['totalIngresos'];
                                                $egresoAnual += $d['totalEgresos'];
                                                $totalAnual += $d['utilidad'];
                                                $inversionAnual += $d['inversiones'];
                                            @endphp
                                            <td class="green-text">${{ number_format($d['totalIngresos'], 0, ',', '.') }}</td>
                                            <td class="red-text">${{ number_format($d['totalEgresos'], 0, ',', '.') }}</td>
                                            <td><strong>{{ $d['utilidad'] < 0 ? '-' : '' }}${{ number_format(abs($d['utilidad']), 0, ',', '.') }}</strong></td>
                                            <td class="grey-text">{{ $d['inversiones'] ? '$' . number_format($d['inversiones'], 0, ',', '.') : '—' }}</td>
                                        @endif
                                    </tr>
                                @endfor
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td></td>
                                    <td><strong>Ingreso Anual: ${{ number_format($ingresoAnual, 0, ',', '.') }}</strong></td>
                                    <td><strong>Egreso Anual: ${{ number_format($egresoAnual, 0, ',', '.') }}</strong></td>
                                    <td><strong>Utilidad Anual: {{ $totalAnual < 0 ? '-' : '' }}${{ number_format(abs($totalAnual), 0, ',', '.') }}</strong></td>
                                    <td><strong>${{ number_format($inversionAnual, 0, ',', '.') }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                        <p class="grey-text" style="font-size:.75rem"><span class="orange-text">*</span> Mes sin sincronizar con el SII: IVA débito y PPM estimados.</p>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
