{{-- ===== Afiches de programas ===== --}}
@php $afiches = \App\Services\BotAfichesService::lista(); @endphp
<div class="bc-panel" id="panel-afiches">
    <p style="color:#555;margin-bottom:16px;">Afiches que el bot envía cuando un cliente pregunta por los programas, precios o qué incluye cada uno. Así no escribe listas largas: manda la imagen y pregunta fecha y cantidad de personas. Hasta {{ \App\Services\BotAfichesService::MAX }} afiches (WhatsApp recibe máximo 4 imágenes por respuesta). Usa imágenes verticales de hasta 8 MB; si el servidor permite menos, el límite es {{ ini_get('upload_max_filesize') }}.</p>
    <form action="{{ route('backoffice.bot-configuracion.afiches.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;">
            @foreach ($afiches as $n => $a)
                <div style="border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;background:#fff;{{ $a->url && !$a->activo ? 'opacity:.6;' : '' }}">
                    <div style="aspect-ratio:3/4;background:#f1f5f9;display:flex;align-items:center;justify-content:center;">
                        @if ($a->url)
                            <a href="{{ $a->url }}" target="_blank" rel="noopener" style="display:block;width:100%;height:100%;"><img src="{{ $a->url }}" alt="Afiche {{ $n }}" style="width:100%;height:100%;object-fit:contain;background:#fff;"></a>
                        @else
                            <span style="color:#94a3b8;font-size:.9rem;">Afiche {{ $n }}: sin imagen</span>
                        @endif
                    </div>
                    <div style="padding:12px 14px;">
                        <input type="text" name="titulo[{{ $n }}]" value="{{ $a->titulo }}" maxlength="120" placeholder="Nombre (ej. Programas Noviembre)" class="browser-default" style="width:100%;height:38px;border:1px solid #d0d0d0;border-radius:10px;padding:0 10px;margin-bottom:8px;box-sizing:border-box;font-weight:600;">
                        <input type="file" name="afiche[{{ $n }}]" accept="image/jpeg,image/png,image/webp" class="browser-default" style="font-size:.85rem;margin-bottom:8px;width:100%;">
                        <textarea name="texto[{{ $n }}]" rows="2" maxlength="1000" class="browser-default" placeholder="Texto que acompaña la imagen (opcional)" style="width:100%;height:auto;border:1px solid #d0d0d0;border-radius:10px;padding:8px 10px;font-size:.9rem;box-sizing:border-box;">{{ $a->texto }}</textarea>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;color:#0f172a;font-size:.9rem;">
                            <input type="checkbox" name="activo[{{ $n }}]" value="1" class="browser-default" style="position:static;opacity:1;pointer-events:auto;width:16px;height:16px;" {{ $a->activo ? 'checked' : '' }}> El bot lo envía
                        </label>
                        @if ($a->url)
                        <label style="display:flex;align-items:center;gap:8px;margin-top:4px;color:#b91c1c;font-size:.85rem;">
                            <input type="checkbox" name="quitar[{{ $n }}]" value="1" class="browser-default" style="position:static;opacity:1;pointer-events:auto;width:16px;height:16px;"> Quitar esta imagen
                        </label>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <button type="submit" class="btn" style="background:#3B82F6;margin-top:18px;">Guardar afiches</button>
    </form>
</div>
<script>
(function () {
    var p = document.getElementById('panel-afiches');
    if (!p) return;
    document.querySelectorAll('.bc-tab-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            if (b.dataset.tab === 'afiches') {
                document.querySelectorAll('.bc-panel').forEach(function (x) { x.classList.toggle('active', x === p); });
                document.querySelectorAll('.bc-tab-btn').forEach(function (x) { x.classList.toggle('active', x === b); });
                if (history.replaceState) history.replaceState(null, '', '#afiches');
            } else {
                p.classList.remove('active');
            }
        });
    });
    document.addEventListener('DOMContentLoaded', function () {
        if (location.hash === '#afiches') { var t = document.querySelector('.bc-tab-btn[data-tab="afiches"]'); if (t) t.click(); }
    });
    if (location.hash === '#afiches' && document.readyState !== 'loading') { var t = document.querySelector('.bc-tab-btn[data-tab="afiches"]'); if (t) t.click(); }
})();
</script>
