# Atencion de ejecutivo (10/11/2026): rutas y opcion en el bloque Reservaciones Bot del menu.
import re, sys, shutil, time
B = '/var/www/html/botacurapp/'
ts = time.strftime('%Y%m%d%H%M%S')
def guardar(f, t):
    shutil.copy(f, f + '.bak.' + ts); open(f, 'w', encoding='utf-8').write(t)

RUTAS = B + 'routes/web.php'
r = open(RUTAS, encoding='utf-8').read()
if 'bot-ejecutivo.index' not in r:
    ancla = "Route::get('bot-conversaciones', 'BotConversacionesController@index')->name('bot-conversaciones.index');"
    if r.count(ancla) != 1:
        sys.exit('ERROR: no encontre la ruta de Conversaciones del bot. No se cambio nada.')
    r = r.replace(ancla, ancla + """
    Route::get('bot-ejecutivo', 'BotEjecutivoController@index')->name('bot-ejecutivo.index');
    Route::post('bot-ejecutivo/atender', 'BotEjecutivoController@atender')->name('bot-ejecutivo.atender');""")
    guardar(RUTAS, r); print('Rutas agregadas: /bot-ejecutivo')

MENU = B + 'resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php'
m = open(MENU, encoding='utf-8').read()
if 'backoffice.bot-ejecutivo.index' not in m:
    a = re.search(r"^([ \t]*)\[\s*'Verificación de pagos'", m, re.M)
    if "['Reservaciones Bot'" not in m or not a:
        sys.exit('ERROR: falta el bloque Reservaciones Bot en el menu (correr antes aplicar_menu_bot.sh).')
    linea = a.group(1) + "['Atención de ejecutivo', 'support_agent', 'backoffice.bot-ejecutivo.index', [], $rol('admin'), 'backoffice.bot-ejecutivo.*'],\n"
    m = m[:a.start()] + linea + m[a.start():]
    guardar(MENU, m); print('Menu: Atención de ejecutivo agregado al inicio de Reservaciones Bot')
print('INSTALADO')
