import re, sys, os
# Conversaciones del bot con WhatsApp e Instagram juntos: canal por conversación, filtro, enlace correcto
# y un interruptor para encender o apagar el bot de Instagram (solo administradores).
base = sys.argv[1]
hechos = []

def editar(rel, fn):
    p = os.path.join(base, rel)
    s = open(p, encoding='utf-8').read()
    n = fn(s)
    if n != s:
        open(p, 'w', encoding='utf-8').write(n)
        hechos.append(rel.split('/')[-1])

def rutas(s):
    if 'BotInstagramController' in s:
        return s
    m = re.search(r"^.*'BotConversacionesController@index'\).*$", s, re.M)
    if not m:
        sys.exit('routes/web.php: no encontré bot-conversaciones')
    return s[:m.end()] + "\n    Route::post('bot-conversaciones/instagram-bot', 'BotInstagramController@cambiar')->name('bot-conversaciones.instagram_bot');" + s[m.end():]

def servicio(s):
    if "'canal'      =>" in s:
        return s
    a = "->select('id', 'usuario_id', 'nombre_cliente', 'historial_json', 'id_reserva', 'requiere_humano', 'updated_at', 'ultimo_mensaje')->get();"
    if a not in s:
        sys.exit('BotAprendizajeService: no encontré la consulta de conversaciones recientes')
    s = s.replace(a, a.replace("'ultimo_mensaje')", "'ultimo_mensaje', 'canal')"), 1)
    b = "                'telefono'   => $c->usuario_id,\n"
    if b not in s:
        sys.exit('BotAprendizajeService: no encontré el campo telefono')
    return s.replace(b, b + "                'canal'      => $c->canal ?? 'whatsapp',\n", 1)

CAJA = """    {{-- WhatsApp e Instagram en la misma lista --}}
    @php $igActivo = class_exists(\\App\\Http\\Controllers\\Api\\InstagramWebhookController::class) && \\App\\Http\\Controllers\\Api\\InstagramWebhookController::botActivo(); @endphp
    <div class="apr-box" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
        <div style="display:flex;gap:6px;flex-wrap:wrap" role="group" aria-label="Filtrar por canal">
            <button type="button" class="apr-bot-btn on" data-filtro-canal="">Todos</button>
            <button type="button" class="apr-bot-btn" data-filtro-canal="whatsapp">WhatsApp</button>
            <button type="button" class="apr-bot-btn" data-filtro-canal="instagram">Instagram</button>
        </div>
        <form method="POST" action="{{ route('backoffice.bot-conversaciones.instagram_bot') }}" style="display:flex;gap:8px;align-items:center;margin:0">@csrf
            <span>Bot en Instagram: <strong style="color:{{ $igActivo ? '#0e5f49' : '#a33a12' }}">{{ $igActivo ? 'Encendido' : 'Apagado' }}</strong></span>
            <input type="hidden" name="accion" value="{{ $igActivo ? 'apagar' : 'encender' }}">
            <button class="apr-bot-btn {{ $igActivo ? 'off' : 'on' }}" onclick="return confirm('{{ $igActivo ? '¿Apagar el bot en Instagram? Los mensajes quedarán en espera para el equipo.' : '¿Encender el bot en Instagram? Responderá los mensajes directos que lleguen desde ahora.' }}')">{{ $igActivo ? 'Apagar' : 'Encender' }}</button>
        </form>
    </div>
    <style>
        .apr-canal { display:inline-block; font-size:.68rem; font-weight:700; padding:1px 7px; border-radius:999px; margin-right:4px; }
        .apr-canal.wa { background:#e7f6ee; color:#0e6b45; } .apr-canal.ig { background:#fbe9f1; color:#9b1b52; }
        .apr-bot-btn { border:1px solid #cbd5e1; background:#fff; border-radius:999px; padding:5px 12px; font-size:.8rem; cursor:pointer; white-space:nowrap; }
        .apr-bot-btn.on { border-color:#0e5f49; background:#0e5f49; color:#fff; } .apr-bot-btn.off { border-color:#a33a12; color:#a33a12; }
    </style>
"""

