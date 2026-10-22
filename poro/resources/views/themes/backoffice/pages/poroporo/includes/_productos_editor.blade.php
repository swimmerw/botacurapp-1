@php
    $poroLista = \App\PoroPoro::withCount('detalleVentas')->orderBy('nombre')->get();
    $poroPuedeEditar = Auth::user()->has_role(config('app.admin_role'));
@endphp
<div class="card-panel poro-editor" id="poro-editor">
    <style>
        .poro-editor h5{margin:0 0 12px;font-size:1.3rem}
        .poro-editor table td{padding:6px 4px;vertical-align:middle}
        .poro-editor input.pe-in{margin:0;height:2.2rem}
        .poro-editor .pe-valor{max-width:120px}
        .poro-editor .pe-msg{min-height:20px;font-size:.9rem}
        .poro-editor .pe-sucio{background:#FFFDE7}
    </style>
    <h5><i class="material-icons left" style="color:#039B7B">local_florist</i>Productos Poro Poro <span class="grey-text" style="font-size:.9rem">({{ $poroLista->count() }})</span></h5>
    @if($poroPuedeEditar)<p class="grey-text" style="margin-top:0">Cambia el nombre o el valor y guarda con 💾 o Enter.</p>@endif
    <div class="pe-msg" id="pe-msg"></div>
    <table class="highlight">
        <thead><tr><th>Producto</th><th>Valor</th>@if($poroPuedeEditar)<th>Ventas</th><th style="width:96px"></th>@endif</tr></thead>
        <tbody id="pe-lista">
        @forelse($poroLista as $p)
            <tr data-id="{{ $p->id }}" data-ventas="{{ $p->detalle_ventas_count }}">
                @if($poroPuedeEditar)
                    <td><input class="pe-in pe-nombre" type="text" value="{{ $p->nombre }}" maxlength="255"></td>
                    <td><input class="pe-in pe-valor" type="number" min="0" step="1" value="{{ (int) $p->valor }}"></td>
                    <td class="grey-text">{{ $p->detalle_ventas_count }}</td>
                    <td>
                        <a href="#!" class="btn-floating btn-small waves-effect pe-guardar" title="Guardar cambios" style="background:#039B7B"><i class="material-icons">save</i></a>
                        @if($p->detalle_ventas_count == 0)
                        <a href="#!" class="btn-floating btn-small waves-effect red pe-borrar" title="Eliminar"><i class="material-icons">delete</i></a>
                        @endif
                    </td>
                @else
                    <td>{{ $p->nombre }}</td><td>${{ number_format($p->valor, 0, '', '.') }}</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="4">No hay productos todavía.</td></tr>
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
        if (!r.ok) throw new Error(j.errors ? Object.values(j.errors).flat().join(' ') : (j.mensaje || j.message || 'No se pudo guardar.'));
        return j;
    }
    lista.addEventListener('input', function (e) { const tr = e.target.closest('tr'); if (tr) tr.classList.add('pe-sucio'); });
    lista.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.classList.contains('pe-in')) { e.preventDefault(); e.target.closest('tr').querySelector('.pe-guardar').click(); } });
    lista.addEventListener('click', async function (e) {
        const g = e.target.closest('.pe-guardar'), b = e.target.closest('.pe-borrar');
        if (!g && !b) return; e.preventDefault();
        const tr = e.target.closest('tr'), id = tr.dataset.id, nombre = tr.querySelector('.pe-nombre').value.trim();
        if (g) {
            const valor = tr.querySelector('.pe-valor').value;
            if (!nombre || valor === '') return aviso('Escribe el nombre y el valor.', false);
            try { await pedir(base + '/' + id, 'PATCH', { nombre: nombre, valor: parseInt(valor, 10) }); tr.classList.remove('pe-sucio'); aviso('Guardado: ' + nombre, true); }
            catch (err) { aviso(err.message, false); }
        } else {
            const ok = window.Swal ? (await Swal.fire({ title: '¿Eliminar "' + nombre + '"?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' })).isConfirmed : confirm('¿Eliminar "' + nombre + '"?');
            if (!ok) return;
            try { await pedir(base + '/' + id, 'DELETE'); tr.remove(); aviso('Eliminado: ' + nombre, true); } catch (err) { aviso(err.message, false); }
        }
    });
})();
</script>
@endif
