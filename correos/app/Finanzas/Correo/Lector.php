<?php

namespace App\Finanzas\Correo;

/**
 * Reconoce los correos que mueven plata y saca sus datos.
 *
 * tipo:
 *  - abono   : transferencia recibida por Botacura (aviso del banco de quien paga)
 *  - pago    : transferencia hecha por Botacura (aviso de su propio banco)
 *  - traspaso: transferencia entre cuentas de Botacura
 *  - retiro  : transferencia de Botacura a la cuenta de un socio (Sebastián o Michelle)
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

        if ($banco && self::esPublicidad($r)) {
            return $d; // publicidad del banco: no mueve dinero
        }

        if ($banco && !self::origenBotacura($t) && preg_match('/(has realizado una transferencia|acabas de realizar una transferencia|tu transferencia est[áa] lista|transferencia de fondos realizada|has realizado un pago|se ha realizado una compra|compra por \$|pago de comercios|cargo en cuenta)/iu', $todo)) {
            return self::fechaPorDefecto(self::salidaPersonal($d, $a, $t), $fechaCorreo);
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
            } elseif ($d['tipo'] === 'pago' && $d['nombre'] && preg_match('/(sebasti[aá]n\s+wimmer|michelle\s+(alejandra\s+)?silva)/iu', $d['nombre'])) {
                $d['tipo'] = 'retiro'; // de Botacura a la cuenta de un socio
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

    /** Remitentes de publicidad de los bancos (comercial.bancochile.cl, contacto@cl.bancofalabella.com, ...). */
    public static function esPublicidad(string $remitente): bool
    {
        $r = strtolower($remitente);
        $local = strstr($r, '@', true) ?: '';
        $dom = self::dominio($r);
        return (bool) preg_match('/^(contacto|marketing|promociones?|ofertas|noticias|newsletter|beneficios|comunicaciones|novedades)/', $local)
            || (bool) preg_match('/^(comercial|marketing|promo|news|mkt|email\.mkt)\./', $dom);
    }

    /** El correo describe una transferencia hecha desde una cuenta de Botacura. */
    private static function origenBotacura(string $t): bool
    {
        return preg_match('/(hemos realizado una transferencia instruida por nuestro cliente|botacura[\w. ]*?\W+ha instruido una transferencia|has realizado una transferencia|transferencia enviada|comprobante de pago)/i', $t)
            && preg_match('/centro recreativo botacura/i', $t)
            && !preg_match('/(has recibido|ha recibido|a tu cuenta|a su cuenta|hacia su cuenta|hacia tu cuenta)/i', $t);
    }

    /** Titulares cuyos avisos llegan al correo pero no son de Sebastián ni de Botacura. */
    private static function titularesAjenos(): array
    {
        $v = function_exists('config') ? config('finanzas.titulares_ajenos') : null;
        return $v ?: ['jaime emersson'];
    }

    /**
     * Salidas desde cuentas personales: compras con débito ("Cargo en Cuenta"), pagos de cuentas
     * ("Pago de Comercios") y transferencias a terceros. Si la cuenta es de Botacura queda como 'compra';
     * si el aviso es de otro titular (p. ej. la cuenta de Jaime) queda como 'ajeno'.
     */
    private static function salidaPersonal(array $d, string $a, string $t): array
    {
        $d['tipo'] = 'personal';

        if (preg_match('/compra por \$\s?([\d\.]+) con cargo a (?:Cuenta|Tarjeta)[^*\d]{0,20}\*+\s?(\d{3,})\s+en\s+(.+?)\s+el\s+(\d{1,2}\/\d{1,2}\/\d{4})\s+(\d{1,2}:\d{2})/iu', $t, $m)) {
            $d['monto'] = self::monto($m[1]);
            $d['cuenta'] = $m[2];
            $d['nombre'] = $m[3];
            $d['fecha'] = self::fecha($m[4]);
            $d['hora'] = $m[5];
            $d['comentario'] = 'Compra con débito';
        } else {
            $d['nombre'] = self::primero($t, [
                '/Nombre y Apellido\s*[|:]?\s*\|?\s*([^|]+?)\s*(?:\||Rut\b)/iu',
                '/Nombre (?:del )?destinatario\s*[|:]?\s*\|?\s*([^|]+?)\s*(?:\||Rut\b)/iu',
                '/Hacia:?\s*\|?\s*Nombre\s*:?\s*([^|]+?)\s*(?:\||RUT\b)/iu',
                '/Nombre del Beneficiario\s*\|?\s*([^|]+?)\s*(?:\||Rut\b)/iu',
                '/Comercio\s*[|:]?\s*\|?\s*([^|]+?)\s*\|/iu',
            ]);
            if (preg_match('/Comercio\s*[|:]/iu', $t)) {
                $d['comentario'] = 'Pago de cuenta';
                if ($prov = self::proveedorEnTexto($t)) {
                    $d['proveedor'] = $prov;
                    $d['nombre'] = trim(($d['nombre'] ? $d['nombre'] . ' · ' : '') . $prov);
                }
            } else {
                $d['comentario'] = self::primero($t, [
                    '/(?:Comentario(?: para el destinatario)?|Mensaje)\s*[|:]\s*\|?\s*([^|]{2,120}?)\s*(\||Monto|$)/i',
                    '/Asunto\s*[|:]?\s*\|?\s*([^|]{2,120}?)\s*(\||Monto|$)/i',
                ]);
            }
            $d['rut'] = self::primero($t, ['/(?:Hacia|Destino).{0,120}?R(?:ut|UT)\s*[|:]?\s*\|?\s*(\d{1,2}\.?\d{3}\.?\d{3}-?[\dkK])\b/u', '/R(?:ut|UT)\s*[|:]?\s*\|?\s*(\d{1,2}\.?\d{3}\.?\d{3}-?[\dkK])\b/u']);
            $d['monto'] = self::monto(self::primero($t, [
                '/Monto (?:total|transferido|transferencia|de la transferencia|pagado|a pagar)[^$\d]{0,20}\$\s?([\d\.]+)/iu',
                '/Monto\s*[|:]?\s*\|?\s*\$\s?([\d\.]+)/iu',
                '/\$\s?([\d]{1,3}(?:\.\d{3})+|\d{4,})/',
            ]));
            $d['operacion'] = self::primero($t, [
                '/Transacci[óo]n\s*[|:]?\s*\|?\s*([A-Z]{2,}[A-Z0-9]{8,})/u',
                '/ID de (?:la )?(?:transacci[óo]n|operaci[óo]n)\s*[|:]?\s*\|?\s*([A-Z0-9_]{5,})/iu',
                '/N[°º]?\s*de TEF\s*:?\s*\|?\s*(\d{4,})/iu',
                '/N[úu]mero de operaci[óo]n\s*[|:]?\s*\|?\s*(\d{5,})/iu',
            ]);
            $d['cuenta'] = self::primero($t, [
                '/Cuenta de origen\s*[|:]?\s*\|?\s*(?:Cuenta\s*)?(?:Vista|Corriente|RUT)?\s*([\d\-]{6,})/iu',
                '/Origen.{0,80}?N[úu]mero(?: de cuenta)?\s*[|:]?\s*\|?\s*([\d\-*]{6,})/iu',
            ]);
            list($f, $h) = self::fechaHora($t);
            $d['fecha'] = $f;
            $d['hora'] = $h;
        }
        if ($d['cuenta']) {
            $d['cuenta'] = ltrim(preg_replace('/\D/', '', $d['cuenta']), '0');
        }

        $titular = self::primero($t, [
            '/Estimad[oa](?:\(a\))?:?\s*(?:Sr\(a\)\.?\s*)?([\p{L} ]{3,60}?)\s*(?:,|\||Acabas|Te informamos|Le informamos|se ha realizado)/u',
            '/^\s*(?:\d{2}\/\d{2}\/\d{4}\s*)?([\p{Lu} ]{3,60}),\s*tu transferencia/u',
        ]);
        foreach (self::titularesAjenos() as $aj) {
            if ($titular && stripos(self::sinTildes($titular), self::sinTildes($aj)) !== false) {
                $d['tipo'] = 'ajeno';
                $d['comentario'] = trim('Aviso de ' . $titular . ($d['comentario'] ? ' · ' . $d['comentario'] : ''));
            }
        }
        if ($d['tipo'] === 'personal' && $d['cuenta'] && self::esCuentaBotacura($d['cuenta'])) {
            $d['tipo'] = 'compra'; // tarjeta o cuenta de Botacura
        }
        return $d;
    }

    private static function esCuentaBotacura(string $digitos): bool
    {
        $cuentas = function_exists('config') ? (array) config('finanzas.cuentas') : [];
        foreach ($cuentas as $num => $nombre) {
            $num = ltrim((string) $num, '0');
            if (stripos($nombre, 'botacura') !== false && strlen($digitos) >= 4
                && substr($num, -strlen($digitos)) === $digitos) {
                return true;
            }
        }
        return false;
    }

    private static function proveedorEnTexto(string $t): ?string
    {
        foreach (self::PROVEEDORES as $clave => $nombre) {
            if ($clave !== 'servipag' && preg_match('/\b' . preg_quote($clave, '/') . '\b/i', $t)) {
                return $nombre;
            }
        }
        return null;
    }

    private static function sinTildes(string $s): string
    {
        return strtolower(strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'ñ' => 'n', 'Ñ' => 'n']));
    }

    private static function fechaPorDefecto(array $d, ?string $fechaCorreo): array
    {
        if (!$d['fecha'] && $fechaCorreo && ($ts = strtotime($fechaCorreo))) {
            $d['fecha'] = date('Y-m-d', $ts);
            $d['hora'] = $d['hora'] ?: date('H:i', $ts);
        }
        if ($d['nombre']) {
            $d['nombre'] = trim(preg_replace('/\s+/', ' ', self::reparar($d['nombre'])), " .,|");
        }
        if ($d['comentario']) {
            $d['comentario'] = self::reparar($d['comentario']);
        }
        return $d;
    }

    /** Corrige textos codificados dos veces ("GuzmÃ¡n" -> "Guzmán"). */
    public static function reparar(string $s): string
    {
        if (preg_match('/Ã[\x{0080}-\x{00BF}]|Ã[¡©­³º±]/u', $s)) {
            $r = @mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
            if ($r !== false && mb_check_encoding($r, 'UTF-8')) {
                return $r;
            }
        }
        return $s;
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
