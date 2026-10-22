@php
    $poroLista = \App\PoroPoro::withCount('detalleVentas')->orderBy('nombre')->get();
    $poroPuedeEditar = Auth::user()->has_role(config('app.admin_role')) || Auth::user()->has_role(config('app.jefe_local_role'));
@endphp
<div class="card-panel poro-editor" id="poro-editor">
    <style>
        .poro-editor h5{margin:0 0 12px;font-size:1.3rem}
        .poro-editor table td{padding:6px 4px;vertical-align:middle}
        .poro-editor input.pe-in{margin:0;height:2.2rem}
        .poro-editor .pe-valor{max-width:110px}
        .poro-editor .pe-nuevo{background:#f3faf7;border-radius:8px;padding:8px 12px 2px;margin-bottom:12px}
        .poro-editor .pe-msg{min-height:20px;font-size:.9rem}
        .poro-editor .pe-sucio{background:#FFFDE7}
    </style>
    <h5><i class="material-icons left" style="color:#039B7B">local_florist</i>Productos Poro Poro <span class="grey-text" style="font-size:.9rem">({{ $poroLista->count() }})</span></h5>

    @if($poroPuedeEditar)
    <div class="pe-nuevo row" style="margin-left:0;margin-right:0">
        <div class="col s12 m5"><input class="pe-in" id="pe-nuevo-nombre" type="text" placeholder="Nombre del producto nuevo" maxlength="255"></div>
        <div class="col s6 m3"><input class="pe-in" id="pe-nuevo-valor" type="number" min="0" step="1" placeholder="Valor $"></div>
        <div class="col s6 m4"><button type="button" class="btn waves-effect" id="pe-crear" style="background:#039B7B;width:100%"><i class="material-icons left">add</i>Agregar</button></div>
    </div>
    @endif

    <div class="pe-msg" id="pe-msg"></div>
    <table class="highlight">
        <thead><tr><th>Producto</th><th>Valor</th>@if($poroPuedeEditar)<th style="width:96px"></th>@endif</tr></thead>
        <tbody id="pe-lista">
        @forelse($poroLista as $p)
            <tr data-id="{{ $p->id }}" data-ventas="{{ $p->detalle_ventas_count }}">
                @if($poroPuedeEditar)
                    <td><input class="pe-in pe-nombre" type="text" value="{{ $p->nombre }}" maxlength="255"></td>
                    <td><input class="pe-in pe-valor" type="number" min="0" step="1" value="{{ (int) $p->valor }}"></td>
                    <td>
                        <a href="#!" class="btn-floating btn-small waves-effect pe-guardar" title="Guardar cambios" style="background:#039B7B"><i class="material-icons">save</i></a>
                        <a href="#!" class="btn-floating btn-small waves-effect red pe-borrar" title="Eliminar"><i class="material-icons">delete</i></a>
                    </td>
                @else
                    <td>{{ $p->nombre }}</td><td>${{ number_format($p->valor, 0, '', '.') }}</td>
                @endif
            </tr>
        @empty
            <tr class="pe-vacio"><td colspan="3">No hay productos todavía.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($poroPuedeEditar)
<script>
(function () {
    const base = @json(route('backoffice.poroporo_productos.store'));
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const msg = document.getElementById('pe-msg');
    const lista = document.getElementById('pe-lista');
    function aviso(t, ok) { msg.textContent = t; msg.style.color = ok ? '#039B7B' : '#c62828'; if (ok) setTimeout(function () { if (msg.textContent === t) msg.textContent = ''; }, 3000); }
    async function pedir(url, metodo, datos) {
        const r = await fetch(url, { method: metodo, headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: datos ? JSON.stringify(datos) : null });
        let j = {}; try { j = await r.json(); } catch (e) {}
        if (!r.ok) { const errs = j.errors ? Object.values(j.errors).flat().join(' ') : (j.mensaje || j.message || 'No se pudo guardar.'); throw new Error(errs); }
        return j;
    }
    function fila(p) {
        const tr = document.createElement('tr');
        tr.dataset.id = p.id; tr.dataset.ventas = p.ventas || 0;
        tr.innerHTML = '<td><input class="pe-in pe-nombre" type="text" maxlength="255"></td><td><input class="pe-in pe-valor" type="number" min="0" step="1"></td>'
            + '<td><a href="#!" class="btn-floating btn-small waves-effect pe-guardar" title="Guardar cambios" style="background:#039B7B"><i class="material-icons">save</i></a> '
            + '<a href="#!" class="btn-floating btn-small waves-effect red pe-borrar" title="Eliminar"><i class="material-icons">delete</i></a></td>';
        tr.querySelector('.pe-nombre').value = p.nombre; tr.querySelector('.pe-valor').value = p.valor;
        return tr;
    }
    document.getElementById('pe-crear').addEventListener('click', async function () {
        const n = document.getElementById('pe-nuevo-nombre'), v = document.getElementById('pe-nuevo-valor');
        if (!n.value.trim() || v.value === '') return aviso('Escribe el nombre y el valor.', false);
        this.disabled = true;
        try {
            const j = await pedir(base, 'POST', { nombre: n.value.trim(), valor: parseInt(v.value, 10) });
            const vacio = lista.querySelector('.pe-vacio'); if (vacio) vacio.remove();
            lista.prepend(fila(j.producto)); n.value = ''; v.value = ''; aviso('Producto creado: ' + j.producto.nombre, true);
        } catch (e) { aviso(e.message, false); }
        this.disabled = false;
    });
    lista.addEventListener('input', function (e) { const tr = e.target.closest('tr'); if (tr) tr.classList.add('pe-sucio'); });
    lista.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.classList.contains('pe-in')) { e.preventDefault(); const b = e.target.closest('tr').querySelector('.pe-guardar'); if (b) b.click(); } });
    lista.addEventListener('click', async function (e) {
        const g = e.target.closest('.pe-guardar'), b = e.target.closest('.pe-borrar');
        if (!g && !b) return; e.preventDefault();
        const tr = e.target.closest('tr'), id = tr.dataset.id;
        if (g) {
            const nombre = tr.querySelector('.pe-nombre').value.trim(), valor = tr.querySelector('.pe-valor').value;
            if (!nombre || valor === '') return aviso('Escribe el nombre y el valor.', false);
            try { await pedir(base + '/' + id, 'PATCH', { nombre: nombre, valor: parseInt(valor, 10) }); tr.classList.remove('pe-sucio'); aviso('Guardado: ' + nombre, true); }
            catch (err) { aviso(err.message, false); }
        } else {
            const nombre = tr.querySelector('.pe-nombre').value;
            if (parseInt(tr.dataset.ventas || '0', 10) > 0) return aviso('"' + nombre + '" tiene ventas registradas: no se puede eliminar, solo editar.', false);
            const ok = window.Swal ? (await Swal.fire({ title: '¿Eliminar "' + nombre + '"?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' })).isConfirmed : false;
            if (!ok) return;
            try { await pedir(base + '/' + id, 'DELETE'); tr.remove(); aviso('Eliminado: ' + nombre, true); } catch (err) { aviso(err.message, false); }
        }
    });
})();
</script>
@endif
