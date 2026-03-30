<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="mg-portal mg-portal-login">
    <div class="mg-portal-card">
        <div class="mg-portal-lock-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
        </div>
        <h2 class="mg-portal-title">Access Your Giving Portal</h2>
        <p class="mg-portal-subtitle">Enter your email address and we'll send you a secure link to view your giving history.</p>

        <div class="mg-portal-form">
            <label class="mg-label" for="mg-portal-email">Email Address</label>
            <input type="email" class="mg-input" id="mg-portal-email" placeholder="your@email.com" required>

            <?php
            $bot_protection = Maranatha_Giving::get_option( 'bot_protection', 'none' );
            $bot_site_key   = Maranatha_Giving::get_option( 'bot_site_key', '' );
            if ( $bot_protection === 'turnstile' && $bot_site_key ) : ?>
            <div id="mg-portal-bot-widget"></div>
            <?php endif; ?>

            <button type="button" class="mg-submit-btn" id="mg-portal-send-link">
                <span class="mg-submit-text">Send Login Link</span>
                <span class="mg-submit-spinner" style="display:none;"></span>
            </button>

            <div id="mg-portal-message" class="mg-message" style="display:none;"></div>
        </div>
    </div>
</div>
