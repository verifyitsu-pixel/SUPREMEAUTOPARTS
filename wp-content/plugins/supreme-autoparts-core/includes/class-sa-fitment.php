<?php
defined( 'ABSPATH' ) || exit;

final class SA_Fitment {
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_taxonomies' ) );
		add_action( 'woocommerce_product_query', array( self::class, 'filter_catalog' ) );
		add_action( 'woocommerce_single_product_summary', array( self::class, 'render_fitment' ), 25 );
		add_action( 'woocommerce_product_options_general_product_data', array( self::class, 'product_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( self::class, 'save_product_fields' ) );
	}

	public static function register_taxonomies(): void {
		foreach ( array( 'make' => 'Makes', 'model' => 'Models', 'year' => 'Years' ) as $slug => $label ) {
			register_taxonomy( 'sa_' . $slug, array( 'product' ), array(
				'label'             => $label,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'hierarchical'      => true,
				'rewrite'           => array( 'slug' => 'vehicle-' . $slug ),
			) );
		}
	}

	public static function shortcode(): string {
		$selected = array_map( 'sanitize_text_field', wp_unslash( array_intersect_key( $_GET, array_flip( array( 'vehicle_year', 'vehicle_make', 'vehicle_model' ) ) ) ) );
		ob_start(); ?>
		<form class="sa-vehicle-selector" action="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" method="get" data-sa-vehicle-selector>
			<div class="sa-vehicle-selector__heading"><span class="sa-kicker">Find your fit</span><strong>Shop by vehicle</strong></div>
			<label><span class="screen-reader-text">Year</span><select name="vehicle_year" data-vehicle-year required><option value="">Year</option><?php self::year_options( $selected['vehicle_year'] ?? '' ); ?></select></label>
			<label><span class="screen-reader-text">Make</span><select name="vehicle_make" data-vehicle-make required><option value="">Make</option><?php self::term_options( 'sa_make', $selected['vehicle_make'] ?? '' ); ?></select></label>
			<label><span class="screen-reader-text">Model</span><select name="vehicle_model" data-vehicle-model required><option value="">Model</option><?php self::term_options( 'sa_model', $selected['vehicle_model'] ?? '' ); ?></select></label>
			<button class="button button--accent" type="submit">View parts <span aria-hidden="true">→</span></button>
			<button class="sa-garage-save" type="button" data-save-vehicle hidden>Save to garage</button>
		</form>
		<?php return (string) ob_get_clean();
	}

	private static function year_options( string $selected ): void {
		for ( $year = (int) gmdate( 'Y' ) + 1; $year >= 1950; $year-- ) {
			printf( '<option value="%1$d"%2$s>%1$d</option>', $year, selected( (string) $year, $selected, false ) );
		}
	}

	private static function term_options( string $taxonomy, string $selected ): void {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 500 ) );
		if ( is_wp_error( $terms ) ) return;
		foreach ( $terms as $term ) printf( '<option value="%s"%s>%s</option>', esc_attr( $term->slug ), selected( $term->slug, $selected, false ), esc_html( $term->name ) );
	}

	public static function filter_catalog( WP_Query $query ): void {
		$map = array( 'vehicle_year' => 'sa_year', 'vehicle_make' => 'sa_make', 'vehicle_model' => 'sa_model' );
		$tax_query = $query->get( 'tax_query' ) ?: array();
		foreach ( $map as $param => $taxonomy ) {
			if ( ! empty( $_GET[ $param ] ) ) $tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => sanitize_title( wp_unslash( $_GET[ $param ] ) ) );
		}
		if ( count( $tax_query ) ) $query->set( 'tax_query', $tax_query );
	}

	public static function render_fitment(): void {
		global $product;
		$summary = $product ? $product->get_meta( '_sa_fitment_summary' ) : '';
		if ( $summary ) printf( '<div class="sa-fitment"><strong>Vehicle fitment</strong><p>%s</p><small>Always verify trim and options before ordering.</small></div>', esc_html( $summary ) );
	}

	public static function product_fields(): void {
		woocommerce_wp_text_input( array( 'id' => '_sa_fitment_summary', 'label' => 'Fitment summary', 'description' => 'Human-readable year/make/model/trim fitment.' ) );
		woocommerce_wp_text_input( array( 'id' => '_sa_source_id', 'label' => 'Legacy source ID', 'description' => 'Public catalog identifier for migration traceability.' ) );
	}

	public static function save_product_fields( int $post_id ): void {
		foreach ( array( '_sa_fitment_summary', '_sa_source_id' ) as $key ) if ( isset( $_POST[ $key ] ) ) update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
	}
}