JS_FILTRO = """
    document.querySelectorAll('[data-filtro-canal]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('[data-filtro-canal]').forEach(function (x) { x.classList.toggle('on', x === b); });
            var c = b.dataset.filtroCanal;
            document.querySelectorAll('tr[data-buscar]').forEach(function (tr) {
                tr.style.display = !c || (tr.dataset.canal || 'whatsapp') === c ? '' : 'none';
            });
        });
    });
"""

def vista(s):
    if 'data-filtro-canal' in s:
        return s
    q = 'bot-conversaciones/index'
    i = s.find('<thead><tr><th>Fecha</th><th>Cliente</th>')
    if i < 0:
        sys.exit(q + ': no encontré la tabla de conversaciones')
    j = s.rfind('<div class="apr-box" style="overflow-x:auto;">', 0, i)
    if j < 0:
        sys.exit(q + ': no encontré el contenedor de la tabla')
    s = s[:j] + CAJA + '    ' + s[j:].lstrip(' ')
    a = '<tr data-buscar="{{ mb_strtolower($c->nombre . \' \' . $c->telefono) }}">'
    if a not in s:
        sys.exit(q + ': no encontré la fila de conversación')
    s = s.replace(a, '<tr data-buscar="{{ mb_strtolower($c->nombre . \' \' . $c->telefono . \' \' . ($c->canal ?? \'whatsapp\')) }}" data-canal="{{ $c->canal ?? \'whatsapp\' }}">', 1)
    b = '<td>{{ $c->nombre }}<br><small style="color:#94a3b8;">+{{ $c->telefono }}</small></td>'
    if b not in s:
        sys.exit(q + ': no encontré la celda del cliente')
    s = s.replace(b, '<td>@if(($c->canal ?? \'whatsapp\') === \'instagram\')<span class="apr-canal ig">Instagram</span>@else<span class="apr-canal wa">WhatsApp</span>@endif {{ $c->nombre }}<br><small style="color:#94a3b8;">@if(($c->canal ?? \'whatsapp\') === \'instagram\') Mensaje directo @else +{{ $c->telefono }} @endif</small></td>', 1)
    s, n = re.subn(r'data-tel="\{\{ \$c->telefono \}\}">Ver</span>', 'data-tel="{{ $c->telefono }}" data-canal="{{ $c->canal ?? \'whatsapp\' }}">Ver</span>', s, count=1)
    if n != 1:
        sys.exit(q + ': no encontré el enlace Ver')
    c = "document.getElementById('aprModalWa').href = 'https://wa.me/' + (el.dataset.tel || '');"
    if c not in s:
        sys.exit(q + ': no encontré el enlace a WhatsApp del visor')
    s = s.replace(c, """var esIg = el.dataset.canal === 'instagram', lk = document.getElementById('aprModalWa');
        lk.href = esIg ? 'https://www.instagram.com/direct/inbox/' : 'https://wa.me/' + (el.dataset.tel || '');
        lk.textContent = esIg ? 'Instagram' : 'WhatsApp'; lk.style.color = esIg ? '#9b1b52' : '#128c7e';""", 1)
    d = "    var modal = document.getElementById('aprModal'), chat = document.getElementById('aprChat');"
    if d not in s:
        sys.exit(q + ': no encontré el script del visor')
    s = s.replace(d, JS_FILTRO + d, 1)
    return s

editar('routes/web.php', rutas)
editar('app/Services/BotAprendizajeService.php', servicio)
editar('resources/views/themes/backoffice/pages/bot-conversaciones/index.blade.php', vista)
print('Gestor de conversaciones: ' + (', '.join(hechos) if hechos else 'ya estaba'))
