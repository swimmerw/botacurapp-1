import sys, re
base = sys.argv[1]
def rw(p, fn):
    path = base + '/' + p
    s = open(path, encoding='utf-8').read(); n = fn(s)
    if n is None: print('SIN CAMBIO', p); return
    open(path, 'w', encoding='utf-8').write(n); print('OK', p)

def tarifas(s):
    if 'lunes_jueves_minimo' in s: return None
    # parámetro nuevo
    a = "'param_horas_silencio_humano'"
    i = s.find(a)
    if i < 0: raise SystemExit('ERROR: Tarifas sin parametros esperados')
    s = s[:i] + "'param_min_personas_lun_jue'   => ['Mínimo de personas por reserva de lunes a jueves (feriados excluidos)', '10'],\n            " + s[i:]
    # programas S: lunes a jueves (antes jueves y viernes)
    m = re.search(r"if \(\$dow > 5\) \{\s*\$err\('semana_solo_lunes_viernes'.*?\} elseif \(\$dow <= 3\) \{.*?\$err\('semana_feriado'[^;]*;\s*\} elseif", s, re.S)
    if not m: raise SystemExit('ERROR: bloque de programas S distinto')
    s = s[:m.start()] + "if ($dow > 4) {\n                        $err('semana_solo_lunes_jueves', 'Los programas de semana (S) son solo de lunes a jueves.');\n                    } elseif" + s[m.end():]
    # mínimo de personas de lunes a jueves
    b = "            // Cupo de masajes del día"
    if b not in s: raise SystemExit('ERROR: no encontre donde agregar el minimo lun-jue')
    regla = ("            // Lunes a jueves: solo reservas desde N personas (feriados excluidos)\n"
             "            $minLJ = self::entero('param_min_personas_lun_jue');\n"
             "            if ($fecha && $minLJ > 0 && $fecha->dayOfWeekIso <= 4 && $personas > 0 && $personas < $minLJ\n"
             "                && !in_array($fecha->toDateString(), self::feriados(), true)) {\n"
             "                $err('lunes_jueves_minimo', 'De lunes a jueves recibimos reservas desde ' . $minLJ . ' personas. Para menos personas ofrece viernes, sábado o domingo.');\n"
             "            }\n")
    return s.replace(b, regla + b, 1)
rw('app/Services/BotTarifasService.php', tarifas)

def prompt(s):
    if 'solo reservas de 10+' in s: return None
    a = "$semana = $c->dayOfWeekIso >= 4 && $c->dayOfWeekIso <= 5 && !$feriado"
    s = s.replace('solo jueves y viernes que no sean feriado (mira en la lista de fechas si aplica ese día)', 'solo lunes a jueves que no sean feriado y para reservas de 10+ personas (mira en la lista de fechas si aplica ese día)')
    if a not in s:
        print('AVISO: etiquetaFecha no encontrada; el bot usa las reglas de texto'); return s
    s = s.replace(a, "$semana = $c->dayOfWeekIso <= 4 && !$feriado", 1)
    s = s.replace("'temporada de verano (con recargo de piscina)'", "'temporada de verano'", 1)
    s = s.replace("' · programas S (10% off): ' . ($semana ? 'SÍ aplican' : 'no aplican');",
                  "' · programas S (10% off): ' . ($semana ? 'SÍ aplican' : 'no aplican')\n                . ($c->dayOfWeekIso <= 4 && !$feriado ? ' · lunes a jueves: solo reservas de 10+ personas' : '');", 1)
    return s
rw('app/Services/BotPromptService.php', prompt)
