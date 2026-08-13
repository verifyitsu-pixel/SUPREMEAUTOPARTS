<?php
defined( 'ABSPATH' ) || exit;

final class SA_REST {
	public static function init(): void { add_action( 'rest_api_init', array( self::class, 'routes' ) ); }
	public static function routes(): void {
		register_rest_route( 'supreme/v1', '/vehicles', array( 'methods' => 'GET', 'callback' => array( self::class, 'vehicles' ), 'permission_callback' => '__return_true', 'args' => array( 'taxonomy' => array( 'sanitize_callback' => 'sanitize_key' ) ) ) );
		register_rest_route( 'supreme/v1', '/search', array( 'methods' => 'GET', 'callback' => array( self::class, 'search' ), 'permission_callback' => '__return_true', 'args' => array( 'q' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ) ) );
		register_rest_route( 'supreme/v1', '/checkout-providers', array( 'methods' => 'GET', 'callback' => array( 'SA_Checkout_Providers', 'rest' ), 'permission_callback' => '__return_true' ) );
	}
	public static function vehicles( WP_REST_Request $request ): WP_REST_Response {
		$hierarchy_file = '/opt/supreme/data/vehicle-hierarchy.json';
		if ( is_readable( $hierarchy_file ) ) {
			$hierarchy = json_decode( (string) file_get_contents( $hierarchy_file ), true );
			$makes = is_array( $hierarchy['makes'] ?? null ) ? $hierarchy['makes'] : array();
			$type = sanitize_key( (string) ( $request->get_param( 'taxonomy' ) ?: 'make' ) );
			$make_slug = sanitize_title( (string) $request->get_param( 'make' ) );
			$model_slug = sanitize_title( (string) $request->get_param( 'model' ) );
			if ( 'make' === $type ) {
				return new WP_REST_Response( array_map( static fn( $row ) => array( 'name' => $row['make'], 'slug' => sanitize_title( $row['make'] ) ), $makes ) );
			}
			$make = current( array_filter( $makes, static fn( $row ) => sanitize_title( $row['make'] ?? '' ) === $make_slug ) );
			if ( ! is_array( $make ) ) return new WP_REST_Response( array() );
			if ( 'model' === $type ) {
				return new WP_REST_Response( array_map( static fn( $row ) => array( 'name' => $row['model'], 'slug' => sanitize_title( $row['model'] ) ), $make['models'] ?? array() ) );
			}
			if ( 'year' === $type ) {
				$model = current( array_filter( $make['models'] ?? array(), static fn( $row ) => sanitize_title( $row['model'] ?? '' ) === $model_slug ) );
				return new WP_REST_Response( is_array( $model ) ? array_values( $model['years'] ?? array() ) : array() );
			}
		}
		$allowed = array( 'make' => 'sa_make', 'model' => 'sa_model', 'year' => 'sa_year' );
		$key = $request->get_param( 'taxonomy' ) ?: 'make';
		if ( ! isset( $allowed[ $key ] ) ) return new WP_REST_Response( array( 'message' => 'Invalid taxonomy.' ), 400 );
		$terms = get_terms( array( 'taxonomy' => $allowed[ $key ], 'hide_empty' => false, 'number' => 500 ) );
		return new WP_REST_Response( is_wp_error( $terms ) ? array() : array_map( fn( $t ) => array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count ), $terms ) );
	}
	public static function search( WP_REST_Request $request ): WP_REST_Response {
		$query = new WP_Query( array( 'post_type' => 'product', 'post_status' => 'publish', 's' => $request->get_param( 'q' ), 'posts_per_page' => 8, 'no_found_rows' => true ) );
		$results = array_map( function ( $post ) { $product = function_exists( 'wc_get_product' ) ? wc_get_product( $post ) : null; return array( 'title' => get_the_title( $post ), 'url' => get_permalink( $post ), 'image' => get_the_post_thumbnail_url( $post, 'thumbnail' ) ?: ( $product && class_exists( 'SA_Product_Images' ) ? SA_Product_Images::url( $product ) : '' ), 'price' => $product ? $product->get_price_html() : '' ); }, $query->posts );
		return new WP_REST_Response( $results );
	}
}
