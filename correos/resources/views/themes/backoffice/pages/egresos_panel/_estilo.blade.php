<style>
    .eg { max-width: 1180px; margin: 0 auto; color: #1f2a26; font-size: 15px; }
    .eg a, .eg a:hover { text-decoration: none; }
    .eg h1 { font-size: 1.75rem; font-weight: 400; margin: 6px 0 2px; }
    .eg h2 { font-size: 1.1rem; font-weight: 500; margin: 0 0 10px; }
    .eg .sub { color: #6b756f; font-size: .9rem; margin: 0; }
    .eg .eyebrow { font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: #6b756f; }
    .eg .top { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; justify-content: space-between; margin-bottom: 16px; }
    .eg .pills { display: flex; flex-wrap: wrap; gap: 6px; }
    .eg nav.pills { background: none; height: auto; line-height: normal; box-shadow: none; width: auto; color: inherit; }
    .eg nav.pills a, .eg nav.pills span { line-height: normal; height: auto; }
    .eg .pill { padding: 8px 14px; border: 1px solid #dfe3e0; border-radius: 999px; background: #fff; color: #3b4541; font-size: .88rem; }
    .eg .pill.on { background: #1f2a26; border-color: #1f2a26; color: #fff; }
    .eg .card-e { background: #fff; border: 1px solid #e6e9e7; border-radius: 12px; padding: 16px 18px; }
    .eg .kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
    .eg .kpi .v { font-size: 1.6rem; font-weight: 500; margin-top: 2px; font-variant-numeric: tabular-nums; }
    .eg .kpi .l { font-size: .82rem; color: #6b756f; }
    .eg .cols { display: flex; flex-wrap: wrap; gap: 18px; align-items: flex-start; }
    .eg .col-a { flex: 3 1 560px; min-width: 0; display: grid; gap: 18px; }
    .eg .col-b { flex: 2 1 340px; min-width: 0; display: grid; gap: 18px; }
    .eg .aviso { border-radius: 12px; padding: 14px 18px; margin-bottom: 16px; display: flex; gap: 12px; align-items: flex-start; }
    .eg .aviso.ok { background: #eef7f2; border: 1px solid #c8e6d6; color: #1b5e45; }
    .eg .aviso.falta { background: #fff8ec; border: 1px solid #f1dfba; color: #6b4a10; }
    .eg .aviso a { color: inherit; border-bottom: 1px solid currentColor; }
    .eg .cat > summary { list-style: none; cursor: pointer; display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) 120px 16px; gap: 14px; align-items: center; padding: 11px 0; border-top: 1px solid #eef0ee; }
    .eg .cat > summary::-webkit-details-marker { display: none; }
    .eg .cat[open] > summary .mas { transform: rotate(45deg); }
    .eg .mas { color: #6b756f; transition: transform .15s; text-align: center; }
    .eg .barra { height: 6px; background: #eef0ee; border-radius: 3px; overflow: hidden; }
    .eg .barra span { display: block; height: 6px; background: #1b7f5b; border-radius: 3px; }
    .eg .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .eg .subs { padding: 0 0 12px 12px; display: grid; gap: 6px; }
    .eg .subs .s { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.6fr) 110px; gap: 12px; font-size: .9rem; }
    .eg .gris { color: #6b756f; }
    .eg .fila { display: flex; justify-content: space-between; gap: 12px; font-size: .9rem; padding: 4px 0; }
    .eg .btn-c { border: 1px solid #1f2a26; background: #1f2a26; color: #fff; border-radius: 8px; padding: 6px 12px; font-size: .85rem; cursor: pointer; line-height: 1.2; }
    .eg .btn-c.ghost { background: #fff; color: #1f2a26; border-color: #cfd5d1; }
    .eg select.browser-default, .eg input.campo { height: 36px; border: 1px solid #cfd5d1; border-radius: 8px; padding: 0 10px; background: #fff; font-size: .88rem; margin: 0; box-sizing: border-box; }
    .eg .deuda { border-top: 1px solid #eef0ee; padding: 12px 0; }
    .eg .deuda:first-of-type { border-top: 0; }
    .eg .prog { height: 4px; background: #eef0ee; border-radius: 2px; margin: 6px 0; }
    .eg .prog span { display: block; height: 4px; background: #1b7f5b; border-radius: 2px; }
    .eg .rojo { color: #a33a12; }
    .eg .verde { color: #1b7f5b; }
    .eg .tag { display: inline-block; font-size: .72rem; padding: 2px 8px; border-radius: 999px; background: #eef3f0; color: #2f5d4b; }
    .eg details.abrir > summary { list-style: none; cursor: pointer; }
    .eg details.abrir > summary::-webkit-details-marker { display: none; }
    .eg .form-l { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 8px; }
    @media (max-width: 760px) {
        .eg .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .eg .cat > summary { grid-template-columns: minmax(0, 1fr) 110px 16px; }
        .eg .cat > summary .barra { display: none; }
        .eg .subs .s { grid-template-columns: minmax(0, 1fr) 100px; }
        .eg .subs .s .gris { display: none; }
    }
</style>
