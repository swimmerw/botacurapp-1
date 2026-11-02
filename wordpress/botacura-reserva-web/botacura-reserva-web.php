<?php
/**
 * Plugin Name: Botacura – Reserva web
 * Description: Compra de programas en botacura.cl con las mismas reglas del bot de app.botacura.cl: personas, fechas con cupo real, ubicación, precio de la app, cupo apartado mientras se paga y solo Webpay.
 * Version: 2.0.0
 * Author: Botacura
 * Requires PHP: 7.2
 * WC requires at least: 6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Botacura_Reserva_Web
{
    const VERSION   = '2.0.0';
    const OPT       = 'botacura_rw';
    const REST_NS   = 'botacura-rw/v1';
    const GATEWAY   = 'transbank_webpay_plus_rest';

    /** @var array|null */
    private static $apartado = null;

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu'], 60);
        add_action('admin_init', [__CLASS__, 'guardar_ajustes']);
        add_action('rest_api_init', [__CLASS__, 'rutas']);

        add_action('woocommerce_before_add_to_cart_button', [__CLASS__, 'widget'], 5);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_filter('woocommerce_is_purchasable', [__CLASS__, 'comprable'], 20, 2);
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validar_carrito'], 5, 3);
        add_filter('woocommerce_add_to_cart_quantity', [__CLASS__, 'cantidad'], 20, 2);
        add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'datos_item'], 20, 2);
        add_filter('woocommerce_get_item_data', [__CLASS__, 'mostrar_item'], 20, 2);
        add_action('woocommerce_before_calculate_totals', [__CLASS__, 'precio'], 20);
        add_filter('woocommerce_cart_item_quantity', [__CLASS__, 'cantidad_fija'], 20, 3);
        add_filter('woocommerce_update_cart_validation', [__CLASS__, 'no_cambiar_cantidad'], 20, 4);
        add_action('woocommerce_cart_item_removed', [__CLASS__, 'item_quitado'], 20, 2);
        add_filter('woocommerce_available_payment_gateways', [__CLASS__, 'solo_webpay'], 50);
        add_action('woocommerce_after_checkout_validation', [__CLASS__, 'validar_checkout'], 20, 2);
        add_action('woocommerce_checkout_create_order', [__CLASS__, 'meta_orden'], 20, 2);
        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'meta_linea'], 20, 4);
        add_action('woocommerce_order_status_failed', [__CLASS__, 'liberar_orden']);
        add_action('woocommerce_order_status_cancelled', [__CLASS__, 'liberar_orden']);
    }

    // ───────────────────────── Ajustes ─────────────────────────

    public static function op($k, $def = '')
    {
        $o = get_option(self::OPT, []);
        return isset($o[$k]) && $o[$k] !== '' ? $o[$k] : $def;
    }

    /** ¿El flujo nuevo aplica a quien está viendo la página? */
    public static function activo()
    {
        $modo = self::op('modo', 'apagado');
        if ($modo === 'todos') {
            return true;
        }
        return $modo === 'admins' && current_user_can('manage_woocommerce');
    }

    public static function menu()
    {
        add_submenu_page('woocommerce', 'Reserva web', 'Reserva web', 'manage_woocommerce', 'botacura-rw', [__CLASS__, 'pagina']);
    }

    public static function guardar_ajustes()
    {
        if (!isset($_POST['botacura_rw_nonce']) || !current_user_can('manage_woocommerce')) {
            return;
        }
        check_admin_referer('botacura_rw', 'botacura_rw_nonce');
        $o = get_option(self::OPT, []);
        $o['url'] = esc_url_raw(trim(wp_unslash($_POST['url'] ?? 'https://app.botacura.cl')));
        $modo = sanitize_key($_POST['modo'] ?? 'apagado');
        $o['modo'] = in_array($modo, ['apagado', 'admins', 'todos'], true) ? $modo : 'apagado';
        $token = trim(wp_unslash($_POST['token'] ?? ''));
        if ($token !== '') {
            $o['token'] = sanitize_text_field($token);
        }
        update_option(self::OPT, $o, false);
        delete_transient('botacura_rw_programas');
        add_settings_error('botacura_rw', 'ok', 'Ajustes guardados.', 'updated');
    }

    public static function pagina()
    {
        $prueba = null;
        if (isset($_GET['probar'])) {
            delete_transient('botacura_rw_programas');
            $prueba = self::api('GET', 'programas');
        }
        $modo = self::op('modo', 'apagado');
        settings_errors('botacura_rw');
        ?>
        <div class="wrap">
            <h1>Botacura – Reserva web</h1>
            <p>Compra con las reglas de app.botacura.cl: personas, fechas con cupo real, ubicación, precio de la app y solo Webpay.</p>
            <form method="post">
                <?php wp_nonce_field('botacura_rw', 'botacura_rw_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tr><th><label for="brw-modo">Estado</label></th><td>
                        <select name="modo" id="brw-modo">
                            <option value="apagado" <?php selected($modo, 'apagado'); ?>>Apagado (la tienda sigue como antes)</option>
                            <option value="admins" <?php selected($modo, 'admins'); ?>>Marcha blanca: solo administradores conectados</option>
                            <option value="todos" <?php selected($modo, 'todos'); ?>>Activo para todos los clientes</option>
                        </select>
                        <p class="description">En "Activo para todos", desactiva el plugin anterior "Botacura – Fecha de Visita".</p>
                    </td></tr>
                    <tr><th><label for="brw-url">URL de la app</label></th><td><input type="url" class="regular-text" name="url" id="brw-url" value="<?php echo esc_attr(self::op('url', 'https://app.botacura.cl')); ?>"></td></tr>
                    <tr><th><label for="brw-token">Token (WEB_API_TOKEN)</label></th><td><input type="password" class="regular-text" name="token" id="brw-token" value="" autocomplete="new-password" placeholder="<?php echo self::op('token') ? 'Guardado — escribe uno nuevo solo para cambiarlo' : 'Pega aquí el token de la app'; ?>"></td></tr>
                </table>
                <?php submit_button('Guardar'); ?>
            </form>
            <p><a class="button" href="<?php echo esc_url(add_query_arg('probar', 1)); ?>">Probar conexión con la app</a></p>
            <?php if ($prueba !== null) : ?>
                <?php if (!empty($prueba['ok'])) : ?>
                    <div class="notice notice-success inline"><p>Conexión OK. Programas que se venden en la web:
                        <?php echo esc_html(implode(', ', array_map(function ($p) { return $p['nombre'] . ' (#' . $p['wc_product_id'] . ')'; }, array_filter($prueba['programas'], function ($p) { return !empty($p['vendible']); })))); ?></p></div>
                <?php else : ?>
                    <div class="notice notice-error inline"><p>No se pudo conectar: <?php echo esc_html($prueba['motivo'] ?? 'error desconocido'); ?></p></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    // ───────────────────────── API de la app ─────────────────────────

    public static function api($metodo, $ruta, $datos = [])
    {
        $token = self::op('token');
        if ($token === '') {
            return ['ok' => false, 'motivo' => 'Falta el token de la app en WooCommerce › Reserva web.'];
        }
        $url = untrailingslashit(self::op('url', 'https://app.botacura.cl')) . '/api/web/' . ltrim($ruta, '/');
        $args = ['timeout' => 25, 'headers' => ['X-Botacura-Token' => $token, 'Accept' => 'application/json']];
        if ($metodo === 'GET') {
            $res = wp_remote_get(add_query_arg($datos, $url), $args);
        } else {
            $args['body'] = $datos;
            $res = wp_remote_post($url, $args);
        }
        if (is_wp_error($res)) {
            return ['ok' => false, 'motivo' => 'No pudimos conectar con el sistema de reservas. Intenta de nuevo en un momento.'];
        }
        $j = json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($j)) {
            return ['ok' => false, 'motivo' => 'Respuesta inválida del sistema de reservas (' . wp_remote_retrieve_response_code($res) . ').'];
        }
        return $j;
    }

    /** Programas de la app indexados por producto de WooCommerce (10 min de caché). */
    public static function programas()
    {
        $p = get_transient('botacura_rw_programas');
        if (!is_array($p)) {
            $r = self::api('GET', 'programas');
            if (empty($r['ok'])) {
                return [];
            }
            $p = [];
            foreach ($r['programas'] as $x) {
                $p[(int) $x['wc_product_id']] = $x;
            }
            set_transient('botacura_rw_programas', $p, 10 * MINUTE_IN_SECONDS);
        }
        return $p;
    }

    public static function programa($product_id)
    {
        $ps = self::programas();
        return $ps[(int) $product_id] ?? null;
    }

    // ───────────────────────── Rutas para el navegador ─────────────────────────

    public static function rutas()
    {
        $pub = function () { return true; };
        register_rest_route(self::REST_NS, '/fechas', ['methods' => 'GET', 'permission_callback' => $pub, 'callback' => function (WP_REST_Request $r) {
            $p = self::programa($r->get_param('producto'));
            if (!$p || empty($p['vendible'])) {
                return new WP_REST_Response(['ok' => false, 'motivo' => 'Este programa no se reserva en la web.'], 404);
            }
            return self::api('GET', 'fechas', ['wc_product_id' => (int) $p['wc_product_id'], 'personas' => max(1, (int) $r->get_param('personas'))]);
        }]);
        register_rest_route(self::REST_NS, '/opciones', ['methods' => 'GET', 'permission_callback' => $pub, 'callback' => function (WP_REST_Request $r) {
            $p = self::programa($r->get_param('producto'));
            if (!$p || empty($p['vendible'])) {
                return new WP_REST_Response(['ok' => false, 'motivo' => 'Este programa no se reserva en la web.'], 404);
            }
            return self::api('GET', 'opciones', [
                'wc_product_id' => (int) $p['wc_product_id'],
                'personas' => max(1, (int) $r->get_param('personas')),
                'fecha' => sanitize_text_field((string) $r->get_param('fecha')),
            ]);
        }]);
    }

    // ───────────────────────── Ficha del programa ─────────────────────────

    public static function assets()
    {
        if (!function_exists('is_product') || !is_product() || !self::activo()) {
            return;
        }
        $p = self::programa(get_the_ID());
        if (!$p || empty($p['vendible'])) {
            return;
        }
        $base = plugin_dir_url(__FILE__) . 'assets/';
        wp_enqueue_style('botacura-rw', $base . 'reserva.css', [], self::VERSION);
        wp_enqueue_script('botacura-rw', $base . 'reserva.js', [], self::VERSION, true);
        wp_localize_script('botacura-rw', 'BotacuraRW', [
            'rest'     => esc_url_raw(rest_url(self::REST_NS)),
            'producto' => (int) get_the_ID(),
            'min'      => (int) $p['min_personas'],
            'max'      => (int) $p['max_personas'],
            'correo'   => 'hola@botacura.cl',
        ]);
    }

    public static function widget()
    {
        global $product;
        if (!$product || !self::activo()) {
            return;
        }
        $p = self::programa($product->get_id());
        if (!$p || empty($p['vendible'])) {
            return;
        }
        echo '<div id="botacura-rw" class="brw" aria-live="polite">'
            . '<input type="hidden" name="brw_personas" value=""><input type="hidden" name="brw_fecha" value="">'
            . '<input type="hidden" name="brw_ubicacion" value="">'
            . '<noscript>Para reservar necesitas activar JavaScript o escribirnos por WhatsApp.</noscript></div>';
    }

    /** Programas de la app que no se venden en la web (S, antiguos, etc.) quedan sin botón de compra. */
    public static function comprable($ok, $product)
    {
        if (!$ok || is_admin() || self::op('modo', 'apagado') !== 'todos') {
            return $ok;
        }
        $id = $product->get_parent_id() ?: $product->get_id();
        $p = self::programa($id);
        return $p && empty($p['vendible']) ? false : $ok;
    }

    // ───────────────────────── Carrito ─────────────────────────

    private static function sesion()
    {
        if (WC()->session && !WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
        $id = WC()->session ? (string) WC()->session->get_customer_id() : '';
        return $id !== '' ? $id : wp_generate_uuid4();
    }

    private static function item_reserva()
    {
        if (!WC()->cart) {
            return null;
        }
        foreach (WC()->cart->get_cart() as $k => $it) {
            if (!empty($it['brw'])) {
                return ['clave_item' => $k, 'item' => $it];
            }
        }
        return null;
    }

    public static function validar_carrito($ok, $product_id, $qty)
    {
        if (!$ok || !self::activo()) {
            return $ok;
        }
        $p = self::programa($product_id);
        if (!$p) {
            return $ok;
        }
        if (empty($p['vendible'])) {
            wc_add_notice('Este programa se reserva por WhatsApp (+56 9 7448 4112).', 'error');
            return false;
        }
        $personas = (int) ($_POST['brw_personas'] ?? 0);
        $fecha = sanitize_text_field(wp_unslash($_POST['brw_fecha'] ?? ''));
        $ubic = sanitize_key($_POST['brw_ubicacion'] ?? '');
        if ($personas < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            wc_add_notice('Elige la cantidad de personas y la fecha de tu visita.', 'error');
            return false;
        }
        $previo = self::item_reserva();
        $r = self::api('POST', 'apartar', [
            'wc_product_id' => (int) $p['wc_product_id'], 'personas' => $personas, 'fecha' => $fecha,
            'ubicacion' => $ubic, 'sesion' => self::sesion(),
            'hold_anterior' => $previo ? (int) $previo['item']['brw']['hold_id'] : 0,
        ]);
        if (empty($r['ok'])) {
            wc_add_notice($r['motivo'] ?? 'No pudimos apartar ese cupo. Prueba con otra fecha.', 'error');
            return false;
        }
        // Una reserva por compra: se reemplaza lo que hubiera en el carrito.
        WC()->cart->empty_cart();
        $_POST['botacura_fecha_visita'] = $fecha; // compatibilidad con el plugin anterior
        self::$apartado = $r;
        return true;
    }

    public static function cantidad($qty, $product_id)
    {
        return self::$apartado ? (int) self::$apartado['personas'] : $qty;
    }

    public static function datos_item($data, $product_id)
    {
        if (self::$apartado) {
            $a = self::$apartado;
            $data['brw'] = [
                'hold_id' => (int) $a['hold_id'], 'clave' => $a['clave'], 'fecha' => $a['fecha'],
                'fecha_texto' => $a['fecha_texto'], 'personas' => (int) $a['personas'],
                'ubicacion' => $a['ubicacion'] ?? '', 'ubicacion_texto' => $a['ubicacion_texto'] ?? '',
                'valor_persona' => (int) $a['valor_persona'], 'total' => (int) $a['total'],
            ];
            $data['botacura_fecha_visita'] = $a['fecha'];
            $data['unique_key'] = md5($a['hold_id'] . microtime());
            self::$apartado = null;
        }
        return $data;
    }

    public static function mostrar_item($items, $item)
    {
        if (empty($item['brw'])) {
            return $items;
        }
        $b = $item['brw'];
        $items = array_values(array_filter($items, function ($i) {
            return stripos((string) ($i['key'] ?? $i['name'] ?? ''), 'fecha') === false;
        }));
        $items[] = ['key' => 'Fecha de visita', 'value' => esc_html(ucfirst((string) $b['fecha_texto']))];
        $items[] = ['key' => 'Personas', 'value' => (int) $b['personas']];
        if (!empty($b['ubicacion_texto'])) {
            $items[] = ['key' => 'Ubicación', 'value' => esc_html(ucfirst((string) $b['ubicacion_texto']))];
        }
        return $items;
    }

    public static function precio($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        foreach ($cart->get_cart() as $it) {
            if (!empty($it['brw']['valor_persona'])) {
                $it['data']->set_price((int) $it['brw']['valor_persona']);
            }
        }
    }

    public static function cantidad_fija($html, $key, $item)
    {
        return !empty($item['brw']) ? '<span class="brw-cant">' . (int) $item['brw']['personas'] . '</span>' : $html;
    }

    public static function no_cambiar_cantidad($ok, $key, $values, $qty)
    {
        if (!empty($values['brw']) && (int) $qty !== (int) $values['brw']['personas']) {
            wc_add_notice('Para cambiar la cantidad de personas, vuelve a elegirla en la página del programa.', 'error');
            return false;
        }
        return $ok;
    }

    public static function item_quitado($key, $cart)
    {
        $it = $cart->removed_cart_contents[$key] ?? null;
        if ($it && !empty($it['brw']['hold_id'])) {
            self::api('POST', 'apartado/' . (int) $it['brw']['hold_id'] . '/liberar', ['clave' => $it['brw']['clave']]);
        }
    }

    // ───────────────────────── Checkout ─────────────────────────

    public static function solo_webpay($gateways)
    {
        if (is_admin() || !WC()->cart || !self::item_reserva()) {
            return $gateways;
        }
        return isset($gateways[self::GATEWAY]) ? [self::GATEWAY => $gateways[self::GATEWAY]] : $gateways;
    }

    public static function validar_checkout($data, $errors)
    {
        $r = self::item_reserva();
        if (!$r) {
            return;
        }
        $tel = preg_replace('/\D/', '', (string) ($data['billing_phone'] ?? ''));
        if (strlen($tel) < 9) {
            $errors->add('brw_tel', 'Ingresa tu WhatsApp (ej: +56 9 1234 5678): por ahí te enviamos la confirmación y el menú.');
        }
        if (($data['payment_method'] ?? '') !== self::GATEWAY) {
            $errors->add('brw_pago', 'Las reservas web se pagan con Webpay.');
        }
        $b = $r['item']['brw'];
        $e = self::api('GET', 'apartado/' . (int) $b['hold_id'], ['clave' => $b['clave']]);
        if (empty($e['ok'])) {
            $errors->add('brw_cupo', $e['motivo'] ?? 'Tu cupo ya no está disponible. Vuelve a elegir la fecha.');
        } elseif (!empty($e['total']) && (int) $e['total'] !== (int) $b['total']) {
            $errors->add('brw_total', 'El valor de tu reserva cambió. Vuelve a elegir la fecha en la página del programa.');
        }
    }

    public static function meta_orden($order, $data)
    {
        $r = self::item_reserva();
        if (!$r) {
            return;
        }
        $b = $r['item']['brw'];
        $order->update_meta_data('billing_fecha_visita', $b['fecha']);
        $order->update_meta_data('botacura_hold_id', (int) $b['hold_id']);
        $order->update_meta_data('botacura_ubicacion', (string) $b['ubicacion']);
        $order->update_meta_data('botacura_personas', (int) $b['personas']);
        $order->update_meta_data('botacura_valor_total', (int) $b['total']);
        $order->update_meta_data('_botacura_hold_clave', (string) $b['clave']);
    }

    public static function meta_linea($item, $key, $values, $order)
    {
        if (empty($values['brw'])) {
            return;
        }
        $b = $values['brw'];
        $item->add_meta_data('Fecha de visita', $b['fecha'], true);
        if (!empty($b['ubicacion_texto'])) {
            $item->add_meta_data('Ubicación', ucfirst((string) $b['ubicacion_texto']), true);
        }
    }

    public static function liberar_orden($order_id)
    {
        $o = wc_get_order($order_id);
        $id = $o ? (int) $o->get_meta('botacura_hold_id') : 0;
        if ($id) {
            self::api('POST', 'apartado/' . $id . '/liberar', ['clave' => (string) $o->get_meta('_botacura_hold_clave')]);
        }
    }
}

add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) {
        Botacura_Reserva_Web::init();
    }
});
