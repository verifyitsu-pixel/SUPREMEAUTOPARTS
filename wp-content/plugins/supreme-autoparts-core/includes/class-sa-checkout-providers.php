<?php
defined( 'ABSPATH' ) || exit;

final class SA_Checkout_Providers {
	public static function init(): void { add_filter( 'woocommerce_payment_gateways', array( self::class, 'gateways' ) ); }
	public static function gateways( array $gateways ): array { $gateways[] = 'SA_Gateway_Pesapal'; $gateways[] = 'SA_Gateway_TransactPay'; return $gateways; }
	public static function rest(): WP_REST_Response {
		$items = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) $items[] = array( 'id' => $gateway->id, 'label' => wp_strip_all_tags( $gateway->get_title() ), 'available' => $gateway->is_available() );
		return new WP_REST_Response( $items );
	}
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

abstract class SA_Remote_Gateway extends WC_Payment_Gateway {
	protected function env( string $name ): string { return trim( (string) getenv( $name ) ); }
	protected function json_request( string $url, array $body, array $headers = array() ): array|WP_Error {
		$response = wp_remote_post( $url, array( 'timeout' => 30, 'headers' => array_merge( array( 'Accept' => 'application/json', 'Content-Type' => 'application/json' ), $headers ), 'body' => wp_json_encode( $body ) ) );
		if ( is_wp_error( $response ) ) return $response;
		$code = wp_remote_retrieve_response_code( $response ); $data = json_decode( wp_remote_retrieve_body( $response ), true );
		return $code >= 200 && $code < 300 && is_array( $data ) ? $data : new WP_Error( 'gateway_http', sprintf( 'Gateway returned HTTP %d.', $code ) );
	}
	protected function json_get( string $url, array $headers = array() ): array|WP_Error {
		$response = wp_remote_get( $url, array( 'timeout' => 30, 'headers' => array_merge( array( 'Accept' => 'application/json' ), $headers ) ) );
		if ( is_wp_error( $response ) ) return $response;
		$code = wp_remote_retrieve_response_code( $response ); $data = json_decode( wp_remote_retrieve_body( $response ), true );
		return $code >= 200 && $code < 300 && is_array( $data ) ? $data : new WP_Error( 'gateway_http', sprintf( 'Gateway returned HTTP %d.', $code ) );
	}
	protected function fail( WC_Order $order, string $message ): array { $order->add_order_note( $message ); wc_add_notice( 'The payment provider could not start the transaction. Please try another method or contact support.', 'error' ); return array( 'result' => 'failure' ); }
}

