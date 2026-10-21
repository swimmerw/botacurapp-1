<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Encender o apagar el bot en una conversación puntual (Conversaciones del bot). */
class BotChatPausaController extends Controller
{
    public function cambiar(Request $request)
    {
        $u = auth()->user();
        abort_unless($u && $u->has_role(config('app.admin_role')), 403);
        $tel = preg_replace('/\D/', '', (string) $request->input('telefono'));
        if (strlen($tel) === 9 && $tel[0] === '9') {
            $tel = '56' . $tel;
        }
        if (strlen($tel) < 8) {
            return back()->with('error', 'Número no válido.');
        }
        $existe = DB::table('bot_chats_pausados')->where('telefono', $tel)->first();
        if ($existe && $request->input('accion') !== 'apagar') {
            DB::table('bot_chats_pausados')->where('id', $existe->id)->delete();
            return back()->with('success', "Bot encendido para +$tel: vuelve a responder desde el próximo mensaje.");
        }
        if (!$existe) {
            DB::table('bot_chats_pausados')->insert(['telefono' => $tel, 'nombre' => mb_substr((string) $request->input('nombre'), 0, 120) ?: null,
                'pausado_por' => $u->id, 'motivo' => mb_substr((string) $request->input('motivo'), 0, 191) ?: null,
                'created_at' => now(), 'updated_at' => now()]);
        }
        return back()->with('success', "Bot apagado para +$tel: sus mensajes quedan en espera para el equipo.");
    }
}
