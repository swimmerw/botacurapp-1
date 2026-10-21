import sys
hechos=[]
p='app/Http/Controllers/WhatsAppConectarController.php'; s=open(p,encoding='utf-8').read()
if "'code' => 'nullable|string'" not in s:
    s=s.replace("'code' => 'required|string'","'code' => 'nullable|string'",1)
    s=s.replace("        foreach ([null, (string) $request->input('pagina'), url('/') . '/', ''] as $redir) {","        foreach ($request->code ? [null, (string) $request->input('pagina'), url('/') . '/', ''] : [] as $redir) {",1)
    open(p,'w',encoding='utf-8').write(s); hechos.append('controlador')
p='resources/views/whatsapp/conectar.blade.php'; s=open(p,encoding='utf-8').read()
if 'enviar(' not in s:
    a="""        linea('Meta informó: ' + m.event, /^FINISH/.test(m.event), 'cuenta ' + (datos.waba_id || '?') + ' · número ' + (datos.phone_number_id || '?'));"""
    if a not in s: sys.exit('vista: no encontré el aviso de Meta')
    s=s.replace(a, a+"""
        if (enviado && !enviadoConIds && datos.phone_number_id) enviar(null); // el aviso llegó después de cerrar la ventana""",1)
    old_start=s.index("      var code = r.authResponse.code;")
    old_end=s.index("      }, 1200);")+len("      }, 1200);")
    s=s[:old_start]+"""      var code = r.authResponse.code;
      setTimeout(function () { enviar(code); }, 2500); // dar tiempo a que llegue el evento con waba_id y phone_number_id"""+s[old_end:]
    s=s.replace("  var datos = {}, res = document.getElementById('res');","""  var datos = {}, res = document.getElementById('res'), enviado = false, enviadoConIds = false;
  function enviar(code) {
    enviado = true; if (datos.phone_number_id) enviadoConIds = true;
    var b = document.getElementById('go');
    fetch(@json(route('whatsapp.conectar.finalizar')), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      body: JSON.stringify({ pagina: location.origin + location.pathname, code: code, waba_id: datos.waba_id || null, phone_number_id: datos.phone_number_id || null, evento: datos.evento || null })
    }).then(function (x) { return x.json(); }).then(function (j) {
      (j.pasos || []).forEach(function (p) { linea(p[0], p[1], p[2]); });
      if (j.phone_number_id) linea('Identificador del número: ' + j.phone_number_id + ' — úsalo para activar el bot', true);
      b.disabled = false;
    }).catch(function (err) { linea('Error al terminar la conexión', false, String(err)); b.disabled = false; });
  }""",1)
    s=s.replace("      if (!datos.evento) linea('Meta no informó el resultado del registro (puede que no se haya terminado)', false);","      if (!datos.evento) linea('Esperando el resultado del registro de Meta…', false);",1)
    open(p,'w',encoding='utf-8').write(s); hechos.append('vista')
p='resources/views/whatsapp/conectar.blade.php'; s=open(p,encoding='utf-8').read()
if 'typeof e.data' not in s:
    s=s.replace("      var m = JSON.parse(e.data);","      var m = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;\n      try { console.log('[Meta]', JSON.stringify(m)); } catch (z) {}\n      if (m && m.type && m.type !== 'WA_EMBEDDED_SIGNUP' && /WA_|EMBEDDED|SIGNUP/i.test(m.type)) linea('Aviso de Meta: ' + m.type + ' ' + (m.event || ''), false, JSON.stringify(m.data || {}).slice(0, 300));",1)
    open(p,'w',encoding='utf-8').write(s); hechos.append('lectura del aviso')
print('Página Conectar: ' + (', '.join(hechos) if hechos else 'ya estaba'))
