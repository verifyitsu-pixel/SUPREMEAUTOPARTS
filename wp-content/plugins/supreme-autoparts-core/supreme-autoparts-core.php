<?php
/**
 * Plugin Name: Supreme Autoparts Core
 * Description: Vehicle fitment, catalog import, brand settings, search, and WooCommerce integrations.
 * Version: 1.0.0
 * Author: Supreme Autoparts
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * WC requires at least: 8.7
 * Text Domain: supreme-autoparts
 */

defined( 'ABSPATH' ) || exit;

define( 'SA_CORE_VERSION', '1.0.0' );
define( 'SA_CORE_FILE', __FILE__ );
define( 'SA_CORE_PATH', plugin_dir_path( __FILE__ ) );

require_once SA_CORE_PATH . 'includes/class-sa-brand.php';
require_once SA_CORE_PATH . 'includes/class-sa-fitment.php';
require_once SA_CORE_PATH . 'includes/class-sa-rest.php';
require_once SA_CORE_PATH . 'includes/class-sa-admin.php';
require_once SA_CORE_PATH . 'includes/class-sa-checkout-providers.php';
require_once SA_CORE_PATH . 'includes/class-sa-local-pricing.php';

final class Supreme_Autoparts_Core {
	public static function init(): void {
		SA_Brand::init();
		SA_Fitment::init();
		SA_REST::init();
		SA_Admin::init();
		SA_Checkout_Providers::init();
		SA_Local_Pricing::init();
		add_shortcode( 'sa_vehicle_selector', array( SA_Fitment::class, 'shortcode' ) );
		add_action( 'before_woocommerce_init', array( self::class, 'declare_compatibility' ) );
	}

	public static function declare_compatibility(): void {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}

	public static function activate(): void {
		SA_Fitment::register_taxonomies();
		flush_rewrite_rules();
	}
}

register_activation_hook( __FILE__, array( Supreme_Autoparts_Core::class, 'activate' ) );
add_action( 'plugins_loaded', array( Supreme_Autoparts_Core::class, 'init' ) );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once SA_CORE_PATH . 'includes/class-sa-import-command.php';
	WP_CLI::add_command( 'supreme catalog', 'SA_Import_Command' );
}
