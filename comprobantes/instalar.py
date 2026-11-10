# Comprobantes sin reserva (10/11/2026): rutas, aviso en Verificacion de pagos y registro desde el webhook.
import re, sys, shutil, time
B = '/var/www/html/botacurapp/'
ts = time.strftime('%Y%m%d%H%M%S')
def guardar(f, t):
    shutil.copy(f, f + '.bak.' + ts); open(f, 'w', encoding='utf-8').write(t)

RUTAS = B + 'routes/web.php'
r = open(RUTAS, encoding='utf-8').read()
if 'comprobantes-sin-reserva.index' in r and 'comprobantes-sin-reserva.crear' not in r:
    ancla2 = "Route::post('comprobantes-sin-reserva/{id}/asociar', 'ComprobantesSinReservaController@asociar')->name('comprobantes-sin-reserva.asociar');"
    if r.count(ancla2) == 1:
        r = r.replace(ancla2, ancla2 + "\n    Route::post('comprobantes-sin-reserva/{id}/crear', 'ComprobantesSinReservaController@crear')->name('comprobantes-sin-reserva.crear');")
        guardar(RUTAS, r); print('Ruta agregada: crear reserva')
if 'comprobantes-sin-reserva.index' not in r:
    ancla = "Route::get('bot-conversaciones', 'BotConversacionesController@index')->name('bot-conversaciones.index');"
    if r.count(ancla) != 1:
        sys.exit('ERROR: no encontre la ruta de Conversaciones del bot. No se cambio nada.')
    r = r.replace(ancla, ancla + """
    Route::get('comprobantes-sin-reserva', 'ComprobantesSinReservaController@index')->name('comprobantes-sin-reserva.index');
    Route::get('comprobantes-sin-reserva/{id}/imagen', 'ComprobantesSinReservaController@imagen')->name('comprobantes-sin-reserva.imagen');
    Route::post('comprobantes-sin-reserva/{id}/asociar', 'ComprobantesSinReservaController@asociar')->name('comprobantes-sin-reserva.asociar');
    Route::post('comprobantes-sin-reserva/{id}/crear', 'ComprobantesSinReservaController@crear')->name('comprobantes-sin-reserva.crear');
    Route::post('comprobantes-sin-reserva/{id}/descartar', 'ComprobantesSinReservaController@descartar')->name('comprobantes-sin-reserva.descartar');""")
    guardar(RUTAS, r); print('Rutas agregadas')

AVISO = "@include('themes.backoffice.pages.comprobantes-sin-reserva._aviso')"
for vista in ['resources/views/themes/backoffice/pages/verificacion_pago/index.blade.php',
              'resources/views/themes/backoffice/pages/reporte/reservas_dia.blade.php']:
    f = B + vista
    try:
        t = open(f, encoding='utf-8').read()
    except FileNotFoundError:
        print('AVISO: no existe ' + vista); continue
    if AVISO in t:
        continue
    m = re.search(r"@section\(\s*'content'\s*\)\s*\n", t)
    if not m:
        print('AVISO: no pude poner el aviso en ' + vista); continue
    t = t[:m.end()] + AVISO + "\n" + t[m.end():]
    guardar(f, t); print('Aviso agregado en ' + vista.split('/')[-2])

W = B + 'app/Http/Controllers/Api/WhatsAppWebhookController.php'
w = open(W, encoding='utf-8').read()
if 'ComprobanteTextoService::registrar' not in w:
    ancla = "                \\App\\Services\\BotCasoService::registrar('comprobante_sin_reserva', $telefono, ['archivo' => $archivoSinReserva]);"
    if w.count(ancla) != 1:
        sys.exit('ERROR: falta el parche del bot del 09/11 en el webhook.')
    w = w.replace(ancla, "                \\App\\Services\\ComprobanteTextoService::registrar($telefono, null, $descripcion, $archivoSinReserva, $this->phoneNumberId);\n" + ancla)
    guardar(W, w); print('Webhook: los comprobantes sin reserva quedan en la pantalla nueva')
print('INSTALADO')
