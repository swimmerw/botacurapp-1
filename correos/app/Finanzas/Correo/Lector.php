<?php

namespace App\Finanzas\Correo;

/**
 * Reconoce los correos que mueven plata y saca sus datos.
 *
 * tipo:
 *  - abono   : transferencia recibida por Botacura (aviso del banco de quien paga)
 *  - pago    : transferencia hecha por Botacura (aviso de su propio banco)
 *  - traspaso: transferencia entre cuentas de Botacura
 *  - cuenta  : cuenta o boleta por pagar / vencida (Movistar, API Gateway, luz, agua…)
 *  - cartola : cartola diaria o mensual enviada por el banco
 *  - factura : factura electrónica recibida por correo (el SII ya la importa)
 *  null      : no es un correo de plata
 */
class Lector
{
    /** Dominios oficiales desde los que cada banco o billetera envía sus avisos (también se exige firma DKIM de ese dominio). */
    const BANCOS = [
        'BancoEstado'         => ['bancoestado.cl', 'correobancoestado.cl'],
        'Banco de Chile'      => ['bancochile.cl', 'bancochile.com', 'bancoedwards.cl'],
        'Santander'           => ['santander.cl'],
        'Banco Falabella'     => ['bancofalabella.com', 'bancofalabella.cl'],
        'Scotiabank'          => ['scotiabank.cl'],
        'Bci'                 => ['bci.cl'],
        'Mach'                => ['machbank.cl', 'mach.cl', 'somosmach.com'],
        'Itaú'                => ['itau.cl'],
        'Banco Security'      => ['bancosecurity.cl', 'security.cl'],
        'Bice'                => ['bice.cl'],
        'Banco Ripley'        => ['bancoripley.cl'],
        'Banco Consorcio'     => ['bancoconsorcio.cl'],
        'Banco Internacional' => ['bancointernacional.cl'],
        'HSBC'                => ['hsbc.cl'],
        'Coopeuch'            => ['coopeuch.cl'],
        'Mercado Pago'        => ['mercadopago.cl', 'mercadopago.com', 'mercadolibre.cl', 'mercadolibre.com'],
        'Tenpo'               => ['tenpo.cl'],
        'Tapp'                => ['tapp.cl'],
        'Global66'            => ['global66.com'],
        'Superdigital'        => ['superdigital.cl'],
        'Prex'                => ['prexcard.cl', 'prex.cl'],
        'Chek'                => ['chek.cl'],
        'Los Héroes'          => ['losheroes.cl'],
        'La Araucana'         => ['laaraucana.cl'],
    ];

    /** Banco al que pertenece un dominio (exacto o subdominio), o null. */
    public static function bancoDeDominio(string $dominio): ?string
    {
        $dominio = strtolower(trim($dominio, '. '));
        foreach (self::BANCOS as $banco => $doms) {
            foreach ($doms as $d) {
                if ($dominio === $d || substr($dominio, -strlen('.' . $d)) === '.' . $d) {
                    return $banco;
                }
            }
        }
        return null;
    }

    public static function dominio(string $correo): string
    {
        return strtolower(substr(strrchr($correo, '@') ?: '', 1));
    }

    const PROVEEDORES = [
        'movistar' => 'Movistar', 'entel' => 'Entel', 'vtr' => 'VTR', 'enel' => 'Enel', 'cge' => 'CGE',
        'aguasandinas' => 'Aguas Andinas', 'servipag' => 'Servipag', 'capa2' => 'capa2 (internet)',
        'sasco' => 'API Gateway / SASCO', 'tesoreria' => 'Tesorería', 'autopista' => 'Autopista (TAG)',
        'costaneranorte' => 'Costanera Norte (TAG)', 'vespucio' => 'Vespucio (TAG)', 'claro' => 'Claro',
        'wom' => 'WOM', 'gtd' => 'GTD', 'metrogas' => 'Metrogas', 'abastible' => 'Abastible',
        'lipigas' => 'Lipigas', 'gasco' => 'Gasco',
    ];

