p = 'app/Finanzas/Correo/Lector.php'
s = open(p, encoding='utf-8').read()
if 'c[óo]digo de autorizaci' not in s:
    a = "        if ($banco && self::esPublicidad($r)) {"
    if a not in s: raise SystemExit('Lector: ancla no encontrada')
    s = s.replace(a, """        if ($banco && preg_match('/(c[óo]digo de autorizaci[óo]n|clave din[áa]mica|c[óo]digo de verificaci[óo]n|c[óo]digo de seguridad)/iu', $a)) {
            return $d; // código para autorizar una operación: no mueve dinero (la transferencia llega en su propio aviso)
        }
""" + a, 1)
    open(p, 'w', encoding='utf-8').write(s)
    print('Lector: los correos de "Código de Autorización" ya no se leen como abono')
else:
    print('Lector: ya estaba')
