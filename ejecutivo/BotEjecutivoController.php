<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Atención de ejecutivo (10/11/2026): conversaciones que necesitan a una persona del equipo.
 *  - Derivadas: el bot las pasó a una persona (pago, grupo, excepción, error de la IA, etc.).
 *  - Sin respuesta hace más de 24 h: el bot ya no puede responderlas.
 * Lee los mensajes pendientes del bot (los mismos de Configuración del bot > Conversaciones pendientes).
 */
class BotEjecutivoController extends Controller
{
    const VENTANA_HORAS = 24;

    public static function esDerivado($p): bool
    {
        return stripos((string) $p->mensaje, 'Derivado por el bot') !== false
            || in_array(strtolower((string) $p->tipo), ['derivado', 'escalado', 'escalar_humano', 'humano'], true);
    }

    public static function n9($t): string
    {
        return substr(preg_replace('/\D/', '', (string) $t), -9);
    }

    public function index(Request $request)
    {
        abort_unless(auth()->check(), 403);
        $pendientes = \App\BotMensajePendiente::where('estado', 'pendiente')->orderBy('created_at')->get();

        $porTelefono = [];
        foreach ($pendientes as $p) {
            if (strtolower((string) $p->tipo) === 'comprobante') {
                continue; // van a Comprobantes sin reserva
            }
            $porTelefono[(string) $p->telefono][] = $p;
        }

        $derivadas = [];
        $vencidas = [];
        $atrasados = 0;
        foreach ($porTelefono as $tel => $msgs) {
            $derivado = null;
            foreach ($msgs as $m) {
                if (self::esDerivado($m)) {
                    $derivado = $m;
                }
            }
            $ultimo = end($msgs);
            $soloAtrasados = collect($msgs)->every(function ($m) { return strpos((string) $m->mensaje, '[atrasado') === 0; });
            $horas = Carbon::parse($ultimo->created_at)->diffInMinutes(now()) / 60;

            if (!$derivado && ($soloAtrasados || $horas < self::VENTANA_HORAS)) {
                if ($soloAtrasados) { $atrasados++; }
                continue; // el bot todavía puede responder, o es historial antiguo
            }

            $item = (object) [
                'telefono' => $tel,
                'instagram' => stripos($tel, 'ig') === 0,
                'nombre' => $ultimo->nombre ?: $derivado->nombre ?? null,
                'motivo' => $derivado ? self::motivo($derivado->mensaje) : null,
                'mensajes' => collect($msgs)->map(function ($m) { return self::limpiar($m->mensaje); })->filter()->values(),
                'desde' => Carbon::parse(($derivado ?: $msgs[0])->created_at),
                'ultimo_at' => Carbon::parse($ultimo->created_at),
                'horas_restantes' => max(0, self::VENTANA_HORAS - $horas),
                'conversacion' => $this->conversacion($tel),
            ];
            if (!$item->nombre && $item->conversacion) {
                $item->nombre = $item->conversacion->nombre;
            }
            if ($derivado) { $derivadas[] = $item; } else { $vencidas[] = $item; }
        }

        // Primero las que aun se pueden responder a tiempo (menos tiempo restante arriba); despues las vencidas, mas recientes arriba
        usort($derivadas, function ($a, $b) {
            $va = $a->horas_restantes > 0; $vb = $b->horas_restantes > 0;
            if ($va !== $vb) { return $va ? -1 : 1; }
            return $va ? $a->horas_restantes <=> $b->horas_restantes : $b->ultimo_at <=> $a->ultimo_at;
        });
        usort($vencidas, function ($a, $b) { return $b->ultimo_at <=> $a->ultimo_at; });

        // Filtro por canal (?canal=whatsapp | instagram)
        $canal = in_array($request->query('canal'), ['whatsapp', 'instagram'], true) ? $request->query('canal') : 'todos';
        $todas = array_merge($derivadas, $vencidas);
        $conteo = [
            'todos' => count($todas),
            'whatsapp' => count(array_filter($todas, function ($i) { return !$i->instagram; })),
            'instagram' => count(array_filter($todas, function ($i) { return $i->instagram; })),
        ];
        if ($canal !== 'todos') {
            $f = function ($i) use ($canal) { return $canal === 'instagram' ? $i->instagram : !$i->instagram; };
            $derivadas = array_values(array_filter($derivadas, $f));
            $vencidas = array_values(array_filter($vencidas, $f));
        }

        return view('themes.backoffice.pages.bot-ejecutivo.index', compact('derivadas', 'vencidas', 'atrasados', 'canal', 'conteo'));
    }

    public function atender(Request $request)
    {
        $request->validate(['telefono' => 'required|string|max:60']);
        $tel = $request->telefono;
        $n = \App\BotMensajePendiente::where('telefono', $tel)->where('estado', 'pendiente')
            ->where(function ($q) { $q->whereNull('tipo')->orWhere('tipo', '!=', 'comprobante'); })
            ->update(['estado' => 'atendido', 'updated_at' => now()]);
        Log::info('[BotEjecutivo] Atendida', ['telefono' => $tel, 'mensajes' => $n, 'por' => auth()->id()]);
        $canal = in_array($request->input('canal'), ['whatsapp', 'instagram'], true) ? ['canal' => $request->input('canal')] : [];
        return redirect()->route('backoffice.bot-ejecutivo.index', $canal)->with('success', 'Conversación marcada como atendida.');
    }

    private static function motivo(string $m): ?string
    {
        if (preg_match('/Derivado por el bot\s*\((.*)\)\.\s*(Último|Ultimo) mensaje/su', $m, $x)) {
            return trim($x[1]);
        }
        if (preg_match('/Derivado por el bot\s*\(([^)]*)\)/u', $m, $x)) {
            return trim($x[1]);
        }
        return null;
    }

    private static function limpiar(string $m): string
    {
        $m = preg_replace('/^Derivado por el bot\s*\(.*\)\.\s*(Último|Ultimo) mensaje:\s*/su', '', $m);
        return trim($m);
    }

    private function conversacion(string $tel)
    {
        if (!Schema::hasTable('bot_conversaciones')) {
            return null;
        }
        $q = DB::table('bot_conversaciones');
        $n9 = self::n9($tel);
        $c = (clone $q)->where('usuario_id', $tel)->orderBy('id', 'desc')->first();
        if (!$c && strlen($n9) === 9 && stripos($tel, 'ig') !== 0) {
            $c = (clone $q)->where('usuario_id', 'like', '%' . $n9)->orderBy('id', 'desc')->first();
        }
        if (!$c) {
            return null;
        }
        $h = json_decode($c->historial_json ?: '[]', true) ?: [];
        $h = array_values(array_filter($h, function ($x) {
            return in_array($x['role'] ?? '', ['user', 'assistant'], true) && is_string($x['content'] ?? null);
        }));
        $h = array_map(function ($x) {
            $t = preg_replace('/\[Sistema[^\]]*\]/u', '', $x['content']);
            return ['de' => $x['role'] === 'user' ? 'Cliente' : 'Bot', 'texto' => trim($t)];
        }, array_slice($h, -10));
        return (object) [
            'id' => $c->id,
            'nombre' => $c->nombre_cliente ?? null,
            'historial' => array_values(array_filter($h, function ($x) { return $x['texto'] !== ''; })),
        ];
    }
}
