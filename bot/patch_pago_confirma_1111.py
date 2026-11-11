"""Bot-Acura (11/11/2026): el bot no confirma una reserva (ni se despide con "nos vemos el sábado") si el pago
no figura en el sistema. Tampoco inventa números de reserva ni cambios de servicios.
Pago figura = cupo apartado convertido en reserva (comprobante aceptado) o venta de una reserva futura del
cliente con abono registrado y estado distinto de pendiente.
"""
import sys

BC = 'app/Http/Controllers/Api/BotController.php'
BP = 'app/Services/BotPromptService.php'


def reemplazar(s, viejo, nuevo, nombre):
    n = s.count(viejo)
    if n != 1:
        sys.exit('No encontré el ancla "%s" (%d coincidencias). No se cambió nada.' % (nombre, n))
    return s.replace(viejo, nuevo, 1)


bc = open(BC, encoding='utf-8').read()
bp = open(BP, encoding='utf-8').read()
if 'patch_pago_confirma_1111' in bc:
    print('ya estaba aplicado')
    sys.exit(0)

bc = reemplazar(bc, """        // Control de montos: todo "$" del mensaje debe venir del sistema.""", """        // patch_pago_confirma_1111: nada se da por confirmado si el pago no figura.
        $msgC = (string) ($respuesta['mensaje'] ?? '');
        if ($msgC !== '' && $this->confirmaSinPago($msgC, $usuarioId)) {
            \\App\\Services\\BotAprendizajeService::evento('confirmacion_sin_pago', $usuarioId, ['mensaje' => mb_substr($msgC, 0, 400)]);
            $h = $historial;
            $h[] = ['role' => 'assistant', 'content' => json_encode($respuesta, JSON_UNESCAPED_UNICODE)];
            $h[] = ['role' => 'user', 'content' => '[Sistema-validador: tu mensaje da la reserva por confirmada (o se despide hasta el día de la visita, o nombra un número de reserva), pero el PAGO NO FIGURA en el sistema. La reserva solo se confirma cuando el sistema registra el pago. No inventes números de reserva. Responde de nuevo sin confirmar nada: si falta pagar, recuérdale cómo pagar con lo que el sistema ya entregó; si hubo un problema con el pago, ofrece la otra forma de pago. Formato JSON.]'];
            $re = $this->llamarClaude($systemPrompt, $h, $nombre);
            if ($re && trim((string) ($re['mensaje'] ?? '')) !== '' && !$this->confirmaSinPago((string) $re['mensaje'], $usuarioId)
                && !in_array($re['accion'] ?? '', ['crear_reserva', 'verificar_disponibilidad', 'cotizar', 'ubicaciones'], true)) {
                $respuesta = $re;
            } else {
                $respuesta = ['accion' => 'responder', 'datos' => [], 'mensaje' => 'Tu reserva queda confirmada recién cuando nos llegue el pago 🙌 Cuando lo hagas, me avisas por aquí.'];
            }
        }

        // Control de montos: todo "$" del mensaje debe venir del sistema.""", 'validador de pago')

metodo = r'''
    // ── patch_pago_confirma_1111 ─────────────────────────────────────────────
    /** ¿El mensaje confirma la reserva (o se despide hasta la visita) sin que el pago figure? */
    private function confirmaSinPago(string $msg, string $tel): bool
    {
        $dias = 'lunes|martes|mi[eé]rcoles|jueves|viernes|s[aá]bado|domingo';
        $condicional = '(?![a-záéíóú]*\s+(reci[eé]n|cuando|una\s+vez|apenas|al\s+recibir|en\s+cuanto))';
        $confirma = preg_match('/reserva\s+(est[aá]|qued[oó]|queda|ya\s+est[aá])\s+(confirmad|list|reservad|ingresad)' . $condicional . '|ya\s+(tenemos|tienes|qued[oó])\s+(tu\s+)?reserva|reserva\s+confirmada' . $condicional . '|tu\s+reserva\s+(confirmada|lista)' . $condicional . '|confirmamos\s+tu\s+reserva|nos\s+vemos\s+(el|este|ese)\s+(' . $dias . ')|hasta\s+el\s+(' . $dias . ')|te\s+esperamos\s+el|reserva\s+n[°º.o]?\s*\d+/iu', $msg);
        if (!$confirma) {
            return false;
        }
        return !$this->pagoFigura($tel);
    }

    /** Pago registrado: comprobante aceptado (hold convertido) o venta de reserva futura con abono y estado no pendiente. */
    private function pagoFigura(string $tel): bool
    {
        try {
            $corto = substr(preg_replace('/\D/', '', $tel), -9);
            $tels = array_values(array_unique([$tel, $corto, '56' . $corto]));
            if (DB::table('reserva_holds')->whereIn('telefono', $tels)->where('estado', 'confirmado')->whereNotNull('id_reserva')
                ->where('fecha', '>=', now()->toDateString())->exists()) {
                return true;
            }
            return DB::table('ventas as v')->join('reservas as r', 'r.id', '=', 'v.id_reserva')->join('clientes as c', 'c.id', '=', 'r.cliente_id')
                ->whereIn('c.whatsapp_cliente', $tels)->where('r.fecha_visita', '>=', now()->toDateString())
                ->where('v.abono_programa', '>', 0)
                ->where(function ($q) { $q->whereNull('v.estado_pago')->orWhereNotIn('v.estado_pago', ['pendiente', 'pendiente_verificacion', 'rechazado', 'anulado', 'fallido']); })
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('[Bot] pagoFigura: ' . $e->getMessage());
            return false;
        }
    }

'''
ancla = '    private function persistirHistorial(int $convId'
bc = reemplazar(bc, ancla, metodo.lstrip('\n') + ancla, 'métodos pago')

bp = reemplazar(bp, """- Tuteo chileno: nunca voseo""", """- RESERVA CONFIRMADA: solo cuando el sistema registró el pago (comprobante aceptado o pago con tarjeta
  aprobado). Antes de eso no digas "reserva confirmada", "ya tenemos tu reserva", "nos vemos el sábado" ni
  "hasta el domingo", y nunca inventes un número de reserva. Si [Sistema-reserva] trae "ok": false, la
  reserva NO se creó: explica lo que falta. Si el cliente dice que el pago con tarjeta falló, ofrece
  transferencia; no des la reserva por hecha.
- CAMBIOS DE SERVICIOS: no ofrezcas cambiar servicios del programa (tipo o duración de masaje, comidas, etc.)
  si no está en este prompt. Si lo piden, di que eso no lo puedes confirmar por aquí y que, si quiere, escriba
  EJECUTIVO.
- Tuteo chileno: nunca voseo""", 'prompt pago')

open(BC, 'w', encoding='utf-8').write(bc)
open(BP, 'w', encoding='utf-8').write(bp)
print('Bot: no confirma reservas sin pago, no inventa N° de reserva ni cambios de servicios')
