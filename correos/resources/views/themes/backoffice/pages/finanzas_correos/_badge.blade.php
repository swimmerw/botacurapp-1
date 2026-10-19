{{-- Marca "llegó al banco" para una venta o comprobante. Recibe $b (fila de fin_correos) o null. --}}
@if(!empty($b))
    @php
        $okB = $b->estado === 'calzado' && ($b->seguridad ?? '') === 'seguro';
        $txtB = ($okB ? 'En el banco ' : '¿En el banco? ') . '$' . number_format((int) $b->monto, 0, ',', '.')
            . ($b->fecha ? ' · ' . \Carbon\Carbon::parse($b->fecha)->format('d/m') : '') . ($b->hora ? ' ' . $b->hora : '')
            . ($b->banco ? ' · ' . $b->banco : '');
        $tipB = trim(($b->nombre ?? '') . ' · ' . ($b->nota ?? '') . ' · ' . ($b->seguridad_detalle ?? ''), ' ·');
        $verB = \App\Http\Controllers\FinanzasCorreoController::puedeVer();
    @endphp
    <span style="display:block;font-size:.75rem" class="{{ $okB ? 'green-text text-darken-2' : 'orange-text text-darken-2' }}" title="{{ $tipB }}">
        <i class="material-icons" style="font-size:.9rem;vertical-align:middle">{{ $okB ? 'verified_user' : 'help' }}</i>
        @if($verB)<a class="{{ $okB ? 'green-text text-darken-2' : 'orange-text text-darken-2' }}" href="{{ route('backoffice.finanzas.correos.show', $b->id) }}">{{ $txtB }}</a>@else{{ $txtB }}@endif
    </span>
@endif
