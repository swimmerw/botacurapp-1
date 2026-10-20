import sys
hechos = []
def editar(p, fn):
    s = open(p, encoding='utf-8').read(); n = fn(s)
    if n != s: open(p, 'w', encoding='utf-8').write(n); hechos.append(p.split('/')[-1])

def ss(s):
    if 'function confirmar' in s: return s
    s = s.replace("""        if ($existe) {
            return (int) $existe;
        }""", """        if ($existe) {
            self::confirmar((int) $existe);
            return (int) $existe;
        }""", 1)
    metodo = """    /** El aviso del banco confirma que la transferencia de ese sueldo ya se hizo. */
    public static function confirmar(int $id): int
    {
        if (!$id || !Schema::hasColumn('sueldos_pagados', 'confirmado')) {
            return 0;
        }
        return DB::table('sueldos_pagados')->where('id', $id)
            ->where(function ($q) { $q->where('confirmado', false)->orWhereNull('confirmado'); })
            ->update(['confirmado' => true, 'confirmado_at' => now(), 'updated_at' => now()]);
    }

"""
    i = s.find('    /** Marca la semana como pagada')
    if i < 0: i = s.find('    public function marcarPagada')
    if i < 0: sys.exit('SueldoSemanas: no encontré marcarPagada')
    return s[:i] + metodo + s[i:]

def cs(s):
    if 'SueldoSemanas::confirmar' in s: return s
    a = "        if (!empty($res['deuda_id'])) {"
    if s.count(a) != 1: sys.exit('ConciliadorSalidas: ancla no encontrada')
    return s.replace(a, """        if (!empty($res['sueldo_pagado_id']) && ($res['estado'] ?? '') === 'calzado') {
            \\App\\Finanzas\\SueldoSemanas::confirmar((int) $res['sueldo_pagado_id']);
        }
""" + a, 1)

def fc(s):
    if 'SueldoSemanas::confirmar' in s: return s
    a = "        return back()->with('success', 'Salida asociada.');"
    if s.count(a) != 1: sys.exit('FinanzasCorreoController: ancla no encontrada')
    return s.replace(a, """        if ($request->clase === 'sueldo') {
            \\App\\Finanzas\\SueldoSemanas::confirmar((int) $request->ref);
        }
""" + a, 1)

editar('app/Finanzas/SueldoSemanas.php', ss)
editar('app/Finanzas/Correo/ConciliadorSalidas.php', cs)
editar('app/Http/Controllers/FinanzasCorreoController.php', fc)
print('Confirmación por aviso del banco: ' + (', '.join(hechos) if hechos else 'ya estaba'))
