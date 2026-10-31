import re, sys, os
# Menús: postres del día (nombre y cantidad). Cocina, administración y jefe de local los ingresan;
# todos los que ven Menús ven la lista en la misma vista.
base = sys.argv[1]
hechos = []

def editar(rel, fn):
    p = os.path.join(base, rel)
    s = open(p, encoding='utf-8').read()
    n = fn(s)
    if n != s:
        open(p, 'w', encoding='utf-8').write(n)
        hechos.append(rel.split('/')[-1])

def rutas(s):
    if 'PostreDiaController' in s:
        return s
    m = re.search(r"^.*'MenuController@dia'\).*$", s, re.M)
    if not m:
        sys.exit("routes/web.php: no encontré la ruta cocina.dia")
    nuevas = ("\n    Route::post('/cocina/postres', 'PostreDiaController@store')->name('cocina.postres.store');"
              "\n    Route::put('/cocina/postres/{id}', 'PostreDiaController@update')->name('cocina.postres.update');"
              "\n    Route::delete('/cocina/postres/{id}', 'PostreDiaController@destroy')->name('cocina.postres.destroy');")
    return s[:m.end()] + nuevas + s[m.end():]

def menu(s):
    if 'PostreDiaController::delDia' in s:
        return s
    a = "        return response()->json([\n            'fecha' => $fechaDMY,"
    if s.count(a) != 1:
        sys.exit("MenuController: no encontré la respuesta de dia()")
    s = s.replace(a, "        $postresDia = \\App\\Http\\Controllers\\PostreDiaController::delDia($fechaISO);\n\n" + a, 1)
    b = "            'reservas' => $reservasPayload,"
    if s.count(b) != 1:
        sys.exit("MenuController: no encontré 'reservas' => $reservasPayload")
    return s.replace(b, b + "\n            'postres' => $postresDia['postres'],\n            'postres_sugeridos' => $postresDia['sugeridos'],\n            'puede_postres' => $postresDia['puede'],", 1)

