import re, sys, os
# Gestión consumo: botón "Código QR" (carta digital) y, solo administradores, "Corregir diferencia" por reserva.
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

# 2) Corregir diferencia (solo administradores), en las acciones de cada reserva
if 'editar-diferencia-consumo' not in v:
    m = re.search(r"PDF venta</a>\s*@endif", v)
    if m:
        boton = """
                                @if(Auth::user()->is_admin() && !is_null($reserva->venta) && !$reserva->venta->tiene_saldo_a_favor)
                                    <a href="{{ route('backoffice.reserva.show', $reserva) }}#editar-diferencia-consumo" class="btn-floating btn-small waves-effect waves-light orange darken-2 tooltipped" data-position="bottom" data-tooltip="{{ is_null($reserva->venta->diferencia_programa) ? 'Corregir diferencia por pagar' : 'Editar diferencia cobrada' }}"><i class="material-icons">edit</i></a>
                                @endif"""
        v = v[:m.end()] + boton + v[m.end():]
        print("Botón Corregir diferencia agregado")
    else:
        print("AVISO: no encontré las acciones de la fila (PDF venta); se puede corregir desde la reserva")
else:
    print("Botón Corregir diferencia ya existía")

open(vp, 'w', encoding='utf-8').write(v)
