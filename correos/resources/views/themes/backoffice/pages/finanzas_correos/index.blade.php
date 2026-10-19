@extends('themes.backoffice.layouts.admin')

@section('title', 'Correos bancarios')

@section('breadcrumbs')
<li>Finanzas</li><li>Correos bancarios</li>
@endsection

@php
    $tipos = ['abono' => 'Abonos recibidos', 'pago' => 'Pagos hechos', 'cuenta' => 'Cuentas por pagar', 'cartola' => 'Cartolas', 'factura' => 'Facturas'];
    $estados = ['todos' => 'Todos', 'calzado' => 'Calzados', 'revisar' => 'Por revisar', 'nuevo' => 'Nuevos', 'pagado' => 'Pagados', 'ignorado' => 'Ignorados', 'sospechoso' => 'Sospechosos'];
    $color = ['calzado' => 'green', 'revisar' => 'orange', 'nuevo' => 'blue', 'pagado' => 'teal', 'ignorado' => 'grey', 'sospechoso' => 'red'];
    $escudo = ['seguro' => ['verified_user', 'green-text', 'Verificado: firma del banco'], 'revisar' => ['help', 'orange-text', 'Sin verificar'], 'falso' => ['gpp_bad', 'red-text', 'Falso o alterado']];
    $peso = function ($n) { return $n === null ? '–' : '$' . number_format($n, 0, ',', '.'); };
@endphp

@section('content')
<div class="section">
    @if(session('success'))<div class="card-panel green lighten-4">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="card-panel red lighten-4">{{ session('error') }}</div>@endif

    <div class="row valign-wrapper" style="margin-bottom:0">
        <div class="col s12 m8">
            <h5 class="grey-text text-darken-2" style="margin:0 0 4px"><i class="material-icons left">mark_email_read</i>Correos bancarios</h5>
            <p class="grey-text" style="margin:0;font-size:.85rem">
                Avisos de transferencias, pagos y cuentas que llegan al correo. Se leen solos cada 5 minutos.
                Última lectura: {{ $ultimo ? \Carbon\Carbon::parse($ultimo)->format('d-m H:i') : 'todavía no' }}.
            </p>
        </div>
        <div class="col s12 m4 right-align">
            <form method="POST" action="{{ route('backoffice.finanzas.correos.leer') }}" style="display:inline">@csrf
                <button class="btn waves-effect blue darken-1"><i class="material-icons left">refresh</i>Leer ahora</button>
            </form>
        </div>
    </div>

    <div class="card-panel" style="padding:12px 16px">
        <form method="GET" class="row" style="margin:0">
            <div class="input-field col s12 m4" style="margin:0">
                <select name="tipo" onchange="this.form.submit()">
                    @foreach($tipos as $k => $v)<option value="{{ $k }}" {{ $tipo === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div class="input-field col s12 m4" style="margin:0">
                <select name="estado" onchange="this.form.submit()">
                    @foreach($estados as $k => $v)<option value="{{ $k }}" {{ $estado === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div class="input-field col s12 m4" style="margin:0">
                <input type="date" name="desde" value="{{ $desde }}" onchange="this.form.submit()">
            </div>
        </form>
        <div style="margin-top:8px">
            @foreach($conteo->where('tipo', $tipo) as $c)
                <span class="chip {{ $color[$c->estado] ?? 'grey' }} lighten-4">{{ $estados[$c->estado] ?? $c->estado }}: {{ $c->n }} · {{ $peso($c->total) }}</span>
            @endforeach
        </div>
    </div>

    <div class="card"><div class="card-content" style="padding:8px">
    <table class="striped highlight responsive-table" style="font-size:.9rem">
        <thead><tr>
            <th>Fecha</th>
            <th>{{ $tipo === 'cuenta' ? 'Proveedor' : ($tipo === 'pago' ? 'Para' : 'De') }}</th>
            <th>Banco</th><th class="right-align">Monto</th><th>Detalle</th><th>Estado</th>
            @if($tipo === 'abono')<th>Reserva</th>@endif
            <th></th>
        </tr></thead>
        <tbody>
        @forelse($filas as $f)
            <tr>
                <td style="white-space:nowrap">{{ $f->fecha ? \Carbon\Carbon::parse($f->fecha)->format('d-m') : '' }} <span class="grey-text">{{ $f->hora }}</span></td>
                <td>{{ $f->nombre ?: $f->proveedor }}</td>
                <td style="white-space:nowrap">@php($e = $escudo[$f->seguridad] ?? null)@if($e)<i class="material-icons tiny {{ $e[1] }}" title="{{ $e[2] }}: {{ $f->seguridad_detalle }}">{{ $e[0] }}</i>@endif {{ $f->banco }}</td>
                <td class="right-align" style="white-space:nowrap">{{ $peso($f->monto) }}</td>
                <td class="grey-text" style="max-width:260px">{{ $f->comentario }}@if($f->vencimiento) · vence {{ \Carbon\Carbon::parse($f->vencimiento)->format('d-m') }}@endif
                    @if($f->nota)<br><small>{{ $f->nota }}</small>@endif</td>
                <td><span class="chip {{ $color[$f->estado] ?? 'grey' }} lighten-4">{{ $estados[$f->estado] ?? $f->estado }}</span></td>
                @if($tipo === 'abono')
                <td style="white-space:nowrap">
                    @if($f->reserva_id)
                        <a href="{{ route('backoffice.reserva.show', $f->reserva_id) }}">#{{ $f->reserva_id }}</a>
                        <small class="grey-text">{{ $f->nombre_cliente }} · {{ $f->campo }}</small>
                    @elseif($f->comprobante_id)
                        <a href="{{ route('backoffice.finanzas.reservas_dia') }}">Comprobante del bot #{{ $f->comprobante_id }}</a>
                    @endif
                </td>
                @endif
                <td style="white-space:nowrap">
                    <a href="{{ route('backoffice.finanzas.correos.show', $f->id) }}" class="btn-flat btn-small" title="Ver correo"><i class="material-icons">visibility</i></a>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="center grey-text">Sin correos en este filtro.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div></div>
</div>
@endsection

@section('foot')
<script>document.addEventListener('DOMContentLoaded', function () { M.FormSelect.init(document.querySelectorAll('select')); });</script>
@endsection
