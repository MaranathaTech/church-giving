<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Maranatha_Giving_Gateway_Interface {

    /**
     * Get the gateway identifier (e.g. 'stripe', 'paypal').
     */
    public function get_id(): string;

    /**
     * Whether this gateway is enabled and configured.
     */
    public function is_available(): bool;

    /**
     * Create a one-time payment.
     *
     * @param array $data Donation data (amount, currency, donor, etc.)
     * @return array Gateway response with client-side data needed to complete.
     */
    public function create_payment( array $data ): array;

    /**
     * Create a recurring subscription.
     *
     * @param array $data Subscription data (amount, currency, frequency, donor, etc.)
     * @return array Gateway response with client-side data needed to complete.
     */
    public function create_subscription( array $data ): array;

    /**
     * Verify a webhook signature.
     *
     * @param string $payload Raw request body.
     * @param string $signature Signature header value.
     * @return bool
     */
    public function verify_webhook( string $payload, string $signature ): bool;
}
