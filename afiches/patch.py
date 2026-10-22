import sys
base = sys.argv[1]
def rw(p, fn):
    path = base + '/' + p
    s = open(path, encoding='utf-8').read(); n = fn(s)
    if n is None: print('SIN CAMBIO', p); return
    open(path, 'w', encoding='utf-8').write(n); print('OK', p)

def prompt(s):
    if '__aficheAccion' in s: return None
    a = "        $__aprendizajes = \\App\\Services\\BotAprendizajeService::textoAprobados();"
    if a not in s or '{$__menuAccion}' not in s: raise SystemExit('ERROR: BotPromptService distinto')
    s = s.replace(a, "        $__aficheAccion = \\App\\Services\\BotAfichesService::accionPrompt();\n" + a, 1)
    return s.replace('{$__menuAccion}', '{$__menuAccion}\n\n{$__aficheAccion}', 1)
rw('app/Services/BotPromptService.php', prompt)

def bot(s):
    if "'enviar_afiches'" in s: return None
    a = "        if (($respuesta['accion'] ?? '') === 'escalar_humano' && !$simulacion) {"
    if a not in s: raise SystemExit('ERROR: BotController distinto')
    b = ("        // Afiches de programas: se envían como imagen antes del texto\n"
         "        if (($respuesta['accion'] ?? '') === 'enviar_afiches') {\n"
         "            foreach (\\App\\Services\\BotAfichesService::imagenes($respuesta['datos']['afiche'] ?? null) as $img) {\n"
         "                if (count($this->imagenes) < 4) {\n"
         "                    $this->imagenes[] = $img;\n"
         "                }\n"
         "            }\n"
         "        }\n\n")
    return s.replace(a, b + a, 1)
rw('app/Http/Controllers/Api/BotController.php', bot)

def vista(s):
    if 'data-tab="afiches"' in s: return None
    a = '<button type="button" class="bc-tab-btn" data-tab="menu">Menú</button>'
    m = '{{-- ===== Menú en PDF ===== --}}'
    if a not in s or m not in s: raise SystemExit('ERROR: vista de configuracion distinta')
    s = s.replace(a, a + '\n        <button type="button" class="bc-tab-btn" data-tab="afiches">Afiches</button>', 1)
    return s.replace(m, "@include('themes.backoffice.pages.bot-configuracion.includes._afiches')\n\n    " + m, 1)
rw('resources/views/themes/backoffice/pages/bot-configuracion/index.blade.php', vista)

def web(s):
    if 'BotAfichesController' in s: return None
    a = "Route::post('/fotos', 'BotConfiguracionController@updateFotos')->name('fotos.update');"
    if a not in s: raise SystemExit('ERROR: rutas del bot distintas')
    return s.replace(a, a + "\n        Route::post('/afiches', 'BotAfichesController@update')->name('afiches.update');", 1)
rw('routes/web.php', web)
