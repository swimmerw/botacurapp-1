# Fechas sueltas que usan los precios de temporada baja (ej. 2026-11-01 con valores de octubre).
import sys
p = sys.argv[1] + '/app/Services/BotTarifasService.php'
s = open(p, encoding='utf-8').read()
if 'param_fechas_temporada_baja' in s:
    print('SIN CAMBIO tarifas'); sys.exit(0)
a = "'param_meses_temporada_baja'    =>"
if a not in s: raise SystemExit('ERROR: falta el parche de octubre')
i = s.index(a)
s = s[:i] + "'param_fechas_temporada_baja'   => ['Fechas sueltas (AAAA-MM-DD, separadas por coma) que usan los precios de temporada baja', '2026-11-01'],\n            " + s[i:]
old = "        return $fecha && in_array((int) $fecha->month, self::lista(self::parametros()['param_meses_temporada_baja']), true);"
if old not in s: raise SystemExit('ERROR: esTemporadaBaja distinto')
new = ("        if (!$fecha) return false;\n"
       "        $extra = array_filter(array_map('trim', explode(',', (string) (self::parametros()['param_fechas_temporada_baja'] ?? ''))));\n"
       "        if (in_array(\\Carbon\\Carbon::parse($fecha)->toDateString(), $extra, true)) return true;\n"
       "        return in_array((int) $fecha->month, self::lista(self::parametros()['param_meses_temporada_baja']), true);")
s = s.replace(old, new, 1)
open(p, 'w', encoding='utf-8').write(s); print('OK tarifas')
