<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Encender o apagar Bot-Acura en Instagram desde Conversaciones del bot (solo administradores). */
class BotInstagramController extends Controller
{
    public function cambiar(Request $request)
    {
        $u = auth()->user();
        abort_unless($u && $u->has_role(config('app.admin_role')), 403);
        $activar = $request->input('accion') === 'encender';
        \App\BotSeccionTexto::updateOrCreate(
            ['clave' => 'bot_instagram_activo'],
            ['categoria' => 'sistema', 'etiqueta' => 'Bot en Instagram activo', 'contenido' => $activar ? '1' : '0', 'updated_by' => $u->id]
        );
        return back()->with('success', $activar
            ? 'Bot de Instagram encendido: responde los mensajes directos que lleguen desde ahora.'
            : 'Bot de Instagram apagado: los mensajes quedan en espera para el equipo.');
    }
}
