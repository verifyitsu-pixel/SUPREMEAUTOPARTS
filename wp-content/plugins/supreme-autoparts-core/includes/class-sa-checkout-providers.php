<?php
defined( 'ABSPATH' ) || exit;

interface SA_Checkout_Provider {
	public function id(): string;
	public function label(): string;
	public function available(): bool;
}

final class SA_Woo_Gateway_Provider implements SA_Checkout_Provider {
	public function __construct( private WC_Payment_Gateway $gateway ) {}
	public function id(): string { return $this->gateway->id; }
	public function label(): string { return wp_strip_all_tags( $this->gateway->get_title() ); }
	public function available(): bool { return $this->gateway->is_available(); }
}

final class SA_Checkout_Providers {
	public static function init(): void { add_filter( 'woocommerce_payment_gateways', array( self::class, 'gateways' ) ); }
	public static function gateways( array $gateways ): array { return $gateways; }
	public static function all(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) return array();
		return array_map( fn( $gateway ) => new SA_Woo_Gateway_Provider( $gateway ), WC()->payment_gateways()->payment_gateways() );
	}
	public static function rest(): WP_REST_Response {
		$data = array_values( array_map( fn( $provider ) => array( 'id' => $provider->id(), 'label' => $provider->label(), 'available' => $provider->available() ), self::all() ) );
		return new WP_REST_Response( $data );
	}
}
