import re, sys
base = sys.argv[1]
def rw(p, fn):
    path = base + '/' + p
    s = open(path, encoding='utf-8').read()
    n = fn(s)
    if n is None: print('SIN CAMBIO', p); return
    open(path, 'w', encoding='utf-8').write(n); print('OK', p)

LINEA = "'iva_incluido' => (bool) $request->input('iva_incluido', false),"
def ctrl(s):
    if "$request->input('iva_incluido'" in s: return None
    s2 = s.replace("'iva_incluido' => true,", LINEA, 1)
    m = re.search(r"(\$cotizacion->update\(\[.*?'observaciones' => \$request->observaciones,)", s2, re.S)
    if m and LINEA not in s2[m.start():m.end()+80]:
        s2 = s2[:m.end()] + "\n                " + LINEA + s2[m.end():]
    if s2 == s: raise SystemExit('ERROR: no encontre donde cambiar el controlador')
    return s2
rw('app/Http/Controllers/CotizacionController.php', ctrl)

def caja(valor):
    return ('''
                                    <div class="col s12" style="margin:8px 0 16px">
                                        <input type="hidden" name="iva_incluido" value="0">
                                        <label>
                                            <input type="checkbox" class="filled-in" name="iva_incluido" value="1" ''' + valor + '''>
                                            <span>Los valores ingresados ya incluyen IVA (si no se marca, se suma el 19% al total)</span>
                                        </label>
                                    </div>
''')
def vista(valor):
    def f(s):
        if 'name="iva_incluido"' in s: return None
        i = s.find('<textarea id="observaciones"')
        if i < 0: raise SystemExit('ERROR: no encontre observaciones')
        j = s.find('@enderror', i); j = s.find('</div>', j) + len('</div>')
        return s[:j] + '\n' + caja(valor) + s[j:]
    return f
rw('resources/views/themes/backoffice/pages/cotizacion/create.blade.php', vista("{{ old('iva_incluido') ? 'checked' : '' }}"))
rw('resources/views/themes/backoffice/pages/cotizacion/edit.blade.php', vista("{{ old('iva_incluido', $cotizacion->iva_incluido) ? 'checked' : '' }}"))

def show(s):
    if 'iva_incluido' in s: return None
    a = "<td>${{number_format($sumaTotal,0,'','.')}}</td>"
    b = "<td>${{number_format($iva,0,'','.')}}</td>"
    c = "<td>${{number_format($sumaTotal+$iva,0,'','.')}}</td>"
    for x in (a, b, c):
        if s.count(x) != 1: raise SystemExit('ERROR: show distinto al esperado')
    k = s.rfind('<tr>', 0, s.index(a))
    calc = ("@php\n    $sumaItems = $sumaTotal;\n    if ($cotizacion->iva_incluido) { $netoV = (int) round($sumaItems / 1.19); $ivaV = $sumaItems - $netoV; $totalV = $sumaItems; }\n"
            "    else { $netoV = $sumaItems; $ivaV = (int) round($sumaItems * 0.19); $totalV = $netoV + $ivaV; }\n@endphp\n                                ")
    s = s[:k] + calc + s[k:]
    s = s.replace(a, "<td>${{number_format($netoV,0,'','.')}}</td>").replace(b, "<td>${{number_format($ivaV,0,'','.')}}</td>").replace(c, "<td>${{number_format($totalV,0,'','.')}}</td>")
    return s
rw('resources/views/themes/backoffice/pages/cotizacion/show.blade.php', show)
