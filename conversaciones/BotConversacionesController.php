<?php

namespace App\Http\Controllers;

use App\Services\BotAprendizajeService;
use Illuminate\Http\Request;

/**
 * Conversaciones del bot (13/10/2026): solo el listado "Conversaciones recientes" de
 * Configuracion del bot > Aprendizajes, con el mismo calculo (BotAprendizajeService),
 * sin metricas ni sugerencias. "Ver" abre el chat con la misma ruta que usa Aprendizajes.
 */
class BotConversacionesController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && $user->has_role(config('app.admin_role')), 403);

        $dias = (int) $request->input('dias', 14);
        if (!in_array($dias, [1, 7, 14, 30, 90], true)) {
            $dias = 14;
        }
        $conversaciones = BotAprendizajeService::conversacionesRecientes($dias, 300);

        return view('themes.backoffice.pages.bot-conversaciones.index', compact('conversaciones', 'dias'));
    }
}
