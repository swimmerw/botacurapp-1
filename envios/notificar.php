    private function notificarCliente(int $reservaId, bool $aprobado)
    {
        // 10/11/2026: cada mensaje queda registrado con su estado de entrega (WaEnviosService). Si el cliente escribió
        // hace más de 24 h, la confirmación o el rechazo salen con plantilla y el resto espera a que el cliente responda.
        try {
            $reserva = DB::table('reservas')->where('id', $reservaId)->first();
            if (!$reserva) {
                return;
            }

            $cliente = DB::table('clientes')->where('id', $reserva->cliente_id)->first();
            if (!$cliente || empty($cliente->whatsapp_cliente)) {
                Log::warning('[VerificacionPago] Cliente sin WhatsApp: no se notificó', ['reserva' => $reservaId]);
                return;
            }

            $W = \App\Services\WaEnviosService::class;
            $tel = $cliente->whatsapp_cliente;
            $nombre = $cliente->nombre_cliente ?? 'Cliente';
            $ventaId = DB::table('ventas')->where('id_reserva', $reservaId)->orderBy('id', 'desc')->value('id');
            try {
                $fechaTexto = \Carbon\Carbon::parse($reserva->fecha_visita)->locale('es')->isoFormat('dddd D [de] MMMM');
            } catch (\Throwable $e) {
                $fechaTexto = (string) $reserva->fecha_visita;
            }
            $params = $aprobado ? [$nombre, (string) $reservaId, $fechaTexto] : [$nombre, (string) $reservaId];
            $meta = function ($tipo) use ($reservaId, $ventaId, $params) {
                return ['reserva_id' => $reservaId, 'venta_id' => $ventaId, 'tipo' => $tipo, 'params' => $params];
            };
            $texto = function ($t) { return ['type' => 'text', 'text' => ['body' => $t]]; };

            $mensaje = $aprobado
                ? "¡Hola {$nombre}! 🎉 Verificamos tu comprobante de transferencia.\n\n"
                  . "✅ *Reserva N°{$reservaId} confirmada*\n\n"
                  . "Nos contactaremos contigo los días previos a tu visita para coordinar los horarios del spa. ¡Te esperamos! 🏔️"
                : "Hola {$nombre}, no pudimos verificar el comprobante que enviaste para la reserva N°{$reservaId}. "
                  . "Por favor envía nuevamente una foto clara del comprobante de transferencia, o contáctanos al +56 9 7448 4112.";

            $resto = [];
            if ($aprobado) {
                $resto[] = ['menu_texto', $texto("🌿 Te compartimos las opciones de menú para que puedas elegir.\n"
                    . "Un día antes de tu reserva te enviaremos los horarios disponibles de spa, siempre que ya tengamos tus opciones de menú confirmadas.\n"
                    . "Si no recibimos la selección a tiempo, se asignará el horario disponible sin posibilidad de modificación.\n"
                    . "Quedamos atentas 🙏")];
                $resto[] = ['menu_pdf', ['type' => 'document', 'document' => [
                    'link' => (\App\Services\BotMenuService::url() ?: url('/docs/menu-otono-2026.pdf')),
                    'filename' => \App\Services\BotMenuService::nombreArchivo(),
                ]]];
                $resto[] = ['info_visita', $texto("Para que puedan disfrutar al máximo de su visita, les recomendamos traer lo siguiente:\n\n"
                    . "🩴 Sandalias\n"
                    . "👙 Traje de baño\n"
                    . "🧖‍♀️ Toalla y/o bata\n"
                    . "👕 Ropa de muda\n"
                    . "🧥 Ropa abrigada, mantas o frazadas, especialmente para la mañana y el final de la tarde en días fríos\n\n"
                    . "ℹ️ Información importante:\n\n"
                    . "🕙 Pueden ingresar a partir de las 10:00 am. Les sugerimos llegar 15 minutos antes de su horario agendado para el sauna, tinaja o masaje, para aprovechar al máximo su tiempo y no tener descuentos de minutos en su servicio.\n\n"
                    . "🚫 No está permitido el ingreso de alcohol al recinto.\n"
                    . "💍 Para el ingreso a la sala de masaje, no debe ingresar con collares, anillos o relojes.\n\n"
                    . "📍 Para llegar a Botacura, pueden buscarnos en Google Maps como \"Botacura\", estamos ubicados en el sector El Manzano, antes del Puente Colorado.\n"
                    . "📱 Por favor, avísenos por WhatsApp cuando estén cerca para que nuestras anfitrionas puedan recibirlos con una cálida bienvenida. 😊\n\n"
                    . "¡Esperamos verlos pronto y compartir con ustedes un día lleno de paz y tranquilidad en Botacura! 🌸\n\n"
                    . "📍 Botacura - Cajón del Maipo: https://goo.gl/maps/Nhtf4DdQKSoGDXje7")];
            }

            $horas = $W::horasDesdeCliente($tel);
            $tipoPrincipal = $aprobado ? 'confirmacion' : 'rechazo';
            if ($horas !== null && $horas >= 23.5) {
                // Fuera de la ventana: plantilla aprobada ahora, el resto cuando el cliente responda
                $plantilla = $aprobado ? env('WA_PLANTILLA_CONFIRMACION', $W::PLANTILLA_CONFIRMACION) : env('WA_PLANTILLA_RECHAZO', $W::PLANTILLA_RECHAZO);
                $W::enviar($tel, $W::plantilla($plantilla, $params), $meta($tipoPrincipal));
                foreach ($resto as $r) {
                    $W::encolar($tel, $r[1], $meta($r[0]));
                }
                return;
            }
            // Dentro de la ventana (o sin registro): texto normal; si Meta no lo entrega por las 24 h,
            // el webhook reenvía con plantilla y deja el resto en espera.
            $W::enviar($tel, $texto($mensaje), $meta($tipoPrincipal));
            foreach ($resto as $r) {
                $W::enviar($tel, $r[1], $meta($r[0]));
            }
        } catch (\Exception $e) {
            Log::error('[VerificacionPago] Error notificando: ' . $e->getMessage());
        }
    }

