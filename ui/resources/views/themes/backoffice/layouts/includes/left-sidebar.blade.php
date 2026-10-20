{{--
    Menú lateral (oct 2026): secciones plegables, íconos Material Symbols, mismo control de roles que antes.
    Cada opción se muestra solo si existe su ruta y el usuario tiene el rol. El menú anterior quedó en
    left-sidebar.blade.php.bak.<fecha> en el servidor.
--}}
@php
    $u = Auth::user();
    $rol = function () use ($u) {
        foreach (func_get_args() as $r) {
            if ($u->has_role(config('app.' . $r . '_role'))) {
                return true;
            }
        }
        return false;
    };
    $fin = class_exists(\App\Http\Controllers\FinanzasCorreoController::class)
        && \App\Http\Controllers\FinanzasCorreoController::puedeVer();
    $esGarzonOAnfitriona = $rol('garzon', 'anfitriona');
    $staff = $u->has_any_role([config('app.garzon_role'), config('app.anfitriona_role'), config('app.barman_role'), config('app.cocina_role'), config('app.jefe_local_role')]);

    // [texto, ícono, ruta, parámetros, visible, patrón de ruta activa, id del texto]
    $grupos = [
        ['Inicio', 'space_dashboard', [
            ['Panel', 'monitoring', 'backoffice.admin.show', [], $rol('admin', 'anfitriona', 'jefe_local'), 'backoffice.admin.show'],
            ['Mis accesos', 'apps', 'backoffice.admin.menu', [], true, 'backoffice.admin.menu'],
        ]],
        ['Reservas', 'event', [
            ['Registro del día', 'today', $esGarzonOAnfitriona ? 'backoffice.reservas.registro' : 'backoffice.reservas.registros',
                $esGarzonOAnfitriona ? ['fecha' => now()->format('d-m-Y')] : [], $rol('admin', 'anfitriona', 'jefe_local', 'garzon'), 'backoffice.reservas.registro*'],
            ['Horarios', 'calendar_month', 'backoffice.reserva.index', [], $rol('admin', 'anfitriona', 'jefe_local'), 'backoffice.reserva.index'],
            ['Clientes', 'group', 'backoffice.cliente.index', [], $rol('admin', 'jefe_local'), 'backoffice.cliente.*'],
            ['Cotizaciones', 'request_quote', 'backoffice.cotizacion.index', [], $rol('admin', 'jefe_local'), 'backoffice.cotizacion.*'],
            ['Gift cards', 'redeem', 'backoffice.giftcards.index', [], $rol('admin', 'jefe_local'), 'backoffice.giftcards.*'],
        ]],
        ['Operación', 'room_service', [
            ['Consumos', 'receipt', 'backoffice.reserva.venta.cierre', [], $rol('garzon', 'anfitriona', 'admin', 'jefe_local'), 'backoffice.reserva.venta.cierre'],
            ['Cocina · menús', 'restaurant', 'backoffice.menu.index', [], $rol('cocina', 'garzon', 'admin', 'jefe_local'), 'backoffice.menu.*'],
            ['Barra · bebidas', 'local_bar', 'backoffice.barman.index', [], $rol('barman', 'admin', 'jefe_local'), 'backoffice.barman.index'],
            ['Bebidas pedidas', 'local_bar', 'backoffice.barman.bebidas', [], $rol('garzon'), 'backoffice.barman.bebidas', 'bebidasGarzon'],
            ['Masajes', 'spa', 'backoffice.masaje.index', [], $rol('admin', 'masoterapeuta', 'jefe_local'), 'backoffice.masaje.*'],
            ['Venta directa', 'point_of_sale', 'backoffice.venta_directa.index', [], $rol('admin', 'anfitriona', 'garzon', 'jefe_local'), 'backoffice.venta_directa.*'],
            ['Poro Poro', 'local_florist', 'backoffice.ventas_poroporo.index', [], $rol('admin', 'anfitriona', 'garzon', 'jefe_local'), 'backoffice.ventas_poroporo.*'],
        ]],
        ['Catálogo', 'menu_book', [
            ['Programas', 'hot_tub', 'backoffice.programa.index', [], $rol('admin', 'jefe_local'), 'backoffice.programa.*'],
            ['Servicios', 'category', 'backoffice.servicio.index', [], $rol('admin', 'jefe_local'), 'backoffice.servicio.*'],
            ['Complementos', 'add_circle', 'backoffice.complemento.index', [], $rol('admin', 'jefe_local'), 'backoffice.complemento.*'],
            ['Productos', 'inventory_2', 'backoffice.producto.index', [], $rol('admin', 'cocina', 'jefe_local'), 'backoffice.producto.*'],
        ]],
        ['Inventario', 'warehouse', [
            ['Stock', 'inventory', 'backoffice.inventario.index', [], $rol('admin', 'cocina', 'jefe_local'), 'backoffice.inventario.*'],
            ['Insumos', 'grocery', 'backoffice.insumo.index', [], $rol('admin', 'cocina', 'jefe_local'), 'backoffice.insumo.*'],
        ]],
        ['Finanzas', 'account_balance_wallet', [
            ['Egresos del mes', 'insights', 'backoffice.egreso.mes', [now()->year, now()->month], $fin, 'backoffice.egreso.*'],
            ['Por pagar', 'receipt_long', 'backoffice.finanzas.deudas.index', [], $fin, 'backoffice.finanzas.deudas.*'],
            ['Clasificar', 'rule', 'backoffice.finanzas.clasificar.index', [], $fin, 'backoffice.finanzas.clasificar.*'],
            ['Movimientos', 'swap_vert', 'backoffice.finanzas.correos.movimientos', [], $fin, 'backoffice.finanzas.correos.movimientos'],
            ['Correos bancarios', 'mark_email_read', 'backoffice.finanzas.correos.index', [], $fin, 'backoffice.finanzas.correos.index'],
            ['Reservas del día', 'fact_check', 'backoffice.finanzas.reservas_dia', [], $rol('admin'), 'backoffice.finanzas.reservas_dia*'],
            ['Utilidad', 'trending_up', 'backoffice.finanzas.utilidad', [], $rol('admin'), 'backoffice.finanzas.utilidad'],
            ['F29 estimado', 'calculate', 'backoffice.impuesto.index', [], $rol('admin'), 'backoffice.impuesto.*'],
            ['Informes', 'bar_chart', 'backoffice.informes.index', [], $rol('admin'), 'backoffice.informes.*'],
        ]],
        ['Equipo', 'badge', [
            ['Turnos', 'schedule', 'backoffice.asignacion.index', [], $rol('admin', 'jefe_local'), 'backoffice.asignacion.*'],
            ['Remuneraciones', 'payments', 'backoffice.sueldos.index', [], $rol('admin'), 'backoffice.sueldos.*'],
            ['Mis pagos', 'account_balance_wallet', 'backoffice.sueldo.view', [$u], $staff && !$rol('admin'), 'backoffice.sueldo.view'],
            ['Mis pagos', 'account_balance_wallet', 'backoffice.sueldo.view_maso', [$u], $rol('masoterapeuta') && !$rol('admin'), 'backoffice.sueldo.view_maso'],
            ['Datos bancarios', 'account_balance', 'backoffice.datos-bancarios.index', [], $rol('admin', 'jefe_local'), 'backoffice.datos-bancarios.*'],
        ]],
        ['Sistema', 'settings', [
            ['Usuarios', 'manage_accounts', 'backoffice.user.index', [], $rol('admin', 'jefe_local'), 'backoffice.user.*'],
            ['Roles', 'admin_panel_settings', 'backoffice.role.index', [], $rol('admin', 'jefe_local'), 'backoffice.role.*'],
            ['Permisos', 'key', 'backoffice.permission.index', [], $rol('admin', 'jefe_local'), 'backoffice.permission.*'],
            ['Bot de WhatsApp', 'smart_toy', 'backoffice.bot-configuracion.index', [], $rol('admin'), 'backoffice.bot-configuracion.*'],
            ['Auditoría', 'policy', 'backoffice.auditoria.index', [], $rol('admin'), 'backoffice.auditoria.*'],
        ]],
    ];

    // Solo lo visible y con ruta existente; marca la opción activa
    $menu = [];
    $totalItems = 0;
    foreach ($grupos as $g) {
        $items = [];
        $abierto = false;
        foreach ($g[2] as $it) {
            if (!$it[4] || !Route::has($it[2])) {
                continue;
            }
            $activo = request()->routeIs($it[5]);
            $abierto = $abierto || $activo;
            $items[] = ['t' => $it[0], 'i' => $it[1], 'href' => route($it[2], $it[3]), 'activo' => $activo, 'id' => $it[6] ?? null];
        }
        if ($items) {
            $menu[] = ['t' => $g[0], 'i' => $g[1], 'items' => $items, 'abierto' => $abierto];
            $totalItems += count($items);
        }
    }
    $todoAbierto = $totalItems <= 9; // con pocas opciones (garzón, cocina…) todo queda a la vista
    $iniciales = collect(explode(' ', trim($u->name)))->filter()->take(2)->map(function ($p) { return mb_strtoupper(mb_substr($p, 0, 1)); })->implode('');
