@extends('themes.backoffice.layouts.admin')

@section('title', 'Comprobantes sin reserva')

@section('head')
<style>
.csr{--v:#1f5c4a;--v2:#2f7d64;--suave:#eef4f0;--arena:#f6f1e7;--borde:#d9e5dd;--tx:#1e2b26;--mu:#5f6f68}
.csr .intro{background:var(--v);color:#fff;border-radius:16px;padding:16px 20px;margin:16px 0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.csr .intro p{margin:4px 0 0;opacity:.85;font-size:.9rem}
.csr .intro .num{font-size:2rem;font-weight:800;line-height:1}
.csr .card-c{background:#fff;border:1px solid var(--borde);border-left:6px solid var(--v2);border-radius:14px;padding:16px 18px;margin-bottom:14px}
.csr .fila{display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;justify-content:space-between}
.csr .monto{font-size:1.5rem;font-weight:800;color:var(--v)}
.csr .meta{color:var(--mu);font-size:.85rem}
.csr .glosa{background:var(--arena);border-radius:8px;padding:2px 8px;font-size:.85rem;color:#6b4e16}
.csr .desc{color:var(--mu);font-size:.8rem;margin-top:8px;max-height:3.2em;overflow:hidden}
.csr .desc.abierta{max-height:none}
.csr .cand{margin-top:12px;background:var(--suave);border-radius:10px;padding:10px 12px}
.csr .cand h6{margin:0 0 6px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:var(--mu)}
.csr .cand-item{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:6px 0;border-top:1px solid var(--borde);font-size:.86rem}
.csr .cand-item:first-of-type{border-top:0}
.csr .tag{font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:999px;background:#fff;color:var(--v);border:1px solid var(--borde)}
.csr .btn-v{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;border-radius:9px;border:0;background:var(--v);color:#fff;font-weight:600;font-size:.84rem;cursor:pointer}
.csr .btn-l{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 12px;border-radius:9px;border:1px solid var(--borde);background:#fff;color:var(--tx);font-weight:600;font-size:.84rem;cursor:pointer}
.csr .acciones{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:12px}
.csr .acciones input{height:34px;width:120px;border:1px solid var(--borde);border-radius:9px;padding:0 10px;margin:0}
.csr .vacio{background:var(--suave);border-radius:14px;padding:28px;text-align:center;color:var(--mu)}
.csr table{width:100%;font-size:.85rem}.csr td,.csr th{padding:7px 6px;border-bottom:1px solid #eef2ef;text-align:left}
</style>
@endsection

@section('content')
<div class="section csr">
    <p class="caption"><strong>Comprobantes sin reserva</strong></p>
    <div class="divider"></div>

    @if (session('success'))<div class="card-panel green lighten-4 green-text text-darken-4" style="border-radius:12px">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="card-panel red lighten-4 red-text text-darken-4" style="border-radius:12px">{{ session('error') }}</div>@endif

    <div class="intro">
        <div>
            <strong>Pagos que llegaron por WhatsApp y el bot no pudo asociar a una reserva</strong>
            <p>Revisa el chat, asócialo a la reserva que corresponda (pasa a Verificación de pagos) o descártalo si ya estaba registrado.</p>
        </div>
        <div style="text-align:right"><div class="num">{{ $pendientes->count() }}</div><small>por resolver</small></div>
    </div>

    @forelse ($pendientes as $c)
        <div class="card-c">
            <div class="fila">
                <div>
                    <div class="monto">{{ $c->monto ? '$' . number_format($c->monto, 0, ',', '.') : 'Monto no legible' }}</div>
                    <div class="meta">
                        Pagado {{ $c->fecha_pago ? \Carbon\Carbon::parse($c->fecha_pago)->format('d/m/Y') : '(fecha no legible)' }}
                        · llegó {{ \Carbon\Carbon::parse($c->created_at)->format('d/m H:i') }}
                    </div>
                    @if ($c->glosa)<div style="margin-top:6px"><span class="glosa">«{{ $c->glosa }}»</span></div>@endif
                </div>
                <div style="text-align:right">
                    <strong>{{ $c->nombre ?: 'Cliente' }}</strong><br>
                    <a href="https://wa.me/{{ $c->telefono }}" target="_blank" class="meta">+{{ $c->telefono }} · abrir chat</a>
                    @if ($c->archivo)<br><a href="{{ route('backoffice.comprobantes-sin-reserva.imagen', $c->id) }}" target="_blank" class="meta">Ver comprobante</a>@endif
                </div>
            </div>
            @if ($c->descripcion)<div class="desc" onclick="this.classList.toggle('abierta')" title="Clic para ver completo">{{ $c->descripcion }}</div>@endif

            <div class="cand">
                <h6>Reservas posibles</h6>
                @forelse ($c->candidatas as $r)
                    <div class="cand-item">
                        <span>
                            <strong>N°{{ $r->id }}</strong> · {{ \Carbon\Carbon::parse($r->fecha_visita)->format('d/m') }} · {{ $r->cantidad_personas }}p · {{ $r->nombre_programa }}
                            · {{ $r->nombre_cliente }} · abono ${{ number_format((int) $r->abono_programa, 0, ',', '.') }} · saldo ${{ number_format((int) $r->diferencia_programa, 0, ',', '.') }}
                            <span class="tag">{{ $r->motivo }}</span>
                        </span>
                        <form method="POST" action="{{ route('backoffice.comprobantes-sin-reserva.asociar', $c->id) }}">@csrf
                            <input type="hidden" name="reserva_id" value="{{ $r->id }}">
                            <button class="btn-v" type="submit">Asociar</button>
                        </form>
                    </div>
                @empty
                    <div class="meta">No hay reservas con este teléfono ni con este monto. Si corresponde, créala y luego asóciala con su número.</div>
                @endforelse
            </div>

            <div class="acciones">
                <form method="POST" action="{{ route('backoffice.comprobantes-sin-reserva.asociar', $c->id) }}" style="display:flex;gap:8px;align-items:center">@csrf
                    <input type="number" name="reserva_id" placeholder="Reserva N°" required>
                    <button class="btn-v" type="submit">Asociar a esta reserva</button>
                </form>
                <form method="POST" action="{{ route('backoffice.comprobantes-sin-reserva.descartar', $c->id) }}" style="display:flex;gap:8px;align-items:center">@csrf
                    <input type="text" name="nota" placeholder="Motivo (opcional)" style="width:180px">
                    <button class="btn-l" type="submit">Descartar</button>
                </form>
            </div>
        </div>
    @empty
        <div class="vacio">No hay comprobantes sin reserva. Todo lo que llegó por WhatsApp está asociado.</div>
    @endforelse

    @if ($resueltos->count())
        <p class="caption" style="margin-top:24px"><strong>Resueltos recientemente</strong></p>
        <div class="card-c" style="border-left-color:#cfd8d3">
            <table>
                <tr><th>Resuelto</th><th>Cliente</th><th>Monto</th><th>Resultado</th></tr>
                @foreach ($resueltos as $c)
                    <tr>
                        <td>{{ $c->revisado_at ? \Carbon\Carbon::parse($c->revisado_at)->format('d/m H:i') : '' }}</td>
                        <td>{{ $c->nombre ?: '+' . $c->telefono }}</td>
                        <td>{{ $c->monto ? '$' . number_format($c->monto, 0, ',', '.') : '-' }}</td>
                        <td>{{ $c->estado === 'asociado' ? 'Asociado a la reserva N°' . $c->reserva_id : 'Descartado' . ($c->nota ? ': ' . $c->nota : '') }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</div>
@endsection
