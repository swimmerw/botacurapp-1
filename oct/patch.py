import sys
base = sys.argv[1]
def rw(p, fn):
    path = base + '/' + p
    s = open(path, encoding='utf-8').read(); n = fn(s)
    if n is None: print('SIN CAMBIO', p); return
    open(path, 'w', encoding='utf-8').write(n); print('OK', p)

def tarifas(s):
    if 'param_meses_temporada_baja' in s: return None
    a = "'param_min_personas_lun_jue'"
    i = s.find(a)
    if i < 0: raise SystemExit('ERROR: falta el parche de noviembre (aplicar_noviembre.sh)')
    s = s[:i] + ("'param_meses_temporada_baja'    => ['Meses de temporada baja: precios de la lista de abajo, programas S jueves y viernes y sin mínimo de lunes a jueves', '3,4,5,6,7,8,9,10'],\n            "
                 "'param_precios_temporada_baja'  => ['Precios por persona en temporada baja (ID:valor, separados por coma). Los programas que no estén usan su valor normal', '39:45000,1:50000,28:70000,48:80000,2:95000,45:40500,43:45000,49:64000,3:73000'],\n            ") + s[i:]
    helper = '''
    /** ¿La fecha cae en temporada baja? */
    public static function esTemporadaBaja($fecha): bool
    {
        return $fecha && in_array((int) $fecha->month, self::lista(self::parametros()['param_meses_temporada_baja']), true);
    }

    /** Precio de temporada baja de un programa, o null si no tiene. */
    public static function precioTemporadaBaja(int $programaId): ?int
    {
        foreach (array_filter(array_map('trim', explode(',', (string) self::parametros()['param_precios_temporada_baja']))) as $par) {
            if (preg_match('/^(\\d+)\\s*:\\s*(\\d+)$/', $par, $m) && (int) $m[1] === $programaId) {
                return (int) $m[2];
            }
        }
        return null;
    }

'''
    k = '    public static function cotizar('
    j = s.rfind('\n', 0, s.rfind('/**', 0, s.find(k))) + 1
    s = s[:j] + helper.lstrip('\n') + s[j:]
    # programas S
    o = "                    if ($dow > 4) {\n                        $err('semana_solo_lunes_jueves', 'Los programas de semana (S) son solo de lunes a jueves.');"
    if o not in s: raise SystemExit('ERROR: bloque S distinto')
    s = s.replace(o, "                    if (self::esTemporadaBaja($fecha) && ($dow < 4 || $dow > 5)) {\n                        $err('semana_solo_jueves_viernes', 'En esta fecha los programas de semana (S) son solo jueves y viernes.');\n                    } elseif (!self::esTemporadaBaja($fecha) && $dow > 4) {\n                        $err('semana_solo_lunes_jueves', 'Los programas de semana (S) son solo de lunes a jueves.');", 1)
    o2 = "if ($fecha && $minLJ > 0 && $fecha->dayOfWeekIso <= 4"
    if o2 not in s: raise SystemExit('ERROR: minimo lun-jue distinto')
    s = s.replace(o2, "if ($fecha && $minLJ > 0 && !self::esTemporadaBaja($fecha) && $fecha->dayOfWeekIso <= 4", 1)
    o3 = "        $valorLista    = $programa ? (int) $programa->valor_programa : 0;"
    if o3 not in s: raise SystemExit('ERROR: calculo de valor distinto')
    s = s.replace(o3, o3 + "\n        if ($programa && self::esTemporadaBaja($fecha) && self::precioTemporadaBaja($programaId) !== null) {\n            $valorLista = self::precioTemporadaBaja($programaId);\n            $notas[] = 'Valor de temporada baja (' . $fecha->locale('es')->isoFormat('MMMM') . ').';\n        }", 1)
    return s
rw('app/Services/BotTarifasService.php', tarifas)

def prompt(s):
    if 'esTemporadaBaja' in s: return None
    a = "$semana = $c->dayOfWeekIso <= 4 && !$feriado"
    if a not in s: print('AVISO: etiquetaFecha no encontrada'); return None
    s = s.replace(a, "$baja = \\App\\Services\\BotTarifasService::esTemporadaBaja($c);\n            $semana = ($baja ? ($c->dayOfWeekIso >= 4 && $c->dayOfWeekIso <= 5) : $c->dayOfWeekIso <= 4) && !$feriado", 1)
    s = s.replace("($c->dayOfWeekIso <= 4 && !$feriado ? ' · lunes a jueves: solo reservas de 10+ personas' : '')",
                  "(!$baja && $c->dayOfWeekIso <= 4 && !$feriado ? ' · lunes a jueves: solo reservas de 10+ personas' : '') . ($baja ? ' · valores de temporada baja (los da cotizar)' : '')", 1)
    return s
rw('app/Services/BotPromptService.php', prompt)
