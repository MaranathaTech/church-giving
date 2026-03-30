<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="mg-portal mg-portal-authenticated">

    <!-- Header -->
    <div class="mg-portal-header">
        <div class="mg-portal-greeting">
            <h2>Welcome back, <?php echo esc_html( $donor->first_name ?: 'Friend' ); ?>!</h2>
            <a href="<?php echo esc_url( $logout_url ); ?>" class="mg-portal-logout">Logout</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="mg-portal-stats">
        <div class="mg-stat-card">
            <div class="mg-stat-value">$<?php echo esc_html( number_format( $year_to_date, 2 ) ); ?></div>
            <div class="mg-stat-label">Year-to-Date</div>
        </div>
        <div class="mg-stat-card">
            <div class="mg-stat-value">$<?php echo esc_html( number_format( $lifetime_total, 2 ) ); ?></div>
            <div class="mg-stat-label">Lifetime Total</div>
        </div>
        <div class="mg-stat-card">
            <div class="mg-stat-value"><?php echo esc_html( $donation_count ); ?></div>
            <div class="mg-stat-label">Total Gifts</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mg-portal-tabs">
        <button type="button" class="mg-portal-tab mg-active" data-tab="history">Giving History</button>
        <button type="button" class="mg-portal-tab" data-tab="subscriptions">Recurring Gifts</button>
        <button type="button" class="mg-portal-tab" data-tab="profile">Profile</button>
    </div>

    <!-- History Tab -->
    <div class="mg-portal-panel mg-panel-history mg-active" id="mg-panel-history">
        <div class="mg-table-wrap">
            <div id="mg-donation-history">
                <p class="mg-loading">Loading giving history...</p>
            </div>
        </div>
        <div id="mg-history-pagination" class="mg-pagination" style="display:none;"></div>
    </div>

    <!-- Subscriptions Tab -->
    <div class="mg-portal-panel mg-panel-subscriptions" id="mg-panel-subscriptions">
        <div class="mg-table-wrap">
            <div id="mg-subscriptions-list">
                <p class="mg-loading">Loading recurring gifts...</p>
            </div>
        </div>
    </div>

    <!-- Profile Tab -->
    <div class="mg-portal-panel mg-panel-profile" id="mg-panel-profile">
        <div class="mg-portal-form">
            <div class="mg-row">
                <div class="mg-col">
                    <label class="mg-label" for="mg-profile-first-name">First Name</label>
                    <input type="text" class="mg-input" id="mg-profile-first-name" value="<?php echo esc_attr( $donor->first_name ); ?>">
                </div>
                <div class="mg-col">
                    <label class="mg-label" for="mg-profile-last-name">Last Name</label>
                    <input type="text" class="mg-input" id="mg-profile-last-name" value="<?php echo esc_attr( $donor->last_name ); ?>">
                </div>
            </div>
            <div class="mg-row">
                <div class="mg-col">
                    <label class="mg-label" for="mg-profile-phone">Phone</label>
                    <input type="tel" class="mg-input" id="mg-profile-phone" value="<?php echo esc_attr( $donor->phone ); ?>">
                </div>
            </div>
            <div class="mg-row">
                <div class="mg-col">
                    <label class="mg-label" for="mg-profile-address">Address</label>
                    <input type="text" class="mg-input" id="mg-profile-address" value="<?php echo esc_attr( $donor->address_line1 ); ?>">
                </div>
            </div>
            <div class="mg-row">
                <div class="mg-col">
                    <label class="mg-label" for="mg-profile-city">City</label>
                    <input type="text" class="mg-input" id="mg-profile-city" value="<?php echo esc_attr( $donor->city ); ?>">
                </div>
                <div class="mg-col">
                    <label class="mg-label" for="mg-profile-state">State</label>
                    <input type="text" class="mg-input" id="mg-profile-state" value="<?php echo esc_attr( $donor->state ); ?>">
                </div>
                <div class="mg-col">
                    <label class="mg-label" for="mg-profile-zip">ZIP</label>
                    <input type="text" class="mg-input" id="mg-profile-zip" value="<?php echo esc_attr( $donor->zip ); ?>">
                </div>
            </div>

            <button type="button" class="mg-submit-btn" id="mg-profile-save">Save Profile</button>
            <div id="mg-profile-message" class="mg-message" style="display:none;"></div>
        </div>
    </div>

</div>
