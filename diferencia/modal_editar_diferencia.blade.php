{{-- Editar diferencia de una venta cerrada (solo administradores) --}}
@if(Auth::user()->is_admin() && !is_null($reserva->venta) && !is_null($reserva->venta->diferencia_programa))
@php
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
        <div class="modal-content">
            <h5>Editar diferencia</h5>
            <p class="grey-text">Monto actual: ${{ number_format($reserva->venta->diferencia_programa, 0, '', '.') }}
                {{ optional($reserva->venta->tipoTransaccionDiferencia)->nombre ? '· '.$reserva->venta->tipoTransaccionDiferencia->nombre : '' }}</p>

            <div class="input-field">
                <input id="dif_monto" name="diferencia" type="text" inputmode="numeric" required
                       value="{{ old('diferencia', $reserva->venta->diferencia_programa) }}">
                <label for="dif_monto" class="active">Nuevo monto de la diferencia</label>
            </div>

            <label>Medio de pago</label>
            <select name="id_tipo_transaccion_diferencia" class="browser-default">
                @foreach($tiposDif as $t)
                    <option value="{{ $t->id }}" {{ (int) $reserva->venta->id_tipo_transaccion_diferencia === (int) $t->id ? 'selected' : '' }}>{{ $t->nombre }}</option>
                @endforeach
            </select>

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
                            @if($c->motivo) <span class="grey-text">({{ $c->motivo }})</span> @endif
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
@endif
