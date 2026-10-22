@extends('themes.backoffice.layouts.admin')

@section('title', 'Productos Poro Poro')

@section('breadcrumbs')
<li><a href="{{ route('backoffice.ventas_poroporo.index') }}">Ventas Poro Poro</a></li>
<li>Productos</li>
@endsection

@section('content')
<div class="section">
    <p class="caption"><strong>Productos Poro Poro</strong></p>
    <div class="divider"></div>
    <div class="section">
        <div class="row">
            <div class="col s12 m10 offset-m1">
                @include('themes.backoffice.pages.poroporo.includes._crear_producto')
                @include('themes.backoffice.pages.poroporo.includes._productos_editor')
            </div>
        </div>
    </div>
</div>
@endsection
