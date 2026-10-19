<?php

namespace App\Finanzas\Correo;

/**
 * Lee un correo RFC 822 y entrega cabeceras + texto plano (UTF-8).
 * Si solo trae HTML, lo convierte a texto con " | " entre celdas.
 */
class Mime
{
    public static function cabeceras(string $raw): array
    {
        $pos = strpos($raw, "\r\n\r\n");
        $h = $pos === false ? $raw : substr($raw, 0, $pos);
        $h = preg_replace("/\r\n[ \t]+/", ' ', $h);
        $out = [];
        foreach (explode("\r\n", $h) as $l) {
            if (strpos($l, ':') === false) {
                continue;
            }
            list($k, $v) = explode(':', $l, 2);
            $out[strtolower(trim($k))] = self::decodificarCabecera(trim($v));
        }
        return $out;
    }

    public static function decodificarCabecera(string $v): string
    {
        if (strpos($v, '=?') !== false) {
            $d = @iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($d !== false) {
                return $d;
            }
            return mb_decode_mimeheader($v);
        }
        return self::utf8($v, '');
    }

    /** Correo del remitente en minúsculas. */
    public static function remitente(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $m)) {
            return strtolower(trim($m[1]));
        }
        return strtolower(trim($from));
    }

    /** Texto del cuerpo: text/plain si existe; si no, el HTML convertido. */
    public static function texto(string $raw): string
    {
        $partes = [];
        self::recorrer($raw, $partes);
        foreach (['text/plain', 'text/html'] as $tipo) {
            foreach ($partes as $p) {
                if ($p['tipo'] === $tipo && trim($p['cuerpo']) !== '') {
                    // Algunos bancos mandan HTML dentro de la parte "texto plano"
                    $esHtml = $tipo === 'text/html' || preg_match('/<(td|tr|table|div|p|br)\b/i', $p['cuerpo']);
                    return $esHtml ? self::htmlATexto($p['cuerpo']) : $p['cuerpo'];
                }
            }
        }
        return '';
    }

    private static function recorrer(string $entidad, array &$partes): void
    {
        $h = self::cabeceras($entidad);
        $pos = strpos($entidad, "\r\n\r\n");
        $cuerpo = $pos === false ? '' : substr($entidad, $pos + 4);
        $ct = strtolower($h['content-type'] ?? 'text/plain');
        if (strpos($ct, 'multipart/') === 0 && preg_match('/boundary="?([^";]+)"?/i', $h['content-type'], $m)) {
            foreach (explode('--' . $m[1], $cuerpo) as $i => $sub) {
                if ($i === 0 || strpos($sub, '--') === 0) {
                    continue;
                }
                self::recorrer(preg_replace('/^\r?\n/', '', $sub), $partes);
            }
            return;
        }
        $tipo = trim(explode(';', $ct)[0]);
        if (!in_array($tipo, ['text/plain', 'text/html'], true)) {
            return;
        }
        $enc = strtolower(trim($h['content-transfer-encoding'] ?? ''));
        if ($enc === 'base64') {
            $cuerpo = base64_decode(preg_replace('/\s+/', '', $cuerpo));
        } elseif ($enc === 'quoted-printable') {
            $cuerpo = quoted_printable_decode($cuerpo);
        }
        $charset = preg_match('/charset="?([\w\-]+)"?/i', $ct, $c) ? $c[1] : '';
        $partes[] = ['tipo' => $tipo, 'cuerpo' => self::utf8($cuerpo, $charset)];
    }

    private static function utf8(string $s, string $charset): string
    {
        $charset = strtoupper($charset);
        if ($charset && $charset !== 'UTF-8' && $charset !== 'US-ASCII') {
            $c = @mb_convert_encoding($s, 'UTF-8', $charset);
            if ($c !== false) {
                return $c;
            }
        }
        if (!mb_check_encoding($s, 'UTF-8')) {
            return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }
        return $s;
    }

    public static function htmlATexto(string $html): string
    {
        $html = preg_replace('#<(script|style|head)\b.*?</\1>#is', ' ', $html);
        $html = preg_replace('#<br\s*/?>|</p>|</div>|</tr>|</h\d>#i', "\n", $html);
        $html = preg_replace('#</t[dh]>#i', ' | ', $html);
        $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = str_replace("\xC2\xA0", ' ', $t);
        $t = preg_replace("/[ \t]+/", ' ', $t);
        return trim(preg_replace("/\n\s*\n+/", "\n", $t));
    }
}
