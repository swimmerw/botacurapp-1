@extends('themes.backoffice.layouts.admin')

@section('title', 'Atención de ejecutivo')

@section('head')
<style>
.bej{--v:#1f5c4a;--v2:#2f7d64;--suave:#eef4f0;--arena:#f6f1e7;--borde:#d9e5dd;--tx:#1e2b26;--mu:#5f6f68;--rojo:#b4442c;--ambar:#a8740f}
.bej .intro{background:var(--v);color:#fff;border-radius:16px;padding:16px 20px;margin:16px 0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.bej .intro p{margin:4px 0 0;opacity:.85;font-size:.9rem}
.bej .intro .nums{display:flex;gap:22px;text-align:right}
.bej .intro .num{font-size:2rem;font-weight:800;line-height:1}
.bej h5.sec{font-size:1.05rem;font-weight:700;color:var(--tx);margin:22px 0 4px}
.bej .sec-sub{color:var(--mu);font-size:.85rem;margin:0 0 12px}
.bej .card-c{background:#fff;border:1px solid var(--borde);border-left:6px solid var(--v2);border-radius:14px;padding:14px 18px;margin-bottom:12px}
.bej .card-c.urg{border-left-color:var(--rojo)}
.bej .card-c.med{border-left-color:var(--ambar)}
.bej .card-c.venc{border-left-color:#9aa6a0}
.bej .fila{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start;justify-content:space-between}
.bej .nombre{font-weight:700;font-size:1rem;color:var(--tx)}
.bej .meta{color:var(--mu);font-size:.83rem}
.bej .canal{font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:999px;background:var(--suave);color:var(--v);margin-left:6px;vertical-align:middle}
.bej .motivo{background:var(--arena);color:#5a4413;border-radius:10px;padding:8px 12px;font-size:.88rem;margin-top:10px}
.bej .msgs{margin-top:8px;font-size:.88rem;color:var(--tx)}
.bej .msgs div{padding:3px 0;border-top:1px dashed #e3ebe6}
.bej .msgs div:first-child{border-top:0}
.bej .reloj{font-size:.78rem;font-weight:700;padding:3px 10px;border-radius:999px;white-space:nowrap}
.bej .reloj.urg{background:#fbe9e5;color:var(--rojo)}
.bej .reloj.med{background:#fbf1dc;color:var(--ambar)}
.bej .reloj.ok{background:var(--suave);color:var(--v)}
.bej .reloj.venc{background:#eceeed;color:#5d6862}
.bej .acciones{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:12px}
.bej .btn-v{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;border-radius:9px;border:0;background:var(--v);color:#fff !important;font-weight:600;font-size:.84rem;cursor:pointer;text-decoration:none}
.bej .btn-l{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 12px;border-radius:9px;border:1px solid var(--borde);background:#fff;color:var(--tx) !important;font-weight:600;font-size:.84rem;cursor:pointer;text-decoration:none}
.bej details{margin-top:10px}
.bej summary{cursor:pointer;color:var(--v);font-weight:600;font-size:.85rem}
.bej .chat{background:var(--suave);border-radius:10px;padding:10px 12px;margin-top:8px;max-height:340px;overflow:auto}
.bej .chat p{margin:0 0 8px;font-size:.85rem;white-space:pre-wrap}
.bej .chat p b{display:block;font-size:.72rem;color:var(--mu);text-transform:uppercase;letter-spacing:.04em}
.bej .chat p.bot{padding-left:14px;border-left:3px solid #c9d9cf}
.bej .vacio{background:var(--suave);border-radius:14px;padding:22px;text-align:center;color:var(--mu)}
</style>
@endsection

@section('content')
<div class="section bej">
    <p class="caption"><strong>Atención de ejecutivo</strong></p>
    <div class="divider"></div>

    @if (session('success'))<div class="card-panel green lighten-4 green-text text-darken-4" style="border-radius:12px">{{ session('success') }}</div>@endif

    <div class="intro">
        <div>
            <strong>Conversaciones que necesitan a una persona del equipo</strong>
            <p>Responde desde WhatsApp Business o Instagram y luego márcala como atendida. Las más urgentes van primero.</p>
        </div>
        <div class="nums">
            <div><div class="num">{{ count($derivadas) }}</div><small>derivadas por el bot</small></div>
            <div><div class="num">{{ count($vencidas) }}</div><small>sin respuesta +24 h</small></div>
        </div>
    </div>

    @php
        $reloj = function ($it) {
            if ($it->horas_restantes <= 0) return ['venc', 'Más de 24 h'];
            $h = (int) floor($it->horas_restantes);
            if ($it->horas_restantes < 3) return ['urg', 'Quedan ' . ($h ?: 'menos de 1') . ' h'];
            if ($it->horas_restantes < 8) return ['med', 'Quedan ' . $h . ' h'];
            return ['ok', 'Quedan ' . $h . ' h'];
        };
        $chatUrl = function ($it) {
            return $it->instagram ? 'https://business.facebook.com/latest/inbox/instagram' : 'https://wa.me/' . preg_replace('/\D/', '', $it->telefono);
        };
    @endphp

    <h5 class="sec">Derivadas por el bot</h5>
    <p class="sec-sub">El bot no podía resolverlas solo: pagos, grupos, excepciones o errores. El reloj indica cuánto queda de las 24 horas desde el último mensaje del cliente.</p>

    @forelse ($derivadas as $it)
        @php($rj = $reloj($it))
        <div class="card-c {{ $rj[0] }}">
            <div class="fila">
                <div>
                    <span class="nombre">{{ $it->nombre ?: 'Cliente' }}</span>
                    <span class="canal">{{ $it->instagram ? 'Instagram' : 'WhatsApp' }}</span>
                    <div class="meta">
                        {{ $it->instagram ? 'Instagram' : '+' . $it->telefono }}
                        · derivada {{ $it->desde->diffForHumans() }} · último mensaje {{ $it->ultimo_at->format('d/m H:i') }}
                    </div>
                </div>
                <span class="reloj {{ $rj[0] }}">{{ $rj[1] }}</span>
            </div>
            @if ($it->motivo)<div class="motivo"><strong>Motivo:</strong> {{ $it->motivo }}</div>@endif
            @if ($it->mensajes->count())
                <div class="msgs">@foreach ($it->mensajes->take(-4) as $m)<div>{{ \Illuminate\Support\Str::limit($m, 300) }}</div>@endforeach</div>
            @endif
            @include('themes.backoffice.pages.bot-ejecutivo._chat', ['it' => $it])
            <div class="acciones">
                <a class="btn-v" href="{{ $chatUrl($it) }}" target="_blank" rel="noopener">Abrir chat</a>
                <form method="POST" action="{{ route('backoffice.bot-ejecutivo.atender') }}">@csrf
                    <input type="hidden" name="telefono" value="{{ $it->telefono }}">
                    <button class="btn-l" type="submit">Marcar como atendida</button>
                </form>
            </div>
        </div>
    @empty
        <div class="vacio">No hay conversaciones derivadas esperando. 🌿</div>
    @endforelse

    <h5 class="sec">Sin respuesta hace más de 24 horas</h5>
    <p class="sec-sub">El bot ya no puede contestarlas. Solo puede retomarlas una persona desde la app.</p>

    @forelse ($vencidas as $it)
        <div class="card-c venc">
            <div class="fila">
                <div>
                    <span class="nombre">{{ $it->nombre ?: 'Cliente' }}</span>
                    <span class="canal">{{ $it->instagram ? 'Instagram' : 'WhatsApp' }}</span>
                    <div class="meta">{{ $it->instagram ? 'Instagram' : '+' . $it->telefono }} · último mensaje {{ $it->ultimo_at->format('d/m H:i') }} ({{ $it->ultimo_at->diffForHumans() }})</div>
                </div>
                <span class="reloj venc">Más de 24 h</span>
            </div>
            @if ($it->mensajes->count())
                <div class="msgs">@foreach ($it->mensajes->take(-3) as $m)<div>{{ \Illuminate\Support\Str::limit($m, 300) }}</div>@endforeach</div>
            @endif
            @include('themes.backoffice.pages.bot-ejecutivo._chat', ['it' => $it])
            <div class="acciones">
                <a class="btn-v" href="{{ $chatUrl($it) }}" target="_blank" rel="noopener">Abrir chat</a>
                <form method="POST" action="{{ route('backoffice.bot-ejecutivo.atender') }}">@csrf
                    <input type="hidden" name="telefono" value="{{ $it->telefono }}">
                    <button class="btn-l" type="submit">Marcar como atendida</button>
                </form>
            </div>
        </div>
    @empty
        <div class="vacio">Nada pendiente de más de 24 horas.</div>
    @endforelse

    @if ($atrasados)
        <p class="meta" style="margin-top:16px">{{ $atrasados }} chat(s) solo tienen mensajes antiguos que llegaron al sincronizar el historial de WhatsApp; no se muestran aquí. Siguen en Bot de WhatsApp &gt; Conversaciones pendientes.</p>
    @endif
</div>
@endsection
