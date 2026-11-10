# Menu lateral: bloque nuevo "Reservaciones Bot" (10/11/2026).
# Saca "Bot de WhatsApp" de "Dia a dia" y crea el bloque con:
# Verificacion de pagos, Comprobantes sin reserva, Conversaciones del bot y Bot de WhatsApp.
import re, shutil, sys, time

F = 'resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php'
s = open(F, encoding='utf-8').read()

if "['Reservaciones Bot'" in s:
    print('El bloque Reservaciones Bot ya estaba. Sin cambios.')
    sys.exit(0)

# 1) quitar la linea de "Bot de WhatsApp" donde este (hoy en Dia a dia)
linea = re.compile(r"^[ \t]*\[\s*'Bot de WhatsApp'.*?'backoffice\.bot-configuracion\.index'.*?\],[ \t]*\n", re.M)
if len(linea.findall(s)) != 1:
    sys.exit('ERROR: no encontre una sola linea de "Bot de WhatsApp" en el menu. No se cambio nada.')
s2 = linea.sub('', s, count=1)

# 2) insertar el bloque antes de "Reservas"
ancla = re.compile(r"^([ \t]*)\[\s*'Reservas'\s*,\s*'event'\s*,\s*\[", re.M)
m = ancla.search(s2)
if not m:
    sys.exit('ERROR: no encontre el bloque "Reservas" en el menu. No se cambio nada.')
ind = m.group(1)
bloque = (
    f"{ind}['Reservaciones Bot', 'smart_toy', [\n"
    f"{ind}    ['Verificación de pagos', 'price_check', 'backoffice.verificacion-pago.index', [], $rol('admin'), 'backoffice.verificacion-pago.*'],\n"
    f"{ind}    ['Comprobantes sin reserva', 'receipt', 'backoffice.comprobantes-sin-reserva.index', [], $rol('admin'), 'backoffice.comprobantes-sin-reserva.*'],\n"
    f"{ind}    ['Conversaciones del bot', 'forum', 'backoffice.bot-conversaciones.index', [], $rol('admin'), 'backoffice.bot-conversaciones.*'],\n"
    f"{ind}    ['Bot de WhatsApp', 'smart_toy', 'backoffice.bot-configuracion.index', [], $rol('admin'), 'backoffice.bot-configuracion.*'],\n"
    f"{ind}]],\n"
)
s2 = s2[:m.start()] + bloque + s2[m.start():]

shutil.copy(F, F + '.bak.' + time.strftime('%Y%m%d%H%M'))
open(F, 'w', encoding='utf-8').write(s2)
print('Menu: bloque Reservaciones Bot agregado')
