# Avisos de WhatsApp con entrega garantizada (10/11/2026).
#  1) VerificacionPagoController::notificarCliente usa WaEnviosService (registro, plantilla fuera de 24 h, cola)
#  2) Webhook: entrega los estados de Meta y los mensajes entrantes a WaEnviosService (no cambia el flujo del bot)
#  3) Verificacion de pagos: recuadro de avisos enviados y reloj de 24 h en cada comprobante
# Revisa todo antes de cambiar nada: si algo no calza, no toca ningun archivo.
import re, sys, shutil, time
B = '/var/www/html/botacurapp/'
ts = time.strftime('%Y%m%d%H%M%S')
NUEVO = open('/tmp/wae_notificar.php', encoding='utf-8').read()

VC = B + 'app/Http/Controllers/VerificacionPagoController.php'
WH = B + 'app/Http/Controllers/Api/WhatsAppWebhookController.php'
VV = B + 'resources/views/themes/backoffice/pages/verificacion_pago/index.blade.php'
vc, wh, vv = (open(f, encoding='utf-8').read() for f in (VC, WH, VV))
cambios = {}

# 1) Controlador de verificacion
if 'WaEnviosService' not in vc:
    i = vc.find('    private function notificarCliente(int $reservaId, bool $aprobado)')
    j = vc.find('    private function enviarMensajeWhatsApp(', i)
    if i < 0 or j < 0 or vc.count('function notificarCliente') != 1:
        sys.exit('ERROR: VerificacionPagoController cambio de forma (notificarCliente). No se cambio nada.')
    cambios[VC] = vc[:i] + NUEVO + vc[j:]

# 2) Webhook
if 'WaEnviosService::webhook' not in wh:
    m = re.search(r"^([ \t]*)\$value\s*=\s*\$change\s*\[\s*['\"]value['\"]\s*\][^;\n]*;[ \t]*$", wh, re.M) \
        or re.search(r"^([ \t]*)\$value\s*=\s*[^;\n]*\[\s*['\"]value['\"]\s*\][^;\n]*;[ \t]*$", wh, re.M)
    if not m:
        print('No encontre donde se lee "value" en el webhook. Primeras lineas de handle():')
        k = wh.find('function handle')
        print('\n'.join(wh[k:].split('\n')[:45]) if k >= 0 else '(no hay function handle)')
        sys.exit('ERROR: no se cambio nada. Pega esta salida en el chat.')
    ind = m.group(1)
    linea = (f"\n{ind}try {{ \\App\\Services\\WaEnviosService::webhook((array) $value); }}"
             f" catch (\\Throwable $__e) {{ \\Log::warning('[WaEnvios] webhook: ' . $__e->getMessage()); }}")
    cambios[WH] = wh[:m.end()] + linea + wh[m.end():]

# 3) Vista de verificacion
INC = "@include('themes.backoffice.pages.verificacion_pago._entregas')"
if INC not in vv:
    aviso = "@include('themes.backoffice.pages.comprobantes-sin-reserva._aviso')"
    if aviso in vv:
        k = vv.find(aviso) + len(aviso)
        cambios[VV] = vv[:k] + "\n" + INC + vv[k:]
    else:
        m = re.search(r"@section\(\s*'content'\s*\)\s*\n", vv)
        if not m:
            sys.exit("ERROR: no encontre @section('content') en Verificacion de pagos. No se cambio nada.")
        cambios[VV] = vv[:m.end()] + INC + "\n" + vv[m.end():]

for f, t in cambios.items():
    shutil.copy(f, f + '.bak.' + ts)
    open(f, 'w', encoding='utf-8').write(t)
    print('Actualizado: ' + f.replace(B, ''))
print('BAK ' + ts)
print('INSTALADO')
