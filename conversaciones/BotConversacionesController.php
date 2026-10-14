<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conversaciones del bot: solo el historial de chats (13/10/2026).
 * Lista por dia (o busqueda por nombre/telefono) y el chat completo de la conversacion elegida.
 */
class BotConversacionesController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && $user->has_role(config('app.admin_role')), 403);

        $buscar = trim((string) $request->input('q', ''));
        try {
            $fecha = Carbon::parse($request->input('fecha', now()->toDateString()))->startOfDay();
        } catch (\Exception $e) {
            $fecha = now()->startOfDay();
        }
        $tieneNombre = Schema::hasColumn('bot_conversaciones', 'nombre_cliente');

        $q = DB::table('bot_conversaciones')->orderBy('updated_at', 'desc');
        if ($buscar !== '') {
            $digitos = preg_replace('/\D/', '', $buscar);
            $q->where(function ($w) use ($buscar, $digitos, $tieneNombre) {
                $w->whereRaw('1 = 0');
                if (strlen($digitos) >= 4) {
                    $w->orWhere('usuario_id', 'like', '%' . $digitos . '%');
                }
                if ($tieneNombre) {
                    $w->orWhere('nombre_cliente', 'like', '%' . $buscar . '%');
                }
            })->limit(100);
        } else {
            $q->whereDate('updated_at', $fecha->toDateString());
        }
        $conversaciones = $q->get()->map(function ($c) {
            $mensajes = $this->mensajes($c->historial_json ?? '[]');
            $c->total_mensajes = count($mensajes);
            $ultimo = $mensajes ? end($mensajes) : null;
            $c->ultimo = $ultimo ? mb_substr($ultimo['texto'], 0, 90) : '';
            $c->con_equipo = !empty($c->humano_hasta) && Carbon::parse($c->humano_hasta)->isFuture();
            return $c;
        });

        $actual = null;
        $mensajes = [];
        if ($request->filled('id')) {
            $actual = DB::table('bot_conversaciones')->where('id', (int) $request->input('id'))->first();
            if ($actual) {
                $mensajes = $this->mensajes($actual->historial_json ?? '[]');
                $actual->con_equipo = !empty($actual->humano_hasta) && Carbon::parse($actual->humano_hasta)->isFuture();
            }
        }

        return view('themes.backoffice.pages.bot-conversaciones.index', compact('conversaciones', 'actual', 'mensajes', 'fecha', 'buscar', 'tieneNombre'));
    }

    /** historial_json -> [['quien' => cliente|bot|equipo|sistema, 'texto' => ...]] */
    private function mensajes($json): array
    {
        $out = [];
        foreach ((json_decode($json, true) ?: []) as $m) {
            $c = $m['content'] ?? '';
            if (is_array($c)) {
                $partes = [];
                foreach ($c as $b) {
                    if (is_array($b) && isset($b['text'])) {
                        $partes[] = $b['text'];
                    } elseif (is_string($b)) {
                        $partes[] = $b;
                    }
                }
                $c = implode("\n", $partes);
            }
            $c = trim((string) $c);
            if ($c === '') {
                continue;
            }
            $rol = $m['role'] ?? 'user';
            if (strpos($c, '[Equipo Botacura') === 0) {
                $quien = 'equipo';
            } elseif ($rol === 'assistant') {
                $quien = 'bot';
            } elseif (preg_match('/^\[Sistema/', $c)) {
                $quien = 'sistema';
            } else {
                $quien = 'cliente';
            }
            $out[] = ['quien' => $quien, 'texto' => $c];
        }
        return $out;
    }
}
