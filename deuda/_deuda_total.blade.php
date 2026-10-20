@php
    $pesoT = function ($n) { return '$' . number_format((int) $n, 0, ',', '.'); };
    $maxT = max(1, collect($r['por_tipo'])->max('total'));
@endphp
<section class="card-e dt" aria-label="Mi deuda total">
    <div class="dt-top">
        <div>
            <div class="eyebrow">Deuda pendiente total</div>
            <div class="dt-v">{{ $pesoT($r['total']) }}</div>
            <div class="gris" style="font-size:.85rem">
                @if(count($r['por_ambito']) > 1)
                    Botacura {{ $pesoT($r['por_ambito']['botacura'] ?? 0) }} · Personal {{ $pesoT($r['por_ambito']['personal'] ?? 0) }}
                @else
                    Todo lo que está sin pagar, con lo abonado ya descontado
                @endif
            </div>
        </div>
        @if($r['mensual_total'])
        <div class="dt-mes">
            <div class="eyebrow">Se suma cada mes (aprox.)</div>
            <div class="dt-v2">+{{ $pesoT($r['mensual_total']) }}</div>
            @foreach($r['mensual'] as $k => $v)<div class="gris" style="font-size:.8rem">{{ $k }}: {{ $pesoT($v) }}</div>@endforeach
        </div>
        @endif
    </div>

    <div class="dt-cols">
        <div>
            <div class="eyebrow" style="margin-bottom:6px">De qué se compone</div>
            @foreach($r['por_tipo'] as $t => $x)
                <details class="cat">
                    <summary>
                        <span style="font-weight:500">{{ $t }} <span class="gris" style="font-weight:400">· {{ $x['n'] }}</span></span>
                        <span class="barra"><span style="width: {{ round($x['total'] * 100 / $maxT) }}%; background:#e8a33d"></span></span>
                        <span class="num">{{ $pesoT($x['total']) }}</span>
                        <span class="mas" aria-hidden="true">+</span>
                    </summary>
                    <div class="subs">
                        @foreach(collect($x['items'])->sortByDesc('saldo') as $i)
                            <div class="fila" style="padding:2px 0"><span>{{ $i['nombre'] }}</span><span class="num">{{ $pesoT($i['saldo']) }}</span></div>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
        <div>
            <div class="eyebrow" style="margin-bottom:6px">Mes a mes</div>
            <table class="dt-tab">
                <thead><tr><th>Mes</th><th class="num">Se sumó</th><th class="num">Se pagó</th><th class="num">Saldo</th></tr></thead>
                <tbody>
                    <tr><td class="gris">Al partir ({{ \Carbon\Carbon::parse($r['desde'])->format('d-m') }})</td><td class="num">{{ $pesoT($r['inicial']) }}</td><td class="num gris">—</td><td class="num">{{ $pesoT($r['inicial']) }}</td></tr>
                    @foreach($r['meses'] as $m)
                        <tr>
                            <td>{{ $m['mes'] }}</td>
                            <td class="num {{ $m['suma'] ? 'rojo' : 'gris' }}">{{ $m['suma'] ? '+' . $pesoT($m['suma']) : '—' }}</td>
                            <td class="num {{ $m['pago'] ? 'verde' : 'gris' }}">{{ $m['pago'] ? '−' . $pesoT($m['pago']) : '—' }}</td>
                            <td class="num" style="font-weight:500">{{ $pesoT($m['saldo']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="gris" style="font-size:.78rem;margin:8px 0 0">"Se sumó" son las cuentas, impuestos y deudas nuevas del mes; "Se pagó", los abonos del banco y los hechos a mano.</p>
        </div>
    </div>
</section>
<style>
    .eg .dt { margin-bottom: 18px; border-left: 4px solid #e8a33d; }
    .eg .dt-top { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 14px; }
    .eg .dt-v { font-size: 2.2rem; font-weight: 500; font-variant-numeric: tabular-nums; line-height: 1.15; margin: 2px 0; }
    .eg .dt-mes { background: #fff8ec; border: 1px solid #f1dfba; border-radius: 10px; padding: 10px 14px; min-width: 220px; }
    .eg .dt-v2 { font-size: 1.3rem; font-weight: 500; color: #8a5a0b; font-variant-numeric: tabular-nums; }
    .eg .dt-cols { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 24px; }
    .eg .dt-tab { width: 100%; border-collapse: collapse; font-size: .88rem; }
    .eg .dt-tab th { font-weight: 500; color: #6b756f; font-size: .78rem; text-align: left; padding: 4px 0; border-bottom: 1px solid #eef0ee; }
    .eg .dt-tab th.num { text-align: right; }
    .eg .dt-tab td { padding: 6px 0; border-bottom: 1px solid #f3f4f3; }
    .eg .dt .cat > summary { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) 110px 16px; }
    @media (max-width: 760px) {
        .eg .dt-cols { grid-template-columns: 1fr; }
        .eg .dt-v { font-size: 1.8rem; }
        .eg .dt .cat > summary { grid-template-columns: minmax(0, 1fr) 110px 16px; }
        .eg .dt-tab { font-size: .8rem; }
        .eg .dt-tab td, .eg .dt-tab th { padding-left: 4px; }
    }
</style>
