@extends('themes.backoffice.layouts.admin')

@section('title', 'Conversaciones del bot')

@section('content')
@php
    $anterior = $fecha->copy()->subDay()->toDateString();
    $siguiente = $fecha->copy()->addDay()->toDateString();
    $nombreDe = function ($c) use ($tieneNombre) { return ($tieneNombre && !empty($c->nombre_cliente)) ? $c->nombre_cliente : 'Sin nombre'; };
@endphp
<style>
    .bc-lista a.bc-item { display:block; padding:10px 12px; border-bottom:1px solid #eee; color:#333; }
    .bc-lista a.bc-item:hover { background:#f5f5f5; }
    .bc-lista a.bc-item.on { background:#ede7f6; border-left:3px solid #6A1B9A; }
    .bc-chat { background:#efeae2; border-radius:8px; padding:14px; max-height:70vh; overflow-y:auto; }
    .bc-msg { max-width:78%; padding:8px 11px; border-radius:8px; margin:6px 0; white-space:pre-wrap; font-size:.88rem; line-height:1.35; box-shadow:0 1px 1px rgba(0,0,0,.08); }
    .bc-cliente { background:#fff; margin-right:auto; }
    .bc-bot { background:#d9fdd3; margin-left:auto; }
    .bc-equipo { background:#cfe8fc; margin-left:auto; }
    .bc-sistema { background:#fff8e1; color:#795548; font-size:.78rem; margin:6px auto; max-width:92%; }
    .bc-quien { display:block; font-size:.7rem; font-weight:700; opacity:.6; margin-bottom:2px; text-transform:uppercase; }
</style>
<div class="section">
    <div class="row valign-wrapper" style="margin-bottom:8px">
        <div class="col s12 m6">
            <h5 class="grey-text text-darken-2" style="margin:0">
                <i class="material-icons left" style="font-size:1.5rem">forum</i> Conversaciones del bot
            </h5>
        </div>
        <div class="col s12 m6" style="text-align:right">
            <form method="GET" action="{{ route('backoffice.bot-conversaciones.index') }}" style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                <a class="btn-flat waves-effect" href="{{ route('backoffice.bot-conversaciones.index', ['fecha' => $anterior]) }}" title="Día anterior"><i class="material-icons">chevron_left</i></a>
                <input type="date" name="fecha" value="{{ $fecha->toDateString() }}" class="browser-default" style="border:1px solid #ccc;border-radius:4px;padding:4px 8px" onchange="this.form.q.value='';this.form.submit()">
                <a class="btn-flat waves-effect" href="{{ route('backoffice.bot-conversaciones.index', ['fecha' => $siguiente]) }}" title="Día siguiente"><i class="material-icons">chevron_right</i></a>
                <input type="text" name="q" value="{{ $buscar }}" placeholder="Buscar nombre o teléfono" class="browser-default" style="border:1px solid #ccc;border-radius:4px;padding:4px 8px;width:190px">
                <button class="btn-flat waves-effect" type="submit"><i class="material-icons">search</i></button>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col s12 m4">
            <div class="card" style="border-radius:8px">
                <div class="card-content" style="padding:0">
                    <p style="padding:12px;margin:0;font-weight:700;color:#6A1B9A;border-bottom:1px solid #eee">
                        {{ $buscar !== '' ? 'Resultados para "' . $buscar . '"' : 'Con movimiento el ' . $fecha->format('d/m/Y') }} ({{ $conversaciones->count() }})
                    </p>
                    <div class="bc-lista" style="max-height:72vh;overflow-y:auto">
                        @forelse($conversaciones as $c)
                            <a class="bc-item {{ $actual && $actual->id == $c->id ? 'on' : '' }}"
                               href="{{ route('backoffice.bot-conversaciones.index', array_filter(['fecha' => $fecha->toDateString(), 'q' => $buscar, 'id' => $c->id])) }}">
                                <strong>{{ $nombreDe($c) }}</strong>
                                @if($c->con_equipo)<span class="new badge blue" data-badge-caption="equipo"></span>@endif
                                <span class="grey-text" style="display:block;font-size:.75rem">+{{ $c->usuario_id }} · {{ \Carbon\Carbon::parse($c->updated_at)->format('d/m H:i') }} · {{ $c->total_mensajes }} mensajes</span>
                                <span class="grey-text text-darken-1" style="display:block;font-size:.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $c->ultimo }}</span>
                            </a>
                        @empty
                            <p class="grey-text" style="padding:12px">No hay conversaciones.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col s12 m8">
            @if($actual)
                <div class="card" style="border-radius:8px">
                    <div class="card-content" style="padding:14px 16px">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:6px">
                            <div>
                                <strong style="font-size:1.05rem">{{ $nombreDe($actual) }}</strong>
                                <span class="grey-text" style="font-size:.85rem"> +{{ $actual->usuario_id }} · {{ ucfirst($actual->canal ?? 'whatsapp') }}</span>
                                @if($actual->con_equipo)
                                    <span class="blue-text" style="font-size:.8rem;display:block">Atendida por el equipo hasta {{ \Carbon\Carbon::parse($actual->humano_hasta)->format('d/m H:i') }} (el bot no responde)</span>
                                @endif
                            </div>
                            <a class="btn-small green waves-effect" target="_blank" href="https://wa.me/{{ preg_replace('/\D/', '', $actual->usuario_id) }}"><i class="material-icons left">chat</i>Abrir en WhatsApp</a>
                        </div>
                        <div class="bc-chat" id="bc-chat">
                            @forelse($mensajes as $m)
                                <div class="bc-msg bc-{{ $m['quien'] }}"><span class="bc-quien">{{ ['cliente' => 'Cliente', 'bot' => 'Bot', 'equipo' => 'Equipo', 'sistema' => 'Sistema'][$m['quien']] }}</span>{{ $m['texto'] }}</div>
                            @empty
                                <p class="grey-text center-align">Sin mensajes guardados.</p>
                            @endforelse
                        </div>
                        <p class="grey-text" style="font-size:.75rem;margin:6px 0 0">Se guardan los últimos mensajes de cada conversación (no el chat completo de WhatsApp).</p>
                    </div>
                </div>
            @else
                <div class="card-panel grey lighten-4 grey-text center-align" style="padding:40px">Elige una conversación de la lista para ver el chat.</div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('foot')
<script>
    (function () { var c = document.getElementById('bc-chat'); if (c) { c.scrollTop = c.scrollHeight; } })();
</script>
@endsection