final class SA_Gateway_Pesapal extends SA_Remote_Gateway {
	public function __construct() { $this->id = 'sa_pesapal'; $this->method_title = 'PesaPal'; $this->title = 'PesaPal'; $this->description = 'Pay securely by mobile money, bank, or card through PesaPal.'; $this->has_fields = false; $this->enabled = 'yes'; add_action( 'woocommerce_api_sa_pesapal', array( $this, 'callback' ) ); }
	public function is_available(): bool { return parent::is_available() && '' !== $this->env( 'PESAPAL_CONSUMER_KEY' ) && '' !== $this->env( 'PESAPAL_CONSUMER_SECRET' ) && '' !== $this->env( 'PESAPAL_IPN_ID' ); }
	private function base(): string { return 'production' === strtolower( $this->env( 'PESAPAL_ENVIRONMENT' ) ) ? 'https://pay.pesapal.com/v3/api' : 'https://cybqa.pesapal.com/pesapalv3/api'; }
	private function token(): string|WP_Error { $data = $this->json_request( $this->base() . '/Auth/RequestToken', array( 'consumer_key' => $this->env( 'PESAPAL_CONSUMER_KEY' ), 'consumer_secret' => $this->env( 'PESAPAL_CONSUMER_SECRET' ) ) ); return is_wp_error( $data ) || empty( $data['token'] ) ? new WP_Error( 'pesapal_auth', 'PesaPal authentication failed.' ) : $data['token']; }
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id ); $token = $this->token(); if ( is_wp_error( $token ) ) return $this->fail( $order, $token->get_error_message() );
		$reference = 'SA-' . $order->get_id() . '-' . wp_generate_password( 8, false );
		$payload = array( 'id' => $reference, 'currency' => 'USD', 'amount' => (float) $order->get_total(), 'description' => 'Supreme Autoparts order ' . $order->get_order_number(), 'callback_url' => add_query_arg( 'order_id', $order->get_id(), WC()->api_request_url( 'sa_pesapal' ) ), 'notification_id' => $this->env( 'PESAPAL_IPN_ID' ), 'redirect_mode' => 'TOP_WINDOW', 'billing_address' => array( 'email_address' => $order->get_billing_email(), 'phone_number' => $order->get_billing_phone(), 'first_name' => $order->get_billing_first_name(), 'last_name' => $order->get_billing_last_name(), 'country_code' => $order->get_billing_country() ) );
		$data = $this->json_request( $this->base() . '/Transactions/SubmitOrderRequest', $payload, array( 'Authorization' => 'Bearer ' . $token ) );
		if ( is_wp_error( $data ) || empty( $data['redirect_url'] ) ) return $this->fail( $order, is_wp_error( $data ) ? $data->get_error_message() : 'PesaPal did not return a checkout URL.' );
		$order->update_meta_data( '_sa_pesapal_reference', $reference ); $order->update_meta_data( '_sa_pesapal_tracking_id', sanitize_text_field( $data['order_tracking_id'] ?? '' ) ); $order->save();
		$order->update_status( 'pending', 'Awaiting PesaPal confirmation.' ); return array( 'result' => 'success', 'redirect' => esc_url_raw( $data['redirect_url'] ) );
	}
	public function callback(): void {
		$order_id = absint( $_REQUEST['order_id'] ?? 0 ); $merchant_reference = sanitize_text_field( wp_unslash( $_REQUEST['OrderMerchantReference'] ?? '' ) );
		if ( ! $order_id && preg_match( '/^SA-(\d+)-/', $merchant_reference, $matches ) ) $order_id = absint( $matches[1] );
		$tracking = sanitize_text_field( wp_unslash( $_REQUEST['OrderTrackingId'] ?? '' ) ); $order = wc_get_order( $order_id );
		if ( ! $order || ! $tracking ) { status_header( 400 ); exit; }
		$token = $this->token(); $status = is_wp_error( $token ) ? $token : $this->json_get( $this->base() . '/Transactions/GetTransactionStatus?orderTrackingId=' . rawurlencode( $tracking ), array( 'Authorization' => 'Bearer ' . $token ) );
		if ( ! is_wp_error( $status ) && ( 1 === (int) ( $status['status_code'] ?? 0 ) || 'COMPLETED' === strtoupper( (string) ( $status['payment_status_description'] ?? '' ) ) ) ) { $order->payment_complete( $tracking ); $order->add_order_note( 'PesaPal payment independently verified.' ); }
		if ( 'IPNCHANGE' === sanitize_text_field( wp_unslash( $_REQUEST['OrderNotificationType'] ?? '' ) ) ) { wp_send_json( array( 'orderNotificationType' => 'IPNCHANGE', 'orderTrackingId' => $tracking, 'orderMerchantReference' => $merchant_reference, 'status' => is_wp_error( $status ) ? 500 : 200 ) ); }
		wp_safe_redirect( $this->get_return_url( $order ) ); exit;
	}
}

final class SA_Gateway_TransactPay extends SA_Remote_Gateway {
	public function __construct() { $this->id = 'sa_transactpay'; $this->method_title = 'TransactPay'; $this->title = 'TransactPay'; $this->description = 'Pay securely by card or supported local payment method through TransactPay.'; $this->has_fields = false; $this->enabled = 'yes'; }
	public function is_available(): bool { return parent::is_available() && '' !== $this->env( 'TRANSACTPAY_API_KEY' ) && '' !== $this->env( 'TRANSACTPAY_PUBLIC_KEY' ); }
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id ); $payload = array( 'customer' => array( 'email' => $order->get_billing_email(), 'firstname' => $order->get_billing_first_name(), 'lastname' => $order->get_billing_last_name(), 'mobile' => $order->get_billing_phone() ), 'order' => array( 'amount' => (float) $order->get_total(), 'reference' => 'SA-' . $order->get_id() . '-' . time(), 'description' => 'Supreme Autoparts order ' . $order->get_order_number(), 'currency' => 'USD' ), 'payment' => array( 'RedirectUrl' => $this->get_return_url( $order ) ), 'paymentMeta' => array( 'ipAddress' => WC_Geolocation::get_ip_address() ) );
		$key = openssl_pkey_get_public( $this->env( 'TRANSACTPAY_PUBLIC_KEY' ) ); $encrypted = '';
		if ( ! $key || ! openssl_public_encrypt( wp_json_encode( $payload ), $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING ) ) return $this->fail( $order, 'TransactPay public-key encryption failed.' );
		$data = $this->json_request( 'https://payment-api-service.transactpay.ai/payment/order/create', array( 'data' => base64_encode( $encrypted ) ), array( 'api-key' => $this->env( 'TRANSACTPAY_API_KEY' ) ) ); $redirect = is_array( $data ) ? ( $data['redirectUrl'] ?? $data['paymentUrl'] ?? $data['data']['redirectUrl'] ?? '' ) : '';
		if ( is_wp_error( $data ) || ! $redirect ) return $this->fail( $order, is_wp_error( $data ) ? $data->get_error_message() : 'TransactPay did not return a checkout URL.' );
		$order->update_status( 'pending', 'Awaiting TransactPay confirmation.' ); return array( 'result' => 'success', 'redirect' => esc_url_raw( $redirect ) );
	}
}
