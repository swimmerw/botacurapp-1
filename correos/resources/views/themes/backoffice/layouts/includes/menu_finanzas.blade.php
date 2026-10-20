{{-- Finanzas (solo usuarios de FINANZAS_USUARIOS) --}}
@if (class_exists(\App\Http\Controllers\FinanzasCorreoController::class) && \App\Http\Controllers\FinanzasCorreoController::puedeVer())
    <li class="bold">
        <a class="waves-effect waves-cyan" href="{{ route('backoffice.egreso.mes', [now()->year, now()->month]) }}">
            <i class="material-icons">insights</i>
            <span class="nav-text">Egresos del mes</span>
        </a>
    </li>
    <li class="bold">
        <a class="waves-effect waves-cyan" href="{{ route('backoffice.finanzas.deudas.index') }}">
            <i class="material-icons">receipt_long</i>
            <span class="nav-text">Por pagar</span>
        </a>
    </li>
    <li class="bold">
        <a class="waves-effect waves-cyan" href="{{ route('backoffice.finanzas.clasificar.index') }}">
            <i class="material-icons">rule</i>
            <span class="nav-text">Clasificar</span>
        </a>
    </li>
    <li class="bold">
        <a class="waves-effect waves-cyan" href="{{ route('backoffice.finanzas.correos.movimientos') }}">
            <i class="material-icons">swap_vert</i>
            <span class="nav-text">Movimientos</span>
        </a>
    </li>
    <li class="bold">
        <a class="waves-effect waves-cyan" href="{{ route('backoffice.finanzas.correos.index') }}">
            <i class="material-icons">mark_email_read</i>
            <span class="nav-text">Correos bancarios</span>
        </a>
    </li>
@endif
