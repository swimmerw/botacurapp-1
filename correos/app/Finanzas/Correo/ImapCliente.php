<?php

namespace App\Finanzas\Correo;

/**
 * Cliente IMAP mínimo sobre TLS (sin la extensión php-imap).
 * Solo lee: usa BODY.PEEK, así que no marca los correos como leídos.
 * Compatible PHP 7.2.
 */
class ImapCliente
{
    private $s;
    private $n = 0;

    public function __construct(string $host, int $port = 993, int $timeout = 30)
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $this->s = @stream_socket_client("ssl://{$host}:{$port}", $errno, $err, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->s) {
            throw new \RuntimeException("IMAP: no se pudo conectar a {$host}:{$port} ({$err})");
        }
        stream_set_timeout($this->s, $timeout);
        $this->linea();
    }

    private function linea(): string
    {
        $l = fgets($this->s);
        if ($l === false) {
            throw new \RuntimeException('IMAP: la conexión se cerró');
        }
        return $l;
    }

    /** Envía un comando y devuelve las líneas de respuesta (con sus literales). */
    public function cmd(string $c): array
    {
        $tag = 'A' . (++$this->n);
        fwrite($this->s, "{$tag} {$c}\r\n");
        $out = [];
        while (true) {
            $l = $this->linea();
            if (preg_match('/\{(\d+)\}\r\n$/', $l, $m)) {
                $len = (int) $m[1];
                $lit = '';
                while (strlen($lit) < $len) {
                    $chunk = fread($this->s, $len - strlen($lit));
                    if ($chunk === false || $chunk === '') {
                        throw new \RuntimeException('IMAP: respuesta incompleta');
                    }
                    $lit .= $chunk;
                }
                $out[] = ['linea' => $l, 'literal' => $lit];
                continue;
            }
            if (strpos($l, $tag . ' ') === 0) {
                if (!preg_match('/^' . $tag . ' OK/i', $l)) {
                    throw new \RuntimeException('IMAP: ' . trim(substr($l, strlen($tag) + 1)));
                }
                return $out;
            }
            $out[] = ['linea' => $l];
        }
    }

    private function q(string $s): string
    {
        return '"' . addcslashes($s, "\"\\") . '"';
    }

    public function login(string $usuario, string $clave): void
    {
        $this->cmd('LOGIN ' . $this->q($usuario) . ' ' . $this->q($clave));
    }

    public function seleccionar(string $carpeta): void
    {
        $this->cmd('EXAMINE ' . $this->q($carpeta)); // solo lectura
    }

    /** UIDs de los correos recibidos desde una fecha. */
    public function uidsDesde(\DateTimeInterface $desde): array
    {
        foreach ($this->cmd('UID SEARCH SINCE ' . $desde->format('j-M-Y')) as $r) {
            if (preg_match('/^\* SEARCH(.*)$/', trim($r['linea']), $m)) {
                return array_values(array_filter(array_map('intval', preg_split('/\s+/', trim($m[1])))));
            }
        }
        return [];
    }

    /** Cabeceras (From, Subject, Message-ID, Date) de varios UIDs: [uid => texto]. */
    public function cabeceras(array $uids): array
    {
        $res = [];
        foreach (array_chunk($uids, 100) as $grupo) {
            $r = $this->cmd('UID FETCH ' . implode(',', $grupo) . ' (UID BODY.PEEK[HEADER.FIELDS (FROM SUBJECT MESSAGE-ID DATE TO)])');
            foreach ($r as $x) {
                if (isset($x['literal']) && preg_match('/UID (\d+)/', $x['linea'], $m)) {
                    $res[(int) $m[1]] = $x['literal'];
                }
            }
        }
        return $res;
    }

    /** Correo completo (RFC 822) de un UID. */
    public function correo(int $uid): ?string
    {
        foreach ($this->cmd("UID FETCH {$uid} (UID BODY.PEEK[])") as $x) {
            if (isset($x['literal'])) {
                return $x['literal'];
            }
        }
        return null;
    }

    public function salir(): void
    {
        try {
            $this->cmd('LOGOUT');
        } catch (\Throwable $e) {
        }
        if (is_resource($this->s)) {
            fclose($this->s);
        }
    }
}
