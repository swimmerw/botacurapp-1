import sys, os
# Sueldos y CSV del banco:
#  1) El CSV no vuelve a incluir semanas ya pagadas (selección y botón CSV de la semana).
#  2) Cada CSV descargado guarda sus líneas (persona, semana, monto exacto).
#  3) El aviso del banco calza contra esas líneas y marca "Pagado" solo (también con bono o lote completo).
base = sys.argv[1]
hechos = []

def editar(rel, fn):
    p = os.path.join(base, rel)
    s = open(p, encoding='utf-8').read()
    n = fn(s)
    if n != s:
        open(p, 'w', encoding='utf-8').write(n)
        hechos.append(rel.split('/')[-1])

def uno(s, a, nuevo, donde, quien):
    if s.count(a) < 1:
        sys.exit(f'{quien}: no encontré "{a.strip()[:60]}"')
    return s.replace(a, nuevo, 1)

# 1) Servicio del CSV: devuelve las líneas incluidas
def servicio(s):
    if "'lineas'" in s:
        return s
    q = 'CsvPagoBancoService'
    s = uno(s, "        $omitidos = [];\n", "        $omitidos = [];\n        $incluidos = [];\n", 0, q)
    s = uno(s, "            $filas[] = implode(';', [",
            "            $incluidos[] = ['user_id' => $user->id, 'inicio' => $item['inicio'], 'fin' => $item['fin'], 'monto' => $monto];\n\n"
            "            $filas[] = implode(';', [", 0, q)
    s = uno(s, "            'omitidos' => $omitidos,\n", "            'omitidos' => $omitidos,\n            'lineas'   => $incluidos,\n", 0, q)
    return s

# 2) Controlador: saca los ya pagados y registra las líneas de cada descarga
FILTRO = """        // Semanas ya pagadas en Remuneraciones no vuelven a salir en el CSV
        $yaPagados = [];
        if (class_exists(\\App\\Finanzas\\SueldoCsv::class)) {
            $seleccionados = array_values(array_filter($seleccionados, function ($s) use (&$yaPagados) {
                if (\\App\\Finanzas\\SueldoCsv::pagada((int) $s['user_id'], $s['inicio'], $s['fin'])) {
                    $yaPagados[] = optional(User::find($s['user_id']))->name ?: 'usuario ' . $s['user_id'];
                    return false;
                }
                return true;
            }));
        }
        if (empty($seleccionados)) {
            return back()->with('error', 'No hay nada que exportar: ' . ($yaPagados ? 'todo lo seleccionado ya está pagado.' : 'no hay sueldos.'));
        }

        $resultado = $csvService->generar($seleccionados);
        foreach ($yaPagados as $n) {
            $resultado['omitidos'][] = ['user' => (object) ['name' => $n], 'motivo' => 'ya pagado'];
        }
"""
REGISTRO = """        if (!empty($resultado['lineas']) && class_exists(\\App\\Finanzas\\SueldoCsv::class)) {
            \\App\\Finanzas\\SueldoCsv::registrar($resultado['lineas']);
        }

        $nombreArchivo = 'pago_sueldos_"""

def controlador(s):
    if 'SueldoCsv::' in s:
        return s
    a = "        $resultado = $csvService->generar($seleccionados);\n"
    if s.count(a) != 2:
        sys.exit(f'SueldoController: esperaba 2 exportaciones de CSV y encontré {s.count(a)}')
    s = s.replace(a, FILTRO)
    b = "        $nombreArchivo = 'pago_sueldos_"
    if s.count(b) != 2:
        sys.exit('SueldoController: no encontré el nombre del archivo CSV en las 2 exportaciones')
    return s.replace(b, REGISTRO)

# 3) Conciliador de salidas: calza el aviso del banco con las líneas del CSV
BUSCAR = """        // Sueldo pagado con el CSV del banco: calza con la línea exportada (persona + monto exacto, o el lote completo)
        if ($c->tipo === 'pago' && class_exists(\\App\\Finanzas\\SueldoCsv::class)) {
            $comoCsv = null;
            $pCsv = $this->persona($c, $comoCsv);
            $hit = \\App\\Finanzas\\SueldoCsv::buscar($pCsv ? (int) $pCsv->id : null, (int) $c->monto, $c->fecha ?: date('Y-m-d'));
            if ($hit) {
                if (($c->seguridad ?? 'seguro') !== 'seguro') {
                    return ['estado' => 'revisar', 'nota' => str_replace(': marcada pagada', '', $hit['nota']) . ', pero el correo no pasó la verificación del banco'];
                }
                return ['estado' => 'calzado', 'vinculo' => 'sueldo', 'pagar_csv' => $hit['ids'], 'nota' => $hit['nota']];
            }
        }

        $cands = $this->candidatos($c);"""
MARCAR = """        if (!empty($res['pagar_csv']) && class_exists(\\App\\Finanzas\\SueldoCsv::class)) {
            // Marca "Pagado" las semanas del CSV con la fecha de la transferencia
            $res['sueldo_pagado_id'] = \\App\\Finanzas\\SueldoCsv::marcar($res['pagar_csv'], $c->fecha ?: date('Y-m-d'), (int) $c->id);
        }
        if (!empty($res['pagar_semana'])) {"""

def conciliador(s):
    if 'SueldoCsv::' in s:
        return s
    q = 'ConciliadorSalidas'
    s = uno(s, "        $cands = $this->candidatos($c);", BUSCAR, 0, q)
    s = uno(s, "        if (!empty($res['pagar_semana'])) {", MARCAR, 0, q)
    return s

editar('app/Services/CsvPagoBancoService.php', servicio)
editar('app/Http/Controllers/SueldoController.php', controlador)
editar('app/Finanzas/Correo/ConciliadorSalidas.php', conciliador)
print('Sueldos CSV: ' + (', '.join(hechos) if hechos else 'ya estaba'))
