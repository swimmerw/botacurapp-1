{{-- Entrega de avisos de WhatsApp (10/11/2026): estado de lo enviado al aprobar o rechazar, y aviso de 24 h en cada comprobante --}}
@if (class_exists(\App\Services\WaEnviosService::class) && \App\Services\WaEnviosService::listo())
@php($__waRec = \App\Services\WaEnviosService::recientes(3))
@php($__waHoras = \App\Services\WaEnviosService::horasPorVenta())
<style>
.wae{background:#fff;border:1px solid #d9e5dd;border-radius:14px;padding:14px 18px;margin:14px 0}
.wae h6{margin:0 0 4px;font-weight:700;color:#1e2b26;font-size:.95rem}
.wae .sub{color:#5f6f68;font-size:.82rem;margin:0 0 10px}
.wae table{width:100%;font-size:.84rem}.wae td,.wae th{padding:7px 6px;border-bottom:1px solid #eef2ef;text-align:left;vertical-align:top}
.wae .e{display:inline-block;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:999px;margin:1px 2px;white-space:nowrap}
.wae .e.ok{background:#e3f1ea;color:#1f5c4a}.wae .e.env{background:#eef2ef;color:#5f6f68}
.wae .e.esp{background:#fbf1dc;color:#a8740f}.wae .e.mal{background:#fbe9e5;color:#b4442c}
.wae-24{display:inline-block;font-size:.75rem;font-weight:700;padding:3px 10px;border-radius:999px;margin:6px 0}
.wae-24.ok{background:#e3f1ea;color:#1f5c4a}.wae-24.med{background:#fbf1dc;color:#a8740f}.wae-24.mal{background:#fbe9e5;color:#b4442c}
</style>
@php
    $__waEtq = ['confirmacion' => 'Confirmación', 'rechazo' => 'Rechazo', 'menu_texto' => 'Aviso menú', 'menu_pdf' => 'Menú PDF', 'info_visita' => 'Info visita'];
    $__waEst = function ($f) {
        $pl = $f->via === 'template' ? ' (plantilla)' : '';
        switch ($f->estado) {
            case 'leido': return ['ok', 'Leído' . $pl];
            case 'entregado': return ['ok', 'Entregado' . $pl];
            case 'enviado': case 'enviando': return ['env', 'Enviado' . $pl];
            case 'en_espera': return ['esp', 'Espera respuesta del cliente'];
            default: return ['mal', 'No entregado' . ($f->error_code ? ' (' . $f->error_code . ')' : '')];
        }
    };
@endphp
<div class="wae">
    <h6>Avisos enviados a clientes (últimos 3 días)</h6>
    <p class="sub">Lo que salió por WhatsApp al aprobar o rechazar. Si el cliente escribió hace más de 24 h, la confirmación va con plantilla y el menú y la información salen apenas responda. «No entregado» = escribirle a mano.</p>
    @if ($__waRec->count())
    <table>
        <tr><th>Reserva</th><th>Cliente</th><th>Enviado</th><th>Estado</th></tr>
        @foreach ($__waRec->take(25) as $r)
            @php($__malo = collect($r->tipos)->contains(function ($f) { return in_array($f->estado, ['fallido', 'error'], true); }))
            <tr>
                <td>N°{{ $r->reserva_id }}@if($r->fecha_visita)<br><small>{{ \Carbon\Carbon::parse($r->fecha_visita)->format('d/m') }}</small>@endif</td>
                <td>{{ $r->nombre ?: 'Cliente' }}<br><a href="https://wa.me/{{ $r->telefono }}" target="_blank" rel="noopener"><small>+{{ $r->telefono }}{{ $__malo ? ' · escribirle' : '' }}</small></a></td>
                <td>{{ $r->enviado_at->format('d/m H:i') }}</td>
                <td>@foreach ($r->tipos as $tipo => $f)@php($__x = $__waEst($f))<span class="e {{ $__x[0] }}" title="{{ $f->error_text }}">{{ $__waEtq[$tipo] ?? $tipo }}: {{ $__x[1] }}</span>@endforeach</td>
            </tr>
        @endforeach
    </table>
    @else
        <p class="sub" style="margin:0">Todavía no hay avisos registrados. Aparecerán desde la próxima aprobación.</p>
    @endif
</div>
<script>
(function () {
    var horas = @json($__waHoras);
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[action*="/verificacion-pago/"][action$="/aprobar"]').forEach(function (f) {
            var m = f.getAttribute('action').match(/verificacion-pago\/(\d+)\/aprobar/);
            if (!m || horas[m[1]] === undefined) return;
            var h = horas[m[1]], s = document.createElement('div'), cls, txt;
            if (h >= 24) { cls = 'mal'; txt = 'Escribió hace ' + Math.floor(h) + ' h: la confirmación irá con plantilla'; }
            else if (h >= 20) { cls = 'med'; txt = 'Escribió hace ' + Math.floor(h) + ' h: aprueba ya (quedan ' + Math.max(0, Math.floor(24 - h)) + ' h)'; }
            else { cls = 'ok'; txt = 'Escribió hace ' + Math.floor(h) + ' h'; }
            s.className = 'wae-24 ' + cls; s.textContent = txt;
            f.parentNode.insertBefore(s, f);
        });
    });
})();
</script>
@endif
