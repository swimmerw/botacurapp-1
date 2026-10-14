@extends('themes.backoffice.layouts.admin')

@section('title', 'Conversaciones del bot')

@section('head')
<style>
.apr-top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.apr-seg{display:inline-flex;background:#f1f5f9;border-radius:12px;padding:4px;gap:2px}
.apr-seg a{padding:6px 14px;border-radius:9px;color:#475569;font-weight:600;font-size:.85rem}
.apr-seg a.on{background:#fff;color:#0f172a;box-shadow:0 1px 3px rgba(15,23,42,.12)}
.apr-box{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px 18px}
.apr-tabla{width:100%;font-size:.88rem}
.apr-tabla td,.apr-tabla th{padding:8px 6px;border-bottom:1px solid #f1f5f9;text-align:left}
.apr-badge{font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:999px;background:#f1f5f9;color:#475569}
.apr-badge.e4,.apr-badge.e5{background:#d1fae5;color:#065f46}.apr-badge.ab{background:#fef3c7;color:#92400e}.apr-badge.de{background:#ede9fe;color:#5b21b6}
.apr-link{color:#2563eb;cursor:pointer;font-size:.82rem}
.apr-btn{display:inline-flex;align-items:center;height:36px;padding:0 14px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;color:#334155;font-weight:600;font-size:.85rem;cursor:pointer}
.apr-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:2000;padding:16px}
.apr-modal.open{display:flex}
.apr-modal-card{background:#f8fafc;border-radius:18px;width:min(640px,100%);max-height:85vh;display:flex;flex-direction:column;overflow:hidden}
.apr-modal-head{padding:14px 18px;background:#fff;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:8px}
.apr-chat{padding:16px;overflow:auto;display:flex;flex-direction:column;gap:8px}
.apr-msg{max-width:85%;padding:8px 12px;border-radius:14px;font-size:.9rem;line-height:1.4;white-space:pre-wrap;word-wrap:break-word}
.apr-msg.cliente{align-self:flex-start;background:#fff;border:1px solid #e2e8f0}
.apr-msg.bot{align-self:flex-end;background:#dcf8c6}
.apr-msg.equipo{align-self:flex-end;background:#dbeafe}
.apr-msg.sistema{align-self:center;background:transparent;color:#64748b;font-size:.75rem;font-family:monospace;max-width:100%}
</style>
@endsection

@section('content')
<div class="section">
    <p class="caption"><strong>Conversaciones del bot</strong></p>
    <div class="divider"></div>

    <div class="apr-top" style="margin-top:16px">
        <div class="apr-seg">
            @foreach ([1 => 'Hoy', 7 => '7 días', 14 => '14 días', 30 => '30 días', 90 => '90 días'] as $d => $txt)
                <a href="{{ route('backoffice.bot-conversaciones.index', ['dias' => $d]) }}" class="{{ $dias == $d ? 'on' : '' }}">{{ $txt }}</a>
            @endforeach
        </div>
        <input type="text" id="convBuscar" class="browser-default" placeholder="Buscar nombre o teléfono"
               style="height:38px;border:1px solid #d0d0d0;border-radius:10px;padding:0 12px;min-width:240px">
    </div>

    <div class="apr-box" style="overflow-x:auto;">
        <table class="apr-tabla">
            <thead><tr><th>Fecha</th><th>Cliente</th><th>Llegó a</th><th>Estado</th><th>Mensajes</th><th></th></tr></thead>
            <tbody>
            @forelse ($conversaciones as $c)
                <tr data-buscar="{{ mb_strtolower($c->nombre . ' ' . $c->telefono) }}">
                    <td>{{ $c->fecha->locale('es')->isoFormat('D MMM HH:mm') }}</td>
                    <td>{{ $c->nombre }}<br><small style="color:#94a3b8;">+{{ $c->telefono }}</small></td>
                    <td><span class="apr-badge e{{ $c->etapa_num }}">{{ $c->etapa }}</span></td>
                    <td>
                        @if ($c->derivada)<span class="apr-badge de">Equipo</span>@endif
                        @if ($c->abandonada)<span class="apr-badge ab">Se cortó</span>@endif
                    </td>
                    <td>{{ $c->turnos }}</td>
                    <td><span class="apr-link" data-conv="{{ $c->id }}" data-tel="{{ $c->telefono }}">Ver</span></td>
                </tr>
            @empty
                <tr><td colspan="6" style="color:#64748b">No hay conversaciones en el período.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="grey-text" style="font-size:.75rem;margin-top:6px">Se guardan los últimos mensajes de cada conversación. No incluye los números de prueba.</p>
</div>

<div class="apr-modal" id="aprModal">
    <div class="apr-modal-card">
        <div class="apr-modal-head">
            <strong id="aprModalTit">Conversación</strong>
            <span>
                <a class="apr-btn" id="aprModalWa" target="_blank" href="#" style="color:#128c7e">WhatsApp</a>
                <button type="button" class="apr-btn" id="aprModalCerrar">Cerrar</button>
            </span>
        </div>
        <div class="apr-chat" id="aprChat"></div>
    </div>
</div>
@endsection

@section('foot')
<script>
(function () {
    var buscar = document.getElementById('convBuscar');
    buscar.addEventListener('input', function () {
        var q = buscar.value.trim().toLowerCase().replace(/\+/g, '');
        document.querySelectorAll('tr[data-buscar]').forEach(function (tr) {
            tr.style.display = !q || tr.dataset.buscar.indexOf(q) !== -1 ? '' : 'none';
        });
    });

    var modal = document.getElementById('aprModal'), chat = document.getElementById('aprChat');
    var urlBase = @json(route('backoffice.bot-configuracion.aprendizajes.conversacion', 0));
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-conv]');
        if (!el) return;
        chat.innerHTML = '<p style="color:#64748b">Cargando…</p>';
        document.getElementById('aprModalWa').href = 'https://wa.me/' + (el.dataset.tel || '');
        modal.classList.add('open');
        fetch(urlBase.replace(/0$/, el.dataset.conv), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                document.getElementById('aprModalTit').textContent = (d.cliente || 'Conversación') + ' · #' + (d.id || '');
                chat.innerHTML = (d.mensajes || []).map(function (m) {
                    var et = { cliente: 'Cliente', bot: 'Bot', equipo: 'Equipo', sistema: '' }[m.de];
                    return '<div class="apr-msg ' + m.de + '">' + (et ? '<small style="display:block;font-weight:700;color:#64748b;">' + et + '</small>' : '') + esc(m.texto) + '</div>';
                }).join('') || '<p style="color:#64748b">Sin mensajes.</p>';
                chat.scrollTop = chat.scrollHeight;
            })
            .catch(function () { chat.innerHTML = '<p style="color:#b91c1c">No se pudo cargar la conversación.</p>'; });
    });
    document.getElementById('aprModalCerrar').onclick = function () { modal.classList.remove('open'); };
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.classList.remove('open'); });
})();
</script>
@endsection
