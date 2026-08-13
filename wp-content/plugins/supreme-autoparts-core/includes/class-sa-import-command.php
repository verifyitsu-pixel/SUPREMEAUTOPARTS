<?php
defined( 'ABSPATH' ) || exit;

/** Streaming, idempotent catalog importer. */
final class SA_Import_Command {
	/** Create the editable site pages, WooCommerce routes, menus, and product categories. */
	public function setup(): void {
		$pages = array(
			'home' => array( 'Home', '' ),
			'shop' => array( 'Shop', '' ),
			'shop-by-vehicle' => array( 'Shop by Vehicle', '[sa_vehicle_selector]' ),
			'about' => array( 'About Supreme Autoparts', '<h2>Built around the right fit</h2><p>Supreme Autoparts helps drivers find lighting, truck accessories, and performance-ready upgrades through clear vehicle fitment and knowledgeable support.</p><h2>Our standard</h2><p>Accurate catalog data, honest product information, secure checkout, and responsive customer care.</p>' ),
			'contact' => array( 'Contact Us', '<h2>Parts support</h2><p>Questions about fitment, availability, or an order? Call or email the support details configured under Supreme Autoparts in the dashboard.</p>' ),
			'shipping-returns' => array( 'Shipping & Returns', '<h2>Shipping</h2><p>Rates and delivery estimates appear at checkout based on the destination, cart contents, and enabled WooCommerce shipping methods.</p><h2>Returns</h2><p>Contact support before returning an item. Products must be unused, complete, and in resalable condition unless defective.</p>' ),
			'privacy-policy' => array( 'Privacy Policy', '<p>This store collects only information needed to operate accounts, fulfill orders, prevent fraud, and provide support. Payment details are handled by the enabled payment provider and are not stored by this theme.</p>' ),
			'terms' => array( 'Terms & Conditions', '<p>Product fitment, availability, pricing, taxes, shipping, and warranties are subject to the details shown at checkout and on the applicable product record.</p>' ),
			'deals' => array( 'Deals', '[sale_products limit="24" columns="4"]' ),
			'cart' => array( 'Cart', '[woocommerce_cart]' ),
			'checkout' => array( 'Checkout', '[woocommerce_checkout]' ),
			'my-account' => array( 'My Account', '[woocommerce_my_account]' ),
		);
		$ids = array();
		foreach ( $pages as $slug => [ $title, $content ] ) {
			$page = get_page_by_path( $slug );
			$record = array( 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content, 'post_status' => 'publish', 'post_type' => 'page' );
			if ( $page ) $record['ID'] = $page->ID;
			$ids[ $slug ] = wp_insert_post( $record );
		}
		update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $ids['home'] );
		update_option( 'woocommerce_shop_page_id', $ids['shop'] );
		update_option( 'woocommerce_cart_page_id', $ids['cart'] ); update_option( 'woocommerce_checkout_page_id', $ids['checkout'] ); update_option( 'woocommerce_myaccount_page_id', $ids['my-account'] );
		$categories = array( 'Headlights', 'Tail Lights', 'LED Lighting', 'Truck Accessories', 'Towing Mirrors', 'Running Boards', 'Grilles & Armor', 'Fog Lights', 'Fender Flares', 'Bull Bars' );
		foreach ( $categories as $category ) if ( ! term_exists( $category, 'product_cat' ) ) wp_insert_term( $category, 'product_cat' );
		update_option( 'woocommerce_currency', 'USD' ); update_option( 'woocommerce_default_country', 'KE' ); update_option( 'woocommerce_enable_guest_checkout', 'yes' );
		WP_CLI::success( 'Editable pages, WooCommerce routes, and catalog categories are ready.' );
	}
	/**
	 * Import the normalized product CSV into WooCommerce.
	 *
	 * ## OPTIONS
	 * <file> CSV path.
	 * [--limit=<n>] Maximum rows (0 = all). Default: 0.
	 * [--offset=<n>] Rows to skip. Default: 0.
	 * [--status=<status>] draft|publish. Default: draft.
	 * [--include-assets] Sideload the public primary image. Disabled by default.
	 * [--dry-run] Validate and count without database writes.
	 */
	public function import( array $args, array $assoc ): void {
		if ( ! class_exists( 'WooCommerce' ) ) WP_CLI::error( 'WooCommerce must be active.' );
		$file = $args[0];
		if ( ! is_readable( $file ) ) WP_CLI::error( 'CSV is not readable: ' . $file );
		$limit = max( 0, (int) ( $assoc['limit'] ?? 0 ) );
		$offset = max( 0, (int) ( $assoc['offset'] ?? 0 ) );
		$status = isset( $assoc['status'] ) ? (string) $assoc['status'] : 'draft';
		if ( ! in_array( $status, array( 'draft', 'publish' ), true ) ) {
			$status = 'draft';
		}
		$dry = isset( $assoc['dry-run'] );
		$assets = isset( $assoc['include-assets'] );
		$handle = fopen( $file, 'rb' );
		$verified_file = json_decode( (string) @file_get_contents( '/opt/supreme/data/verified-prices.json' ), true );
		$verified_prices = is_array( $verified_file['records'] ?? null ) ? $verified_file['records'] : array();
		$headers = fgetcsv( $handle );
		$count = $skipped = $updated = 0;
		while ( false !== ( $values = fgetcsv( $handle ) ) ) {
			if ( $skipped++ < $offset ) continue;
			if ( $limit && $count >= $limit ) break;
			$row = array_combine( $headers, $values );
			if ( empty( $row['source_id'] ) || empty( $row['title'] ) ) continue;
			$count++;
			if ( $dry ) continue;
			$existing = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'meta_key' => '_sa_source_id', 'meta_value' => sanitize_text_field( $row['source_id'] ), 'fields' => 'ids', 'posts_per_page' => 1 ) );
			$product = $existing ? wc_get_product( $existing[0] ) : new WC_Product_Simple();
			$product->set_name( sanitize_text_field( $row['title'] ) );
			$product->set_slug( sanitize_title( $row['slug'] ) );
			$product->set_status( $status );
			$product->set_catalog_visibility( 'visible' );
			$product->set_sku( 'SA-' . sanitize_text_field( $row['source_id'] ) );
			$price_record = isset( $verified_prices[ $row['source_id'] ] )
				? array_merge( $verified_prices[ $row['source_id'] ], array( 'basis' => 'source-verified', 'confidence' => 'verified' ) )
				: $this->estimate_price( $row['title'] );
			$price = $price_record['price'] ?? '';
			$product->set_regular_price( is_numeric( $price ) ? wc_format_decimal( $price ) : '' );
			$product->set_manage_stock( false );
			$product->update_meta_data( '_sa_source_id', sanitize_text_field( $row['source_id'] ) );
			$product->update_meta_data( '_sa_source_url', esc_url_raw( $row['source_url'] ) );
			$product->update_meta_data( '_sa_source_image_url', esc_url_raw( $row['primary_image_url'] ) );
			$product->update_meta_data( '_sa_price_basis', sanitize_text_field( $price_record['basis'] ?? 'unpriced' ) );
			$product->update_meta_data( '_sa_price_confidence', sanitize_key( $price_record['confidence'] ?? 'unknown' ) );
			$product->update_meta_data( '_sa_price_estimated', isset( $price_record['basis'] ) && 0 === strpos( $price_record['basis'], 'market-estimate:' ) ? 'yes' : 'no' );
			$product->update_meta_data( '_sa_imported_at', gmdate( 'c' ) );
			$id = $product->save();
			$this->assign_category( $id, $row['title'] );
			$this->assign_fitment( $id, $row['title'] );
			if ( $existing ) $updated++;
			if ( $assets && ! has_post_thumbnail( $id ) && ! empty( $row['primary_image_url'] ) ) $this->sideload( $id, $row['primary_image_url'] );
			if ( 0 === $count % 100 ) WP_CLI::log( "Processed {$count} rows..." );
		}
		fclose( $handle );
		if ( ! $dry ) clean_post_cache( 0 );
		WP_CLI::success( sprintf( '%s %d rows (%d updated).', $dry ? 'Validated' : 'Imported', $count, $updated ) );
	}
	private function estimate_price( string $title ): array {
		$families = array(
			array( 'truck-bed-rack', '/truck bed rack|ladder rack/i', 699.99, 'medium' ),
			array( 'towing-mirrors-folding', '/(?:power folding|power fold).*(?:tow|towing) mirror|(?:tow|towing) mirror.*(?:power folding|power fold)/i', 599.99, 'high' ),
			array( 'towing-mirrors', '/tow(?:ing)? mirrors?/i', 349.99, 'high' ),
			array( 'running-boards', '/running boards?|nerf bars?|side steps?|step bars?/i', 329.99, 'high' ),
			array( 'lighting-combo', '/headlights?.*(?:tail lights?|grille)|(?:tail lights?|grille).*headlights?/i', 499.99, 'medium' ),
			array( 'headlights-led-projector', '/(?:led|drl).*(?:projector )?headlights?|headlights?.*(?:led|drl)/i', 349.99, 'high' ),
			array( 'headlights-projector', '/projector headlights?/i', 299.99, 'high' ),
			array( 'headlights-replacement-single', '/(?:left|right|driver|passenger) side replacement headlight/i', 109.99, 'high' ),
			array( 'headlights', '/headlights?|headlamp/i', 189.99, 'high' ),
			array( 'tail-lights-led', '/led tail lights?|tube led tail lights?/i', 269.99, 'high' ),
			array( 'tail-lights', '/tail lights?|altezza lights?/i', 179.99, 'high' ),
			array( 'fog-lights', '/fog lights?/i', 99.99, 'high' ),
			array( 'grille-guard', '/grille guard|brush guard|bull bars?/i', 399.99, 'high' ),
			array( 'grille', '/grilles?|grills?/i', 199.99, 'high' ),
			array( 'coilovers', '/coilovers?/i', 99.99, 'high' ),
			array( 'lowering-springs', '/lowering springs?/i', 89.99, 'high' ),
			array( 'cold-air-intake', '/cold air intake/i', 169.99, 'high' ),
			array( 'intake', '/short ram intake|intake system|air intake/i', 149.99, 'medium' ),
			array( 'headers', '/headers?/i', 159.99, 'high' ),
			array( 'radiator', '/radiators?/i', 139.99, 'high' ),
			array( 'exhaust', '/catalytic converter|exhaust|muffler|test pipe/i', 189.99, 'medium' ),
			array( 'side-mirrors', '/side mirrors?|power mirrors?|manual mirrors?/i', 149.99, 'medium' ),
			array( 'body-accessory', '/fender|bumper|spoiler|front lip|rear lip|hood/i', 249.99, 'medium' ),
			array( 'small-accessory', '/window visors?|deflectors?|door handles?|gauge cluster|strut bars?|antenna/i', 69.99, 'high' ),
			array( 'general-accessory', '/.*/i', 149.99, 'low' ),
		);
		foreach ( $families as $definition ) {
			$family = $definition[0]; $pattern = $definition[1]; $price = $definition[2]; $confidence = $definition[3];
			if ( ! preg_match( $pattern, $title ) ) continue;
			if ( preg_match( '/carbon fiber/i', $title ) ) $price += 100;
			if ( preg_match( '/complete kit|combo|and .* set/i', $title ) && 'lighting-combo' !== $family ) $price += 50;
			$price = max( 29.99, min( 899.99, round( $price ) - 0.01 ) );
			return array( 'price' => number_format( $price, 2, '.', '' ), 'basis' => 'market-estimate:' . $family, 'confidence' => $confidence );
		}
		return array( 'price' => '149.99', 'basis' => 'market-estimate:general-accessory', 'confidence' => 'low' );
	}
	private function assign_category( int $product_id, string $title ): void {
		$rules = array( 'Headlights' => '/headlights?|headlamp/i', 'Tail Lights' => '/tail lights?|altezza lights?/i', 'LED Lighting' => '/\bled\b|light bar/i', 'Towing Mirrors' => '/tow(?:ing)? mirrors?/i', 'Running Boards' => '/running boards?|nerf bars?|side steps?/i', 'Grilles & Armor' => '/grille|grill|bull bar|brush guard/i', 'Fog Lights' => '/fog lights?/i', 'Fender Flares' => '/fender flares?/i' );
		foreach ( $rules as $category => $pattern ) {
			if ( preg_match( $pattern, $title ) ) { wp_set_object_terms( $product_id, $category, 'product_cat' ); return; }
		}
		wp_set_object_terms( $product_id, 'Truck Accessories', 'product_cat' );
	}
	private function assign_fitment( int $product_id, string $title ): void {
		static $hierarchy = null;
		$hierarchy_file = '/opt/supreme/data/vehicle-hierarchy.json';
		if ( ! is_readable( $hierarchy_file ) ) return;
		if ( null === $hierarchy ) $hierarchy = json_decode( (string) file_get_contents( $hierarchy_file ), true );
		foreach ( $hierarchy['makes'] ?? array() as $make ) {
			$make_name = (string) ( $make['make'] ?? '' );
			if ( '' === $make_name || ! preg_match( '/^' . preg_quote( $make_name, '/' ) . '\s+(.+?)\s+(19\d{2}|20\d{2})(?:-(19\d{2}|20\d{2}))?\b/i', $title, $matches ) ) continue;
			$model_text = trim( $matches[1] );
			$models = $make['models'] ?? array();
			usort( $models, static fn( $a, $b ) => strlen( $b['model'] ?? '' ) <=> strlen( $a['model'] ?? '' ) );
			$model_name = $model_text;
			foreach ( $models as $model ) {
				if ( 0 === strcasecmp( substr( $model_text, -strlen( $model['model'] ) ), $model['model'] ) ) { $model_name = $model['model']; break; }
			}
			$start = (int) $matches[2]; $end = isset( $matches[3] ) && $matches[3] ? (int) $matches[3] : $start;
			wp_set_object_terms( $product_id, $make_name, 'sa_make' );
			wp_set_object_terms( $product_id, $model_name, 'sa_model' );
			wp_set_object_terms( $product_id, array_map( 'strval', range( $start, min( $end, $start + 40 ) ) ), 'sa_year' );
			update_post_meta( $product_id, '_sa_fitment_summary', sprintf( '%d%s %s %s', $start, $end > $start ? '-' . $end : '', $make_name, $model_name ) );
			break;
		}
	}
	private function sideload( int $product_id, string $url ): void {
		$head = wp_safe_remote_head( esc_url_raw( $url ), array( 'timeout' => 15, 'redirection' => 3 ) );
		$content_type = is_wp_error( $head ) ? '' : (string) wp_remote_retrieve_header( $head, 'content-type' );
		if ( ! str_starts_with( strtolower( $content_type ), 'image/' ) ) {
			update_post_meta( $product_id, '_sa_image_ingest_status', 'source-unavailable' );
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_sideload_image( esc_url_raw( $url ), $product_id, null, 'id' );
		if ( ! is_wp_error( $id ) ) {
			set_post_thumbnail( $product_id, $id );
			update_post_meta( $product_id, '_sa_image_ingest_status', 'complete' );
		}
	}
}
