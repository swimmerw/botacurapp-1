<?php

namespace App\Http\Controllers;

use App\Services\BotAfichesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class BotAfichesController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'afiche'    => 'nullable|array',
            'afiche.*'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
            'titulo.*'  => 'nullable|string|max:120',
            'texto.*'   => 'nullable|string|max:1000',
            'quitar'    => 'nullable|array',
        ], ['afiche.*.max' => 'Cada afiche puede pesar hasta 8 MB.', 'afiche.*.image' => 'El afiche debe ser una imagen JPG, PNG o WEBP.']);
        BotAfichesService::asegurar();
        $dir = public_path(BotAfichesService::DIR);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $subidos = 0;
        for ($n = 1; $n <= BotAfichesService::MAX; $n++) {
            $f = $request->file('afiche.' . $n);
            if (($f && $f->isValid()) || $request->input('quitar.' . $n)) {
                foreach (glob($dir . '/' . $n . '.*') ?: [] as $viejo) {
                    @unlink($viejo);
                }
            }
            if ($f && $f->isValid()) {
                $ext = strtolower($f->getClientOriginalExtension() ?: 'jpg');
                $ext = in_array($ext, ['jpeg', 'jpg'], true) ? 'jpg' : $ext;
                $f->move($dir, $n . '.' . $ext);
                $subidos++;
            }
            foreach (['titulo', 'texto'] as $campo) {
                DB::table('bot_secciones_texto')->where('clave', 'afiche_' . $n . '_' . $campo)
                    ->update(['contenido' => (string) $request->input($campo . '.' . $n, ''), 'updated_by' => auth()->id(), 'updated_at' => now()]);
            }
            DB::table('bot_secciones_texto')->where('clave', 'afiche_' . $n . '_activo')
                ->update(['contenido' => $request->has('activo.' . $n) ? '1' : '0', 'updated_by' => auth()->id(), 'updated_at' => now()]);
        }
        Alert::success('Listo', $subidos ? "Se guardaron {$subidos} afiche(s). El bot ya los envía." : 'Afiches guardados.')->showConfirmButton();
        return redirect(route('backoffice.bot-configuracion.index') . '#afiches');
    }
}
