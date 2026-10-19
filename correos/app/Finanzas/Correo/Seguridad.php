<?php

namespace App\Finanzas\Correo;

/**
 * ¿El correo es realmente del banco que dice ser?
 *  seguro : firma DKIM válida de un dominio oficial de ese mismo banco (no alterado en el camino)
 *  revisar: sin firma, firmado solo por otro dominio (p. ej. su proveedor de envíos) o no se pudo consultar el DNS
 *  falso  : la firma del dominio del banco no calza (alterado o suplantado)
 * Solo un correo "seguro" puede quedar calzado automáticamente con una reserva.
 */
class Seguridad
{
    public static function evaluar(string $remitente, string $raw): array
    {
        $dominio = Lector::dominio($remitente);
        $banco = Lector::bancoDeDominio($dominio);
        $dk = Dkim::verificar($raw);

        if ($dk['estado'] === 'ok') {
            foreach ($dk['dominios'] as $d) {
                if ($banco ? Lector::bancoDeDominio($d) === $banco : self::mismoDominio($d, $dominio)) {
                    return ['seguridad' => 'seguro', 'detalle' => "Firma DKIM válida de {$d}"];
                }
            }
            return ['seguridad' => 'revisar', 'detalle' => 'Firmado por ' . implode(', ', $dk['dominios']) . ', no por el dominio del remitente'];
        }
        if ($dk['estado'] === 'sin_firma') {
            return ['seguridad' => 'revisar', 'detalle' => 'Sin firma DKIM'];
        }
        // Hubo firmas pero ninguna válida
        if (preg_match('/sin llave pública|no soportado/', $dk['detalle']) && !preg_match('/modificado|no es válida/', $dk['detalle'])) {
            return ['seguridad' => 'revisar', 'detalle' => mb_substr($dk['detalle'], 0, 180)];
        }
        return ['seguridad' => 'falso', 'detalle' => mb_substr('Firma inválida: ' . $dk['detalle'], 0, 180)];
    }

    private static function mismoDominio(string $a, string $b): bool
    {
        $base = function ($d) {
            $p = explode('.', strtolower($d));
            return implode('.', array_slice($p, -2));
        };
        return $base($a) === $base($b);
    }
}
