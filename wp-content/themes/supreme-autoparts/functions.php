<?php
defined( 'ABSPATH' ) || exit;

function sa_theme_setup(): void {
	load_theme_textdomain( 'supreme-autoparts', get_template_directory() . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'woocommerce', array( 'thumbnail_image_width' => 480, 'single_image_width' => 720, 'product_grid' => array( 'default_rows' => 3, 'min_rows' => 1, 'max_rows' => 8, 'default_columns' => 4, 'min_columns' => 2, 'max_columns' => 4 ) ) );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
	register_nav_menus( array( 'primary' => 'Primary menu', 'utility' => 'Utility menu', 'footer' => 'Footer menu' ) );
}
add_action( 'after_setup_theme', 'sa_theme_setup' );

function sa_assets(): void {
	$version = wp_get_theme()->get( 'Version' );
	wp_enqueue_style( 'sa-fonts', 'https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap', array(), null );
	wp_enqueue_style( 'sa-app', get_template_directory_uri() . '/assets/css/app.css', array(), $version );
	wp_enqueue_script( 'sa-app', get_template_directory_uri() . '/assets/js/app.js', array(), $version, true );
	wp_localize_script( 'sa-app', 'saStore', array( 'restUrl' => esc_url_raw( rest_url( 'supreme/v1/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'shopUrl' => sa_wc_page_url( 'shop' ), 'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD' ) );
}
add_action( 'wp_enqueue_scripts', 'sa_assets' );

function sa_brand( string $key ): mixed {
	return class_exists( 'SA_Brand' ) ? SA_Brand::get( $key ) : array( 'name' => 'Supreme Autoparts', 'tagline' => 'Parts that fit. Built to perform.', 'phone' => '+254 714 498 451', 'email' => 'support@supremeautoparts.co.ke', 'hours' => 'Mon–Sat, 8am–6pm EAT', 'address' => 'Midax Plaza, Off Kangundo Rd, Nairobi, Kenya', 'free_ship' => 99 )[ $key ] ?? '';
}

function sa_cart_count(): int { return function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0; }
function sa_wc_page_url( string $page ): string { return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( $page ) : home_url( '/' . ( 'myaccount' === $page ? 'my-account' : $page ) . '/' ); }
function sa_cart_url(): string { return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ); }
function sa_cart_fragments( array $fragments ): array {
	$fragments['[data-cart-count]'] = '<span class="header-cart__count" data-cart-count>' . esc_html( sa_cart_count() ) . '</span>';
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'sa_cart_fragments' );
add_filter( 'woocommerce_enqueue_styles', fn( $styles ) => array() );
add_filter( 'loop_shop_per_page', fn() => 24, 20 );

function sa_widgets(): void {
	register_sidebar( array( 'name' => 'Shop filters', 'id' => 'shop-filters', 'before_widget' => '<section class="filter-widget">', 'after_widget' => '</section>', 'before_title' => '<h3>', 'after_title' => '</h3>' ) );
}
add_action( 'widgets_init', 'sa_widgets' );

function sa_schema(): void {
	if ( is_admin() ) return;
	$data = array( '@context' => 'https://schema.org', '@type' => 'AutoPartsStore', 'name' => sa_brand( 'name' ), 'url' => home_url( '/' ), 'telephone' => sa_brand( 'phone' ), 'email' => sa_brand( 'email' ), 'priceRange' => '$$' );
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES ) . '</script>';
}
add_action( 'wp_head', 'sa_schema', 20 );

function sa_document_description(): void {
	if ( is_front_page() ) {
		$description = 'Shop performance lighting, towing mirrors, running boards, grilles, and truck accessories by year, make, and model at Supreme Autoparts.';
	} elseif ( is_singular( 'product' ) ) {
		$description = 'Shop ' . single_post_title( '', false ) . ' in USD from Supreme Autoparts. Check vehicle fitment, availability, delivery, and secure checkout options.';
	} elseif ( is_singular() ) {
		$description = wp_strip_all_tags( get_the_excerpt() ?: get_the_title() . ' information from Supreme Autoparts.' );
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$description = 'Browse the Supreme Autoparts catalog by category or vehicle make, model, and year.';
	} else {
		return;
	}
	echo '<meta name="description" content="' . esc_attr( wp_trim_words( $description, 28, '' ) ) . '">';
}
add_action( 'wp_head', 'sa_document_description', 2 );

function sa_body_classes( array $classes ): array { $classes[] = 'sa-storefront'; return $classes; }
add_filter( 'body_class', 'sa_body_classes' );
