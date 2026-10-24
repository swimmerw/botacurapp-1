import sys, re
base = sys.argv[1]
def rw(p, fn):
    path = base + '/' + p
    s = open(path, encoding='utf-8').read(); n = fn(s)
    if n is None: print('SIN CAMBIO', p); return
    open(path, 'w', encoding='utf-8').write(n); print('OK', p)

# 1) Regla: lunes a miércoles solo grupos de 10+, salvo que el día ya tenga 10+ reservados
def tarifas(s):
    if 'function permiteReservaChica' in s: return None
    a = "'param_min_personas_lun_jue'"
    i = s.find(a)
    if i < 0: raise SystemExit('ERROR: faltan los parches de noviembre/octubre')
    s = s[:i] + "'param_dias_solo_grupos'        => ['Días que se abren solo con un grupo (1=lunes … 7=domingo). Con el grupo ya reservado se aceptan reservas chicas', '1,2,3'],\n            " + s[i:]
    s = s.replace("['Mínimo de personas por reserva de lunes a jueves (feriados excluidos)', '10']", "['Personas para abrir un día de lunes a miércoles (una reserva de ese tamaño, o la suma del día)', '10']")
    helper = '''    /** Personas ya reservadas ese día (reservas activas + compras web aún sin procesar). */
    public static function personasDelDia(string $fecha): int
    {
        $q = DB::table('reservas')->whereRaw('DATE(fecha_visita) = ?', [$fecha]);
        if (\\Illuminate\\Support\\Facades\\Schema::hasColumn('reservas', 'estado')) {
            $q->where(function ($w) { $w->whereNull('estado')->orWhereNotIn('estado', ['cancelada', 'no_show', 'pendiente_pago']); });
        }
        $n = (int) $q->sum('cantidad_personas');
        try {
            $n += (int) DB::table('woocommerce_orders')->whereNull('reserva_id')->where('procesado', 'pendiente')
                ->whereRaw('DATE(fecha_visita_wc) = ?', [$fecha])->sum('cantidad_personas');
        } catch (\\Throwable $e) {
        }
        return $n;
    }

    /** ¿Se puede reservar $personas ese día? Lunes a miércoles: solo grupos de N+, o si el día ya tiene N+ reservados. */
    public static function permiteReservaChica($fecha, int $personas): bool
    {
        $min = self::entero('param_min_personas_lun_jue');
        if (!$fecha || $min <= 0 || $personas >= $min || self::esTemporadaBaja($fecha)) {
            return true;
        }
        if (!in_array((int) $fecha->dayOfWeekIso, self::lista(self::parametros()['param_dias_solo_grupos']), true)
            || in_array($fecha->toDateString(), self::feriados(), true)) {
            return true;
        }
        return self::personasDelDia($fecha->toDateString()) >= $min;
    }

'''
    k = '    /** ¿La fecha cae en temporada baja? */'
    if k not in s: raise SystemExit('ERROR: falta el parche de octubre')
    s = s.replace(k, helper + k, 1)
    m = re.search(r"            if \(\$fecha && \$minLJ > 0 && !self::esTemporadaBaja\(\$fecha\) && \$fecha->dayOfWeekIso <= 4 && \$personas > 0 && \$personas < \$minLJ\n\s*&& !in_array\(\$fecha->toDateString\(\), self::feriados\(\), true\)\) \{\n\s*\$err\('lunes_jueves_minimo', [^\n]*\n", s)
    if not m: raise SystemExit('ERROR: bloque del mínimo distinto')
    s = s[:m.start()] + ("            if ($fecha && $personas > 0 && !self::permiteReservaChica($fecha, $personas)) {\n"
        "                $err('dia_solo_grupos', 'De lunes a miércoles abrimos el día solo cuando hay un grupo de ' . $minLJ . ' o más personas, y ese día todavía no lo tiene. Ofrece jueves a domingo; si quiere ese día igual, que nos vuelva a escribir más cerca de la fecha.');\n") + s[m.end():]
    return s
rw('app/Services/BotTarifasService.php', tarifas)

def prompt(s):
    a = "(!$baja && $c->dayOfWeekIso <= 4 && !$feriado ? ' · lunes a jueves: solo reservas de 10+ personas' : '')"
    if a not in s: print('AVISO: etiqueta de fecha distinta (sin cambio)'); return None
    b = ("(!$baja && $c->dayOfWeekIso <= 3 && !$feriado ? (\\App\\Services\\BotTarifasService::permiteReservaChica($c, 1)"
         " ? ' · día ya abierto por un grupo: se aceptan reservas de cualquier tamaño' : ' · lunes a miércoles sin grupo aún: solo reservas de 10+ personas') : '')")
    return s.replace(a, b, 1)
rw('app/Services/BotPromptService.php', prompt)

# 2) Web: fechas del calendario y validación al pagar
def verificar(s):
    if 'permiteReservaChica' in s: return None
    a = "        if ($wcProductId) {"
    if a not in s: raise SystemExit('ERROR: verificar-disponibilidad distinto')
    b = ("        // Lunes a miércoles: solo grupos de 10+, o si el día ya tiene un grupo reservado\n"
         "        if (!\\App\\Services\\BotTarifasService::permiteReservaChica(\\Carbon\\Carbon::parse($fecha), $cantidad)) {\n"
         "            return response()->json(['disponible' => false, 'solo_grupos' => true,\n"
         "                'razon' => 'De lunes a miércoles abrimos el día solo para grupos desde 10 personas, y esa fecha aún no tiene uno. Elige de jueves a domingo o escríbenos por WhatsApp al +56 9 7448 4112.']);\n"
         "        }\n\n")
    return s.replace(a, b + a, 1)
