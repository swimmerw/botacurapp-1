@extends('themes.backoffice.layouts.admin')

@section('title', 'Reservas en vivo')

@section('head')
<style>
.rev{--v:#1f5c4a;--v2:#2f7d64;--suave:#eef4f0;--arena:#f6f1e7;--borde:#d9e5dd;--tx:#1e2b26;--mu:#5f6f68}
.rev .top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:16px 0}
.rev .seg{display:inline-flex;background:var(--suave);border-radius:12px;padding:4px;gap:2px;flex-wrap:wrap}
.rev .seg a{padding:6px 12px;border-radius:9px;color:var(--mu);font-weight:600;font-size:.84rem}
.rev .seg a.on{background:var(--v);color:#fff}
.rev .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px}
.rev .kpi{background:var(--v);color:#fff;border-radius:14px;padding:12px 16px}
.rev .kpi.alt{background:#fff;color:var(--tx);border:1px solid var(--borde)}
.rev .kpi b{display:block;font-size:1.6rem;line-height:1.1}.rev .kpi small{opacity:.8}
.rev .aviso{display:flex;justify-content:space-between;align-items:center;background:var(--arena);border:1px solid #eadfc8;border-radius:12px;padding:10px 14px;margin-bottom:12px;color:#6b4e16;font-weight:600}
.rev .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px}
.rev .res{background:#fff;border:1px solid var(--borde);border-radius:14px;overflow:hidden;display:flex;flex-direction:column}
.rev .res-h{display:flex;justify-content:space-between;gap:8px;padding:12px 14px 6px}
.rev .res-h strong{font-size:1rem}.rev .mu{color:var(--mu);font-size:.8rem}
.rev .res-b{display:flex;gap:12px;padding:6px 14px 12px}
.rev .thumb{width:96px;height:120px;border-radius:10px;background:var(--suave);flex:none;display:flex;align-items:center;justify-content:center;text-align:center;font-size:.72rem;color:var(--mu);overflow:hidden;cursor:pointer;border:1px solid var(--borde)}
.rev .thumb img{width:100%;height:100%;object-fit:cover}
.rev .dato{font-size:.86rem;line-height:1.5}
.rev .pill{display:inline-block;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:999px;background:var(--suave);color:var(--v)}
.rev .pill.pagado{background:#d1fae5;color:#065f46}.rev .pill.verif{background:#fef3c7;color:#92400e}
.rev .pill.pend{background:#fee2e2;color:#991b1b}.rev .pill.rech{background:#fecaca;color:#7f1d1d}
.rev .alerta{margin:0 14px 12px;font-size:.76rem;background:#fff7ed;color:#9a3412;border-radius:8px;padding:6px 8px}
.rev .holds{background:#fff;border:1px dashed var(--v2);border-radius:14px;padding:12px 14px;margin-bottom:16px}
.rev .holds div{font-size:.86rem;padding:4px 0;border-top:1px solid var(--suave)}.rev .holds div:first-of-type{border-top:0}
.rev .modal-img{position:fixed;inset:0;background:rgba(10,25,20,.8);display:none;align-items:center;justify-content:center;z-index:3000;padding:20px}
.rev .modal-img.open{display:flex}.rev .modal-img img{max-width:100%;max-height:90vh;border-radius:12px}
</style>
@endsection

@section('content')
<div class="section rev">
    <p class="caption"><strong>Reservas en vivo</strong> <span class="mu">· se actualiza sola cada minuto · {{ now()->format('H:i') }}</span></p>
    <div class="divider"></div>

    <div class="top">
        <div class="seg">
            @foreach ([1 => 'Hoy', 3 => '3 días', 7 => '7 días', 14 => '14 días'] as $d => $txt)
                <a href="{{ route('backoffice.reservas-en-vivo.index', ['dias' => $d, 'fuente' => $fuente]) }}" class="{{ $dias == $d ? 'on' : '' }}">{{ $txt }}</a>
            @endforeach
        </div>
        <div class="seg">
            <a href="{{ route('backoffice.reservas-en-vivo.index', ['dias' => $dias]) }}" class="{{ $fuente === '' ? 'on' : '' }}">Todas</a>
            <a href="{{ route('backoffice.reservas-en-vivo.index', ['dias' => $dias, 'fuente' => 'bot']) }}" class="{{ $fuente === 'bot' ? 'on' : '' }}">Bot</a>
            @foreach ($fuentes as $f => $n)
                @if (strpos((string) $f, 'bot') !== 0)
                <a href="{{ route('backoffice.reservas-en-vivo.index', ['dias' => $dias, 'fuente' => $f]) }}" class="{{ $fuente === $f ? 'on' : '' }}">{{ ucfirst($f ?: 'sin fuente') }} ({{ $n }})</a>
                @endif
            @endforeach
        </div>
    </div>

    <div class="kpis">
        <div class="kpi"><b>{{ $resumen['reservas'] }}</b><small>reservas</small></div>
        <div class="kpi alt"><b>{{ $resumen['personas'] }}</b><small>personas</small></div>
        <div class="kpi alt"><b>${{ number_format($resumen['abonos'], 0, ',', '.') }}</b><small>abonos registrados</small></div>
        <div class="kpi alt"><b>{{ $resumen['porVerificar'] }}</b><small>comprobantes por verificar</small></div>
        <div class="kpi alt"><b>{{ $resumen['sinComprobante'] }}</b><small>sin comprobante</small></div>
    </div>

    @if ($resumen['porVerificar'])
        <a class="aviso" href="{{ route('backoffice.verificacion-pago.index') }}"><span>{{ $resumen['porVerificar'] }} comprobante(s) esperando aprobación</span><span>Verificación de pagos →</span></a>
    @endif
    @if ($sinReserva && \Route::has('backoffice.comprobantes-sin-reserva.index'))
        <a class="aviso" href="{{ route('backoffice.comprobantes-sin-reserva.index') }}"><span>{{ $sinReserva }} comprobante(s) llegaron sin reserva</span><span>Revisar →</span></a>
    @endif

    @if ($holds->count())
        <div class="holds">
            <strong>Cupos apartados esperando comprobante</strong>
            @foreach ($holds as $h)
                <div>{{ $h->nombre }} · +{{ $h->telefono }} · {{ \Carbon\Carbon::parse($h->fecha)->format('d/m') }} · {{ $h->personas }}p · transferir ${{ number_format((int) $h->abono_50, 0, ',', '.') }}
                    <span class="mu">· vence {{ \Carbon\Carbon::parse($h->expira_en)->format('H:i') }}</span></div>
            @endforeach
        </div>
    @endif

    <div class="grid">
        @forelse ($reservas as $r)
            @php
                $ep = $r->estado_pago;
                $cls = $ep === 'pagado' ? 'pagado' : ($ep === 'pendiente_verificacion' ? 'verif' : ($ep === 'rechazado' ? 'rech' : 'pend'));
                $txt = $ep === 'pagado' ? 'Pagado' : ($ep === 'pendiente_verificacion' ? 'Por verificar' : ($ep === 'rechazado' ? 'Rechazado' : 'Sin comprobante'));
            @endphp
            <div class="res">
                <div class="res-h">
                    <div><strong>N°{{ $r->id }} · {{ $r->nombre_cliente ?: 'Cliente' }}</strong><br>
                        <span class="mu">creada {{ \Carbon\Carbon::parse($r->created_at)->format('d/m H:i') }} · {{ $r->fuente ?: 'sin fuente' }}</span></div>
                    <div style="text-align:right"><span class="pill {{ $cls }}">{{ $txt }}</span><br><span class="mu">{{ $r->estado }}</span></div>
                </div>
                <div class="res-b">
                    <div class="thumb" @if ($r->comprobante_transferencia) onclick="revAbrir('{{ route('backoffice.reservas-en-vivo.comprobante', $r->venta_id) }}')" @endif>
                        @if ($r->comprobante_transferencia)
                            <img loading="lazy" src="{{ route('backoffice.reservas-en-vivo.comprobante', $r->venta_id) }}" alt="Comprobante">
                        @else
                            Sin<br>comprobante
                        @endif
                    </div>
                    <div class="dato">
                        <b>{{ \Carbon\Carbon::parse($r->fecha_visita)->locale('es')->isoFormat('ddd D MMM') }}</b> · {{ $r->cantidad_personas }} pers.<br>
                        {{ $r->nombre_programa }}<br>
                        Abono <b>${{ number_format((int) $r->abono_programa, 0, ',', '.') }}</b> · saldo ${{ number_format((int) $r->diferencia_programa, 0, ',', '.') }}<br>
                        @if ($r->comprobante_monto)
                            <span class="mu">Comprobante: ${{ number_format((int) $r->comprobante_monto, 0, ',', '.') }}{{ $r->comprobante_fecha ? ' · ' . \Carbon\Carbon::parse($r->comprobante_fecha)->format('d/m') : '' }}{{ $r->comprobante_hora ? ' ' . $r->comprobante_hora : '' }}{{ $r->comprobante_numero_operacion ? ' · op. ' . $r->comprobante_numero_operacion : '' }}</span><br>
                        @endif
                        @if ($r->whatsapp_cliente)<a class="mu" href="https://wa.me/{{ preg_replace('/\D/', '', $r->whatsapp_cliente) }}" target="_blank">+{{ preg_replace('/\D/', '', $r->whatsapp_cliente) }} · chat</a>@endif
                    </div>
                </div>
                @if ($r->comprobante_alerta)<div class="alerta">{{ \Illuminate\Support\Str::limit($r->comprobante_alerta, 220) }}</div>@endif
            </div>
        @empty
            <div class="mu">No hay reservas en este período.</div>
        @endforelse
    </div>

    <div class="modal-img" id="revModal" onclick="this.classList.remove('open')"><img id="revImg" src="" alt="Comprobante"></div>
</div>
<script>
function revAbrir(u){document.getElementById('revImg').src=u;document.getElementById('revModal').classList.add('open');}
setInterval(function(){ if(!document.getElementById('revModal').classList.contains('open')) location.reload(); }, 60000);
</script>
@endsection
