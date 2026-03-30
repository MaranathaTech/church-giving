<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Admin_Menu {

    public function register_menus() {
        add_menu_page(
            'Church Giving',
            'Church Giving',
            'manage_options',
            'maranatha-giving',
            array( $this, 'render_donations_page' ),
            'dashicons-heart',
            30
        );

        add_submenu_page(
            'maranatha-giving',
            'Donations',
            'Donations',
            'manage_options',
            'maranatha-giving',
            array( $this, 'render_donations_page' )
        );

        add_submenu_page(
            'maranatha-giving',
            'Donors',
            'Donors',
            'manage_options',
            'maranatha-giving-donors',
            array( $this, 'render_donors_page' )
        );

        add_submenu_page(
            'maranatha-giving',
            'Subscriptions',
            'Subscriptions',
            'manage_options',
            'maranatha-giving-subscriptions',
            array( $this, 'render_subscriptions_page' )
        );

        add_submenu_page(
            'maranatha-giving',
            'Funds',
            'Funds',
            'manage_options',
            'maranatha-giving-funds',
            array( $this, 'render_funds_page' )
        );

        add_submenu_page(
            'maranatha-giving',
            'Settings',
            'Settings',
            'manage_options',
            'maranatha-giving-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function render_donations_page() {
        $list = new Maranatha_Giving_Donations_List();
        $list->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Donations</h1>
            <hr class="wp-header-end">
            <form method="get">
                <input type="hidden" name="page" value="maranatha-giving">
                <?php
                $list->search_box( 'Search Donations', 'mg-search' );
                $list->display();
                ?>
            </form>
        </div>
        <?php
        $this->render_support_prompt();
    }

    public function render_donors_page() {
        $list = new Maranatha_Giving_Donors_List();
        $list->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Donors</h1>
            <hr class="wp-header-end">
            <form method="get">
                <input type="hidden" name="page" value="maranatha-giving-donors">
                <?php
                $list->search_box( 'Search Donors', 'mg-search' );
                $list->display();
                ?>
            </form>
        </div>
        <?php
        $this->render_support_prompt();
    }

    public function render_subscriptions_page() {
        $list = new Maranatha_Giving_Subscriptions_List();
        $list->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Subscriptions</h1>
            <hr class="wp-header-end">
            <?php $list->display(); ?>
        </div>
        <?php
        $this->render_support_prompt();
    }

    public function render_funds_page() {
        $funds_admin = new Maranatha_Giving_Funds_Admin();
        $funds_admin->render();
        $this->render_support_prompt();
    }

    public function render_settings_page() {
        $settings = new Maranatha_Giving_Settings_Page();
        $settings->render();
        $this->render_support_prompt();
    }

    private function render_support_prompt() {
        ?>
        <div class="mg-support-prompt" style="margin-top:30px;padding:16px 20px;background:#f0f6fc;border:1px solid #c8d8e8;border-radius:6px;max-width:700px;">
            <p style="margin:0 0 8px;font-size:14px;color:#2c3e50;">
                <strong>Enjoying Church Giving?</strong> This plugin is free and built with love for the local church.
                If it has been a blessing, consider supporting continued development.
            </p>
            <p style="margin:0;">
                <a href="https://www.paypal.com/donate/?business=paypal%40maranathatechnologies.com&currency_code=USD" target="_blank" rel="noopener noreferrer"
                   class="button button-primary" style="margin-right:10px;">
                    Donate via PayPal
                </a>
                <span style="font-size:13px;color:#666;">paypal@maranathatechnologies.com</span>
            </p>
        </div>
        <?php
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'maranatha-giving' ) === false ) {
            return;
        }

        // Media library for logo picker on settings page.
        if ( strpos( $hook, 'maranatha-giving-settings' ) !== false ) {
            wp_enqueue_media();
        }

        wp_enqueue_style(
            'maranatha-giving-admin',
            MARANATHA_GIVING_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MARANATHA_GIVING_VERSION
        );

        wp_enqueue_script(
            'maranatha-giving-admin',
            MARANATHA_GIVING_PLUGIN_URL . 'assets/js/admin.js',
            array(),
            MARANATHA_GIVING_VERSION,
            true
        );

        wp_localize_script( 'maranatha-giving-admin', 'mgAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'maranatha_giving_admin' ),
        ) );
    }
}
