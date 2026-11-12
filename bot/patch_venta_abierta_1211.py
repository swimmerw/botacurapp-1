"""Reservas del bot con la venta ABIERTA (12/11/2026), igual que las de la app: diferencia_programa = NULL
(se llena al cerrar la venta) y el saldo por cobrar en total_pagar. Antes el bot ponía el saldo en
diferencia_programa y Gestión Consumo las mostraba como cerradas."""
import sys, glob
MARCA = 'patch_venta_abierta_1211'
hechos = []

def tocar(path, pares, nombre):
    try:
        s = open(path, encoding='utf-8').read()
    except FileNotFoundError:
        print(nombre + ': no existe, se omite'); return
    if MARCA in s:
        print(nombre + ': ya estaba'); return
    for viejo, nuevo, todos in pares:
        if viejo not in s:
            sys.exit(nombre + ': no encontré "' + viejo[:60] + '". No se cambió nada en este archivo.')
        s = s.replace(viejo, nuevo) if todos else s.replace(viejo, nuevo, 1)
    s = s.replace('<?php', '<?php // ' + MARCA, 1)
    open(path, 'w', encoding='utf-8').write(s)
    hechos.append(nombre)

tocar('app/Http/Controllers/Api/BotReservaController.php', [
    ("'diferencia_programa'       => $diferencia,", "'diferencia_programa'       => null, // venta abierta: el saldo va en total_pagar", True),
], 'BotReservaController')

tocar('app/Http/Controllers/Api/BotController.php', [
    ("->first(['r.id', 'v.abono_programa', 'v.diferencia_programa', 'v.webpay_url']);", "->first(['r.id', 'v.abono_programa', 'v.diferencia_programa', 'v.total_pagar', 'v.webpay_url']);", False),
    ("$total = (int) $r->abono_programa + (int) $r->diferencia_programa;", "$total = (int) $r->abono_programa + (int) ($r->diferencia_programa ?? $r->total_pagar);", False),
    ("'diferencia_formato'  => '$' . number_format((int) $r->diferencia_programa, 0, ',', '.'),", "'diferencia_formato'  => '$' . number_format((int) ($r->diferencia_programa ?? $r->total_pagar), 0, ',', '.'),", False),
    ("$saldo  = (int) ($r->diferencia_programa ?? 0);", "$saldo  = $r->diferencia_programa === null ? (int) ($r->total_pagar ?? 0) : 0;", False),
    ("($r->diferencia_programa !== null ? ', saldo pendiente para el día de la visita: $'", "($saldo > 0 ? ', saldo pendiente para el día de la visita: $'", False),
], 'BotController')

for f in glob.glob('app/Http/Controllers/*ReservasDelDia*Controller.php') + glob.glob('app/Http/Controllers/*/*ReservasDelDia*Controller.php'):
    tocar(f, [
        ("'diferencia_programa' => max(0, $total - $abono),", "'diferencia_programa' => null,", False),
        ("$totalValor += $abono + (int) $r->diferencia_programa;", "$totalValor += $abono + (int) ($r->diferencia_programa ?? $r->total_pagar);", False),
    ], 'Verificación de pagos')

print('Código: ' + (', '.join(hechos) if hechos else 'sin cambios'))
