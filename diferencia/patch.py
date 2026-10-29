import re, sys, os
base = sys.argv[1]

# 1) Ruta
rp = os.path.join(base, 'routes/web.php')
r = open(rp, encoding='utf-8').read()
if 'VentaDiferenciaController@update' not in r:
    anchor = re.search(r"^.*VentaController@cerrar'\).*$", r, re.M)
    if not anchor:
        sys.exit("ERROR: no encontré la ruta de VentaController@cerrar en routes/web.php")
    linea = "\n    Route::post('venta/{venta}/diferencia', 'VentaDiferenciaController@update')->name('venta.diferencia.update');"
    r = r[:anchor.end()] + linea + r[anchor.end():]
    open(rp, 'w', encoding='utf-8').write(r)
    print("Ruta agregada")
else:
    print("Ruta ya existia")

# 2) Boton y modal en la tarjeta de venta de la reserva
vp = os.path.join(base, 'resources/views/themes/backoffice/pages/reserva/includes/venta.blade.php')
v = open(vp, encoding='utf-8').read()
if 'modal_editar_diferencia' not in v:
    m = re.search(r"Diferencia:\s*\{\{.*?diferencia_programa.*?\}\}\s*</a>", v, re.S)
    if not m:
        sys.exit("ERROR: no encontré el bloque 'Diferencia:' en venta.blade.php")
    boton = """
        @if(Auth::user()->is_admin() && !$reserva->venta->tiene_saldo_a_favor)
            <a href="javascript:void(0)" class="collection-item center-align valign-wrapper left"
               onclick="$('#modalEditarDiferencia').modal('open')">
                <i class="material-icons tooltipped" data-position="bottom" data-tooltip="Editar diferencia">edit</i>
            </a>
        @endif
        @include('themes.backoffice.pages.reserva.includes.modal_editar_diferencia')"""
    v = v[:m.end()] + boton + v[m.end():]
    open(vp, 'w', encoding='utf-8').write(v)
    print("Boton agregado")
else:
    viejo = "@if(Auth::user()->is_admin() && !is_null($reserva->venta->diferencia_programa))"
    if viejo in v:
        v = v.replace(viejo, "@if(Auth::user()->is_admin() && !$reserva->venta->tiene_saldo_a_favor)")
        open(vp, 'w', encoding='utf-8').write(v)
        print("Boton actualizado: ahora tambien antes del cierre")
    else:
        print("Boton ya existia")

# 3) Enlace "Corregir" en la pantalla de cierre (solo administradores)
cp = os.path.join(base, 'resources/views/themes/backoffice/pages/venta/cerrar.blade.php')
if os.path.exists(cp):
    c = open(cp, encoding='utf-8').read()
    if 'editar-diferencia' not in c:
        m = re.search(r'<label for="diferencia">[^<]*</label>', c)
        if m:
            enlace = (m.group(0) + "\n                  @if(Auth::user()->is_admin())"
                      "<a href=\"{{ route('backoffice.reserva.show', $reserva) }}#editar-diferencia\" "
                      "style=\"position:absolute;right:12px;top:0;font-size:13px\">Corregir diferencia</a>@endif")
            c = c[:m.start()] + enlace + c[m.end():]
            open(cp, 'w', encoding='utf-8').write(c)
            print("Enlace en cierre agregado")
        else:
            print("AVISO: no encontré la etiqueta 'Diferencia por Pagar' en cerrar.blade.php (se puede corregir desde la reserva)")
    else:
        print("Enlace en cierre ya existia")
