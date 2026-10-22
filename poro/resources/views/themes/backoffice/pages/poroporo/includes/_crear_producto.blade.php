{{-- Botones "Crear producto" (modal) e "Ir a productos" — solo administradores --}}
@if(Auth::user()->has_role(config('app.admin_role')))
<div class="poro-acciones" style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;margin:0 0 12px">
    <a href="#modal-crear-poro" class="btn waves-effect modal-trigger" style="background:#039B7B"><i class="material-icons left">add</i>Crear producto</a>
    <a href="{{ route('backoffice.poroporo.index') }}" class="btn-flat waves-effect" style="border:1px solid #039B7B;color:#039B7B"><i class="material-icons left">edit</i>Ver y editar productos</a>
</div>

<div id="modal-crear-poro" class="modal" style="max-width:520px">
    <form method="post" action="{{ route('backoffice.poroporo.store') }}">
        @csrf
        <div class="modal-content">
            <h5 style="margin-top:0">Nuevo producto Poro Poro</h5>
            <div class="row" style="margin-bottom:0">
                <div class="input-field col s12">
                    <input id="cp-nombre" type="text" name="nombre" value="{{ old('nombre') }}" maxlength="255" required>
                    <label for="cp-nombre" class="{{ old('nombre') ? 'active' : '' }}">Nombre</label>
                    @error('nombre')<span style="color:#c62828;font-size:.85rem">{{ $message }}</span>@enderror
                </div>
                <div class="input-field col s12 m6">
                    <input id="cp-valor" type="number" name="valor" min="0" step="1" value="{{ old('valor') }}" required>
                    <label for="cp-valor" class="{{ old('valor') !== null ? 'active' : '' }}">Valor ($, sin puntos)</label>
                    @error('valor')<span style="color:#c62828;font-size:.85rem">{{ $message }}</span>@enderror
                </div>
                <div class="input-field col s12">
                    <textarea id="cp-descripcion" name="descripcion" class="materialize-textarea" maxlength="1500">{{ old('descripcion') }}</textarea>
                    <label for="cp-descripcion" class="{{ old('descripcion') ? 'active' : '' }}">Descripción (opcional)</label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <a href="#!" class="modal-close modal-action btn-flat">Cancelar</a>
            <button type="submit" class="btn waves-effect" style="background:#039B7B">Guardar</button>
        </div>
    </form>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && jQuery.fn.modal) {
            jQuery('#modal-crear-poro').modal();
            @if($errors->has('nombre') || $errors->has('valor') || $errors->has('descripcion'))
                jQuery('#modal-crear-poro').modal('open');
            @endif
        }
    });
</script>
@endif
