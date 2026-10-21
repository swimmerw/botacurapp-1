import re, sys
hechos = []
# 1) Webhook: un chat apagado no recibe respuesta del bot (igual que cuando el equipo lo atiende)
p = 'app/Http/Controllers/Api/WhatsAppWebhookController.php'
s = open(p, encoding='utf-8').read()
if 'bot_chats_pausados' not in s:
    a = "            if ($convHumano || \\App\\BotSeccionTexto::where('clave', 'bot_activo')->value('contenido') === '0') {"
    if a not in s: sys.exit('Webhook: no encontré el chequeo de bot apagado')
    s = s.replace(a, """            $convPausado = \\Illuminate\\Support\\Facades\\Schema::hasTable('bot_chats_pausados')
                && DB::table('bot_chats_pausados')->where('telefono', preg_replace('/\\D/', '', $telefono))->exists();
            $convHumano = $convHumano || $convPausado;
""" + a, 1)
    open(p, 'w', encoding='utf-8').write(s); hechos.append('webhook')
# 2) Rutas
p = 'routes/web.php'
s = open(p, encoding='utf-8').read()
if 'BotChatPausaController' not in s:
    a = "Route::get('bot-conversaciones', 'BotConversacionesController@index')->name('bot-conversaciones.index');"
    if a not in s: sys.exit('Rutas: no encontré bot-conversaciones')
    s = s.replace(a, a + "\n    Route::post('bot-conversaciones/chat-bot', 'BotChatPausaController@cambiar')->name('bot-conversaciones.chat_bot');", 1)
    open(p, 'w', encoding='utf-8').write(s); hechos.append('rutas')
# 3) Vista: columna Bot con encender/apagar por conversación + apagar un número a mano
p = 'resources/views/themes/backoffice/pages/bot-conversaciones/index.blade.php'
s = open(p, encoding='utf-8').read()
if 'chat_bot' not in s:
    a = """    <div class="apr-box" style="overflow-x:auto;">
        <table class="apr-tabla">
            <thead><tr><th>Fecha</th><th>Cliente</th><th>Llegó a</th><th>Estado</th><th>Mensajes</th><th></th></tr></thead>"""
    if a not in s: sys.exit('Vista: no encontré la tabla de conversaciones')
    nuevo = """    @php $pausados = \\Illuminate\\Support\\Facades\\Schema::hasTable('bot_chats_pausados') ? \\Illuminate\\Support\\Facades\\DB::table('bot_chats_pausados')->orderByDesc('created_at')->get()->keyBy('telefono') : collect(); @endphp
    @if(session('success'))<div class="apr-box" style="border-color:#c8e6d6;background:#eef7f2;color:#1b5e45">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="apr-box" style="border-color:#f1dfba;background:#fff8ec;color:#6b4a10">{{ session('error') }}</div>@endif
    <div class="apr-box" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
        <div><strong>Bot por conversación</strong><br><small style="color:#64748b">Apagado en {{ $pausados->count() }} chat(s). En un chat apagado el bot no responde y los mensajes quedan en espera para el equipo; el resto de WhatsApp sigue igual.</small>
            @if($pausados->count())<br><small style="color:#64748b">@foreach($pausados as $pz)+{{ $pz->telefono }}{{ $pz->nombre ? ' (' . $pz->nombre . ')' : '' }}@if(!$loop->last) · @endif @endforeach</small>@endif</div>
        <form method="POST" action="{{ route('backoffice.bot-conversaciones.chat_bot') }}" style="display:flex;gap:8px;align-items:center">@csrf
            <input type="hidden" name="accion" value="apagar">
            <input type="text" name="telefono" placeholder="+56 9 …" inputmode="tel" required style="height:36px;border:1px solid #d0d0d0;border-radius:10px;padding:0 10px;width:150px;margin:0">
            <button class="apr-bot-btn off">Apagar bot en este número</button>
        </form>
    </div>
    <style>
        .apr-bot-btn { border:1px solid #cbd5e1; background:#fff; border-radius:999px; padding:5px 12px; font-size:.8rem; cursor:pointer; white-space:nowrap; }
        .apr-bot-btn.on { border-color:#0e5f49; background:#0e5f49; color:#fff; }
        .apr-bot-btn.off { border-color:#a33a12; color:#a33a12; }
        .apr-bot-est { display:inline-block; font-size:.72rem; font-weight:700; padding:2px 8px; border-radius:999px; margin-bottom:4px; }
        .apr-bot-est.si { background:#eaf4ef; color:#0e5f49; } .apr-bot-est.no { background:#fdecea; color:#a33a12; }
    </style>
    <div class="apr-box" style="overflow-x:auto;">
        <table class="apr-tabla">
            <thead><tr><th>Fecha</th><th>Cliente</th><th>Llegó a</th><th>Estado</th><th>Mensajes</th><th>Bot</th><th></th></tr></thead>"""
    s = s.replace(a, nuevo, 1)
    a2 = """                    <td>{{ $c->turnos }}</td>
                    <td><span class="apr-link" data-conv="{{ $c->id }}" data-tel="{{ $c->telefono }}">Ver</span></td>"""
    if a2 not in s: sys.exit('Vista: no encontré la fila de conversación')
    s = s.replace(a2, """                    <td>{{ $c->turnos }}</td>
                    <td>@php $telC = preg_replace('/\\D/', '', (string) $c->telefono); $apag = $pausados->has($telC); @endphp
                        <span class="apr-bot-est {{ $apag ? 'no' : 'si' }}">{{ $apag ? 'Apagado' : 'Activo' }}</span><br>
                        <form method="POST" action="{{ route('backoffice.bot-conversaciones.chat_bot') }}" style="display:inline">@csrf
                            <input type="hidden" name="telefono" value="{{ $telC }}"><input type="hidden" name="nombre" value="{{ $c->nombre }}">
                            <input type="hidden" name="accion" value="{{ $apag ? 'encender' : 'apagar' }}">
                            <button class="apr-bot-btn {{ $apag ? 'on' : 'off' }}">{{ $apag ? 'Encender' : 'Apagar' }}</button>
                        </form></td>
                    <td><span class="apr-link" data-conv="{{ $c->id }}" data-tel="{{ $c->telefono }}">Ver</span></td>""", 1)
    s = s.replace('<tr><td colspan="6" style="color:#64748b">No hay conversaciones', '<tr><td colspan="7" style="color:#64748b">No hay conversaciones', 1)
    open(p, 'w', encoding='utf-8').write(s); hechos.append('vista')
print('Bot por conversación: ' + (', '.join(hechos) if hechos else 'ya estaba'))