rw('app/Http/Controllers/Api/VerificarDisponibilidadController.php', verificar)

def fechas(s):
    if 'permiteReservaChica' in s: return None
    a = "        return response()->json([\n            'success' => true,\n            'fechas'  => $fechas,\n        ]);"
    if a not in s: raise SystemExit('ERROR: fechas-disponibles distinto')
    b = ("        // Lunes a miércoles sin grupo: se marcan, y si viene la cantidad (menos de 10) se sacan del calendario\n"
         "        $cantidad = (int) $request->query('cantidad', 0);\n"
         "        $soloGrupos = $fechas->filter(function ($f) { return !\\App\\Services\\BotTarifasService::permiteReservaChica(\\Carbon\\Carbon::parse($f), 1); })->values();\n"
         "        if ($cantidad > 0) {\n"
         "            $fechas = $fechas->filter(function ($f) use ($cantidad) { return \\App\\Services\\BotTarifasService::permiteReservaChica(\\Carbon\\Carbon::parse($f), $cantidad); })->values();\n"
         "        }\n\n"
         "        return response()->json([\n            'success'     => true,\n            'fechas'      => $fechas,\n            'solo_grupos' => $soloGrupos,\n"
         "            'nota_solo_grupos' => 'De lunes a miércoles el día se abre con un grupo desde 10 personas; con el grupo ya reservado, se aceptan reservas de cualquier tamaño.',\n        ]);")
    return s.replace(a, b, 1)
rw('app/Http/Controllers/Api/FechasDisponiblesController.php', fechas)

# 3) Compras web: quedan agendadas igual que las del bot (ubicación, visita, fuente) y re-chequeo del día
def job(s):
    if 'function agendar(' in s: return None
    a = "                $venta = $this->crearVenta($reserva, $campos);"
    if a not in s: raise SystemExit('ERROR: job de WooCommerce distinto')
    s = s.replace(a, a + "\n\n                // 5b) Agendar: fuente web, ubicación y visita (igual que una reserva del bot)\n                $this->agendar($reserva, $programa, $campos);", 1)
    s = s.replace("'cantidad_masajes'  => $programa->incluye_masajes\n                                    ? $campos['cantidad_personas']\n                                    : null,",
                  "'cantidad_masajes'  => \\App\\Services\\BotTarifasService::incluyeMasaje((int) $programa->id)\n                                    ? $campos['cantidad_personas']\n                                    : null,", 1)
    metodo = '''
    /** Deja la compra web agendada como las del bot: fuente, ubicación y visita; avisa si el día no cumple la regla de grupos. */
    private function agendar(Reserva $reserva, Programa $programa, array $campos)
    {
        try {
            \\Illuminate\\Support\\Facades\\DB::table('reservas')->where('id', $reserva->id)->update(['fuente' => 'woocommerce']);
        } catch (\\Throwable $e) {
        }
        $fecha = (string) $campos['fecha_visita'];
        $personas = (int) $campos['cantidad_personas'];
        try {
            $plan = \\App\\Services\\BotUbicacionesService::plan($fecha, (int) $programa->id, $personas, null, (int) $reserva->id);
            $req = \\App\\Http\\Requests\\Visita\\StoreRequest::create('/reserva/' . $reserva->id . '/visitas', 'POST', \\App\\Services\\BotUbicacionesService::payloadVisita($plan));
            $req->setContainer(app());
            $req->setRedirector(app(\\Illuminate\\Routing\\Redirector::class));
            $req->validateResolved();
            app(\\App\\Http\\Controllers\\VisitaController::class)->store($req, $reserva);
            \\App\\Services\\BotUbicacionesService::registrar((int) $reserva->id, $plan);
        } catch (\\Throwable $e) {
            Log::warning("[WC-Webhook] Reserva #{$reserva->id} creada sin ubicación/visita automática: " . $e->getMessage());
        }
        try {
            $c = \\Carbon\\Carbon::parse($fecha);
            $hab = \\App\\FechaDisponible::where('fecha', $c->toDateString())->where('habilitada', true)->exists();
            $grupoOk = \\App\\Services\\BotTarifasService::personasDelDia($c->toDateString()) >= \\App\\Services\\BotTarifasService::parametros()['param_min_personas_lun_jue']
                || \\App\\Services\\BotTarifasService::permiteReservaChica($c, $personas);
            if (!$hab || !$grupoOk) {
                $nota = !$hab ? 'el día no está habilitado' : 'es lunes a miércoles y el día aún no tiene un grupo de 10+';
                \\Illuminate\\Support\\Facades\\DB::table('reservas')->where('id', $reserva->id)->update(['observacion' => trim(($reserva->observacion ?? '') . "\\n⚠️ Compra web: {$nota}. Revisar con el cliente.")]);
                Log::warning("[WC-Webhook] Reserva #{$reserva->id}: {$nota}");
            }
        } catch (\\Throwable $e) {
        }
    }
'''
    i = s.rstrip().rfind('}')
    return s[:i].rstrip() + '\n' + metodo + '}\n'
rw('app/Jobs/ProcesarOrdenWoocommerce.php', job)

# 4) PDF de cotización
def pdf(s):
    a = 'De lunes a jueves se reciben reservas desde 10 personas.'
    if a not in s: return None
    return s.replace(a, 'De lunes a miércoles el día se abre con un grupo desde 10 personas; con el grupo ya reservado se aceptan reservas de cualquier tamaño.', 1)
rw('resources/views/pdf/cotizacion/viewPDF.blade.php', pdf)
