@extends('themes.backoffice.layouts.admin')

@section('title', 'Correo bancario')

@section('breadcrumbs')
<li><a href="{{ route('backoffice.finanzas.correos.index', ['tipo' => $c->tipo]) }}">Correos bancarios</a></li><li>#{{ $c->id }}</li>
@endsection

@section('content')
<div class="section">
    @if(session('success'))<div class="card-panel green lighten-4">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="card-panel red lighten-4">{{ session('error') }}</div>@endif
    <div class="row">
        <div class="col s12 m5">
            <div class="card"><div class="card-content">
                <span class="card-title">{{ ucfirst($c->tipo) }} · {{ $c->monto ? '$' . number_format($c->monto, 0, ',', '.') : 'sin monto' }}</span>
                <table style="font-size:.9rem">
                    <tr><td class="grey-text">Fecha</td><td>{{ $c->fecha }} {{ $c->hora }}</td></tr>
                    <tr><td class="grey-text">{{ $c->tipo === 'pago' ? 'Para' : 'De' }}</td><td>{{ $c->nombre ?: $c->proveedor }} {{ $c->rut }}</td></tr>
                    <tr><td class="grey-text">Banco</td><td>{{ $c->banco }}</td></tr>
                    <tr><td class="grey-text">Cuenta</td><td>{{ $c->cuenta }} {{ config('finanzas.cuentas.' . $c->cuenta) }}</td></tr>
                    <tr><td class="grey-text">N° operación</td><td>{{ $c->operacion }}</td></tr>
                    <tr><td class="grey-text">Comentario</td><td>{{ $c->comentario }}</td></tr>
                    @if($c->vencimiento)<tr><td class="grey-text">Vence</td><td>{{ $c->vencimiento }}</td></tr>@endif
                    <tr><td class="grey-text">Estado</td><td>{{ $c->estado }} <small class="grey-text">{{ $c->nota }}</small></td></tr>
                    <tr><td class="grey-text">Correo</td><td><small>{{ $c->remitente }} · {{ $c->asunto }}</small></td></tr>
                </table>
            </div></div>

            @if($c->tipo === 'abono')
            <div class="card"><div class="card-content">
                <span class="card-title" style="font-size:1.1rem">Asociar a una reserva</span>
                <form method="POST" action="{{ route('backoffice.finanzas.correos.asociar', $c->id) }}">@csrf
                    <div class="row" style="margin:0">
                        <div class="input-field col s5"><select name="que"><option value="reserva">Reserva N°</option><option value="venta">Venta N°</option></select></div>
                        <div class="input-field col s4"><input type="number" name="numero" required></div>
                        <div class="input-field col s3"><button class="btn blue darken-1">Asociar</button></div>
                    </div>
                </form>
            </div></div>
            @endif

            <form method="POST" action="{{ route('backoffice.finanzas.correos.estado', $c->id) }}" class="card-panel">@csrf
                <input type="text" name="nota" placeholder="Nota (opcional)">
                @if($c->tipo === 'cuenta')<button name="estado" value="pagado" class="btn teal">Marcar pagada</button>@endif
                <button name="estado" value="ignorado" class="btn grey">Ignorar</button>
                <button name="estado" value="revisar" class="btn-flat">Dejar por revisar</button>
            </form>
        </div>
        <div class="col s12 m7">
            <div class="card"><div class="card-content">
                <span class="card-title" style="font-size:1.1rem">Texto del correo</span>
                <pre style="white-space:pre-wrap;font-size:.8rem;max-height:70vh;overflow:auto">{{ $c->texto }}</pre>
            </div></div>
        </div>
    </div>
</div>
@endsection

@section('foot')
<script>document.addEventListener('DOMContentLoaded', function () { M.FormSelect.init(document.querySelectorAll('select')); });</script>
@endsection
