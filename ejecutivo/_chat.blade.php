@if ($it->conversacion && count($it->conversacion->historial))
    <details>
        <summary>Ver conversación con el bot (últimos {{ count($it->conversacion->historial) }} mensajes)</summary>
        <div class="chat">
            @foreach ($it->conversacion->historial as $h)
                <p class="{{ $h['de'] === 'Bot' ? 'bot' : '' }}"><b>{{ $h['de'] }}</b>{{ \Illuminate\Support\Str::limit($h['texto'], 700) }}</p>
            @endforeach
        </div>
    </details>
@endif
