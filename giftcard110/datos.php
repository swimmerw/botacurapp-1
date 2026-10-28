<?php
// Gift Card = Botacura Full a $110.000 por persona: texto del bot y programas habilitados para Gift Card.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$p2 = App\Programa::find(2);
echo "  Botacura Full (#2): \$".number_format($p2->valor_programa, 0, ',', '.')."\n";
foreach (App\Programa::where('permite_giftcard', true)->where('id', '!=', 2)->get() as $p) {
    $p->permite_giftcard = false; $p->save(); echo "  {$p->nombre_programa}: ya no aparece para Gift Card\n";
}
if (!$p2->permite_giftcard) { $p2->permite_giftcard = true; $p2->save(); echo "  Botacura Full: habilitado para Gift Card\n"; }
$texto = 'GIFT CARD: es del programa BOTACURA FULL (nuestro programa más completo). Valor $110.000 por persona, desde 2 personas (total = $110.000 × personas). El descuento de semana NO aplica a la Gift Card.
Incluye: sauna (15 min), tinaja (45 min), masaje relajante de 60 min, desayuno buffet u once buffet, almuerzo a la carta, piscina, espumante de 375 ml por pareja y estación de descanso (2 o 3 personas; desde 4, ubicación en terraza).
Cómo comprarla: nos dicen para cuántas personas y a nombre de quién; pago 100% por transferencia o link de pago; la enviamos digital por WhatsApp.
Condiciones: vigencia de 45 días corridos desde la compra; quien la recibe agenda con al menos 10 días de anticipación y dentro de la vigencia; una vez agendada la fecha no se puede cambiar; después de la compra coordinamos directamente con la persona que la recibe.
Si piden Gift Card de otro programa (Wellness, Relax, Full Day), explica con amabilidad que la Gift Card es del Botacura Full y cuéntale qué incluye.
Las Gift Cards ya vendidas se respetan con el programa y las condiciones con que se compraron (las Full Day como Full Day y las Botacura Full a $95.000 con lo que incluían entonces).';
$r = DB::table('bot_secciones_texto')->where('clave', 'gift_cards')->first();
if ($r) { echo "  texto anterior del bot: ".mb_substr(preg_replace('/\s+/', ' ', $r->contenido), 0, 300)."\n";
    DB::table('bot_secciones_texto')->where('id', $r->id)->update(['contenido' => $texto, 'updated_at' => now()]); }
else DB::table('bot_secciones_texto')->insert(['clave' => 'gift_cards', 'categoria' => 'reglas', 'etiqueta' => 'Gift Cards', 'contenido' => $texto, 'orden' => 99, 'created_at' => now(), 'updated_at' => now()]);
App\Services\BotTarifasService::limpiarCache();
echo "  texto Gift Card del bot: \$110.000 ✓\n";
// la ficha web del Full Day deja de decir "Disponible para Gift Card"
$wc = app(App\Services\WooCommerceService::class);
foreach ([48, 2] as $id) { $p = App\Programa::with('servicios')->find($id);
    try { $wc->updateProduct($p); echo "  ficha web {$p->nombre_programa} ✓\n"; } catch (\Throwable $e) { echo "  ficha ERROR ".substr($e->getMessage(), 0, 120)."\n"; } }
