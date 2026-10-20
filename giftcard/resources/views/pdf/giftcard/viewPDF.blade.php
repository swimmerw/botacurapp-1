@php
    // Diseño "Valle" (oct 2026). wkhtmltopdf usa un WebKit antiguo: sin flex ni grid, todo con posiciones y tablas.
    $f = function ($p) { return 'file:///' . ltrim(str_replace('\\', '/', public_path($p)), '/'); };
    $acento = '#037A61';
    $conDuracion = ['masaje', 'tinaja', 'sauna'];
    $personas = (int) ($gc->cantidad_personas ?? 0);
    $zoom = isset($zoom) ? (float) $zoom : 1.0; // 1.3 cuando el wkhtmltopdf del servidor achica la página
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Gift Card Botacura</title>
<style>
    @font-face { font-family: 'GCSerif'; src: url("{{ $f('assents/fonts/giftcard/fraunces-400.ttf') }}") format('truetype'); }
    @font-face { font-family: 'GCSerifBold'; src: url("{{ $f('assents/fonts/giftcard/fraunces-600.ttf') }}") format('truetype'); }
    @font-face { font-family: 'GCSans'; src: url("{{ $f('assents/fonts/giftcard/manrope-400.ttf') }}") format('truetype'); }
    @font-face { font-family: 'GCSansMedium'; src: url("{{ $f('assents/fonts/giftcard/manrope-500.ttf') }}") format('truetype'); }
    @font-face { font-family: 'GCSansBold'; src: url("{{ $f('assents/fonts/giftcard/manrope-700.ttf') }}") format('truetype'); }

    html, body { margin: 0; padding: 0; background: #F4EFE4; }
    body { font-family: 'GCSans', Arial, sans-serif; color: #1D2A25; }
    .hoja { position: relative; width: 1123px; height: 793px; overflow: hidden; background: #F4EFE4; }

    .foto { position: absolute; left: 0; top: 0; width: 470px; height: 793px; }
    .foto img.fondo { position: absolute; left: 0; top: 0; width: 470px; height: 793px; }
    .logo { position: absolute; top: 44px; left: 130px; width: 210px; }
    .programa { position: absolute; left: 48px; right: 48px; bottom: 46px; color: #F4EFE4; }
    .programa .etq { font-family: 'GCSansBold', Arial, sans-serif; font-size: 12px; letter-spacing: 3px; text-transform: uppercase; color: #E6E0D2; }
    .programa .nombre { font-family: 'GCSerifBold', Georgia, serif; font-size: 30px; line-height: 1.15; margin: 6px 0 14px; }
    .programa .serv { font-size: 15px; line-height: 1.75; }

    .lado { position: absolute; left: 470px; top: 0; width: 525px; height: 737px; padding: 56px 64px 0 64px; }
    .eyebrow { font-family: 'GCSansBold', Arial, sans-serif; font-size: 13px; letter-spacing: 4px; text-transform: uppercase; color: {{ $acento }}; }
    h1 { font-family: 'GCSerif', Georgia, serif; font-weight: normal; font-size: 76px; line-height: 1; margin: 14px 0 0; }
    .pill { position: absolute; top: 60px; right: 64px; border: 1.5px solid {{ $acento }}; color: {{ $acento }}; border-radius: 30px; padding: 7px 18px; font-family: 'GCSansBold', Arial, sans-serif; font-size: 14px; white-space: nowrap; }
    .linea { height: 1px; background: #CFC6B4; margin: 36px 0 30px; }
    table.datos { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.datos td { width: 50%; vertical-align: top; padding: 0 16px 26px 0; }
    .etq { font-family: 'GCSansMedium', Arial, sans-serif; font-size: 12px; letter-spacing: 2.5px; text-transform: uppercase; color: #5E6A64; }
    .nombre-gc { font-family: 'GCSerif', Georgia, serif; font-size: 28px; line-height: 1.15; margin-top: 6px; }
    .dato { font-family: 'GCSansMedium', Arial, sans-serif; font-size: 20px; margin-top: 6px; }

    .codigo { position: absolute; left: 64px; right: 64px; bottom: 70px; background: #FFFFFF; border-radius: 14px; padding: 20px 28px; }
    .codigo table { width: 100%; border-collapse: collapse; }
    .codigo .valor { font-family: 'GCSerifBold', Georgia, serif; font-size: 36px; letter-spacing: 5px; color: {{ $acento }}; margin-top: 4px; }
    .codigo img { height: 60px; width: 220px; }
    .nota { position: absolute; left: 64px; right: 64px; bottom: 40px; font-size: 12px; color: #5E6A64; }
</style>
</head>
<body>
<div class="hoja" @if ($zoom != 1.0) style="zoom: {{ $zoom }};" @endif>
    <div class="foto">
        <img class="fondo" src="{{ $f('images/gc/giftcard-foto.jpg') }}" alt="">
        <img class="logo" src="{{ $f('images/gc/logo-crema.png') }}" alt="Botacura · Cajón del Maipo">
        <div class="programa">
            <div class="etq">Programa</div>
            <div class="nombre">{{ $programa->nombre_programa }}</div>
            <div class="serv">
                @foreach ($programa->servicios as $servicio)
                    <div>{{ $servicio->nombre_servicio }}@if (in_array(strtolower($servicio->nombre_servicio), $conDuracion) && $servicio->duracion) · {{ $servicio->duracion }} min @endif @if (strtolower($servicio->nombre_servicio) == 'piscina') (agua fría) @endif</div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="lado">
        <div class="eyebrow">Un regalo para desconectarse</div>
        <h1>Gift Card</h1>
        @if ($personas > 0)
            <div class="pill">{{ $personas }} {{ $personas === 1 ? 'persona' : 'personas' }}</div>
        @endif
        <div class="linea"></div>
        <table class="datos">
            <tr>
                <td><div class="etq">Para</div><div class="nombre-gc">{{ $gc->para }}</div></td>
                <td><div class="etq">De</div><div class="nombre-gc">{{ $gc->de }}</div></td>
            </tr>
            <tr>
                <td><div class="etq">Válida hasta</div><div class="dato">{{ $gc->valido }}</div></td>
                <td><div class="etq">Agenda por WhatsApp</div><div class="dato">+56 9 7448 4112</div></td>
            </tr>
        </table>

        <div class="codigo">
            <table><tr>
                <td style="vertical-align: middle;"><div class="etq">Código</div><div class="valor">{{ $gc->codigo }}</div></td>
                <td style="vertical-align: middle; text-align: right;"><img src="data:image/png;base64,{{ $barcode }}" alt="Código de barras"></td>
            </tr></table>
        </div>
        <div class="nota">Presenta este código al reservar. Válida para una visita, no canjeable por dinero.</div>
    </div>
</div>
</body>
</html>
