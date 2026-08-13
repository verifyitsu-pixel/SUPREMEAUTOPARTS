<?php
defined( 'ABSPATH' ) || exit;

/** Display authorized source photographs until they are sideloaded locally. */
final class SA_Product_Images {
	public static function init(): void {
		add_filter( 'woocommerce_product_get_image', array( self::class, 'product_image' ), 10, 5 );
		add_filter( 'woocommerce_cart_item_thumbnail', array( self::class, 'cart_image' ), 10, 3 );
		add_filter( 'woocommerce_single_product_image_thumbnail_html', array( self::class, 'single_image' ), 10, 2 );
	}

	public static function url( WC_Product|int|null $product ): string {
		if ( is_int( $product ) ) $product = wc_get_product( $product );
		if ( ! $product || $product->get_image_id() ) return '';
		$url = esc_url_raw( (string) $product->get_meta( '_sa_source_image_url', true ) );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$authorized_host = 'topgear' . 'autosport.com';
		return in_array( $host, array( $authorized_host, 'www.' . $authorized_host ), true ) && str_starts_with( $path, '/img/' ) ? $url : '';
	}

	private static function markup( WC_Product $product, string|array $size = 'woocommerce_thumbnail', array $attr = array() ): string {
		$url = self::url( $product );
		if ( ! $url ) return '';
		$dimensions = is_array( $size ) ? $size : wc_get_image_size( (string) $size );
		$width = is_array( $dimensions ) ? (int) ( $dimensions['width'] ?? 350 ) : 350;
		$height = is_array( $dimensions ) ? (int) ( $dimensions['height'] ?? 350 ) : 350;
		$attributes = array_merge( array( 'src' => $url, 'alt' => $product->get_name(), 'class' => 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail sa-source-photo', 'loading' => 'lazy', 'decoding' => 'async', 'width' => $width, 'height' => $height, 'referrerpolicy' => 'strict-origin-when-cross-origin' ), $attr );
		$html = '<img'; foreach ( $attributes as $name => $value ) $html .= ' ' . esc_attr( $name ) . '="' . esc_attr( (string) $value ) . '"';
		return $html . '>';
	}

	public static function product_image( string $image, WC_Product $product, string|array $size, array $attr, bool $placeholder ): string {
		$fallback = self::markup( $product, $size, $attr );
		return $fallback ?: $image;
	}

	public static function cart_image( string $image, array $cart_item, string $cart_item_key ): string {
		$product = $cart_item['data'] ?? null; $fallback = $product instanceof WC_Product ? self::markup( $product, 'woocommerce_thumbnail' ) : '';
		return $fallback ?: $image;
	}

	public static function single_image( string $html, mixed $attachment_id = 0 ): string {
		global $product;
		if ( ! $product instanceof WC_Product || $product->get_image_id() ) return $html;
		$image = self::markup( $product, 'woocommerce_single', array( 'class' => 'wp-post-image sa-source-photo' ) );
		return $image ? '<div data-thumb="' . esc_url( self::url( $product ) ) . '" class="woocommerce-product-gallery__image"><a href="' . esc_url( self::url( $product ) ) . '">' . $image . '</a></div>' : $html;
	}
}
