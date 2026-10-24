import sys
p = sys.argv[1] + '/app/Http/Controllers/Api/WoocommerceWebhookController.php'
s = open(p, encoding='utf-8').read()
if 'ESTADOS_PAGADOS' in s:
    print('SIN CAMBIO webhook'); sys.exit(0)
a = """        if ($status !== 'completed') {
            Log::info("[WC-Webhook] Ignorado (estado: {$status})");
            return response()->json(['message' => 'Estado ignorado: ' . $status], 200);
        }"""
if a not in s: raise SystemExit('ERROR: webhook distinto')
b = """        // ESTADOS_PAGADOS: Webpay deja el pedido en "processing" (pagado); la transferencia pasa a "completed" al confirmarla.
        if (in_array($status, ['cancelled', 'refunded', 'failed'], true)) {
            $prev = WoocommerceOrder::where('wc_order_id', $wcOrderId)->first();
            if ($prev && $prev->reserva_id) {
                $upd = ['observacion' => \\Illuminate\\Support\\Facades\\DB::raw("CONCAT(COALESCE(observacion,''), '\\n⚠️ Pedido web #{$wcOrderId} {$status}: revisar la reserva.')")];
                if (\\Illuminate\\Support\\Facades\\Schema::hasColumn('reservas', 'estado')) {
                    $upd['estado'] = 'cancelada';
                }
                \\Illuminate\\Support\\Facades\\DB::table('reservas')->where('id', $prev->reserva_id)->update($upd);
                Log::warning("[WC-Webhook] Orden #{$wcOrderId} {$status} → reserva #{$prev->reserva_id} marcada cancelada");
            }
            return response()->json(['message' => 'Estado registrado: ' . $status], 200);
        }
        if (!in_array($status, ['processing', 'completed'], true)) {
            Log::info("[WC-Webhook] Ignorado (estado: {$status})");
            return response()->json(['message' => 'Estado ignorado: ' . $status], 200);
        }"""
s = s.replace(a, b, 1)
open(p, 'w', encoding='utf-8').write(s); print('OK webhook: pagos Webpay (processing) y transferencias confirmadas (completed)')
