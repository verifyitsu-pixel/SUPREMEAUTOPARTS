<?php
defined( 'ABSPATH' ) || exit;

final class SA_Admin {
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'settings' ) );
		add_action( 'admin_notices', array( self::class, 'woocommerce_notice' ) );
	}
	public static function menu(): void {
		add_menu_page( 'Supreme Autoparts', 'Supreme Autoparts', 'manage_woocommerce', 'supreme-autoparts', array( self::class, 'page' ), 'dashicons-car', 56 );
	}
	public static function settings(): void {
		register_setting( 'sa_brand', 'sa_brand_settings', array( 'sanitize_callback' => array( self::class, 'sanitize' ) ) );
	}
	public static function sanitize( array $input ): array {
		$out = array();
		foreach ( array( 'name', 'tagline', 'phone', 'email', 'hours', 'address', 'site_url' ) as $key ) $out[ $key ] = sanitize_text_field( $input[ $key ] ?? '' );
		$out['free_ship'] = max( 0, (float) ( $input['free_ship'] ?? 0 ) );
		return $out;
	}
	public static function page(): void {
		$settings = array( 'name', 'tagline', 'phone', 'email', 'hours', 'address', 'site_url', 'free_ship' ); ?>
		<div class="wrap"><h1>Supreme Autoparts</h1><p>Central brand and storefront configuration. Environment variables prefixed with <code>SA_</code> override these values.</p>
		<form method="post" action="options.php"><?php settings_fields( 'sa_brand' ); ?><table class="form-table"><tbody>
		<?php foreach ( $settings as $key ) : ?><tr><th><label for="sa-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></label></th><td><input class="regular-text" id="sa-<?php echo esc_attr( $key ); ?>" name="sa_brand_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( SA_Brand::get( $key ) ); ?>"></td></tr><?php endforeach; ?>
		</tbody></table><?php submit_button(); ?></form>
		<hr><h2>Catalog operations</h2><p>Use WP-CLI for safe, resumable catalog imports:</p><pre>wp supreme catalog import /opt/supreme/data/products.csv --limit=500 --status=draft</pre>
		<p>Vehicle terms can be managed under Products → Makes, Models, and Years. Native WooCommerce screens manage products, inventory, orders, customers, coupons, taxes, shipping, and gateways.</p></div>
	<?php }
	public static function woocommerce_notice(): void {
		if ( current_user_can( 'activate_plugins' ) && ! class_exists( 'WooCommerce' ) ) echo '<div class="notice notice-error"><p><strong>Supreme Autoparts Core requires WooCommerce.</strong> Install and activate WooCommerce before importing catalog data.</p></div>';
	}
}
