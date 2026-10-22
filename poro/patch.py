import sys, re
base = sys.argv[1]
def rw(p, fn):
    path = base + '/' + p
    s = open(path, encoding='utf-8').read(); n = fn(s)
    if n is None: print('SIN CAMBIO', p); return
    open(path, 'w', encoding='utf-8').write(n); print('OK', p)
ED = "@include('themes.backoffice.pages.poroporo.includes._productos_editor')"
CR = "@include('themes.backoffice.pages.poroporo.includes._crear_producto')"
NAV = "@include('themes.backoffice.pages.poroporo.includes.poro_nav', $poroProductos)"

def index(s):  # vista del equipo: lista de precios de solo lectura (como antes)
    if ED in s:
        s = s.replace(ED, NAV).replace('<div class="col s12 m8">', '<div class="col s12 m9">', 1).replace('<div class="col s12 m4">', '<div class="col s12 m3">', 1)
        return s
    return None
rw('resources/views/themes/backoffice/pages/poroporo/venta/index.blade.php', index)

def admin(s):
    if CR in s: return None
    a = '<div class="col s12">' + ED + '</div>'
    if a in s: return s.replace(a, '<div class="col s12">' + CR + '</div>', 1)
    b = '<div id="basic-form" class="section">\n        <div class="row">'
    if b not in s: raise SystemExit('ERROR: index_admin distinto')
    return s.replace(b, b + '\n            <div class="col s12">' + CR + '</div>', 1)
rw('resources/views/themes/backoffice/pages/poroporo/venta/index_admin.blade.php', admin)

def crear(s):
    nuevo = '<h6 class="pe-ir-editor">Productos disponibles @if(Auth::user()->has_role(config(\'app.admin_role\')))<a href="{{ route(\'backoffice.poroporo.index\') }}" style="font-size:.85rem;margin-left:8px">(crear o cambiar precios)</a>@endif</h6>'
    if nuevo in s: return None
    s2 = re.sub(r'<h6 class="pe-ir-editor">.*?</h6>', lambda m: nuevo, s, count=1, flags=re.S)
    if s2 == s:
        a = '<h6>Productos disponibles</h6>'
        if a not in s: raise SystemExit('ERROR: venta/create distinto')
        s2 = s.replace(a, nuevo, 1)
    return s2
rw('resources/views/themes/backoffice/pages/poroporo/venta/create.blade.php', crear)

def web(s):
    if 'routes/poroporo_productos.php' in s: return None
    k = "require base_path('routes/finanzas.php');"; i = s.find(k)
    if i < 0: raise SystemExit('ERROR: no encontre donde enlazar rutas')
    j = s.find('\n', i)
    return s[:j] + "\n    require base_path('routes/poroporo_productos.php'); // Poro Poro: productos editables" + s[j:]
rw('routes/web.php', web)

def ctrl(s):  # crear/editar/eliminar productos: solo administradores
    if 'Solo administradores' in s: return None
    a = 'class PoroPoroController extends Controller\n{'
    if a not in s: raise SystemExit('ERROR: PoroPoroController distinto')
    m = ("\n    public function __construct()\n    {\n        // Solo administradores crean, editan o eliminan productos\n"
         "        $this->middleware(function ($request, $next) {\n"
         "            if (!$request->user() || !$request->user()->has_role(config('app.admin_role'))) { abort(403, 'Solo administradores'); }\n"
         "            return $next($request);\n        })->except(['index']);\n    }\n")
    return s.replace(a, a + m, 1)
rw('app/Http/Controllers/PoroPoroController.php', ctrl)
