<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_enqueue_style( 'maranatha-giving-form' );
wp_enqueue_script( 'maranatha-giving-form' );

if ( $form_vars['stripe_enabled'] && $form_vars['stripe_pk'] ) {
    wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
}

if ( $form_vars['paypal_enabled'] && $form_vars['paypal_client_id'] ) {
    $paypal_sdk_url = 'https://www.paypal.com/sdk/js?client-id=' . urlencode( $form_vars['paypal_client_id'] )
        . '&currency=' . urlencode( $form_vars['currency'] )
        . '&intent=capture';
    if ( $form_vars['venmo_enabled'] ) {
        $paypal_sdk_url .= '&enable-funding=venmo';
    }
    wp_enqueue_script( 'paypal-sdk', $paypal_sdk_url, array(), null, true );
}

$bot_protection = Maranatha_Giving::get_option( 'bot_protection', 'none' );
$bot_site_key   = Maranatha_Giving::get_option( 'bot_site_key', '' );

if ( $bot_protection === 'turnstile' && $bot_site_key ) {
    wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, true );
} elseif ( $bot_protection === 'recaptcha' && $bot_site_key ) {
    wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . urlencode( $bot_site_key ), array(), null, true );
}

wp_localize_script( 'maranatha-giving-form', 'mgFormConfig', array(
    'restUrl'             => $form_vars['rest_url'],
    'nonce'               => $form_vars['nonce'],
    'stripeEnabled'       => $form_vars['stripe_enabled'],
    'stripePk'            => $form_vars['stripe_pk'],
    'paypalEnabled'       => $form_vars['paypal_enabled'],
    'paypalClientId'      => $form_vars['paypal_client_id'] ?? '',
    'venmoEnabled'        => $form_vars['venmo_enabled'],
    'currency'            => $form_vars['currency'],
    'minAmount'           => $form_vars['min_amount'],
    'formId'              => $form_vars['form_id'],
    'confirmationMessage' => Maranatha_Giving::get_option( 'confirmation_message', '' ),
    'botProtection'       => $bot_protection !== 'none' ? $bot_protection : '',
    'botSiteKey'          => $bot_site_key,
) );

$first_gateway = $form_vars['stripe_enabled'] ? 'stripe' : ( $form_vars['paypal_enabled'] ? 'paypal' : '' );
?>