@endphp
<aside id="left-sidebar-nav">
    <ul class="side-nav fixed leftside-navigation bx-nav" id="slide-out">
        <li class="bx-marca">
            <a href="{{ route('backoffice.admin.menu') }}" class="bx-logo" aria-label="Inicio">
                <span class="bx-logo-ic"><span class="ms" aria-hidden="true">forest</span></span>
                <span>
                    <span class="bx-logo-t">BOTACURA</span>
                    <span class="bx-logo-s">{{ $u->name }}</span>
                </span>
            </a>
        </li>
        <li class="bx-buscar">
            <label>
                <span class="ms" aria-hidden="true">search</span>
                <input type="search" id="bx-filtro" placeholder="Buscar en el menú" aria-label="Buscar en el menú" autocomplete="off">
            </label>
        </li>
        @foreach($menu as $g)
            <li class="bx-grupo">
                <details {{ ($g['abierto'] || $todoAbierto) ? 'open' : '' }}>
                    <summary>
                        <span class="ms" aria-hidden="true">{{ $g['i'] }}</span>
                        <span class="bx-grupo-t">{{ $g['t'] }}</span>
                        <span class="ms bx-chev" aria-hidden="true">expand_more</span>
                    </summary>
                    <ul>
                        @foreach($g['items'] as $it)
                            <li>
                                <a href="{{ $it['href'] }}" class="bx-item {{ $it['activo'] ? 'activo' : '' }}" @if($it['activo']) aria-current="page" @endif>
                                    <span class="ms" aria-hidden="true">{{ $it['i'] }}</span>
                                    <span class="bx-item-t" @if($it['id']) id="{{ $it['id'] }}" @endif>{{ $it['t'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            </li>
        @endforeach
        <li class="bx-pie">
            <span class="bx-avatar" aria-hidden="true">{{ $iniciales }}</span>
            <span class="bx-pie-t">{{ $u->name }}<br><small>{{ $u->list_roles() }}</small></span>
            <a href="{{ route('logout') }}" class="bx-salir" aria-label="Cerrar sesión" title="Cerrar sesión"
               onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><span class="ms" aria-hidden="true">logout</span></a>
        </li>
    </ul>

    <a class="sidebar-collapse sidenav-trigger btn-floating btn-medium waves-effect waves-light hide-on-large-only bx-abrir"
        data-activates="slide-out" data-target="slide-out" href="#" aria-label="Abrir menú">
        <i class="material-icons">menu</i>
    </a>

    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        @csrf
    </form>
</aside>
<script>
    // Filtro del menú: muestra solo las opciones que coinciden y abre su sección
    (function () {
        var f = document.getElementById('bx-filtro');
        if (!f) { return; }
        var norm = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
        f.addEventListener('input', function () {
            var q = norm(f.value.trim());
            document.querySelectorAll('#slide-out .bx-grupo').forEach(function (g) {
                var d = g.querySelector('details'), hay = 0;
                g.querySelectorAll('.bx-item').forEach(function (a) {
                    var ok = !q || norm(a.textContent).indexOf(q) >= 0 || norm(g.querySelector('.bx-grupo-t').textContent).indexOf(q) >= 0;
                    a.parentNode.style.display = ok ? '' : 'none';
                    hay += ok ? 1 : 0;
                });
                g.style.display = hay ? '' : 'none';
                if (q) { d.open = hay > 0; }
            });
        });
    })();
</script>
