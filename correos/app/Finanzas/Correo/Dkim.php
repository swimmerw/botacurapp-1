<?php

namespace App\Finanzas\Correo;

/**
 * Verificación DKIM propia (RFC 6376): comprueba con la llave pública publicada en el DNS
 * del banco que el correo fue firmado por ese dominio y que no se alteró en el camino.
 * No depende de cómo llegó el correo a Gmail (directo, reenviado o por POP).
 * Compatible PHP 7.2 (openssl + dns_get_record).
 */
class Dkim
{
    /** @var callable|null para pruebas: fn(string $nombre): ?string devuelve el TXT */
    public static $resolver = null;

    /**
     * @return array ['estado' => ok|falla|sin_firma, 'dominios' => [d...], 'detalle' => string]
     */
    public static function verificar(string $raw): array
    {
        $raw = preg_replace("/(?<!\r)\n/", "\r\n", $raw);
        $pos = strpos($raw, "\r\n\r\n");
        if ($pos === false) {
            return ['estado' => 'sin_firma', 'dominios' => [], 'detalle' => 'correo sin cuerpo'];
        }
        $headers = self::cabecerasCrudas(substr($raw, 0, $pos + 2));
        $cuerpo = substr($raw, $pos + 4);

        $firmas = array_values(array_filter($headers, function ($h) {
            return strtolower($h['nombre']) === 'dkim-signature';
        }));
        if (!$firmas) {
            return ['estado' => 'sin_firma', 'dominios' => [], 'detalle' => 'sin firma DKIM'];
        }
        $ok = [];
        $errores = [];
        foreach ($firmas as $f) {
            try {
                $d = self::verificarUna($f, $headers, $cuerpo);
                $ok[] = $d;
            } catch (\Throwable $e) {
                $errores[] = $e->getMessage();
            }
        }
        if ($ok) {
            return ['estado' => 'ok', 'dominios' => array_values(array_unique($ok)), 'detalle' => 'firma válida de ' . implode(', ', array_unique($ok))];
        }
        return ['estado' => 'falla', 'dominios' => [], 'detalle' => implode('; ', $errores)];
    }

    /** Lista de cabeceras en orden: ['nombre', 'crudo' (con CRLF y pliegues), 'valor']. */
    private static function cabecerasCrudas(string $bloque): array
    {
        $out = [];
        foreach (preg_split("/\r\n(?![ \t])/", rtrim($bloque, "\r\n")) as $linea) {
            if (strpos($linea, ':') === false) {
                continue;
            }
            list($n, $v) = explode(':', $linea, 2);
            $out[] = ['nombre' => $n, 'crudo' => $linea . "\r\n", 'valor' => $v];
        }
        return $out;
    }

    private static function tags(string $v): array
    {
        $t = [];
        foreach (explode(';', $v) as $par) {
            if (strpos($par, '=') === false) {
                continue;
            }
            list($k, $val) = explode('=', $par, 2);
            $t[strtolower(trim($k))] = trim(preg_replace('/\s+/', '', $val)) === '' ? '' : trim($val);
        }
        return $t;
    }

    private static function verificarUna(array $firma, array $headers, string $cuerpo): string
    {
        $t = self::tags($firma['valor']);
        foreach (['d', 's', 'b', 'bh', 'h'] as $k) {
            if (empty($t[$k])) {
                throw new \RuntimeException("firma sin {$k}=");
            }
        }
        $d = strtolower($t['d']);
        $alg = strtolower($t['a'] ?? 'rsa-sha256');
        if (!in_array($alg, ['rsa-sha256', 'rsa-sha1'], true)) {
            throw new \RuntimeException("{$d}: algoritmo {$alg} no soportado");
        }
        $hash = $alg === 'rsa-sha1' ? 'sha1' : 'sha256';
        $c = explode('/', strtolower($t['c'] ?? 'simple/simple'));
        $ch = $c[0];
        $cb = $c[1] ?? 'simple';

        // 1. Cuerpo
        $b = $cb === 'relaxed' ? self::cuerpoRelaxed($cuerpo) : self::cuerpoSimple($cuerpo);
        if (isset($t['l']) && ctype_digit($t['l'])) {
            $b = substr($b, 0, (int) $t['l']);
        }
        $bh = base64_encode(hash($hash, $b, true));
        if (!hash_equals(preg_replace('/\s+/', '', $t['bh']), $bh)) {
            throw new \RuntimeException("{$d}: el cuerpo fue modificado (bh no coincide)");
        }

        // 2. Cabeceras firmadas (de abajo hacia arriba por nombre)
        $usados = [];
        $datos = '';
        foreach (explode(':', $t['h']) as $nombre) {
            $nombre = strtolower(trim($nombre));
            for ($i = count($headers) - 1; $i >= 0; $i--) {
                if (isset($usados[$i]) || strtolower(trim($headers[$i]['nombre'])) !== $nombre) {
                    continue;
                }
                $usados[$i] = true;
                $datos .= $ch === 'relaxed' ? self::cabeceraRelaxed($headers[$i]) : $headers[$i]['crudo'];
                break;
            }
        }
        // 3. La propia DKIM-Signature con b= vacío y sin CRLF final
        $sinB = preg_replace('/(\bb\s*=)[^;]*/', '$1', $firma['crudo'], 1);
        $propia = ['nombre' => $firma['nombre'], 'crudo' => $sinB, 'valor' => explode(':', $sinB, 2)[1]];
        $datos .= rtrim($ch === 'relaxed' ? self::cabeceraRelaxed($propia) : $propia['crudo'], "\r\n");

        // 4. Llave pública en DNS y verificación RSA
        $pem = self::llave($t['s'] . '._domainkey.' . $d);
        $r = openssl_verify($datos, base64_decode(preg_replace('/\s+/', '', $t['b'])), $pem, $hash === 'sha1' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256);
        if ($r !== 1) {
            throw new \RuntimeException("{$d}: la firma no es válida");
        }
        return $d;
    }

    private static function llave(string $nombre): string
    {
        $txt = null;
        if (self::$resolver) {
            $txt = call_user_func(self::$resolver, $nombre);
        } else {
            $r = @dns_get_record($nombre, DNS_TXT);
            if ($r) {
                foreach ($r as $x) {
                    $v = isset($x['entries']) ? implode('', $x['entries']) : ($x['txt'] ?? '');
                    if (stripos($v, 'p=') !== false) {
                        $txt = $v;
                        break;
                    }
                }
            }
        }
        if (!$txt || !preg_match('/(?:^|;)\s*p=([A-Za-z0-9+\/=\s]+)/', $txt, $m) || trim($m[1]) === '') {
            throw new \RuntimeException("sin llave pública en {$nombre}");
        }
        $p = preg_replace('/\s+/', '', $m[1]);
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($p, 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function cabeceraRelaxed(array $h): string
    {
        $v = preg_replace("/\r\n/", '', $h['valor']);
        $v = trim(preg_replace('/[ \t]+/', ' ', $v));
        return strtolower(trim($h['nombre'])) . ':' . $v . "\r\n";
    }

    private static function cuerpoSimple(string $b): string
    {
        $b = preg_replace("/(\r\n)+$/", '', $b);
        return $b . "\r\n";
    }

    private static function cuerpoRelaxed(string $b): string
    {
        $lineas = explode("\r\n", $b);
        foreach ($lineas as &$l) {
            $l = rtrim(preg_replace('/[ \t]+/', ' ', $l), " \t");
        }
        unset($l);
        $b = preg_replace("/(\r\n)+$/", '', implode("\r\n", $lineas));
        return $b === '' ? '' : $b . "\r\n";
    }
}
