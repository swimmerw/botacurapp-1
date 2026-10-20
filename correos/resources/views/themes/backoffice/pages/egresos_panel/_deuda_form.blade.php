@php $f = $f ?? null; @endphp
<form method="POST" action="{{ $accion }}" class="form-l">@csrf
    <input type="text" name="nombre" class="campo" style="flex:1 1 220px" value="{{ $f->nombre ?? '' }}" placeholder="Nombre (ej. CGE · boleta octubre)" required aria-label="Nombre">
    <select name="tipo" class="browser-default" aria-label="Tipo">
        @foreach(['cuenta' => 'Cuenta', 'deuda' => 'Deuda', 'credito' => 'Crédito en cuotas'] as $k => $v)<option value="{{ $k }}" {{ ($f->tipo ?? 'deuda') === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach
    </select>
    <input type="text" name="monto_total" class="campo" style="width:130px" value="{{ $f->monto_total ?? '' }}" placeholder="Monto total" required aria-label="Monto total" inputmode="numeric">
    <input type="text" name="cuota" class="campo" style="width:110px" value="{{ $f->cuota ?? '' }}" placeholder="Cuota" aria-label="Cuota" inputmode="numeric">
    <input type="date" name="vencimiento" class="campo" value="{{ $f->vencimiento ?? '' }}" aria-label="Vencimiento">
    <input type="text" name="acreedor" class="campo" value="{{ $f->acreedor ?? '' }}" placeholder="A quién" aria-label="Acreedor">
    <input type="text" name="rut" class="campo" style="width:130px" value="{{ $f->rut ?? '' }}" placeholder="RUT" aria-label="RUT">
    <input type="text" name="patron" class="campo" style="flex:1 1 220px" value="{{ $f->patron ?? '' }}" placeholder="Palabras para reconocer el pago en el banco" aria-label="Palabras del banco">
    <select name="subcategoria_id" class="browser-default" aria-label="Categoría">
        <option value="">Categoría…</option>
        @foreach($subcats as $sid => $sc)<option value="{{ $sid }}" {{ ($f->subcategoria_id ?? null) == $sid ? 'selected' : '' }}>{{ $sc['cat'] }} › {{ $sc['sub'] }}</option>@endforeach
    </select>
    @if(\App\Http\Controllers\FinanzasCorreoController::puedeVerPersonal())
    <select name="ambito" class="browser-default" aria-label="De quién">
        <option value="botacura" {{ ($f->ambito ?? 'botacura') === 'botacura' ? 'selected' : '' }}>Botacura</option>
        <option value="personal" {{ ($f->ambito ?? '') === 'personal' ? 'selected' : '' }}>Personal</option>
    </select>
    @endif
    <input type="text" name="nota" class="campo" style="flex:1 1 220px" value="{{ $f->nota ?? '' }}" placeholder="Nota" aria-label="Nota">
    <button class="btn-c">{{ $f ? 'Guardar' : 'Agregar' }}</button>
</form>
