import re, sys, shutil, time, os
# 1) La agenda la define solo el calendario (dias habilitados): agendaAbierta() siempre abierta.
# 2) Texto por defecto de regla_apertura_agenda sin "dia 20".
# 3) Guardar Configuracion del bot: un campo vacio se guarda como texto vacio (antes: error 500).
B = os.environ.get('BASE', '/var/www/html/botacurapp/')
T = B + 'app/Services/BotTarifasService.php'
P = B + 'app/Services/BotPromptService.php'
C = B + 'app/Http/Controllers/BotConfiguracionController.php'
ts = time.strftime('%Y%m%d%H%M%S')
NUEVA = ("APERTURA DE AGENDA (parejas y grupos de menos de 10 personas):\n"
         "- No hay una fecha fija de apertura: solo se pueden reservar los días habilitados en el calendario (lo verifican las acciones de disponibilidad y cotización).\n"
         "- Si una pareja o un grupo de menos de 10 personas pide un día que no está habilitado, NO prometas una fecha de apertura ni hables del día 20. "
         "Dile con amabilidad que esa fecha aún no está habilitada y ofrécele los días habilitados más cercanos; si quiere esa fecha igual, que nos vuelva a escribir más adelante.")


def guardar(f, txt):
    shutil.copy(f, f + '.bak.' + ts)
    open(f, 'w', encoding='utf-8').write(txt)


t = open(T, encoding='utf-8').read()
if 'la agenda la define el calendario' not in t:
    pat = r"(public static function agendaAbierta\(Carbon \$fecha\): array\s*\{)"
    if len(re.findall(pat, t)) != 1:
        sys.exit('ERROR: no encontre agendaAbierta(). No se cambio nada.')
    t = re.sub(pat, lambda m: m.group(1) + "\n        // 13/10/2026: la agenda la define el calendario (dias habilitados), sin fecha fija de apertura.\n        return [true, null];\n", t, count=1)
    guardar(T, t)
    print('Bot: la agenda la define solo el calendario')

p = open(P, encoding='utf-8').read()
if 'No hay una fecha fija de apertura' not in p:
    pat = r"('regla_apertura_agenda'\s*=>\s*)'APERTURA DE AGENDA \(parejas[^']*'"
    n = len(re.findall(pat, p))
    if n != 1:
        print('AVISO: el texto por defecto de regla_apertura_agenda aparece %d veces; no se toco (el bot usa el texto guardado en la base, que si se actualiza).' % n)
    else:
        p = re.sub(pat, lambda m: m.group(1) + "'" + NUEVA + "'", p, count=1)
        guardar(P, p)
        print('Bot: texto por defecto de apertura actualizado')

c = open(C, encoding='utf-8').read()
if 'campo vacio = texto vacio' not in c:
    i = c.find('function updateSecciones')
    if i < 0:
        sys.exit('ERROR: no encontre updateSecciones. Lo demas quedo aplicado.')
    j = c.find('public function', i + 20)
    seg = c[i:j if j > 0 else len(c)]
    pat = r"'contenido'\s*=>\s*(\$[A-Za-z_]+(?:\[[^\]]+\])?)\s*,"
    n = len(re.findall(pat, seg))
    if n < 1:
        sys.exit('ERROR: no encontre donde se guarda el contenido en updateSecciones. Lo demas quedo aplicado.')
    seg = re.sub(pat, lambda m: "'contenido' => (string) (" + m.group(1) + " ?? ''), // campo vacio = texto vacio", seg)
    c = c[:i] + seg + (c[j:] if j > 0 else '')
    guardar(C, c)
    print('Configuracion del bot: campos vacios ya no dan error (%d lugar/es)' % n)
