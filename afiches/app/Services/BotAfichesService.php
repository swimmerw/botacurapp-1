<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Afiches de programas que el bot envía por WhatsApp en vez de listar todo en texto.
 * Se suben en Configuración del bot → pestaña "Afiches" (hasta 4: WhatsApp recibe máximo 4 imágenes por respuesta).
 * Compatible Laravel 6 / PHP 7.2
 */
class BotAfichesService
{
    const DIR = 'uploads/bot/afiches';
    const MAX = 4;

    private static function clave(int $n, string $campo): string
    {
        return 'afiche_' . $n . '_' . $campo;
    }

    /** Crea las filas de texto si no existen (sin tocar lo ya guardado). */
    public static function asegurar(): void
    {
        $orden = 0;
        for ($n = 1; $n <= self::MAX; $n++) {
            foreach (['titulo' => 'Nombre del afiche ' . $n, 'texto' => 'Texto que acompaña el afiche ' . $n, 'activo' => 'Afiche ' . $n . ' activo (1/0)'] as $campo => $etq) {
                $orden++;
                if (!DB::table('bot_secciones_texto')->where('clave', self::clave($n, $campo))->exists()) {
                    DB::table('bot_secciones_texto')->insert([
                        'clave' => self::clave($n, $campo), 'categoria' => 'afiches', 'etiqueta' => $etq,
                        'contenido' => $campo === 'activo' ? '1' : '', 'orden' => $orden, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public static function archivo(int $n): ?string
    {
        foreach (['jpg', 'png', 'webp'] as $ext) {
            $p = public_path(self::DIR . '/' . $n . '.' . $ext);
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }

    public static function lista(): array
    {
        try {
            $t = DB::table('bot_secciones_texto')->where('categoria', 'afiches')->pluck('contenido', 'clave');
        } catch (\Throwable $e) {
            $t = collect();
        }
        $out = [];
        for ($n = 1; $n <= self::MAX; $n++) {
            $p = self::archivo($n);
            $out[$n] = (object) [
                'n'      => $n,
                'url'    => $p ? url(self::DIR . '/' . basename($p)) . '?v=' . filemtime($p) : null,
                'titulo' => trim((string) ($t[self::clave($n, 'titulo')] ?? '')),
                'texto'  => trim((string) ($t[self::clave($n, 'texto')] ?? '')),
                'activo' => (string) ($t[self::clave($n, 'activo')] ?? '1') !== '0',
            ];
        }
        return $out;
    }

    public static function activos(): array
    {
        return array_values(array_filter(self::lista(), function ($a) {
            return $a->url && $a->activo;
        }));
    }

    /** Imágenes para la respuesta del bot. $cual: número de afiche o null para todos los activos. */
    public static function imagenes($cual = null): array
    {
        $out = [];
        foreach (self::activos() as $a) {
            if ($cual && (int) $cual !== $a->n) {
                continue;
            }
            $out[] = ['url' => $a->url, 'texto' => $a->texto];
        }
        return $out;
    }

    /** Bloque de la acción para el prompt del bot. */
    public static function accionPrompt(): string
    {
        $act = self::activos();
        if (!$act) {
            return '"enviar_afiches" (desactivado: no hay afiches cargados). Si preguntan por programas, resume en pocas líneas.';
        }
        $lista = implode("\n", array_map(function ($a) {
            return '    ' . $a->n . ' = ' . ($a->titulo !== '' ? $a->titulo : 'Afiche ' . $a->n);
        }, $act));
        return '"enviar_afiches"' . "\n"
            . '  → El cliente pregunta qué programas hay, precios, qué incluye cada uno o pide información general.' . "\n"
            . '    Envía el afiche en vez de escribir la lista de programas: una imagen se entiende mejor que mucho texto.' . "\n"
            . '  → datos: { "afiche": N o null } (null envía todos los activos). Afiches cargados:' . "\n" . $lista . "\n"
            . '  → En el mensaje di en una línea que le envías el afiche y haz UNA pregunta para avanzar (fecha y cantidad de personas).' . "\n"
            . '    No repitas en el texto los precios ni el detalle que ya muestra el afiche. Para totales sigue usando "cotizar".' . "\n"
            . '    Envíalo una sola vez por conversación salvo que lo vuelvan a pedir.';
    }
}
