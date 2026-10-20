@php
    $pesoD = function ($n) { return '$' . number_format((int) $n, 0, ',', '.'); };
    $pct = $x->monto_total > 0 ? min(100, round($x->pagado * 100 / $x->monto_total)) : 0;
    $tipoD = ['cuenta' => 'Cuenta', 'deuda' => 'Deuda', 'credito' => 'Crédito'];
    $sugerido = $x->cuota && $x->cuota < $x->saldo ? $x->cuota : $x->saldo;
@endphp
<div class="deuda">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:baseline">
        <div style="min-width:0">
            <div style="font-weight:500">{{ $x->nombre }}</div>
            <div class="gris" style="font-size:.82rem">
                <span class="tag">{{ $tipoD[$x->tipo] ?? $x->tipo }}</span>
                @if($x->ambito === 'personal') <span class="tag">Personal</span>@endif
                @if($x->vencimiento) <span class="{{ $x->vencida ? 'rojo' : '' }}">{{ $x->vencida ? 'venció' : 'vence' }} {{ \Carbon\Carbon::parse($x->vencimiento)->format('d-m') }}</span>@endif
                @if($x->ultimo) · último abono {{ $pesoD($x->ultimo->monto) }} el {{ \Carbon\Carbon::parse($x->ultimo->fecha)->format('d-m') }}{{ $x->ultimo->correo_id ? ' (banco)' : '' }}@endif
            </div>
        </div>
        <div class="num" style="font-weight:500">{{ $pesoD($x->saldo) }}</div>
    </div>
    <div class="prog" aria-label="Pagado {{ $pct }} %"><span style="width: {{ $pct }}%"></span></div>
    <div class="gris" style="font-size:.8rem">Pagado {{ $pesoD($x->pagado) }} de {{ $pesoD($x->monto_total) }}@if($x->cuota) · cuota {{ $pesoD($x->cuota) }}@endif @if($x->nota) · {{ $x->nota }}@endif</div>
    <details class="abrir">
        <summary class="gris" style="font-size:.85rem;margin-top:6px"><span style="border-bottom:1px solid #cfd5d1">Abonar o editar</span></summary>
        <form method="POST" action="{{ route('backoffice.finanzas.deudas.pago', $x->id) }}" class="form-l">@csrf
            <input type="text" name="monto" class="campo" style="width:120px" value="{{ $sugerido }}" aria-label="Monto del abono" inputmode="numeric">
            <input type="date" name="fecha" class="campo" value="{{ date('Y-m-d') }}" aria-label="Fecha">
            <select name="medio" class="browser-default" aria-label="Medio"><option>transferencia</option><option>efectivo</option><option>tarjeta</option><option>ajuste</option></select>
            <button class="btn-c">Abonar</button>
        </form>
        <div class="form-l">
            <form method="POST" action="{{ route('backoffice.finanzas.deudas.saldar', $x->id) }}">@csrf<button class="btn-c ghost">Ya está pagada</button></form>
            <form method="POST" action="{{ route('backoffice.finanzas.deudas.anular', $x->id) }}">@csrf<button class="btn-c ghost" title="La saca de Por pagar sin registrar pago">Sacar</button></form>
        </div>
        @if(empty($compacta))
            <details class="abrir" style="margin-top:8px">
                <summary class="gris" style="font-size:.85rem"><span style="border-bottom:1px solid #cfd5d1">Editar datos</span></summary>
                @include('themes.backoffice.pages.egresos_panel._deuda_form', ['f' => $x, 'accion' => route('backoffice.finanzas.deudas.update', $x->id)])
            </details>
        @endif
    </details>
</div>
