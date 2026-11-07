<?php

namespace App\Services;

use App\GiftCard;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Gift Card comprada en botacura.cl (06/11/2026).
 * El pedido de WooCommerce con el producto "gift-card-botacura" (valor por pareja) crea una Gift Card
 * en el módulo Gift Cards en vez de una reserva. Una sola vez por pedido (tabla gift_card_pedidos_web).
 */
class GiftCardWebService
{
    const SKU = 'gift-card-botacura';
    const PERSONAS_POR_UNIDAD = 2;
    const PROGRAMA_NOMBRE = 'Gift Card Botacura Full';

    /** true si TODOS los productos del pedido son Gift Card. */
    public static function esPedidoGiftCard(array $orden): bool
    {
        $items = $orden['line_items'] ?? [];
        if (!$items) {
            return false;
        }
        foreach ($items as $li) {
            if (($li['sku'] ?? '') !== self::SKU) {
                return false;
            }
        }
        return true;
    }

    private static function tabla(): void
    {
        if (!Schema::hasTable('gift_card_pedidos_web')) {
            DB::statement('CREATE TABLE gift_card_pedidos_web (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                wc_order_id BIGINT UNSIGNED NOT NULL,
                gift_card_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL,
                UNIQUE KEY gcpw_order (wc_order_id)
            )');
        }
    }

    private static function programaId(): ?int
    {
        $p = DB::table('programas')->where('nombre_programa', self::PROGRAMA_NOMBRE)->first();
        if (!$p) {
            $p = DB::table('programas')->where('nombre_programa', 'Botacura Full')->first();
        }
        return $p ? (int) $p->id : null;
    }

    /** Crea las Gift Cards del pedido (una por unidad comprada). Devuelve los códigos creados. */
    public static function desdePedido(array $orden, int $wcOrderId): array
    {
        self::tabla();
        if (DB::table('gift_card_pedidos_web')->where('wc_order_id', $wcOrderId)->exists()) {
            Log::info("[GC-Web] Pedido #{$wcOrderId} ya procesado");
            return [];
        }
        DB::table('gift_card_pedidos_web')->insert(['wc_order_id' => $wcOrderId, 'created_at' => now()]);

        $b = $orden['billing'] ?? [];
        $comprador = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));
        $meta = collect($orden['meta_data'] ?? [])->pluck('value', 'key');
        $para = trim((string) ($meta['botacura_gc_para'] ?? '')) ?: $comprador;
        $nota = trim((string) ($orden['customer_note'] ?? ''));
        $hoy = Carbon::today()->toDateString();
        $cols = Schema::getColumnListing('gift_cards');
        $programaId = self::programaId();
        $codigos = [];
        $primero = null;

        foreach ($orden['line_items'] as $li) {
            $unidades = max(1, (int) ($li['quantity'] ?? 1));
            $montoUnidad = (int) round(((float) ($li['total'] ?? 0) + (float) ($li['total_tax'] ?? 0)) / $unidades);
            for ($i = 0; $i < $unidades; $i++) {
                $datos = [
                    'codigo'            => GiftCard::generarCodigoUnicoGiftCard(),
                    'monto'             => $montoUnidad,
                    'cantidad_personas' => self::PERSONAS_POR_UNIDAD,
                    'usada'             => false,
                    'fecha_uso'         => null,
                    'id_programa'       => $programaId,
                    'id_venta'          => null,
                    'fecha_compra'      => $hoy,
                    'validez_hasta'     => method_exists(GiftCard::class, 'venceDesde') ? GiftCard::venceDesde($hoy) : Carbon::today()->addDays(45)->toDateString(),
                    'de'                => $comprador,
                    'para'              => $para,
                    'correo'            => $b['email'] ?? null,
                    'telefono'          => $b['phone'] ?? null,
                ];
                $datos = array_intersect_key($datos, array_flip($cols));
                $gc = new GiftCard();
                foreach ($datos as $k => $v) {
                    $gc->{$k} = $v;
                }
                $gc->save();
                $primero = $primero ?: $gc->id;
                $codigos[] = $gc->codigo;
            }
        }
        DB::table('gift_card_pedidos_web')->where('wc_order_id', $wcOrderId)->update(['gift_card_id' => $primero]);
        Log::info("[GC-Web] Pedido #{$wcOrderId}: Gift Card " . implode(', ', $codigos) . ($nota ? " · nota: {$nota}" : ''));
        return $codigos;
    }
}
