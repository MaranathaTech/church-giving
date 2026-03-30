<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Dashboard_Widget {

    public function register() {
        wp_add_dashboard_widget(
            'maranatha_giving_dashboard',
            'Church Giving Overview',
            array( $this, 'render' )
        );

        wp_add_dashboard_widget(
            'maranatha_giving_promo',
            'Church Giving — Need Help?',
            array( $this, 'render_promo' )
        );
    }

    public function render() {
        $donations_db     = new Maranatha_Giving_Donations_DB();
        $subscriptions_db = new Maranatha_Giving_Subscriptions_DB();
        $donors_db        = new Maranatha_Giving_Donors_DB();

        $total_completed  = $donations_db->get_total_by_status( 'completed' );
        $total_donations  = $donations_db->count( array( 'status' => 'completed' ) );
        $total_donors     = $donors_db->count();
        $active_subs      = $subscriptions_db->count( array( 'status' => 'active' ) );

        // Recent donations.
        $recent = $donations_db->get_donations( array(
            'where'    => array( 'status' => 'completed' ),
            'orderby'  => 'completed_at',
            'order'    => 'DESC',
            'per_page' => 5,
            'page'     => 1,
        ) );
        ?>
        <div class="mg-dashboard-widget">
            <div style="display:flex;gap:15px;margin-bottom:15px;">
                <div style="flex:1;background:#f0f0f1;padding:12px;border-radius:4px;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:#2c3e50;">$<?php echo esc_html( number_format( $total_completed, 2 ) ); ?></div>
                    <div style="font-size:11px;color:#666;margin-top:3px;">Total Raised</div>
                </div>
                <div style="flex:1;background:#f0f0f1;padding:12px;border-radius:4px;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:#2c3e50;"><?php echo (int) $total_donors; ?></div>
                    <div style="font-size:11px;color:#666;margin-top:3px;">Donors</div>
                </div>
                <div style="flex:1;background:#f0f0f1;padding:12px;border-radius:4px;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:#2c3e50;"><?php echo (int) $active_subs; ?></div>
                    <div style="font-size:11px;color:#666;margin-top:3px;">Active Recurring</div>
                </div>
            </div>

            <?php if ( ! empty( $recent ) ) : ?>
                <h4 style="margin:0 0 8px;">Recent Donations</h4>
                <table style="width:100%;font-size:13px;">
                    <?php foreach ( $recent as $d ) : ?>
                        <tr>
                            <td style="padding:4px 0;"><?php echo esc_html( $d->donor_name ?: $d->donor_email ); ?></td>
                            <td style="padding:4px 0;text-align:right;font-weight:600;">$<?php echo esc_html( number_format( (float) $d->amount, 2 ) ); ?></td>
                            <td style="padding:4px 0;text-align:right;color:#666;"><?php echo esc_html( wp_date( 'M j', strtotime( $d->completed_at ?: $d->created_at ) ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else : ?>
                <p style="color:#666;font-size:13px;">No donations yet.</p>
            <?php endif; ?>

            <p style="margin:10px 0 0;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=maranatha-giving' ) ); ?>">View All Donations &rarr;</a></p>
        </div>
        <?php
    }

    public function render_promo() {
        ?>
        <div style="text-align:center;padding:8px 0;">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#3858e9" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:8px;">
                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                <path d="M12 6v6l4 2"/>
            </svg>
            <h3 style="margin:0 0 8px;font-size:15px;color:#1d2327;">Web Development for Churches &amp; Ministries</h3>
            <p style="font-size:13px;color:#50575e;line-height:1.5;margin:0 0 12px;">
                Need a complete church website, custom features, or help getting set up?
                <strong>Maranatha Technologies</strong> builds modern, affordable websites for like-minded ministries.
            </p>
            <a href="https://maranathatechnologies.com" target="_blank" rel="noopener noreferrer"
               style="display:inline-block;background:#3858e9;color:#fff;text-decoration:none;padding:8px 20px;border-radius:4px;font-size:13px;font-weight:600;">
                Learn More &rarr;
            </a>
            <p style="font-size:11px;color:#999;margin:10px 0 0;">
                Development assistance &bull; Complete websites &bull; Ongoing support
            </p>
        </div>
        <?php
    }
}
