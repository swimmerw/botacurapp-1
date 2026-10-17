#!/bin/bash
# BotacurApp (AWS): datos agregados para planificar turnos y margen (solo lectura, sin nombres). 17/10/2026
cd /var/www/html/botacurapp
php -r '
require "vendor/autoload.php"; $app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Schema;
$hoy = date("Y-m-d"); $desde = date("Y-m-d", strtotime("-180 days"));
echo "== ROLES (usuarios activos, sueldo base vigente por dia)\n";
foreach (DB::table("roles")->orderBy("id")->get() as $r) {
  $act = DB::table("role_user")->join("users","users.id","=","role_user.user_id")->where("role_user.role_id",$r->id)
        ->when(Schema::hasColumn("users","activo"), function($q){ $q->where("users.activo",1); })->count();
  $base = DB::table("rango_sueldo_roles")->where("role_id",$r->id)->where("vigente_desde","<=",$hoy)
        ->where(function($q) use($hoy){ $q->whereNull("vigente_hasta")->orWhere("vigente_hasta",">=",$hoy); })->orderByDesc("vigente_desde")->value("sueldo_base");
  echo $r->id.";".$r->slug.";".$r->name.";activos=".$act.";base=".($base ?? "-")."\n";
}
if (Schema::hasColumn("users","salario")) {
  echo "== PERSONAL ACTIVO (nombre;rol;salario dia) \n";
  foreach (DB::table("users")->join("role_user","users.id","=","role_user.user_id")->join("roles","roles.id","=","role_user.role_id")
    ->when(Schema::hasColumn("users","activo"), function($q){ $q->where("users.activo",1); })
    ->select("roles.slug","users.salario","users.id","users.name")->orderBy("users.name")->get() as $u) echo $u->name.";".$u->slug.";".$u->salario."\n";
}
echo "== PAGO MASOTERAPEUTA POR TIPO (duracion;precio;pago)\n";
foreach (DB::table("precios_tipos_masajes as p")->join("tipos_masajes as t","t.id","=","p.id_tipo_masaje")->select("t.nombre","p.duracion_minutos","p.precio_unitario","p.pago_masoterapeuta")->get() as $p)
  echo $p->nombre.";".$p->duracion_minutos.";".($p->precio_unitario ?? "").";".($p->pago_masoterapeuta ?? "")."\n";
echo "== DIAS (fecha;dia;reservas;personas;masajes;ingreso_programas;consumos;personal_pagado;costo_sueldos;personal_por_rol)\n";
$dias = DB::table("reservas")->whereBetween("fecha_visita", [$desde, date("Y-m-d", strtotime("+14 days"))])->select(DB::raw("DATE(fecha_visita) f"))->groupBy("f")->orderBy("f")->pluck("f");
$tieneRP = Schema::hasTable("reserva_programas");
foreach ($dias as $f) {
  $res = DB::table("reservas")->leftJoin("programas","programas.id","=","reservas.id_programa")->whereDate("fecha_visita",$f)
        ->select("reservas.id","reservas.cantidad_personas","programas.valor_programa")->get();
  $ing = 0; foreach ($res as $r) { $v = $tieneRP ? DB::table("reserva_programas")->where("id_reserva",$r->id)->sum(DB::raw("valor_unitario*cantidad_personas")) : 0; $ing += $v ?: ((int)$r->valor_programa * (int)$r->cantidad_personas); }
  $mas = DB::table("masajes")->whereIn("id_reserva", $res->pluck("id"))->count();
  $cons = DB::table("consumos")->join("ventas","ventas.id","=","consumos.id_venta")->whereIn("ventas.id_reserva",$res->pluck("id"))->sum("consumos.subtotal");
  $su = DB::table("sueldos")->whereDate("dia_trabajado",$f)->get(["id_user","valor_dia"]);
  $porRol = DB::table("role_user")->join("roles","roles.id","=","role_user.role_id")->whereIn("role_user.user_id",$su->pluck("id_user"))
        ->select("roles.slug", DB::raw("COUNT(DISTINCT role_user.user_id) n"))->groupBy("roles.slug")->get()->map(function($x){return $x->slug."=".$x->n;})->implode(",");
  echo $f.";".date("D",strtotime($f)).";".$res->count().";".$res->sum("cantidad_personas").";".$mas.";".$ing.";".(int)$cons.";".$su->pluck("id_user")->unique()->count().";".$su->sum("valor_dia").";".$porRol."\n";
}
'
