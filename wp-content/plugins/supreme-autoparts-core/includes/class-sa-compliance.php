<?php
defined( 'ABSPATH' ) || exit;

/** Store-policy acceptance, privacy consent, and first-party contact form. */
final class SA_Compliance {
	public static function init(): void {
		add_action( 'woocommerce_review_order_before_submit', array( self::class, 'checkout_acceptance' ), 9 );
		add_action( 'woocommerce_checkout_process', array( self::class, 'validate_acceptance' ) );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'record_acceptance' ), 10, 2 );
		add_shortcode( 'sa_contact_form', array( self::class, 'contact_form' ) );
		add_action( 'init', array( self::class, 'handle_contact' ) );
	}

	private static function policy_link( string $path, string $label ): string {
		return sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( home_url( '/' . $path . '/' ) ), esc_html( $label ) );
	}

	public static function checkout_acceptance(): void {
		woocommerce_form_field( 'sa_policy_acceptance', array(
			'type' => 'checkbox', 'class' => array( 'form-row sa-policy-acceptance' ), 'required' => true,
			'label' => wp_kses_post( sprintf(
				'I have read and accept the %s, %s, %s, and %s. I confirm my vehicle and delivery details are correct.',
				self::policy_link( 'terms', 'Terms & Conditions' ), self::policy_link( 'privacy-cookies', 'Privacy & Cookie Policy' ),
				self::policy_link( 'shipping-returns', 'Shipping & Returns Policy' ), self::policy_link( 'payment-chargebacks', 'Payment & Chargeback Policy' )
			) ),
		), WC()->checkout ? WC()->checkout->get_value( 'sa_policy_acceptance' ) : false );
	}

	public static function validate_acceptance(): void {
		if ( empty( $_POST['sa_policy_acceptance'] ) ) wc_add_notice( 'Please read and accept the store policies before placing your order.', 'error' );
	}

	public static function record_acceptance( WC_Order $order, array $data ): void {
		if ( ! empty( $_POST['sa_policy_acceptance'] ) ) {
			$order->update_meta_data( '_sa_policy_acceptance', 'accepted' );
			$order->update_meta_data( '_sa_policy_acceptance_utc', gmdate( 'c' ) );
			$order->update_meta_data( '_sa_policy_version', '2026-08-13' );
		}
	}

	public static function handle_contact(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['sa_contact_action'] ) ) return;
		if ( ! isset( $_POST['sa_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sa_contact_nonce'] ) ), 'sa_contact' ) ) return;
		$back = wp_get_referer() ?: home_url( '/contact/' );
		$name = sanitize_text_field( wp_unslash( $_POST['sa_name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['sa_email'] ?? '' ) );
		$message = sanitize_textarea_field( wp_unslash( $_POST['sa_message'] ?? '' ) );
		$website = sanitize_text_field( wp_unslash( $_POST['sa_website'] ?? '' ) );
		if ( $website || ! $name || ! is_email( $email ) || strlen( $message ) < 10 ) { wp_safe_redirect( add_query_arg( 'contact', 'invalid', $back ) ); exit; }
		$sent = wp_mail( SA_Brand::get( 'email' ), 'Website enquiry from ' . $name, $message, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );
		wp_safe_redirect( add_query_arg( 'contact', $sent ? 'sent' : 'unavailable', $back ) ); exit;
	}

	public static function contact_form(): string {
		$status = sanitize_key( $_GET['contact'] ?? '' );
		$notice = array( 'sent' => '<div class="woocommerce-message">Thank you. Your message has been sent.</div>', 'invalid' => '<div class="woocommerce-error">Please provide a valid name, email, and message.</div>', 'unavailable' => '<div class="woocommerce-error">Email delivery is temporarily unavailable. Please use WhatsApp or call us.</div>' )[ $status ] ?? '';
		ob_start(); echo wp_kses_post( $notice ); ?>
		<div class="sa-contact-actions"><a class="button button--accent" href="<?php echo esc_url( SA_Brand::whatsapp_url() ); ?>" target="_blank" rel="noopener">Chat on WhatsApp</a><a class="button" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', SA_Brand::get( 'phone' ) ) ); ?>">Call <?php echo esc_html( SA_Brand::get( 'phone' ) ); ?></a></div>
		<form class="sa-contact-form" method="post"><?php wp_nonce_field( 'sa_contact', 'sa_contact_nonce' ); ?><input type="hidden" name="sa_contact_action" value="send"><p class="sa-honeypot" aria-hidden="true"><label>Website<input type="text" name="sa_website" tabindex="-1" autocomplete="off"></label></p><p><label>Your name *<input name="sa_name" required autocomplete="name"></label></p><p><label>Email address *<input name="sa_email" type="email" required autocomplete="email"></label></p><p><label>How can we help? *<textarea name="sa_message" rows="6" minlength="10" required></textarea></label></p><button class="button button--accent" type="submit">Send message</button></form>
		<?php return (string) ob_get_clean();
	}
}
