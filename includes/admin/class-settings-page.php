<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Settings_Page {

    private $option_name = 'maranatha_giving_settings';

    public function register_settings() {
        register_setting( 'maranatha_giving_settings_group', $this->option_name, array(
            'sanitize_callback' => array( $this, 'sanitize' ),
        ) );
    }

    public function sanitize( $input ) {
        $existing  = get_option( $this->option_name, array() );
        $sanitized = $existing;

        $text_fields = array(
            'church_name', 'church_ein', 'church_address', 'church_phone', 'church_website',
            'church_logo', 'church_logo_id', 'currency', 'default_amounts', 'min_amount',
            'stripe_test_publishable', 'stripe_test_secret', 'stripe_live_publishable', 'stripe_live_secret',
            'stripe_webhook_secret', 'stripe_mode',
            'paypal_client_id', 'paypal_secret', 'paypal_webhook_id', 'paypal_mode',
            'email_from_name', 'email_from_address', 'admin_bcc_email',
            'receipt_subject', 'tax_statement', 'confirmation_message',
            'magic_link_expiration', 'donor_portal_page', 'custom_css',
            'bot_protection', 'bot_site_key', 'bot_secret_key', 'recaptcha_threshold',
            'form_heading',
        );

        foreach ( $text_fields as $field ) {
            if ( isset( $input[ $field ] ) ) {
                $sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
            }
        }

        // Clamp reCAPTCHA threshold between 0.0 and 1.0.
        if ( isset( $sanitized['recaptcha_threshold'] ) && $sanitized['recaptcha_threshold'] !== '' ) {
            $sanitized['recaptcha_threshold'] = (string) max( 0.0, min( 1.0, (float) $sanitized['recaptcha_threshold'] ) );
        }

        // wp_editor fields — allow safe HTML.
        if ( isset( $input['receipt_body'] ) ) {
            $sanitized['receipt_body'] = wp_kses_post( $input['receipt_body'] );
        }
        if ( isset( $input['form_lead_in'] ) ) {
            $sanitized['form_lead_in'] = wp_kses_post( $input['form_lead_in'] );
        }

        // Checkboxes — only process those on the active tab so saving one
        // tab doesn't reset checkboxes on other tabs to '0'.
        $active_tab      = isset( $_POST['maranatha_giving_active_tab'] ) ? sanitize_text_field( $_POST['maranatha_giving_active_tab'] ) : '';
        $tab_checkboxes  = array(
            'general'  => array( 'allow_custom_amount' ),
            'payment'  => array( 'stripe_enabled', 'paypal_enabled', 'venmo_enabled' ),
            'advanced' => array( 'delete_data_on_uninstall', 'enable_magic_link' ),
        );
        $checkboxes = $tab_checkboxes[ $active_tab ] ?? array(
            'allow_custom_amount', 'stripe_enabled', 'paypal_enabled', 'venmo_enabled',
            'delete_data_on_uninstall', 'enable_magic_link',
        );
        foreach ( $checkboxes as $field ) {
            $sanitized[ $field ] = isset( $input[ $field ] ) ? '1' : '0';
        }

        // Email validation.
        if ( isset( $input['email_from_address'] ) ) {
            $sanitized['email_from_address'] = sanitize_email( $input['email_from_address'] );
        }
        if ( isset( $input['admin_bcc_email'] ) ) {
            $sanitized['admin_bcc_email'] = sanitize_email( $input['admin_bcc_email'] );
        }
        if ( isset( $input['email_reply_to'] ) ) {
            $sanitized['email_reply_to'] = sanitize_email( $input['email_reply_to'] );
        }

        return $sanitized;
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        $tabs       = array(
            'general'  => 'General',
            'payment'  => 'Payment Gateways',
            'email'    => 'Email',
            'advanced' => 'Advanced',
        );

        $options = get_option( $this->option_name, array() );
        ?>
        <div class="wrap">
            <h1>Church Giving Settings</h1>

            <nav class="nav-tab-wrapper">
                <?php foreach ( $tabs as $slug => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=maranatha-giving-settings&tab=' . $slug ) ); ?>"
                       class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'maranatha_giving_settings_group' );
                ?>
                <input type="hidden" name="maranatha_giving_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">
                <?php

                switch ( $active_tab ) {
                    case 'payment':
                        $this->render_payment_tab( $options );
                        break;
                    case 'email':
                        $this->render_email_tab( $options );
                        break;
                    case 'advanced':
                        $this->render_advanced_tab( $options );
                        break;
                    default:
                        $this->render_general_tab( $options );
                        break;
                }

                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    private function render_general_tab( array $options ) {
        ?>
        <div class="mg-settings-info" style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:16px 20px;margin:20px 0;border-radius:2px;">
            <h3 style="margin:0 0 10px;font-size:14px;">Getting Started</h3>
            <p style="margin:0 0 8px;">Add the donation form or donor portal to any page using these shortcodes:</p>
            <table style="border-collapse:collapse;margin:0 0 10px;">
                <tr>
                    <td style="padding:4px 16px 4px 0;"><code>[maranatha_giving_form]</code></td>
                    <td style="padding:4px 0;">Displays the donation form</td>
                </tr>
                <tr>
                    <td style="padding:4px 16px 4px 0;"><code>[maranatha_giving_portal]</code></td>
                    <td style="padding:4px 0;">Displays the donor portal (magic link login &amp; giving history)</td>
                </tr>
            </table>
            <p style="margin:0 0 4px;"><strong>Shortcode options</strong> (donation form only):</p>
            <ul style="margin:0 0 0 18px;list-style:disc;">
                <li><code>funds="1,3"</code> &mdash; Limit to specific fund IDs</li>
                <li><code>amounts="10,25,50,100"</code> &mdash; Override default suggested amounts</li>
                <li><code>show_recurring="no"</code> &mdash; Hide the recurring giving option</li>
                <li><code>form_id="hero"</code> &mdash; Unique ID when using multiple forms on one site</li>
            </ul>
            <p style="margin:8px 0 0;color:#646970;">Example: <code>[maranatha_giving_form funds="1,2" amounts="25,50,100,250"]</code></p>
        </div>
        <table class="form-table">
            <?php
            $this->text_field( $options, 'church_name', 'Church Name' );
            $this->text_field( $options, 'church_ein', 'EIN (Tax ID)' );
            $this->textarea_field( $options, 'church_address', 'Church Address' );
            $this->text_field( $options, 'church_phone', 'Phone' );
            $this->text_field( $options, 'church_website', 'Website' );
            $logo_url = esc_attr( $options['church_logo'] ?? '' );
            $logo_id  = esc_attr( $options['church_logo_id'] ?? '' );
            ?>
            <tr>
                <th scope="row"><label>Church Logo</label></th>
                <td>
                    <input type="hidden" id="mg-church_logo" name="<?php echo esc_attr( $this->option_name ); ?>[church_logo]" value="<?php echo $logo_url; ?>">
                    <input type="hidden" id="mg-church_logo_id" name="<?php echo esc_attr( $this->option_name ); ?>[church_logo_id]" value="<?php echo $logo_id; ?>">
                    <div id="mg-logo-preview" style="margin-bottom:8px;">
                        <?php if ( $logo_url ) : ?>
                            <img src="<?php echo esc_url( $logo_url ); ?>" style="max-width:200px;height:auto;">
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button" id="mg-logo-select">Select Image</button>
                    <button type="button" class="button" id="mg-logo-remove" <?php echo $logo_url ? '' : 'style="display:none;"'; ?>>Remove</button>
                    <p class="description">Used in email receipts</p>
                </td>
            </tr>
            <?php
            $this->text_field( $options, 'currency', 'Currency', 'Three-letter currency code (e.g., USD)' );
            $this->text_field( $options, 'default_amounts', 'Default Amounts', 'Comma-separated amounts shown on the form' );
            $this->checkbox_field( $options, 'allow_custom_amount', 'Allow Custom Amount' );
            $this->text_field( $options, 'min_amount', 'Minimum Amount' );
            $this->text_field( $options, 'form_heading', 'Form Heading', 'Optional heading shown above the donation form' );
            ?>
            <tr>
                <th scope="row"><label for="mgformleadin">Form Lead-In Text</label></th>
                <td>
                    <?php
                    wp_editor(
                        $options['form_lead_in'] ?? '',
                        'mgformleadin',
                        array(
                            'textarea_name' => $this->option_name . '[form_lead_in]',
                            'textarea_rows' => 8,
                            'media_buttons' => false,
                        )
                    );
                    ?>
                    <p class="description">Optional rich text shown below the heading. Displayed above the donation form.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function render_payment_tab( array $options ) {
        $webhook_url_stripe = rest_url( 'maranatha-giving/v1/webhook/stripe' );
        $webhook_url_paypal = rest_url( 'maranatha-giving/v1/webhook/paypal' );

        // Stripe status badge.
        $stripe_enabled = ! empty( $options['stripe_enabled'] ) && $options['stripe_enabled'] === '1';
        $stripe_mode    = $options['stripe_mode'] ?? 'test';
        $stripe_pk      = $stripe_mode === 'live' ? ( $options['stripe_live_publishable'] ?? '' ) : ( $options['stripe_test_publishable'] ?? '' );
        $stripe_sk      = $stripe_mode === 'live' ? ( $options['stripe_live_secret'] ?? '' ) : ( $options['stripe_test_secret'] ?? '' );

        if ( $stripe_enabled && $stripe_pk && $stripe_sk ) {
            $stripe_badge = '<span class="mg-status mg-status-connected">Connected</span>';
        } elseif ( $stripe_enabled ) {
            $stripe_badge = '<span class="mg-status mg-status-error">Enabled but missing keys</span>';
        } else {
            $stripe_badge = '<span class="mg-status mg-status-disabled">Disabled</span>';
        }

        // PayPal status badge.
        $paypal_enabled = ! empty( $options['paypal_enabled'] ) && $options['paypal_enabled'] === '1';
        $paypal_cid     = $options['paypal_client_id'] ?? '';
        $paypal_sec     = $options['paypal_secret'] ?? '';

        if ( $paypal_enabled && $paypal_cid && $paypal_sec ) {
            $paypal_badge = '<span class="mg-status mg-status-connected">Connected</span>';
        } elseif ( $paypal_enabled ) {
            $paypal_badge = '<span class="mg-status mg-status-error">Enabled but missing keys</span>';
        } else {
            $paypal_badge = '<span class="mg-status mg-status-disabled">Disabled</span>';
        }
        ?>
        <h2>Stripe <?php echo $stripe_badge; ?></h2>
        <table class="form-table">
            <?php
            $this->checkbox_field( $options, 'stripe_enabled', 'Enable Stripe' );
            $this->select_field( $options, 'stripe_mode', 'Mode', array(
                'test' => 'Test',
                'live' => 'Live',
            ) );
            $this->text_field( $options, 'stripe_test_publishable', 'Test Publishable Key', 'Find in <a href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener noreferrer">Stripe Dashboard &rarr; Developers &rarr; API Keys</a> (toggle Test mode)' );
            $this->password_field( $options, 'stripe_test_secret', 'Test Secret Key', 'Starts with <code>sk_test_</code>. Find in <a href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener noreferrer">Stripe API Keys</a>. Never share this key publicly.' );
            $this->text_field( $options, 'stripe_live_publishable', 'Live Publishable Key', 'Find in <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener noreferrer">Stripe Dashboard &rarr; Developers &rarr; API Keys</a> (toggle Live mode)' );
            $this->password_field( $options, 'stripe_live_secret', 'Live Secret Key', 'Starts with <code>sk_live_</code>. Find in <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener noreferrer">Stripe API Keys</a>. Never share this key publicly.' );
            $this->password_field( $options, 'stripe_webhook_secret', 'Webhook Secret', 'Find in <a href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener noreferrer">Stripe Dashboard &rarr; Developers &rarr; Webhooks</a> &rarr; select endpoint &rarr; Signing secret' );
            ?>
            <tr>
                <th scope="row">Stripe Webhook URL</th>
                <td>
                    <code id="mg-stripe-webhook-url"><?php echo esc_html( $webhook_url_stripe ); ?></code>
                    <button type="button" class="button button-small mg-copy-btn" data-target="mg-stripe-webhook-url">Copy</button>
                    <p class="description">Add this URL in <a href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener noreferrer">Stripe Dashboard &rarr; Developers &rarr; Webhooks</a> &rarr; Add endpoint. Select events: <code>payment_intent.succeeded</code>, <code>payment_intent.payment_failed</code>, <code>invoice.paid</code>, <code>invoice.payment_failed</code>, <code>customer.subscription.updated</code>, <code>customer.subscription.deleted</code>, <code>charge.refunded</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row">Test Connection</th>
                <td>
                    <button type="button" class="button" id="mg-test-stripe">Test Stripe Connection</button>
                    <span id="mg-test-stripe-result"></span>
                </td>
            </tr>
        </table>

        <h2>PayPal <?php echo $paypal_badge; ?></h2>
        <table class="form-table">
            <?php
            $this->checkbox_field( $options, 'paypal_enabled', 'Enable PayPal' );
            $this->select_field( $options, 'paypal_mode', 'Mode', array(
                'sandbox' => 'Sandbox',
                'live'    => 'Live',
            ) );
            $this->text_field( $options, 'paypal_client_id', 'Client ID', 'Find in <a href="https://developer.paypal.com/dashboard/applications" target="_blank" rel="noopener noreferrer">PayPal Developer Dashboard &rarr; Apps &amp; Credentials</a> &rarr; select your app' );
            $this->password_field( $options, 'paypal_secret', 'Secret', 'Find in <a href="https://developer.paypal.com/dashboard/applications" target="_blank" rel="noopener noreferrer">PayPal Developer Dashboard &rarr; Apps &amp; Credentials</a> &rarr; select your app &rarr; Show secret' );
            $this->text_field( $options, 'paypal_webhook_id', 'Webhook ID', 'Find in <a href="https://developer.paypal.com/dashboard/applications" target="_blank" rel="noopener noreferrer">PayPal Developer Dashboard &rarr; Apps &amp; Credentials</a> &rarr; Webhooks &rarr; select webhook &rarr; Webhook ID at top' );
            $this->checkbox_field( $options, 'venmo_enabled', 'Enable Venmo', 'Requires PayPal to be enabled' );
            ?>
            <tr>
                <th scope="row">PayPal Webhook URL</th>
                <td>
                    <code id="mg-paypal-webhook-url"><?php echo esc_html( $webhook_url_paypal ); ?></code>
                    <button type="button" class="button button-small mg-copy-btn" data-target="mg-paypal-webhook-url">Copy</button>
                    <p class="description">Add this URL in <a href="https://developer.paypal.com/dashboard/applications" target="_blank" rel="noopener noreferrer">PayPal Developer Dashboard &rarr; Apps &amp; Credentials</a> &rarr; Webhooks &rarr; Add webhook. Select events: Payment capture completed, Billing subscription activated/cancelled/suspended</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Test Connection</th>
                <td>
                    <button type="button" class="button" id="mg-test-paypal">Test PayPal Connection</button>
                    <span id="mg-test-paypal-result"></span>
                </td>
            </tr>
        </table>
        <?php
    }

    private function render_email_tab( array $options ) {
        ?>
        <table class="form-table">
            <?php
            $this->text_field( $options, 'admin_bcc_email', 'Admin BCC Email', 'Receive a copy of every receipt' );
            $this->text_field( $options, 'email_from_name', 'From Name' );
            $this->text_field( $options, 'email_from_address', 'From Address' );
            $this->text_field( $options, 'email_reply_to', 'Reply-To Address', 'Replies to receipts will go to this address. Leave blank to use the From Address.' );
            $this->text_field( $options, 'receipt_subject', 'Receipt Subject', 'Merge tags: {donor_first_name}, {donation_amount}, {church_name}' );
            ?>
            <tr>
                <th scope="row"><label for="mgreceiptbody">Receipt Body</label></th>
                <td>
                    <?php
                    wp_editor(
                        $options['receipt_body'] ?? '',
                        'mgreceiptbody',
                        array(
                            'textarea_name' => $this->option_name . '[receipt_body]',
                            'textarea_rows' => 12,
                            'media_buttons' => false,
                        )
                    );
                    ?>
                    <p class="description">Leave blank to use the default HTML template. Available merge tags: {donor_first_name}, {donor_last_name}, {donation_amount}, {donation_date}, {fund_name}, {donation_type}, {transaction_id}, {church_name}, {church_address}, {church_ein}, {tax_statement}, {year_to_date_total}, {donor_portal_url}</p>
                </td>
            </tr>
            <?php
            $this->textarea_field( $options, 'tax_statement', 'Tax-Deductible Statement' );
            $this->textarea_field( $options, 'confirmation_message', 'Confirmation Message', 'Shown after a successful donation. Merge tags: {donor_first_name}, {donation_amount}, {frequency}, {fund_name}, {gateway}. Leave blank for default.' );
            ?>
        </table>

        <h2>Test Email</h2>
        <p>
            <button type="button" class="button" id="mg-send-test-email">Send Test Email</button>
            <span id="mg-test-email-result"></span>
        </p>
        <?php
    }

    private function render_advanced_tab( array $options ) {
        ?>
        <table class="form-table">
            <?php
            $this->checkbox_field( $options, 'delete_data_on_uninstall', 'Delete Data on Uninstall', 'Remove all donation data when the plugin is deleted' );
            $this->checkbox_field( $options, 'enable_magic_link', 'Enable Magic Link Portal' );
            $this->text_field( $options, 'magic_link_expiration', 'Magic Link Expiration (minutes)' );
            ?>
            <tr>
                <th scope="row"><label for="mg-donor-portal-page">Donor Portal Page</label></th>
                <td>
                    <?php
                    wp_dropdown_pages( array(
                        'name'              => $this->option_name . '[donor_portal_page]',
                        'id'                => 'mg-donor-portal-page',
                        'selected'          => $options['donor_portal_page'] ?? '',
                        'show_option_none'  => '&mdash; Select a page &mdash;',
                        'option_none_value' => '',
                    ) );
                    ?>
                    <p class="description">Select the page containing the <code>[maranatha_giving_portal]</code> shortcode. This page is linked in magic link emails so donors can view their giving history. A "Donor Portal" page was created automatically on activation.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mg-custom-css">Custom CSS</label></th>
                <td>
                    <textarea name="<?php echo esc_attr( $this->option_name ); ?>[custom_css]" id="mg-custom-css" rows="8" class="large-text code"><?php echo esc_textarea( $options['custom_css'] ?? '' ); ?></textarea>
                </td>
            </tr>
        </table>

        <h2>Bot Protection</h2>
        <table class="form-table">
            <?php
            $this->select_field( $options, 'bot_protection', 'Bot Protection', array(
                'none'      => 'None',
                'turnstile' => 'Cloudflare Turnstile',
                'recaptcha' => 'reCAPTCHA v3',
            ) );

            // Site key — dynamic description swapped by JS.
            $bot_mode        = $options['bot_protection'] ?? 'none';
            $turnstile_desc  = 'Get your site key from <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">Cloudflare Dashboard &rarr; Turnstile</a>';
            $recaptcha_desc  = 'Get your site key from <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer">Google reCAPTCHA Admin</a> &mdash; choose reCAPTCHA v3';
            $site_key_desc   = $bot_mode === 'recaptcha' ? $recaptcha_desc : $turnstile_desc;
            ?>
            <tr id="mg-bot-site-key-row">
                <th scope="row"><label for="mg-bot_site_key">Site Key</label></th>
                <td>
                    <input type="text" id="mg-bot_site_key" name="<?php echo esc_attr( $this->option_name ); ?>[bot_site_key]" value="<?php echo esc_attr( $options['bot_site_key'] ?? '' ); ?>" class="regular-text">
                    <p class="description">
                        <span id="mg-bot-site-key-desc-turnstile" <?php echo $bot_mode === 'recaptcha' ? 'style="display:none;"' : ''; ?>><?php echo wp_kses( $turnstile_desc, array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></span>
                        <span id="mg-bot-site-key-desc-recaptcha" <?php echo $bot_mode !== 'recaptcha' ? 'style="display:none;"' : ''; ?>><?php echo wp_kses( $recaptcha_desc, array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ); ?></span>
                    </p>
                </td>
            </tr>
            <?php
            $this->password_field( $options, 'bot_secret_key', 'Secret Key' );
            // Default threshold to 0.5 for display.
            $threshold_options = $options;
            if ( empty( $threshold_options['recaptcha_threshold'] ) ) {
                $threshold_options['recaptcha_threshold'] = '0.5';
            }
            $this->text_field( $threshold_options, 'recaptcha_threshold', 'reCAPTCHA Score Threshold', 'Score 0.0&ndash;1.0. Higher values are stricter. Recommended: <code>0.5</code>' );
            ?>
        </table>
        <?php
    }

    // ── Field helpers ──

    private function description_html( string $description ) {
        if ( ! $description ) {
            return;
        }
        $allowed = array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ), 'code' => array(), 'strong' => array(), 'em' => array() );
        ?>
        <p class="description"><?php echo wp_kses( $description, $allowed ); ?></p>
        <?php
    }

    private function text_field( array $options, string $key, string $label, string $description = '' ) {
        $value = esc_attr( $options[ $key ] ?? '' );
        $name  = esc_attr( $this->option_name . "[{$key}]" );
        ?>
        <tr>
            <th scope="row"><label for="mg-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <input type="text" id="mg-<?php echo esc_attr( $key ); ?>" name="<?php echo $name; ?>" value="<?php echo $value; ?>" class="regular-text">
                <?php $this->description_html( $description ); ?>
            </td>
        </tr>
        <?php
    }

    private function password_field( array $options, string $key, string $label, string $description = '' ) {
        $value = esc_attr( $options[ $key ] ?? '' );
        $name  = esc_attr( $this->option_name . "[{$key}]" );
        ?>
        <tr>
            <th scope="row"><label for="mg-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <input type="password" id="mg-<?php echo esc_attr( $key ); ?>" name="<?php echo $name; ?>" value="<?php echo $value; ?>" class="regular-text">
                <?php $this->description_html( $description ); ?>
            </td>
        </tr>
        <?php
    }

    private function textarea_field( array $options, string $key, string $label, string $description = '' ) {
        $value = esc_textarea( $options[ $key ] ?? '' );
        $name  = esc_attr( $this->option_name . "[{$key}]" );
        ?>
        <tr>
            <th scope="row"><label for="mg-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <textarea id="mg-<?php echo esc_attr( $key ); ?>" name="<?php echo $name; ?>" rows="3" class="large-text"><?php echo $value; ?></textarea>
                <?php $this->description_html( $description ); ?>
            </td>
        </tr>
        <?php
    }

    private function checkbox_field( array $options, string $key, string $label, string $description = '' ) {
        $checked = ! empty( $options[ $key ] ) && $options[ $key ] === '1';
        $name    = esc_attr( $this->option_name . "[{$key}]" );
        ?>
        <tr>
            <th scope="row"><?php echo esc_html( $label ); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="<?php echo $name; ?>" value="1" <?php checked( $checked ); ?>>
                    <?php echo esc_html( $label ); ?>
                </label>
                <?php $this->description_html( $description ); ?>
            </td>
        </tr>
        <?php
    }

    private function select_field( array $options, string $key, string $label, array $choices ) {
        $value = $options[ $key ] ?? '';
        $name  = esc_attr( $this->option_name . "[{$key}]" );
        ?>
        <tr>
            <th scope="row"><label for="mg-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <select id="mg-<?php echo esc_attr( $key ); ?>" name="<?php echo $name; ?>">
                    <?php foreach ( $choices as $val => $text ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $value, $val ); ?>><?php echo esc_html( $text ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <?php
    }
}
