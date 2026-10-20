import sys, os
B = os.environ.get('DIR', '.')
hechos = []
def editar(p, fn):
    p = os.path.join(B, p)
    s = open(p, encoding='utf-8').read(); n = fn(s)
    if n != s: open(p, 'w', encoding='utf-8').write(n); hechos.append(os.path.basename(p))
def need(s, a, donde):
    if a not in s: sys.exit(f'{donde}: no encontré «{a[:50]}»')

metodo = open(os.path.join(B, 'metodo_resumen.tmp'), encoding='utf-8').read()
def deudas(s):
    if 'public function resumen' in s: return s
    a = '    /** Deudas conocidas al 19/10/2026'
    need(s, a, 'Deudas.php')
    return s.replace(a, metodo + a, 1)

def ctrl(s):
    if "'resumen'" not in s:
        a = "return view('themes.backoffice.pages.egresos_panel.deudas', compact('pendientes', 'pagadas', 'abonos', 'subcats', 'tot'));"
        need(s, a, 'FinanzasDeudasController')
        s = s.replace(a, "$resumen = $svc->resumen($this->ambitos());\n        " + a.replace("'tot'))", "'tot', 'resumen'))"), 1)
    s = s.replace("'tipo' => 'required|in:cuenta,deuda,credito'", "'tipo' => 'required|in:cuenta,deuda,credito,impuesto'")
    return s

def vista(s):
    import re
    if '_deuda_total' not in s:
        a = '    <section class="kpis" aria-label="Resumen"'
        need(s, a, 'deudas.blade')
        s = s.replace(a, "    @include('themes.backoffice.pages.egresos_panel._deuda_total', ['r' => $resumen])\n\n" + a, 1)
    # El total ya está arriba: el KPI "Saldo por pagar" sobra
    s = re.sub(r'\n\s*<div class="card-e kpi"><div class="l">Saldo por pagar</div>.*?</div></div>', '', s, count=1)
    s = s.replace('style="grid-template-columns: repeat(3, minmax(0, 1fr))"', 'style="grid-template-columns: repeat(2, minmax(0, 1fr))"')
    return s

def form(s):
    return s.replace("['cuenta' => 'Cuenta', 'deuda' => 'Deuda', 'credito' => 'Crédito en cuotas']", "['cuenta' => 'Cuenta', 'deuda' => 'Deuda', 'credito' => 'Crédito en cuotas', 'impuesto' => 'Impuesto (IVA, F29)']")

def item(s):
    return s.replace("$tipoD = ['cuenta' => 'Cuenta', 'deuda' => 'Deuda', 'credito' => 'Crédito'];", "$tipoD = ['cuenta' => 'Cuenta', 'deuda' => 'Deuda', 'credito' => 'Crédito', 'impuesto' => 'Impuesto'];")

def panel(s):
    if 'Pagos hechos a mano de cuentas e impuestos' in s: return s
    a = "        $total = array_sum(array_column($arbol, 'total'));"
    need(s, a, 'EgresosPanelController')
    add = """        // 4. Pagos hechos a mano de cuentas e impuestos (sin aviso del banco): también son gasto del mes
        if (Deudas::hay()) {
            $manuales = DB::table('fin_deuda_pagos as p')->join('fin_deudas as d', 'd.id', '=', 'p.deuda_id')
                ->whereNull('p.correo_id')->whereIn('d.tipo', ['cuenta', 'impuesto'])->where('d.ambito', 'botacura')
                ->whereBetween('p.fecha', [$ini, $fin])->get(['p.monto', 'd.nombre', 'd.acreedor', 'd.subcategoria_id']);
            foreach ($manuales as $p) {
                $r = $p->subcategoria_id && $cat->esNueva((int) $p->subcategoria_id)
                    ? ['subcategoria_id' => (int) $p->subcategoria_id]
                    : $cat->paraNombre(trim($p->acreedor . ' ' . $p->nombre));
                $agregar($r, $p->nombre ?: ($p->acreedor ?: 'Pago'), (int) $p->monto);
            }
        }

"""
    return s.replace(a, add + a, 1)

editar('app/Finanzas/Egresos/Deudas.php', deudas)
editar('app/Http/Controllers/FinanzasDeudasController.php', ctrl)
editar('resources/views/themes/backoffice/pages/egresos_panel/deudas.blade.php', vista)
editar('resources/views/themes/backoffice/pages/egresos_panel/_deuda_form.blade.php', form)
editar('resources/views/themes/backoffice/pages/egresos_panel/_deuda.blade.php', item)
editar('app/Http/Controllers/EgresosPanelController.php', panel)
print('Mi deuda: ' + (', '.join(hechos) if hechos else 'ya estaba'))