JS = r"""
  // ===== Postres del día =====
  var POSTRES_STORE = "{{ route('backoffice.cocina.postres.store') }}";
  var POSTRES_ITEM  = "{{ route('backoffice.cocina.postres.update', '__ID__') }}";

  function renderPostres(data) {
    var lista = data.postres || [];
    var puede = !!data.puede_postres;
    var total = lista.reduce(function(s, p) { return s + (p.cantidad || 0); }, 0);
    var items = lista.length ? lista.map(function(p) {
      var acciones = puede
        ? `<button type="button" class="btn-flat btn-small" style="padding:0 8px" onclick="postreCantidad(${p.id}, ${p.cantidad - 1})" aria-label="Restar uno a ${escapeHtml(p.nombre)}">−</button>
           <span class="task-cat pink"><strong>${p.cantidad}</strong></span>
           <button type="button" class="btn-flat btn-small" style="padding:0 8px" onclick="postreCantidad(${p.id}, ${p.cantidad + 1})" aria-label="Sumar uno a ${escapeHtml(p.nombre)}">+</button>
           <button type="button" class="btn-flat btn-small" style="padding:0 6px" onclick="postreQuitar(${p.id})" aria-label="Quitar ${escapeHtml(p.nombre)}"><i class="material-icons tiny grey-text">delete</i></button>`
        : `<span class="task-cat pink"><strong>${p.cantidad}</strong> ${p.cantidad === 1 ? 'unidad' : 'unidades'}</span>`;
      return `
        <li class="collection-item">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
            <p class="collections-title" style="margin:0">${escapeHtml(p.nombre)}</p>
            <div style="display:flex;align-items:center;gap:2px">${acciones}</div>
          </div>
        </li>`;
    }).join('') : '<li class="collection-item grey-text">Aún no se ingresan postres para este día.</li>';

    var form = puede ? `
        <li class="collection-item">
          <form onsubmit="return postreAgregar(event)" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin:0">
            <div class="input-field" style="flex:1 1 180px;margin:0">
              <input id="postre-nombre" type="text" list="postres-sugeridos" maxlength="120" required autocomplete="off">
              <label for="postre-nombre">Postre</label>
            </div>
            <div class="input-field" style="flex:0 0 90px;margin:0">
              <input id="postre-cantidad" type="number" min="1" max="999" required>
              <label for="postre-cantidad">Cantidad</label>
            </div>
            <button type="submit" class="btn pink">Agregar</button>
          </form>
          <datalist id="postres-sugeridos">${(data.postres_sugeridos || []).map(function(n) { return '<option value="' + escapeHtml(n) + '">'; }).join('')}</datalist>
        </li>` : '';

    return `
      <ul class="collection z-depth-1">
        <li class="collection-item avatar">
          <i class="material-icons pink circle">cake</i>
          <h6 class="collection-header m-0">Postres del día</h6>
          <p>Total: ${total}</p>
        </li>
        ${items}
        ${form}
      </ul>`;
  }

  function postresPeticion(url, metodo, cuerpo) {
    cuerpo = cuerpo || {};
    if (metodo !== 'POST') cuerpo._method = metodo;
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify(cuerpo)
    })
    .then(function(r) {
      return r.json().catch(function() { return {}; }).then(function(j) {
        if (!r.ok) {
          var msg = j.errors ? Object.values(j.errors)[0][0] : (j.message || ('Error ' + r.status));
          throw new Error(msg);
        }
        return j;
      });
    })
    .then(function(j) {
      var col = document.getElementById('postres-col');
      if (col) col.innerHTML = renderPostres({ postres: j.postres, postres_sugeridos: j.sugeridos, puede_postres: j.puede });
    })
    .catch(function(e) {
      if (typeof Swal !== 'undefined') { Swal.fire('No se pudo guardar', e.message, 'error'); } else { alert(e.message); }
    });
  }

  function postreAgregar(ev) {
    ev.preventDefault();
    var nombre = document.getElementById('postre-nombre').value.trim();
    var cantidad = parseInt(document.getElementById('postre-cantidad').value, 10);
    if (!nombre || !(cantidad > 0)) return false;
    postresPeticion(POSTRES_STORE, 'POST', { fecha: window.__fechaPostres, nombre: nombre, cantidad: cantidad });
    return false;
  }

  function postreCantidad(id, cantidad) {
    if (cantidad < 0) return;
    postresPeticion(POSTRES_ITEM.replace('__ID__', id), 'PUT', { cantidad: cantidad });
  }

  function postreQuitar(id) {
    if (!confirm('¿Quitar este postre del día?')) return;
    postresPeticion(POSTRES_ITEM.replace('__ID__', id), 'DELETE', {});
  }

  // ===== AJAX ====="""

def vista(s):
    if 'renderPostres' in s:
        return s
    if s.count("  // ===== AJAX =====") != 1:
        sys.exit("cocina/index: no encontré el bloque AJAX")
    s = s.replace("  // ===== AJAX =====", JS, 1)
    a = "${renderContador('Desayuno / Once', 'free_breakfast', 'purple', data.buffet)}\n              </div>"
    if s.count(a) != 1:
        sys.exit("cocina/index: no encontré la columna Desayuno / Once")
    s = s.replace(a, a + "\n              <div class=\"col s12 m8 l8\" id=\"postres-col\">\n                ${renderPostres(data)}\n              </div>", 1)
    b = "        var tituloDia = data.hoy ? 'Hoy' : data.fecha;"
    if s.count(b) != 1:
        sys.exit("cocina/index: no encontré tituloDia")
    return s.replace(b, b + "\n        window.__fechaPostres = data.fecha;", 1)

editar('routes/web.php', rutas)
editar('app/Http/Controllers/MenuController.php', menu)
editar('resources/views/themes/backoffice/pages/cocina/index.blade.php', vista)
print('Postres del día: ' + (', '.join(hechos) if hechos else 'ya estaba'))