<div class="mg-donation-form" id="mg-donation-form-<?php echo esc_attr( $form_vars['form_id'] ); ?>" data-form-id="<?php echo esc_attr( $form_vars['form_id'] ); ?>">

    <!-- Lead-In -->
    <?php if ( $form_vars['form_heading'] || $form_vars['form_lead_in'] ) : ?>
    <div class="mg-lead-in">
        <?php if ( $form_vars['form_heading'] ) : ?>
            <h2 class="mg-lead-in-heading"><?php echo esc_html( $form_vars['form_heading'] ); ?></h2>
        <?php endif; ?>
        <?php if ( $form_vars['form_lead_in'] ) : ?>
            <div class="mg-lead-in-body"><?php echo wp_kses_post( $form_vars['form_lead_in'] ); ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Step 1: Amount + Frequency + Fund -->
    <div class="mg-step mg-step-1 mg-step-active" data-step="1">
        <div class="mg-section mg-amount-section">
            <label class="mg-label">Gift Amount</label>
            <div class="mg-amount-buttons">
                <?php foreach ( $form_vars['amounts'] as $i => $amt ) : ?>
                    <button type="button" class="mg-amount-btn<?php echo $i === 0 ? ' mg-active' : ''; ?>" data-amount="<?php echo esc_attr( $amt ); ?>">
                        $<?php echo esc_html( number_format( $amt, 0 ) ); ?>
                    </button>
                <?php endforeach; ?>
                <?php if ( $form_vars['allow_custom'] ) : ?>
                    <button type="button" class="mg-amount-btn" data-amount="custom">Other</button>
                <?php endif; ?>
            </div>
            <?php if ( $form_vars['allow_custom'] ) : ?>
                <div class="mg-custom-amount" style="display:none;">
                    <div class="mg-input-group">
                        <span class="mg-input-prefix">$</span>
                        <input type="number" class="mg-input" id="mg-custom-amount" min="<?php echo esc_attr( $form_vars['min_amount'] ); ?>" step="0.01" placeholder="0.00">
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( $form_vars['show_recurring'] ) : ?>
        <div class="mg-section mg-frequency-section">
            <label class="mg-label">Frequency</label>
            <div class="mg-frequency-buttons">
                <button type="button" class="mg-freq-btn mg-active" data-frequency="one-time">One-Time</button>
                <button type="button" class="mg-freq-btn" data-frequency="weekly">Weekly</button>
                <button type="button" class="mg-freq-btn" data-frequency="monthly">Monthly</button>
                <button type="button" class="mg-freq-btn" data-frequency="quarterly">Quarterly</button>
                <button type="button" class="mg-freq-btn" data-frequency="annually">Annually</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( count( $form_vars['funds'] ) > 1 ) : ?>
        <div class="mg-section mg-fund-section">
            <label class="mg-label" for="mg-fund">Designate To</label>
            <select class="mg-select" id="mg-fund">
                <?php foreach ( $form_vars['funds'] as $fund ) : ?>
                    <option value="<?php echo esc_attr( $fund->id ); ?>"><?php echo esc_html( $fund->name ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php elseif ( count( $form_vars['funds'] ) === 1 ) : ?>
            <input type="hidden" id="mg-fund" value="<?php echo esc_attr( $form_vars['funds'][0]->id ); ?>">
        <?php endif; ?>

        <div class="mg-step-error" id="mg-step-1-error"></div>
        <div class="mg-step-nav">
            <button type="button" class="mg-btn mg-btn-next" data-next="2">Continue</button>
        </div>
    </div>

    <!-- Step 2: Donor Info -->
    <div class="mg-step mg-step-2" data-step="2">
        <div class="mg-section mg-donor-section">
            <div class="mg-row">
                <div class="mg-col">
                    <label class="mg-label" for="mg-first-name">First Name</label>
                    <input type="text" class="mg-input" id="mg-first-name" required>
                </div>
                <div class="mg-col">
                    <label class="mg-label" for="mg-last-name">Last Name</label>
                    <input type="text" class="mg-input" id="mg-last-name" required>
                </div>
            </div>
            <div class="mg-row">
                <div class="mg-col">
                    <label class="mg-label" for="mg-email">Email</label>
                    <input type="email" class="mg-input" id="mg-email" required>
                </div>
            </div>
        </div>

        <div class="mg-step-error" id="mg-step-2-error"></div>
        <div class="mg-step-nav">
            <button type="button" class="mg-btn mg-btn-back" data-prev="1">Back</button>
            <button type="button" class="mg-btn mg-btn-next" data-next="3">Continue</button>
        </div>
    </div>

    <!-- Step 3: Payment + Submit -->
    <div class="mg-step mg-step-3" data-step="3">
        <div class="mg-section mg-payment-section">
            <label class="mg-label">Payment Method</label>
            <div class="mg-payment-tabs">
                <?php if ( $form_vars['stripe_enabled'] ) : ?>
                    <button type="button" class="mg-payment-tab<?php echo $first_gateway === 'stripe' ? ' mg-active' : ''; ?>" data-gateway="stripe">Credit Card</button>
                <?php endif; ?>
                <?php if ( $form_vars['paypal_enabled'] ) : ?>
                    <button type="button" class="mg-payment-tab<?php echo $first_gateway === 'paypal' ? ' mg-active' : ''; ?>" data-gateway="paypal">PayPal</button>
                <?php endif; ?>
                <?php if ( $form_vars['venmo_enabled'] ) : ?>
                    <button type="button" class="mg-payment-tab" data-gateway="venmo">Venmo</button>
                <?php endif; ?>
            </div>

            <?php if ( $form_vars['stripe_enabled'] ) : ?>
            <div class="mg-payment-panel mg-payment-stripe<?php echo $first_gateway === 'stripe' ? ' mg-active' : ''; ?>">
                <div id="mg-stripe-elements"></div>
                <div id="mg-stripe-errors" class="mg-error" role="alert"></div>
            </div>
            <?php endif; ?>

            <?php if ( $form_vars['paypal_enabled'] ) : ?>
            <div class="mg-payment-panel mg-payment-paypal<?php echo $first_gateway === 'paypal' ? ' mg-active' : ''; ?>">
                <div id="mg-paypal-buttons"></div>
            </div>
            <?php endif; ?>

            <?php if ( $form_vars['venmo_enabled'] ) : ?>
            <div class="mg-payment-panel mg-payment-venmo">
                <div id="mg-venmo-buttons"></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Submit (hidden for PayPal/Venmo — they use their own buttons) -->
        <div class="mg-section mg-submit-section">
            <div class="mg-step-nav">
                <button type="button" class="mg-btn mg-btn-back" data-prev="2">Back</button>
                <button type="button" class="mg-submit-btn" id="mg-submit" style="flex:1;">
                    <span class="mg-submit-text">Give $<?php echo esc_html( number_format( $form_vars['amounts'][0] ?? 25, 2 ) ); ?></span>
                    <span class="mg-submit-spinner" style="display:none;"></span>
                </button>
            </div>
            <div id="mg-form-message" class="mg-message" style="display:none;"></div>
        </div>
    </div>

    <!-- Bot Protection (outside steps so invisible widget initializes even when steps are hidden) -->
    <?php if ( $bot_protection === 'turnstile' && $bot_site_key ) : ?>
    <div class="mg-section mg-turnstile-section">
        <div id="mg-turnstile-container"></div>
    </div>
    <?php endif; ?>

    <!-- Tax Footer (always visible) -->
    <?php if ( $form_vars['tax_statement'] ) : ?>
    <div class="mg-section mg-tax-footer">
        <p class="mg-tax-text"><?php echo esc_html( $form_vars['tax_statement'] ); ?></p>
    </div>
    <?php endif; ?>

</div>
