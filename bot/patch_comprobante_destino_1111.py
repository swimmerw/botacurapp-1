# Comprobantes (11/11/2026): si el destinatario es Botacura pero el RUT o la cuenta leídos no calzan, va a revisión
# del equipo en vez de rechazarse (la lectura de la imagen puede confundir la cuenta de origen con la de destino).
import sys
W = 'app/Http/Controllers/Api/WhatsAppWebhookController.php'
s = open(W, encoding='utf-8').read()
if 'patch_comprobante_destino_1111' in s:
    print('Comprobantes: ya estaba'); sys.exit(0)
a = "        if ($rutMal || $cuentaMal || $nombreMal) {\n            $rechazo[] = 'la transferencia no fue hecha a la cuenta de Botacura'"
if s.count(a) != 1:
    sys.exit('Comprobantes: no encontré la regla del destinatario; no se cambió')
s = s.replace(a, """        $esBotacura = $nombre && strpos($texto($nombre), 'BOTACUR') !== false; // patch_comprobante_destino_1111
        if ($esBotacura && !$nombreMal && ($rutMal || $cuentaMal)) {
            $duda[] = 'el destinatario es Botacura, pero el RUT o la cuenta leídos no calzan: revisar la imagen';
        } elseif ($rutMal || $cuentaMal || $nombreMal) {
            $rechazo[] = 'la transferencia no fue hecha a la cuenta de Botacura'""", 1)
open(W, 'w', encoding='utf-8').write(s)
print('Comprobantes: destinatario Botacura con dato dudoso -> revisión del equipo')
