/* Botacura – Reserva web 2.0.0: personas → fecha → ubicación → total (reglas de app.botacura.cl) */
(function () {
    'use strict';
    var C = window.BotacuraRW;
    var root = document.getElementById('botacura-rw');
    if (!C || !root) { return; }
    var form = root.closest('form.cart') || document.querySelector('form.cart');
    if (!form) { return; }

    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var DIAS = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
    var st = { personas: Math.max(2, C.min || 2), fechas: [], porFecha: {}, mes: null, fecha: null, opciones: null, ubicacion: '' };

    var inPers = root.querySelector('[name=brw_personas]');
    var inFecha = root.querySelector('[name=brw_fecha]');
    var inUbic = root.querySelector('[name=brw_ubicacion]');
    var btn = form.querySelector('[name=add-to-cart], .single_add_to_cart_button');
    var qty = form.querySelector('input.qty, input[name=quantity]');

    // El campo de fecha del plugin anterior y la cantidad quedan ocultos: la cantidad = personas.
    form.querySelectorAll('[name=botacura_fecha_visita]').forEach(function (i) {
        if (!root.contains(i)) { var w = i.closest('p, .form-row, div') || i; w.style.display = 'none'; i.disabled = true; }
    });
    var qtyBox = qty ? (qty.closest('.quantity') || qty) : null;
    if (qtyBox) { qtyBox.style.display = 'none'; }

    function fmt(n) { return '$' + Math.round(n).toLocaleString('es-CL'); }
    function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (txt != null) { e.textContent = txt; } return e; }
    function api(ruta, params) {
        var q = Object.keys(params).map(function (k) { return k + '=' + encodeURIComponent(params[k]); }).join('&');
        return fetch(C.rest + '/' + ruta + '?' + q, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .catch(function () { return { ok: false, motivo: 'No pudimos conectar con el sistema de reservas. Intenta de nuevo.' }; });
    }

    // ── Estructura ──
    root.appendChild(el('div', 'brw-titulo', 'Arma tu reserva'));
    var p1 = el('div', 'brw-paso');
    p1.appendChild(el('div', 'brw-etq', '1. ¿Cuántas personas?'));
    var step = el('div', 'brw-step');
    var menos = el('button', 'brw-sb', '−'); menos.type = 'button'; menos.setAttribute('aria-label', 'Una persona menos');
    var num = el('span', 'brw-num', String(st.personas)); num.setAttribute('aria-live', 'polite');
    var mas = el('button', 'brw-sb', '+'); mas.type = 'button'; mas.setAttribute('aria-label', 'Una persona más');
    step.appendChild(menos); step.appendChild(num); step.appendChild(mas);
    p1.appendChild(step);
    var notaGrupo = el('div', 'brw-nota', 'Para grupos de ' + (C.max + 1) + ' o más personas escríbenos a ' + C.correo + ' y te enviamos una cotización.');
    p1.appendChild(notaGrupo);
    root.appendChild(p1);

    var p2 = el('div', 'brw-paso');
    p2.appendChild(el('div', 'brw-etq', '2. Elige la fecha'));
    var cal = el('div', 'brw-cal');
    p2.appendChild(cal);
    var aviso = el('div', 'brw-nota');
    p2.appendChild(aviso);
    root.appendChild(p2);

    var p3 = el('div', 'brw-paso brw-oculto');
    p3.appendChild(el('div', 'brw-etq', '3. Ubicación'));
    var ubic = el('div', 'brw-ubic');
    p3.appendChild(ubic);
    root.appendChild(p3);

    var res = el('div', 'brw-resumen brw-oculto');
    root.appendChild(res);

    function listo(ok) {
        if (!btn) { return; }
        btn.disabled = !ok;
        btn.classList.toggle('disabled', !ok);
        btn.setAttribute('aria-disabled', ok ? 'false' : 'true');
    }
    listo(false);

    function sincronizar() {
        inPers.value = st.personas;
        inFecha.value = st.fecha || '';
        inUbic.value = st.ubicacion || '';
        if (qty) { qty.value = st.personas; }
        var o = st.opciones;
        var falta = !st.fecha || !o || !o.ok || (o.requiere_eleccion && !st.ubicacion);
        listo(!falta);
    }

    // ── Personas ──
    function cambiarPersonas(n) {
        n = Math.max(C.min || 1, Math.min(C.max, n));
        if (n === st.personas && st.fechas.length) { return; }
        st.personas = n; num.textContent = String(n);
        menos.disabled = n <= (C.min || 1); mas.disabled = n >= C.max;
        notaGrupo.style.display = n >= C.max ? '' : 'none';
        st.fecha = null; st.opciones = null; st.ubicacion = '';
        p3.classList.add('brw-oculto'); res.classList.add('brw-oculto');
        cargarFechas();
    }
    menos.addEventListener('click', function () { cambiarPersonas(st.personas - 1); });
    mas.addEventListener('click', function () { cambiarPersonas(st.personas + 1); });

    // ── Fechas ──
    var pedido = 0;
    function cargarFechas() {
        var mio = ++pedido;
        cal.innerHTML = ''; cal.appendChild(el('div', 'brw-cargando', 'Buscando fechas con cupo para ' + st.personas + ' personas…'));
        aviso.textContent = '';
        sincronizar();
        api('fechas', { producto: C.producto, personas: st.personas }).then(function (r) {
            if (mio !== pedido) { return; }
            if (!r || !r.ok) { cal.innerHTML = ''; aviso.textContent = (r && r.motivo) || 'No pudimos cargar las fechas.'; return; }
            st.fechas = r.fechas || []; st.porFecha = {};
            st.fechas.forEach(function (f) { st.porFecha[f.fecha] = f; });
            var meses = r.meses_sin_abrir ? Object.keys(r.meses_sin_abrir) : [];
            if (!st.fechas.length) {
                cal.innerHTML = '';
                aviso.textContent = 'No quedan fechas con cupo para ' + st.personas + ' personas en las agendas abiertas.' + (meses.length ? ' ' + r.meses_sin_abrir[meses[0]] : '') + ' Escríbenos por WhatsApp al +56 9 7448 4112.';
                return;
            }
            if (meses.length) { aviso.textContent = r.meses_sin_abrir[meses[0]]; }
            st.mes = st.fechas[0].fecha.slice(0, 7);
            dibujarMes();
        });
    }

    function dibujarMes() {
        cal.innerHTML = '';
        var y = +st.mes.slice(0, 4), m = +st.mes.slice(5, 7) - 1;
        var meses = st.fechas.map(function (f) { return f.fecha.slice(0, 7); }).filter(function (v, i, a) { return a.indexOf(v) === i; });
        var idx = meses.indexOf(st.mes);
        var cab = el('div', 'brw-cab');
        var ant = el('button', 'brw-nav', '‹'); ant.type = 'button'; ant.setAttribute('aria-label', 'Mes anterior'); ant.disabled = idx <= 0;
        var sig = el('button', 'brw-nav', '›'); sig.type = 'button'; sig.setAttribute('aria-label', 'Mes siguiente'); sig.disabled = idx >= meses.length - 1;
        ant.onclick = function () { st.mes = meses[idx - 1]; dibujarMes(); };
        sig.onclick = function () { st.mes = meses[idx + 1]; dibujarMes(); };
        cab.appendChild(ant); cab.appendChild(el('div', 'brw-mes', MESES[m] + ' ' + y)); cab.appendChild(sig);
        cal.appendChild(cab);
        var grid = el('div', 'brw-grid');
        DIAS.forEach(function (d) { grid.appendChild(el('div', 'brw-dow', d)); });
        var primero = new Date(y, m, 1), offset = (primero.getDay() + 6) % 7, total = new Date(y, m + 1, 0).getDate();
        for (var i = 0; i < offset; i++) { grid.appendChild(el('div', 'brw-vacio')); }
        for (var d = 1; d <= total; d++) {
            var iso = st.mes + '-' + (d < 10 ? '0' : '') + d;
            var f = st.porFecha[iso];
            var b = el('button', 'brw-dia' + (f ? ' ok' : '') + (st.fecha === iso ? ' sel' : ''), String(d));
            b.type = 'button';
            if (f) {
                b.title = fmt(f.valor_persona) + ' por persona';
                b.setAttribute('aria-label', d + ' de ' + MESES[m] + ', ' + fmt(f.valor_persona) + ' por persona');
                (function (iso) { b.onclick = function () { elegirFecha(iso); }; })(iso);
            } else {
                b.disabled = true;
                b.setAttribute('aria-label', d + ' de ' + MESES[m] + ', sin cupo');
            }
            grid.appendChild(b);
        }
        cal.appendChild(grid);
    }

    // ── Opciones de la fecha ──
    function elegirFecha(iso) {
        st.fecha = iso; st.opciones = null; st.ubicacion = '';
        dibujarMes();
        p3.classList.remove('brw-oculto');
        ubic.innerHTML = ''; ubic.appendChild(el('div', 'brw-cargando', 'Revisando ubicaciones…'));
        res.classList.add('brw-oculto');
        sincronizar();
        api('opciones', { producto: C.producto, personas: st.personas, fecha: iso }).then(function (o) {
            if (st.fecha !== iso) { return; }
            st.opciones = o;
            ubic.innerHTML = '';
            if (!o || !o.ok) {
                ubic.appendChild(el('div', 'brw-error', (o && o.motivo) || 'Esa fecha ya no está disponible. Elige otra.'));
                sincronizar(); return;
            }
            if (o.requiere_eleccion && o.opciones.length) {
                ubic.appendChild(el('div', 'brw-nota', 'Elijan dónde quieren pasar el día:'));
                var cont = el('div', 'brw-cards');
                o.opciones.forEach(function (op) {
                    var c = el('label', 'brw-card');
                    var r = el('input'); r.type = 'radio'; r.name = 'brw_ubic_radio'; r.value = op.valor;
                    r.onchange = function () { st.ubicacion = op.valor; cont.querySelectorAll('.brw-card').forEach(function (x) { x.classList.toggle('sel', x === c); }); sincronizar(); };
                    c.appendChild(r);
                    if (op.foto) { var im = el('img'); im.src = op.foto; im.alt = op.titulo; im.loading = 'lazy'; c.appendChild(im); }
                    c.appendChild(el('strong', null, op.titulo));
                    if (op.texto) { c.appendChild(el('small', null, op.texto)); }
                    cont.appendChild(c);
                });
                ubic.appendChild(cont);
            } else {
                var t = el('div', 'brw-asig');
                if (o.foto) { var im2 = el('img'); im2.src = o.foto; im2.alt = ''; im2.loading = 'lazy'; t.appendChild(im2); }
                t.appendChild(el('span', null, o.ubicacion_texto ? 'Les reservamos ' + o.ubicacion_texto + '.' : 'Les asignamos el espacio según su grupo.'));
                ubic.appendChild(t);
            }
            res.innerHTML = '';
            res.appendChild(el('div', 'brw-fecha', o.fecha_texto ? o.fecha_texto.charAt(0).toUpperCase() + o.fecha_texto.slice(1) : iso));
            var fila = el('div', 'brw-total');
            fila.appendChild(el('span', null, st.personas + ' × ' + fmt(o.valor_persona)));
            fila.appendChild(el('strong', null, fmt(o.total)));
            res.appendChild(fila);
            (o.notas || []).forEach(function (n) { res.appendChild(el('div', 'brw-nota', n)); });
            res.appendChild(el('div', 'brw-nota', 'Pago 100 % con Webpay. Te apartamos el cupo por 30 minutos mientras pagas.'));
            res.classList.remove('brw-oculto');
            sincronizar();
        });
    }

    form.addEventListener('submit', function (e) {
        sincronizar();
        if (btn && btn.disabled) { e.preventDefault(); }
    });

    cambiarPersonas(st.personas);
})();