    public static function leer(string $remitente, string $asunto, string $texto, ?string $fechaCorreo = null): array
    {
        $r = strtolower($remitente);
        $a = self::plano($asunto);
        $t = self::plano($texto);
        $todo = $a . ' | ' . $t;
        $banco = self::bancoDeDominio(self::dominio($r));

        $d = ['tipo' => null, 'banco' => $banco, 'proveedor' => null, 'nombre' => null, 'rut' => null,
              'monto' => null, 'fecha' => null, 'hora' => null, 'operacion' => null, 'comentario' => null,
              'cuenta' => null, 'vencimiento' => null];

        if (preg_match('/cartola/i', $a)) {
            $d['tipo'] = 'cartola';
            return self::fechaPorDefecto($d, $fechaCorreo);
        }

        if ($banco) {
            $esPropio = preg_match('/(hemos realizado una transferencia instruida por nuestro cliente|botacura[\w. ]*?\W+ha instruido una transferencia|has realizado una transferencia|transferencia enviada|comprobante de pago)/i', $t)
                && preg_match('/centro recreativo botacura/i', $t)
                && !preg_match('/(has recibido|ha recibido|a tu cuenta|a su cuenta|hacia su cuenta|hacia tu cuenta)/i', $t);
            if ($esPropio) {
                $d['tipo'] = 'pago';
                $d['nombre'] = self::primero($t, [
                    '/Nombre del Beneficiario\s*\|?\s*([^|]+?)\s*\|/i',
                    '/Nombre del Beneficiario\s*\|?\s*([^|]+?)\s*(\||Rut|RUT)/i',
                    '/Nombre Empresa\s*\|?\s*([^|]+?)\s*\|?\s*Rut/i',
                    '/Estimado\(a\) Sr\(a\)\.?\s*(.+?)\s*CENTRO RECREATIVO/i',
                ]);
            } elseif (preg_match('/(transferencia|traspaso|has recibido|ha recibido|recibiste|te envi[óo]|te enviaron|te transfiri[óo])/iu', $todo)) {
                $d['tipo'] = 'abono';
                $d['nombre'] = self::primero($t, [
                    '/nuestro\(a\) cliente\s+(.+?),?\s+(con los siguientes|ha efectuado|ha instruido|realiz)/i',
                    '/nuestro cliente\s+(.+?)\s+realiz[óo] una transferencia/iu',
                    '/nuestro cliente\s+(?:el\s+)?(?:Sr\(a\)\s+)?(.+?),?\s+(le informamos|ha instruido|ha realizado)/i',
                    '/transferencia de fondos de\s+(.+?)\s+hacia tu cuenta/i',
                    '/Titular cuenta origen\s*\|.*?\|\s*[\d\.]+-[\dkK]\s*\|\s*([^|]+?)\s*\|/i',
                    '/(?:de|desde)\s+([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ ]{5,60}?)\s+(?:a tu cuenta|a su cuenta)/u',
                    '/([\p{Lu}][\p{L}]+(?: [\p{Lu}][\p{L}]+){1,4}) te (?:envi[óo]|transfiri[óo]|deposit[óo])/u',
                    '/(?:Recibiste|recibiste) (?:dinero|una transferencia|un pago)? ?de ([\p{L} ]{4,60}?)(?:\s*\||\.|,| por| el)/u',
                ]);
            } else {
                return $d;
            }
            if ($d['tipo'] === 'pago' && $d['nombre'] && preg_match('/botacura/i', $d['nombre'])) {
                $d['tipo'] = 'traspaso'; // entre cuentas de Botacura
            }
            $d['monto'] = self::monto(self::primero($t, [
                '/Monto (?:transferido|recibido|transferencia|de la transferencia|Operaci[óo]n)[^$\d]{0,30}\$\s?([\d\.]+)/iu',
                '/Monto\s*\|?\s*:?\s*\|?\s*\$\s?([\d\.]+)/i',
                '/(?:Recibiste|te envi[óo]|te transfiri[óo])[^$]{0,60}\$\s?([\d\.]+)/i',
                '/\$\s?([\d]{1,3}(?:\.\d{3})+|\d{4,})/',
            ]));
            $d['operacion'] = self::primero($t, [
                '/ID de la operaci[óo]n\s*\|?\s*([A-Z0-9_]{6,})/iu',
                '/N[úu]mero de (?:Operaci[óo]n|la operacion|operacion|comprobante)\s*\|?\s*:?\s*\|?\s*([A-Z0-9]*\d[A-Z0-9]{3,})/i',
                '/\|\s*BANCO [^|]+\|\s*(\d{10,})\s*\|/i',
                '/N[°º]?\s*de operaci[óo]n\s*:?\s*([A-Z0-9]{5,})/i',
            ]);
            $d['rut'] = self::primero($t, ['/(?:Rut cuenta origen|RUT origen)[^\d]{0,40}(\d{1,2}\.?\d{3}\.?\d{3}-[\dkK])/i']);
            $d['comentario'] = self::primero($t, [
                '/(?:Comentario(?: para el destinatario)?|Mensaje)\s*[|:]\s*\|?\s*:?\s*\|?\s*([^|]{2,120}?)\s*(\||Monto|Inf[óo]rmese|N[úu]mero|$)/i',
                '/Asunto\s*\|?\s*:?\s*\|?\s*([^|]{2,120}?)\s*(\||Monto|Inf[óo]rmese|N[úu]mero|$)/i',
            ]);
            $d['cuenta'] = self::primero($t, [
                '/(?:N[º°]? de cuenta|N[úu]mero de Cuenta Corriente|Cuenta de destino|Cuenta destino|cuenta N°|nro\.)\s*\|?\s*:?\s*(?:Cuenta Vista|Cuenta Corriente)?\s*([\d\-]{8,})/i',
            ]);
            if ($d['cuenta']) {
                $d['cuenta'] = ltrim(preg_replace('/\D/', '', $d['cuenta']), '0');
            }
            list($d['fecha'], $d['hora']) = self::fechaHora($t);
        } elseif (preg_match('/(factura electr[óo]nica|documento tributario)/i', $todo) && !preg_match('/cotizaci[óo]n|cobro/i', $a)) {
            $d['tipo'] = 'factura';
            $d['proveedor'] = self::nombreDe($r, self::PROVEEDORES) ?: $remitente;
            $d['monto'] = self::monto(self::primero($t, ['/Total\s*\$\s?([\d\.]+)/i', '/monto total de\s*\$\s?([\d\.]+)/i']));
            $d['operacion'] = self::primero($a, ['/N[°º]\s*(\d+)/']);
        } elseif (preg_match('/(boleta|cuenta|cobro|factura|pago|vence|vencid|vencimiento|deuda|saldo)/i', $a)
            && ($prov = self::nombreDe($r, self::PROVEEDORES))) {
            $d['tipo'] = 'cuenta';
            $d['proveedor'] = $prov;
            $d['monto'] = self::monto(self::primero($t, [
                '/(?:Total(?: a pagar)?|Monto(?: a pagar)?|Deuda|Saldo)\s*:?\s*\|?\s*\$\s?([\d\.]+)/i',
                '/\$\s?([\d]{1,3}(?:\.\d{3})+)/',
            ]));
            $d['vencimiento'] = self::fecha(self::primero($t, [
                '/(?:vence|vencimiento|fecha de vencimiento|pagar hasta)[^\d]{0,30}(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i',
            ]));
            $d['operacion'] = self::primero($t, ['/c[óo]digo de cliente:?\s*(\d+)/i', '/N[°º]\s*([\w\-]+)/']);
            $d['comentario'] = mb_substr($a, 0, 120);
            if (preg_match('/vencid/i', $todo)) {
                $d['comentario'] = 'VENCIDA · ' . $d['comentario'];
            }
        } else {
            return $d;
        }
        return self::fechaPorDefecto($d, $fechaCorreo);
    }

