<?php
namespace SockSpecsCN\Commerce;

defined('ABSPATH') || exit;

final class Blocks extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
    public function __construct(string $name) { $this->name = $name; }
    public function initialize() { $this->settings = get_option('woocommerce_' . $this->name . '_settings', []); }
    public function is_active() {
        $gateway = WC()->payment_gateways()->payment_gateways()[$this->name] ?? null;
        return $gateway && $gateway->is_available();
    }
    public function get_payment_method_script_handles() {
        wp_register_script('sscn-payment-blocks', plugins_url('../assets/blocks.js', __FILE__),
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'], '1.0.0', true);
        return ['sscn-payment-blocks'];
    }
    public function get_payment_method_data() {
        $gateway = WC()->payment_gateways()->payment_gateways()[$this->name];
        return ['title' => $gateway->get_title(), 'description' => $gateway->get_description(), 'supports' => ['products']];
    }
}
