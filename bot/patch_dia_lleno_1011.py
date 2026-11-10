# Bot-Acura: un día habilitado pero con los turnos de tinaja completos se informa como "sin cupos", no como "no habilitado" (10/11/2026)
import sys
BC = 'app/Http/Controllers/Api/BotController.php'
s = open(BC, encoding='utf-8').read()
if 'patch_dia_lleno_1011' in s:
    print('ya estaba aplicado'); sys.exit(0)
viejo = "                    . ($hab ? ', habilitado en el calendario' : ', NO habilitado en el calendario') . $extra;"
if s.count(viejo) != 1:
    sys.exit('No encontré la línea de fechas (¿falta aplicar_bot_orden.sh?)')
nuevo = """                    . ($hab
                        ? (empty(app(\\App\\Services\\DisponibilidadService::class)->slotsTinaja($iso, 1)['ok'])
                            ? ', habilitado pero SIN CUPOS: los turnos de tinaja están completos (día cerrado por capacidad; dilo así, nunca que no está habilitado)'
                            : ', habilitado en el calendario')
                        : ', NO habilitado en el calendario') . $extra; // patch_dia_lleno_1011"""
s = s.replace(viejo, nuevo, 1)
open(BC, 'w', encoding='utf-8').write(s)
print('Fechas llenas: el bot dirá "sin cupos"')
