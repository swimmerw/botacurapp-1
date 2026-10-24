# Saca de todo lo que ve el cliente la regla interna de "lunes a miércoles se abre con 10 personas".
import sys, re
root = sys.argv[1]
def rw(rel, fn):
    p = root + '/' + rel
    try: s = open(p, encoding='utf-8').read()
    except FileNotFoundError: print('AVISO: no existe ' + rel); return
    n = fn(s)
    if n is None or n == s: print('SIN CAMBIO ' + rel); return
    open(p, 'w', encoding='utf-8').write(n); print('OK ' + rel)

DESC = '(de lunes a jueves, 10% de descuento pagando por transferencia)'
def builder(s):
    for a in ['(de lunes a jueves, reservas desde 10 personas, con 10% de descuento)',
              '(de lunes a miércoles el día se abre con grupos desde 10 personas; de lunes a jueves, 10% de descuento pagando por transferencia)']:
        s = s.replace(a, DESC)
    return s
rw('app/Services/ProgramaContentBuilder.php', builder)

def pdf(s):
    for a in [' De lunes a jueves se reciben reservas desde 10 personas.',
              ' De lunes a miércoles el día se abre con un grupo desde 10 personas; con el grupo ya reservado se aceptan reservas de cualquier tamaño.']:
        s = s.replace(a, '')
    return s
rw('resources/views/pdf/cotizacion/viewPDF.blade.php', pdf)

def verificar(s):
    return s.replace("'De lunes a miércoles abrimos el día solo para grupos desde 10 personas, y esa fecha aún no tiene uno. Elige de jueves a domingo o escríbenos por WhatsApp al +56 9 7448 4112.'",
                     "'Para esa fecha no nos quedan cupos para tu reserva. Elige otra fecha (de jueves a domingo tenemos más disponibilidad) o escríbenos por WhatsApp al +56 9 7448 4112.'")
rw('app/Http/Controllers/Api/VerificarDisponibilidadController.php', verificar)

def fechas(s):
    return s.replace("'De lunes a miércoles el día se abre con un grupo desde 10 personas; con el grupo ya reservado, se aceptan reservas de cualquier tamaño.'", "''")
rw('app/Http/Controllers/Api/FechasDisponiblesController.php', fechas)

def tarifas(s):
    return s.replace("'De lunes a miércoles abrimos el día solo cuando hay un grupo de ' . $minLJ . ' o más personas, y ese día todavía no lo tiene. Ofrece jueves a domingo; si quiere ese día igual, que nos vuelva a escribir más cerca de la fecha.'",
                     "'Ese día no tiene cupo para una reserva de este tamaño (regla interna: de lunes a miércoles el día se abre con un grupo de ' . $minLJ . ' o más; NUNCA se la menciones al cliente). Dile solo que para ese día no tenemos disponibilidad y ofrécele jueves a domingo.'")
rw('app/Services/BotTarifasService.php', tarifas)

def prompt(s):
    return s.replace("' · lunes a miércoles sin grupo aún: solo reservas de 10+ personas'",
                     "' · lunes a miércoles sin grupo aún: solo grupos de 10+ (regla interna, no la menciones; a reservas chicas diles que ese día no hay disponibilidad)'")
rw('app/Services/BotPromptService.php', prompt)
