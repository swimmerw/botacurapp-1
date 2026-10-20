import re, sys
p = 'app/Services/BotPromptService.php'
s = open(p, encoding='utf-8').read()
cambios = []

# 1) Lista de programas: el texto viejo decía "de marzo a octubre"
viejo = "solo lunes a viernes no feriados de marzo a octubre, solo transferencia, no reagendable"
nuevo = "solo jueves y viernes que no sean feriado (mira en la lista de fechas si aplica ese día), solo transferencia, no reagendable"
if viejo in s:
    s = s.replace(viejo, nuevo); cambios.append('programas S sin "marzo a octubre"')

# 2) Cada fecha de la lista lleva temporada y si aplican programas S (calculado, no deducido)
linea = "$diasOperativosProximos[] = \\Carbon\\Carbon::parse($f)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') . ' -> ' . $f;"
if linea in s:
    s = s.replace(linea, "$diasOperativosProximos[] = self::etiquetaFecha($f);")
    s = s.replace("array_slice($fechasData['fechas'] ?? [], 0, 10)", "array_slice($fechasData['fechas'] ?? [], 0, 16)")
    cambios.append('fechas con temporada y programas S')
if 'function etiquetaFecha' not in s:
    metodo = '''    /** Temporada y programas S de una fecha, calculados aquí para que el modelo no los deduzca. */
    public static function etiquetaFecha(string $f): string
    {
        $c = \\Carbon\\Carbon::parse($f, 'America/Santiago');
        $txt = $c->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') . ' -> ' . $f;
        try {
            $p = \\App\\Services\\BotTarifasService::parametros();
            $feriado = in_array($c->toDateString(), \\App\\Services\\BotTarifasService::feriados(), true);
            $verano = in_array($c->month, \\App\\Services\\BotTarifasService::lista($p['param_meses_verano'] ?? '11,12,1,2'), true);
            $semana = $c->dayOfWeekIso >= 4 && $c->dayOfWeekIso <= 5 && !$feriado
                && in_array($c->month, \\App\\Services\\BotTarifasService::lista($p['param_meses_semana'] ?? '1,2,3,4,5,6,7,8,9,10,11,12'), true);
            $txt .= ($feriado ? ' · feriado' : '')
                . ' · ' . ($verano ? 'temporada de verano (con recargo de piscina)' : 'temporada regular (precio de lista, sin recargo)')
                . ' · programas S (10% off): ' . ($semana ? 'SÍ aplican' : 'no aplican');
        } catch (\\Throwable $e) {
        }
        return $txt;
    }

'''
    i = s.find('    /** Reglas comerciales editables')
    if i < 0:
        i = s.find('    public static function reglasDefault')
    if i < 0:
        sys.exit('No encontré dónde agregar etiquetaFecha')
    s = s[:i] + metodo + s[i:]

# 3) Instrucción: no deducir temporada ni descuento
ancla = "necesitas mencionar NO aparece en esta lista."
regla = ancla + """
La temporada (regular o verano) y si aplican los programas S de cada fecha vienen escritos en esta
lista: úsalos tal cual. Octubre completo es temporada regular; el verano parte el 1 de noviembre.
Para una fecha que no está en la lista, usa la acción "cotizar"; nunca lo deduzcas tú."""
if ancla in s and 'vienen escritos en esta' not in s:
    s = s.replace(ancla, regla, 1); cambios.append('regla de no deducir temporada')

# 4) Niños en programas para 2 personas
ancla2 = "- Tuteo chileno: nunca voseo"
regla2 = """- NIÑOS EN PROGRAMAS PARA 2 PERSONAS: no se permiten niños, de ninguna edad, en los programas o planes
  para 2 personas. Esos programas incluyen servicios como masajes y tinajas que los menores no pueden tomar,
  y llevar un niño complica que el servicio se haga de la mejor forma. Explícalo así, con amabilidad, sin
  ofrecer excepciones; si el cliente insiste, usa escalar_humano.
"""
if ancla2 in s and 'NIÑOS EN PROGRAMAS PARA 2 PERSONAS' not in s:
    s = s.replace(ancla2, regla2 + ancla2, 1); cambios.append('niños en programas de 2')

open(p, 'w', encoding='utf-8').write(s)
print('Prompt: ' + (', '.join(cambios) if cambios else 'ya estaba al día'))
