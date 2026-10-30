{{-- Editar la diferencia de una venta (solo administradores).
     Antes del cierre: diferencia por pagar (la que usa el cierre y la boleta).
     Después del cierre: diferencia cobrada y su medio de pago. --}}
@if(Auth::user()->is_admin() && !is_null($reserva->venta) && !$reserva->venta->tiene_saldo_a_favor)
@php
    $difCerrada = !is_null($reserva->venta->diferencia_programa);
    $difActual  = $difCerrada ? $reserva->venta->diferencia_programa : $reserva->venta->total_pagar;
    $tiposDif = \App\TipoTransaccion::orderBy('nombre')->get();
    $cambiosDif = \Illuminate\Support\Facades\Schema::hasTable('venta_diferencia_cambios')
        ? \Illuminate\Support\Facades\DB::table('venta_diferencia_cambios as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.id_user')
            ->where('c.id_venta', $reserva->venta->id)
            ->orderByDesc('c.id')->limit(5)
            ->get(['c.*', 'u.name as usuario'])
        : collect();
@endphp
<div id="modalEditarDiferencia" class="modal">
    <form method="POST" action="{{ route('backoffice.venta.diferencia.update', $reserva->venta) }}">
        @csrf
        <input type="hidden" name="ir_a_cierre" id="dif_ir_a_cierre" value="0">
        <div class="modal-content">
            <h5>{{ $difCerrada ? 'Editar diferencia cobrada' : 'Corregir diferencia por pagar' }}</h5>
            <p class="grey-text">
                Monto actual: ${{ number_format($difActual, 0, '', '.') }}
                @if($difCerrada && optional($reserva->venta->tipoTransaccionDiferencia)->nombre)
                    · {{ $reserva->venta->tipoTransaccionDiferencia->nombre }}
                @endif
                @if(!$difCerrada)
                    <br>La venta aún no se cierra: el cierre y la boleta usarán el monto nuevo.
                @endif
            </p>

            <div class="input-field">
                <input id="dif_monto" name="diferencia" type="text" inputmode="numeric" required
                       value="{{ old('diferencia', $difActual) }}">
                <label for="dif_monto" class="active">{{ $difCerrada ? 'Nuevo monto de la diferencia' : 'Diferencia por pagar correcta' }}</label>
            </div>

            @if($difCerrada)
                <label>Medio de pago</label>
                <select name="id_tipo_transaccion_diferencia" class="browser-default">
                    @foreach($tiposDif as $t)
                        <option value="{{ $t->id }}" {{ (int) $reserva->venta->id_tipo_transaccion_diferencia === (int) $t->id ? 'selected' : '' }}>{{ $t->nombre }}</option>
                    @endforeach
                </select>
            @endif

            <div class="input-field" style="margin-top:24px">
                <input id="dif_motivo" name="motivo" type="text" maxlength="255" value="{{ old('motivo') }}">
                <label for="dif_motivo">Motivo (opcional)</label>
            </div>

            @if($cambiosDif->isNotEmpty())
                <p style="margin-top:16px"><strong>Cambios anteriores</strong></p>
                <ul class="collection">
                    @foreach($cambiosDif as $c)
                        <li class="collection-item" style="font-size:13px">
                            {{ \Carbon\Carbon::parse($c->created_at)->format('d-m-Y H:i') }} · {{ $c->usuario ?? 'Usuario '.$c->id_user }}:
                            ${{ number_format($c->monto_anterior, 0, '', '.') }} → ${{ number_format($c->monto_nuevo, 0, '', '.') }}
                            @if(($c->campo ?? '') === 'total_pagar') <span class="grey-text">(antes del cierre)</span> @endif
                            @if($c->motivo) <span class="grey-text">· {{ $c->motivo }}</span> @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="modal-footer">
            <a href="#!" class="modal-close btn-flat">Cancelar</a>
            <button type="submit" class="btn">Guardar</button>
        </div>
    </form>
</div>
<script>
    // Llega con #editar-diferencia (cierre) o #editar-diferencia-consumo (Gestión consumo): abre el modal.
    window.addEventListener('load', function () {
        var h = window.location.hash;
        if ((h === '#editar-diferencia' || h === '#editar-diferencia-consumo') && window.jQuery) {
            // desde el cierre vuelve al cierre; desde Gestión consumo vuelve a Gestión consumo
            document.getElementById('dif_ir_a_cierre').value = h === '#editar-diferencia' ? '1' : 'consumo';
            jQuery('#modalEditarDiferencia').modal('open');
        }
    });
</script>
@endif
