import re, sys, os
# Gestión consumo: botón "Código QR" (carta digital, todos los perfiles) y, solo administradores,
# edición del total a pagar en la columna "Diferencia" de Detalles Venta.
base = sys.argv[1]
vp = os.path.join(base, 'resources/views/themes/backoffice/pages/venta/cierre/index_cierre.blade.php')
v = open(vp, encoding='utf-8').read()

# 1) Botón Código QR + modal, bajo el título "Reservas de hoy"
if 'modalCodigoQR' not in v:
    m = re.search(r"Reservas de hoy:.*?</a>", v, re.S)
    if not m:
        m = re.search(r"@section\('content'\)\s*<div class=\"section\">", v)
    if not m:
        sys.exit("ERROR: no encontré dónde poner el botón Código QR en index_cierre.blade.php")
    bloque = """

    {{-- Código QR de la carta digital (para mostrarlo al cliente desde la pantalla) --}}
    <a href="#modalCodigoQR" class="btn waves-effect waves-light green darken-3 modal-trigger" style="margin:4px 0 12px">
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-4px;margin-right:8px"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><path d="M14 14h3v3h-3zM18 18h3v3h-3zM14 20h2M20 14v2"></path></svg>Código QR
    </a>
    <div id="modalCodigoQR" class="modal" style="max-width:520px">
        <div class="modal-content center-align">
            <h5>Carta Botacura</h5>
            <p class="grey-text" style="margin-top:0">Escanea con la cámara del celular</p>
            <img src="{{ asset('images/qr_carta.png') }}?v=1" alt="Código QR de la carta digital de Botacura"
                 style="width:100%;max-width:380px;height:auto;image-rendering:pixelated;background:#fff;padding:12px">
            <p style="margin-bottom:0"><a href="https://botacura.ola.click/products?from=qrcode" target="_blank" rel="noopener">botacura.ola.click</a></p>
        </div>
        <div class="modal-footer">
            <a href="{{ asset('images/qr_carta.png') }}" download="QR-carta-Botacura.png" class="btn-flat">Descargar</a>
            <a href="#!" class="modal-close btn-flat">Cerrar</a>
        </div>
    </div>"""
    v = v[:m.end()] + bloque + v[m.end():]
    print("Botón Código QR agregado")
else:
    print("Botón Código QR ya existía")

# 2) Columna "Diferencia" de Detalles Venta: editar el total a pagar (solo administradores)
#    Si un parche anterior dejó el botón naranjo por fila, se quita: la edición va en la columna.
v = re.sub(r"\n\s*@if\(Auth::user\(\)->is_admin\(\) && !is_null\(\$reserva->venta\) && !\$reserva->venta->tiene_saldo_a_favor\)\s*<a href=\"[^\"]*#editar-diferencia-consumo\".*?</a>\s*@endif", "", v, flags=re.S)
if 'difEditable' not in v:
    a1 = "var totalPagar = $(this).data('totalpagar');"
    a2 = "tablaResumen += '<td>' + formatCLP(totalPagar) + '</td>';"
    a3 = "var tablaResumen = '<table class=\"highlight responsive-table centered\">';"
    if a1 in v and a2 in v and a3 in v:
        v = v.replace(a1, a1 + "\n            var ventaIdDif = $(this).data('id');", 1)
        v = v.replace(a3, """// Columna Diferencia: solo administradores pueden corregir el total a pagar
            var difEditable = {{ (Auth::user()->is_admin() && Route::has('backoffice.venta.diferencia.update')) ? 'true' : 'false' }};
            var difUrl = "{{ Route::has('backoffice.venta.diferencia.update') ? route('backoffice.venta.diferencia.update', '__ID__') : '' }}".replace('__ID__', ventaIdDif);
            """ + a3, 1)
        v = v.replace(a2, """if (difEditable) {
                tablaResumen += '<td>'
                  + '<span class="dif-ver">' + formatCLP(totalPagar)
                  + ' <a href="javascript:void(0)" class="dif-editar tooltipped" data-position="bottom" data-tooltip="Editar total a pagar" title="Editar total a pagar"><i class="material-icons tiny" style="vertical-align:middle">edit</i></a></span>'
                  + '<form class="dif-form" method="POST" action="' + difUrl + '" style="display:none;max-width:220px;margin:0 auto">'
                  + '<input type="hidden" name="_token" value="{{ csrf_token() }}">'
                  + '<input type="hidden" name="ir_a_cierre" value="consumo">'
                  + '<input type="text" name="diferencia" inputmode="numeric" required value="' + totalPagar + '" aria-label="Total a pagar">'
                  + '<input type="text" name="motivo" maxlength="255" placeholder="Motivo (opcional)" aria-label="Motivo">'
                  + '<button type="submit" class="btn btn-small">Guardar</button> '
                  + '<a href="javascript:void(0)" class="dif-cancelar btn-flat btn-small">Cancelar</a>'
                  + '</form></td>';
              } else {
                tablaResumen += '<td>' + formatCLP(totalPagar) + '</td>';
              }""", 1)
        # manejadores (una vez)
        v = v.replace("$(document).ready(function(){\n      $('.modal-trigger').on('click', function(){", """$(document).on('click', '.dif-editar', function(){
        var td = $(this).closest('td'); td.find('.dif-ver').hide(); td.find('.dif-form').show().find('input[name=diferencia]').focus().select();
      });
      $(document).on('click', '.dif-cancelar', function(){
        var td = $(this).closest('td'); td.find('.dif-form').hide(); td.find('.dif-ver').show();
      });
    $(document).ready(function(){
      $('.modal-trigger').on('click', function(){""", 1)
        if '.dif-editar' not in v:
            sys.exit("ERROR: no encontré el script de Detalles Venta para agregar la edición")
        print("Edición del total a pagar en la columna Diferencia agregada")
    else:
        print("AVISO: no encontré la columna Diferencia de Detalles Venta; se puede corregir desde la reserva")
else:
    print("Edición en la columna Diferencia ya existía")

open(vp, 'w', encoding='utf-8').write(v)
