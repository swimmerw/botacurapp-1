import re, sys, shutil, time, os
# Inicio > Administracion: tarjetas Reservas del dia, Configuracion del bot y Conversaciones del bot.
# Menu lateral: icono visible para Configuracion del Bot (smart_toy no existe en la fuente de iconos del tema).
B = os.environ.get('BASE', '/var/www/html/botacurapp/')
INICIO = B + 'resources/views/themes/backoffice/pages/admin/show.blade.php'
MENU = B + 'resources/views/themes/backoffice/layouts/includes/left-sidebar.blade.php'
ts = time.strftime('%Y%m%d%H%M')

t = open(INICIO, encoding='utf-8').read()
if 'finanzas.reservas_dia' not in t:
    pat = r"(route\('backoffice\.compras\.botacura'\).*?</a>\s*</div>)(\s*@endif)"
    if len(re.findall(pat, t, re.S)) != 1:
        sys.exit('ERROR: no encontre la tarjeta "Ventas desde Botacura" en el inicio. No se cambio nada.')
    TARJETAS = r'''

@php
    $reservasHoy = \DB::table('reservas')->whereDate('created_at', now()->toDateString())->count();
    $comprobantesPorRevisar = \Schema::hasTable('comprobantes_revision') ? \DB::table('comprobantes_revision')->where('estado', 'pendiente')->count() : 0;
@endphp
<div class="col s12 m6 l3">
<a href="{{ route('backoffice.finanzas.reservas_dia') }}">
<div class="animate__animated animate__backInLeft card {{ $comprobantesPorRevisar ? 'gradient-45deg-orange-amber black-text' : 'gradient-45deg-green-teal white-text' }} gradient-shadow min-height-100"
style="--animate-delay: 1s; --animate-duration: 2s;">
<div class="padding-4">
<div class="col s7 m7">
<i class="material-icons background-round mt-5">event_available</i>
<p>Reservas del día</p>
</div>
<div class="col s5 m5 right-align">
<h5 class="mb-0">{{ $reservasHoy }}</h5>
<p class="no-margin">{{ $comprobantesPorRevisar ? $comprobantesPorRevisar . ' por revisar' : 'hoy' }}</p>
</div>
</div>
</div>
</a>
</div>

@if (Auth::user()->has_role(config('app.admin_role')))
@php
    $conversacionesHoy = \Schema::hasTable('bot_conversaciones') ? \DB::table('bot_conversaciones')->whereDate('updated_at', now()->toDateString())->count() : 0;
    $botActivo = \App\BotSeccionTexto::where('clave', 'bot_activo')->value('contenido') !== '0';
@endphp
<div class="col s12 m6 l3">
<a href="{{ route('backoffice.bot-configuracion.index') }}">
<div class="animate__animated animate__backInLeft card gradient-45deg-purple-deep-purple gradient-shadow min-height-100 white-text"
style="--animate-delay: 1s; --animate-duration: 2s;">
<div class="padding-4">
<div class="col s7 m7">
<i class="material-icons background-round mt-5">android</i>
<p>Configuración del bot</p>
</div>
<div class="col s5 m5 right-align">
<h5 class="mb-0">{{ $botActivo ? 'On' : 'Off' }}</h5>
<p class="no-margin">{{ $botActivo ? 'activo' : 'apagado' }}</p>
</div>
</div>
</div>
</a>
</div>

<div class="col s12 m6 l3">
<a href="{{ route('backoffice.bot-configuracion.index') }}#aprendizajes">
<div class="animate__animated animate__backInLeft card gradient-45deg-purple-deep-purple gradient-shadow min-height-100 white-text"
style="--animate-delay: 1s; --animate-duration: 2s;">
<div class="padding-4">
<div class="col s7 m7">
<i class="material-icons background-round mt-5">forum</i>
<p>Conversaciones del bot</p>
</div>
<div class="col s5 m5 right-align">
<h5 class="mb-0">{{ $conversacionesHoy }}</h5>
<p class="no-margin">hoy</p>
</div>
</div>
</div>
</a>
</div>
@endif'''
    t = re.sub(pat, lambda x: x.group(1) + TARJETAS + x.group(2), t, count=1, flags=re.S)
    shutil.copy(INICIO, INICIO + '.bak.' + ts)
    open(INICIO, 'w', encoding='utf-8').write(t)
    print('Tarjetas agregadas al cuadro Administracion')

m = open(MENU, encoding='utf-8').read()
pat = r"(route \('backoffice\.bot-configuracion\.index'\) \}\}\">\s*<i class=\"material-icons\">\s*)smart_toy"
if len(re.findall(pat, m)) == 1:
    m = re.sub(pat, lambda x: x.group(1) + 'android', m, count=1)
    shutil.copy(MENU, MENU + '.bak.' + ts)
    open(MENU, 'w', encoding='utf-8').write(m)
    print('Icono del menu: Configuracion del Bot')
elif 'bot-configuracion.index' in m and 'android' in m:
    print('Icono del menu ya estaba')
else:
    print('AVISO: no encontre el icono de Configuracion del Bot en el menu')
