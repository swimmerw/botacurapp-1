{{-- Abonos que avisó el banco este día (correos), con su reserva. Recibe $bancoDia. --}}
@if(!empty($bancoDia) && $bancoDia->isNotEmpty())
    @php $fB = function ($n) { return '$' . number_format((int) $n, 0, ',', '.'); }; $verB = \App\Http\Controllers\FinanzasCorreoController::puedeVer(); @endphp
    <p style="font-weight:700;margin:20px 0 8px;color:#00695C">
        Abonos avisados por el banco este día ({{ $bancoDia->count() }} · {{ $fB($bancoDia->sum('monto')) }})
        <span class="grey-text" style="font-weight:400;font-size:.8rem">· {{ $bancoDia->where('estado', 'calzado')->count() }} con su reserva, {{ $bancoDia->where('estado', '!=', 'calzado')->count() }} por revisar</span>
    </p>
    <table class="striped" style="font-size:.85rem">
        <thead><tr><th>Hora</th><th>De</th><th>Banco</th><th class="right-align">Monto</th><th>Reserva</th><th>Estado</th></tr></thead>
        <tbody>
        @foreach($bancoDia as $x)
            <tr>
                <td>{{ $x->hora }}</td>
                <td>{{ $x->nombre }}@if($x->comentario)<span class="grey-text" style="display:block;font-size:.75rem">{{ $x->comentario }}</span>@endif</td>
                <td>@if(($x->seguridad ?? '') === 'seguro')<i class="material-icons green-text" style="font-size:.9rem;vertical-align:middle" title="Verificado: firma del banco">verified_user</i>@endif {{ $x->banco }}</td>
                <td class="right-align">{{ $fB($x->monto) }}</td>
                <td>@if($x->reserva_id)<a href="{{ route('backoffice.reserva.show', $x->reserva_id) }}">N° {{ $x->reserva_id }}</a> <span class="grey-text">{{ $x->campo }}</span>@elseif($x->comprobante_id)Comprobante del bot @endif</td>
                <td>
                    @if($x->estado === 'calzado')<span class="green-text">calzado</span>@else<span class="orange-text">{{ $x->estado }}</span>@endif
                    @if($verB) · <a href="{{ route('backoffice.finanzas.correos.show', $x->id) }}">ver</a>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
