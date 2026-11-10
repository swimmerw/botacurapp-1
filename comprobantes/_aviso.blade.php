@php
    $__csr = \Schema::hasTable('comprobantes_sin_reserva') ? \DB::table('comprobantes_sin_reserva')->where('estado', 'pendiente')->count() : 0;
@endphp
@if ($__csr)
<a href="{{ route('backoffice.comprobantes-sin-reserva.index') }}" style="display:flex;justify-content:space-between;align-items:center;gap:12px;background:#1f5c4a;color:#fff;border-radius:14px;padding:14px 18px;margin:14px 0">
    <span><strong>{{ $__csr }} comprobante{{ $__csr == 1 ? '' : 's' }} sin reserva</strong> · llegaron por WhatsApp y hay que asociarlos a una reserva</span>
    <span style="background:#fff;color:#1f5c4a;border-radius:9px;padding:6px 12px;font-weight:700">Revisar →</span>
</a>
@endif
