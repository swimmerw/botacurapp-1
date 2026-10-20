@extends('themes.backoffice.layouts.admin')

@section('title', 'Por pagar')

@section('breadcrumbs')
<li>Egresos</li><li>Por pagar</li>
@endsection

@php $peso = function ($n) { return '$' . number_format((int) $n, 0, ',', '.'); }; @endphp

@section('content')
@include('themes.backoffice.pages.egresos_panel._estilo')
<div class="section eg">
    @if(session('success'))<div class="aviso ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="aviso falta">{{ session('error') }}</div>@endif

    <div class="top">
        <div>
            <div class="eyebrow">Finanzas · Egresos</div>
            <h1>Por pagar y pagado</h1>
            <p class="sub">Cuentas que llegan al correo, deudas y créditos. Los pagos que avisa el banco se descuentan solos; los demás se abonan aquí.</p>
        </div>
        <nav class="pills">
            <a class="pill" href="{{ route('backoffice.egreso.mes', [now()->year, now()->month]) }}">Egresos del mes</a>
            <a class="pill" href="{{ route('backoffice.finanzas.clasificar.index') }}">Clasificar</a>
        </nav>
    </div>

    <section class="kpis" aria-label="Resumen" style="grid-template-columns: repeat(3, minmax(0, 1fr))">
        <div class="card-e kpi"><div class="l">Saldo por pagar</div><div class="v">{{ $peso($tot['saldo']) }}</div><div class="l">{{ $pendientes->count() }} pendientes</div></div>
        <div class="card-e kpi"><div class="l">Vencido</div><div class="v {{ $tot['vencido'] ? 'rojo' : 'verde' }}">{{ $peso($tot['vencido']) }}</div><div class="l">{{ $pendientes->where('vencida', true)->count() }} cuentas</div></div>
        <div class="card-e kpi"><div class="l">Abonado este mes</div><div class="v verde">{{ $peso($tot['mes']) }}</div><div class="l">banco y a mano</div></div>
    </section>

    <div class="cols">
        <div class="col-a">
            <section class="card-e" aria-label="Pendientes">
                <h2>Pendientes</h2>
                @forelse($pendientes as $x)
                    @include('themes.backoffice.pages.egresos_panel._deuda', ['x' => $x])
                @empty
                    <p class="verde">No hay nada pendiente.</p>
                @endforelse
                <details class="abrir" style="margin-top:12px">
                    <summary><span class="btn-c ghost" style="display:inline-block">Agregar algo por pagar</span></summary>
                    @include('themes.backoffice.pages.egresos_panel._deuda_form', ['f' => null, 'accion' => route('backoffice.finanzas.deudas.store')])
                </details>
            </section>
        </div>
        <div class="col-b">
            <section class="card-e" aria-label="Abonos">
                <h2>Últimos abonos</h2>
                @forelse($abonos as $a)
                    <div class="fila" style="align-items:center">
                        <span style="min-width:0">{{ $a->deuda }}<br><span class="gris" style="font-size:.8rem">{{ \Carbon\Carbon::parse($a->fecha)->format('d-m') }} · {{ $a->correo_id ? 'aviso ' . $a->banco : $a->medio }}@if($a->nota) · {{ $a->nota }}@endif</span></span>
                        <span style="display:flex;gap:8px;align-items:center">
                            <span class="num verde">−{{ $peso($a->monto) }}</span>
                            <form method="POST" action="{{ route('backoffice.finanzas.deudas.quitar_pago', $a->id) }}">@csrf<button class="btn-c ghost" style="padding:4px 8px" title="Quitar este abono" aria-label="Quitar abono">×</button></form>
                        </span>
                    </div>
                @empty
                    <p class="gris">Todavía no hay abonos.</p>
                @endforelse
            </section>
            <section class="card-e" aria-label="Pagadas">
                <h2>Pagadas</h2>
                @forelse($pagadas as $p)
                    <div class="fila"><span>{{ $p->nombre }}</span><span class="num gris">{{ $peso($p->monto_total) }} · {{ \Carbon\Carbon::parse($p->updated_at)->format('d-m') }}</span></div>
                @empty
                    <p class="gris">Aún no hay deudas pagadas.</p>
                @endforelse
            </section>
        </div>
    </div>
</div>
@endsection
