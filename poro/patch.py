import sys
base = sys.argv[1]
def rw(p, fn):
    path = base + '/' + p
    s = open(path, encoding='utf-8').read(); n = fn(s)
    if n is None: print('SIN CAMBIO', p); return
    open(path, 'w', encoding='utf-8').write(n); print('OK', p)
INC = "@include('themes.backoffice.pages.poroporo.includes._productos_editor')"
def index(s):
    if '_productos_editor' in s: return None
    a = "@include('themes.backoffice.pages.poroporo.includes.poro_nav', $poroProductos)"
    if a not in s: raise SystemExit('ERROR: index de ventas distinto')
    s = s.replace(a, INC)
    return s.replace('<div class="col s12 m9">', '<div class="col s12 m8">', 1).replace('<div class="col s12 m3">', '<div class="col s12 m4">', 1)
rw('resources/views/themes/backoffice/pages/poroporo/venta/index.blade.php', index)
def admin(s):
    if '_productos_editor' in s: return None
    a = '<div id="basic-form" class="section">\n        <div class="row">'
    if a not in s: raise SystemExit('ERROR: index_admin distinto')
    return s.replace(a, a + '\n            <div class="col s12">' + INC + '</div>', 1)
rw('resources/views/themes/backoffice/pages/poroporo/venta/index_admin.blade.php', admin)
def crear(s):
    if 'pe-ir-editor' in s: return None
    a = '<h6>Productos disponibles</h6>'
    if a not in s: raise SystemExit('ERROR: venta/create distinto')
    return s.replace(a, '<h6 class="pe-ir-editor">Productos disponibles <a href="{{ route(\'backoffice.ventas_poroporo.index\') }}#poro-editor" style="font-size:.85rem;margin-left:8px">(agregar o cambiar precios)</a></h6>', 1)
rw('resources/views/themes/backoffice/pages/poroporo/venta/create.blade.php', crear)
def web(s):
    if 'routes/poroporo_productos.php' in s: return None
    k = "require base_path('routes/finanzas.php');"
    i = s.find(k)
    if i < 0: raise SystemExit('ERROR: no encontre donde enlazar rutas')
    j = s.find('\n', i)
    return s[:j] + "\n    require base_path('routes/poroporo_productos.php'); // Poro Poro: productos editables" + s[j:]
rw('routes/web.php', web)