    private static function fechaPorDefecto(array $d, ?string $fechaCorreo): array
    {
        if (!$d['fecha'] && $fechaCorreo && ($ts = strtotime($fechaCorreo))) {
            $d['fecha'] = date('Y-m-d', $ts);
            $d['hora'] = $d['hora'] ?: date('H:i', $ts);
        }
        if ($d['nombre']) {
            $d['nombre'] = trim(preg_replace('/\s+/', ' ', $d['nombre']), " .,|");
        }
        return $d;
    }

    public static function plano(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', $s)));
    }

    private static function nombreDe(string $remitente, array $lista): ?string
    {
        foreach ($lista as $clave => $nombre) {
            if (strpos($remitente, $clave) !== false) {
                return $nombre;
            }
        }
        return null;
    }

    private static function primero(string $t, array $patrones): ?string
    {
        foreach ($patrones as $p) {
            $fin = strrpos($p, '/');
            if (strpos(substr($p, $fin), 'u') === false) {
                $p .= 'u';
            }
            if (preg_match($p, $t, $m) && preg_match('/[\p{L}\d]/u', $m[1])) {
                return trim($m[1]);
            }
        }
        return null;
    }

    public static function monto(?string $s): ?int
    {
        if ($s === null) {
            return null;
        }
        $n = (int) preg_replace('/\D/', '', $s);
        return $n > 0 ? $n : null;
    }

    private static function fecha(?string $s): ?string
    {
        if (!$s || !preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $s, $m)) {
            return null;
        }
        $y = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
        return checkdate((int) $m[2], (int) $m[1], $y) ? sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]) : null;
    }

    private static function fechaHora(string $t): array
    {
        $f = self::fecha(self::primero($t, [
            '/(?:hoy,?|Fecha(?: y Hora de la Operaci[óo]n| de la transferencia| de Pago)?:?|con fecha(?: de hoy)?:?)\s*\|?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i',
            '/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/',
        ]));
        $h = self::primero($t, ['/\d{4}\s*(?:-\s*)?(\d{1,2}:\d{2})/', '/Hora\s*\|?\s*:?\s*(\d{1,2}:\d{2})/i', '/(\d{1,2}:\d{2})\s*(?:hrs|horas)?/i']);
        return [$f, $h];
    }
}
