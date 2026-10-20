import sys, re
hechos = []
def editar(p, fn):
    s = open(p, encoding='utf-8').read(); n = fn(s)
    if n != s: open(p, 'w', encoding='utf-8').write(n); hechos.append(p.split('/')[-1])

def eg(s):
    if "'Café'" in s: return s
    m = re.search(r"\n(\s*)'Bar'\s*=>[^\n]*\n", s)
    if not m: sys.exit('egresos.php: no encontré Bar')
    return s[:m.end()] + m.group(1) + "'Café'               => ['cafe de grano'],\n" + s[m.end():]

def fz(s):
    if 'cuentas_proveedor' in s: return s
    i = s.rfind('];')
    add = """
    // Cuentas de destino que son de un proveedor aunque la persona sea del equipo (no es sueldo).
    // Clave: número de cuenta sin ceros a la izquierda.
    'cuentas_proveedor' => [
        '23674351' => ['nombre' => 'Café de Francisca Bahamondes (2 kg $72.000, 500 g $18.000)', 'cat' => 'Insumos', 'sub' => 'Café'],
    ],
"""
    return s[:i] + add + s[i:]

def cs(s):
    if 'cuentas_proveedor' in s: return s
    a = "        // ¿Es un abono a algo por pagar (cuenta del correo, deuda, crédito)?"
    if s.count(a) != 1: sys.exit('ConciliadorSalidas: ancla café no encontrada')
    add = """        // Cuenta de un proveedor aunque sea de alguien del equipo (ej. el café de Francisca): no es sueldo
        $ctaN = ltrim(preg_replace('/\\D/', '', (string) $c->cuenta), '0');
        $prov = (array) config('finanzas.cuentas_proveedor', []);
        if ($ctaN !== '' && isset($prov[$ctaN])) {
            $sub = $this->subId($prov[$ctaN]['cat'], $prov[$ctaN]['sub']);
            return ['estado' => 'calzado', 'vinculo' => 'egreso', 'nota' => mb_substr($prov[$ctaN]['nombre'], 0, 191),
                    'categoria_id' => $sub[0] ?? null, 'subcategoria_id' => $sub[1] ?? null];
        }

"""
    return s.replace(a, add + a, 1)

editar('config/egresos.php', eg)
editar('config/finanzas.php', fz)
editar('app/Finanzas/Correo/ConciliadorSalidas.php', cs)
print('Café de Francisca: ' + (', '.join(hechos) if hechos else 'ya estaba'))
