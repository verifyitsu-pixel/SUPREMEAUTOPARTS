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
			$ids[ $slug ] = $page ? $page->ID : wp_insert_post( array( 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content, 'post_status' => 'publish', 'post_type' => 'page' ) );
		}
		update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $ids['home'] );
		update_option( 'woocommerce_shop_page_id', $ids['shop'] );
		update_option( 'woocommerce_cart_page_id', $ids['cart'] ); update_opw}ùêÚ$z{-®éÜj×ption" content="Shop performance lighting, towing mirrors, running boards, grilles, and truck accessories by year, make, and model at Supreme Autoparts.">';
}
add_action( 'wp_head', 'sa_document_description', 2 );

function sa_body_classes( array $classes ): array { $classes[] = 'sa-storefront'; return $classes; }
add_filter( 'body_class', 'sa_body_classes' );
