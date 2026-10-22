import sys
base = sys.argv[1]
def rw(p, fn):
    path = base + '/' + p
    s = open(path, encoding='utf-8').read(); n = fn(s)
    if n is None: print('SIN CAMBIO', p); return
    open(path, 'w', encoding='utf-8').write(n); print('OK', p)

def ctrl(s):
    if "Programa::activos()->orderBy('nombre_programa')" in s: return None
    i = s.find('public function create()'); j = s.find('public function store', i)
    a = '$programas = Programa::all();'
    k = s.find(a, i)
    if k < 0 or k > j: raise SystemExit('ERROR: no encontre la lista de programas en create()')
    return s[:k] + "$programas = Programa::activos()->orderBy('nombre_programa')->get(); // solo planes activos" + s[k+len(a):]
rw('app/Http/Controllers/CotizacionController.php', ctrl)

def vista(s):
    if 'cotiz-ir-programas' in s: return None
    a = '<h6>Programas disponibles</h6>'
    if s.count(a) != 1: raise SystemExit('ERROR: no encontre el titulo de programas')
    b = ('<div class="cotiz-ir-programas" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:16px">\n'
         '                                            <h6 style="margin:0">Programas activos ({{ $programas->count() }})</h6>\n'
         '                                            <a href="{{ route(\'backoffice.programa.index\') }}" target="_blank" rel="noopener" class="btn-small waves-effect waves-light" style="background:#039B7B">'
         '<i class="material-icons left">hot_tub</i>Ir a programas</a>\n'
         '                                        </div>')
    return s.replace(a, b)
rw('resources/views/themes/backoffice/pages/cotizacion/create.blade.php', vista)
