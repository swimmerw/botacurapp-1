import sys
p = sys.argv[1] + '/app/Http/Controllers/Api/WoocommerceWebhookController.php'
s = open(p, encoding='utf-8').read()
if 'GiftCardWebService' in s:
    print('OK  webhook (ya aplicado)'); sys.exit(0)
a = """        if (!in_array($status, ['processing', 'completed'], true)) {
            Log::info("[WC-Webhook] Ignorado (estado: {$status})");
            return response()->json(['message' => 'Estado ignorado: ' . $status], 200);
        }"""
if s.count(a) != 1:
    raise SystemExit('ERROR: no encontré el bloque de estados pagados en el webhook (¿falta aplicar_ventas_web.sh?)')
b = a + """

        // GIFTCARD_WEB (06/11/2026): la Gift Card comprada en la web crea una Gift Card, no una reserva
        $ordenGc = request()->all();
        if (\\App\\Services\\GiftCardWebService::esPedidoGiftCard($ordenGc)) {
            try {
                $codigos = \\App\\Services\\GiftCardWebService::desdePedido($ordenGc, (int) $wcOrderId);
                return response()->json(['message' => 'Gift Card creada', 'codigos' => $codigos], 200);
            } catch (\\Throwable $e) {
                Log::error("[GC-Web] Pedido #{$wcOrderId}: " . $e->getMessage());
                return response()->json(['message' => 'Error Gift Card'], 200);
            }
        }"""
s = s.replace(a, b, 1)
open(p, 'w', encoding='utf-8').write(s)
print('OK  webhook: pedidos de Gift Card → módulo Gift Cards')
