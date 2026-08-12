<?php
defined( 'ABSPATH' ) || exit;

/** Informational local-currency prices; WooCommerce accounting and checkout stay in USD. */
final class SA_Local_Pricing {
	private const COUNTRY_CURRENCY = array( 'KE' => 'KES', 'UG' => 'UGX', 'TZ' => 'TZS', 'RW' => 'RWF', 'NG' => 'NGN', 'GH' => 'GHS', 'ZA' => 'ZAR', 'GB' => 'GBP', 'DE' => 'EUR', 'FR' => 'EUR', 'CA' => 'CAD', 'AU' => 'AUD', 'IN' => 'INR', 'AE' => 'AED' );
	public static function init(): void { add_filter( 'woocommerce_get_price_html', array( self::class, 'price_html' ), 20, 2 ); add_action( 'woocommerce_review_order_after_order_total', array( self::class, 'checkout_notice' ) ); }
	private static function currency(): string { $location = class_exists( 'WC_Geolocation' ) ? WC_Geolocation::geolocate_ip( '', true, false ) : array(); $country = strtoupper( (string) ( $location['country'] ?? '' ) ); return self::COUNTRY_CURRENCY[ $country ] ?? 'USD'; }
	private static function rates(): array {
		$rates = get_transient( 'sa_usd_fx_rates' ); if ( is_array( $rates ) ) return $rates;
		$response = wp_remote_get( 'https://open.er-api.com/v6/latest/USD', array( 'timeout' => 8 ) ); $data = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true ); $rates = 'success' === ( $data['result'] ?? '' ) && is_array( $data['rates'] ?? null ) ? $data['rates'] : array(); $rates['USD'] = 1.0; set_transient( 'sa_usd_fx_rates', $rates, 12 * HOUR_IN_SECONDS ); return $rates;
	}
	public static function price_html( string $html, WC_Product $product ): string { $currency = self::currency(); $price = (float) wc_get_price_to_display( $product ); $rate = (float) ( self::rates()[ $currency ] ?? 0 ); if ( 'USD' === $currency || $price <= 0 || $rate <= 0 ) return $html; return $html . sprintf( '<small class="sa-local-price">Approx. %s %s</small>', esc_html( $currency ), esc_html( number_format_i18n( $price * $rate, 2 ) ) ); }
	public static function checkout_notice(): void { echo '<tr class="sa-fx-notice"><td colspan="2"><small>Local-currency estimates are informational. Your order is securely charged in USD at checkout.</small></td></tr>'; }
}
