<?php
defined( 'ABSPATH' ) || exit;

final class SA_Brand {
	private const DEFAULTS = array(
		'name'        => 'Supreme Autoparts',
		'tagline'     => 'Parts that fit. Built to perform.',
		'phone'       => '+254 714 498 451',
		'email'       => 'support@supremeautoparts.co.ke',
		'hours'       => 'Mon–Sat, 8am–6pm EAT',
		'address'     => 'Midax Plaza, Off Kangundo Rd, Nairobi, Kenya',
		'site_url'    => 'https://www.supremeautoparts.co.ke',
		'free_ship'   => 99,
	);

	public static function init(): void {
		add_filter( 'pre_option_blogname', fn( $value ) => $value ?: self::get( 'name' ) );
	}

	public static function get( string $key ): mixed {
		$env_key = 'SA_' . strtoupper( $key );
		$env = getenv( $env_key );
		if ( false !== $env && '' !== $env ) {
			return 'free_ship' === $key ? (float) $env : sanitize_text_field( $env );
		}
		$settings = get_option( 'sa_brand_settings', array() );
		return $settings[ $key ] ?? self::DEFAULTS[ $key ] ?? '';
	}

	public static function all(): array {
		$values = array();
		foreach ( array_keys( self::DEFAULTS ) as $key ) $values[ $key ] = self::get( $key );
		return $values;
	}

	public static function whatsapp_url( string $message = 'Hello Supreme Autoparts, I need help with a part.' ): string {
		$number = preg_replace( '/\D+/', '', (string) self::get( 'phone' ) );
		return 'https://wa.me/' . $number . '?text=' . rawurlencode( $message );
	}
}
