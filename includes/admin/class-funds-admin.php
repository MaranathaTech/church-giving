<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maranatha_Giving_Funds_Admin {

    private $funds_db;

    public function __construct() {
        $this->funds_db = new Maranatha_Giving_Funds_DB();
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $this->handle_actions();

        $editing = null;
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['fund_id'] ) ) {
            $editing = $this->funds_db->get( absint( $_GET['fund_id'] ) );
        }

        $funds = $this->funds_db->get_all_funds( array( 'per_page' => 100 ) );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Funds</h1>
            <hr class="wp-header-end">

            <div class="mg-funds-grid" style="display:flex;gap:30px;margin-top:20px;">
                <!-- Add/Edit Form -->
                <div style="flex:0 0 350px;">
                    <h2><?php echo $editing ? 'Edit Fund' : 'Add New Fund'; ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'maranatha_giving_fund', 'mg_fund_nonce' ); ?>
                        <?php if ( $editing ) : ?>
                            <input type="hidden" name="fund_id" value="<?php echo esc_attr( $editing->id ); ?>">
                        <?php endif; ?>
                        <input type="hidden" name="mg_fund_action" value="<?php echo $editing ? 'update' : 'create'; ?>">

                        <table class="form-table">
                            <tr>
                                <th><label for="fund-name">Name</label></th>
                                <td><input type="text" id="fund-name" name="name" value="<?php echo esc_attr( $editing->name ?? '' ); ?>" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="fund-description">Description</label></th>
                                <td><textarea id="fund-description" name="description" rows="3" class="large-text"><?php echo esc_textarea( $editing->description ?? '' ); ?></textarea></td>
                            </tr>
                            <tr>
                                <th>Active</th>
                                <td><label><input type="checkbox" name="is_active" value="1" <?php checked( $editing ? $editing->is_active : 1 ); ?>> Visible on donation forms</label></td>
                            </tr>
                            <tr>
                                <th><label for="fund-sort-order">Sort Order</label></th>
                                <td><input type="number" id="fund-sort-order" name="sort_order" value="<?php echo esc_attr( $editing->sort_order ?? 0 ); ?>" class="small-text"></td>
                            </tr>
                        </table>

                        <?php submit_button( $editing ? 'Update Fund' : 'Add Fund' ); ?>

                        <?php if ( $editing ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=maranatha-giving-funds' ) ); ?>">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Funds List -->
                <div style="flex:1;">
                    <h2>Existing Funds</h2>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Active</th>
                                <th>Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $funds ) ) : ?>
                                <tr><td colspan="5">No funds yet.</td></tr>
                            <?php else : ?>
                                <?php foreach ( $funds as $fund ) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $fund->name ); ?></strong></td>
                                        <td><?php echo esc_html( wp_trim_words( $fund->description, 10 ) ); ?></td>
                                        <td><?php echo $fund->is_active ? 'Yes' : 'No'; ?></td>
                                        <td><?php echo (int) $fund->sort_order; ?></td>
                                        <td>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=maranatha-giving-funds&action=edit&fund_id=' . $fund->id ) ); ?>">Edit</a>
                                            |
                                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=maranatha-giving-funds&mg_fund_action=delete&fund_id=' . $fund->id ), 'maranatha_giving_delete_fund_' . $fund->id ) ); ?>"
                                               onclick="return confirm('Delete this fund?');"
                                               style="color:#a00;">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function handle_actions() {
        if ( ! isset( $_REQUEST['mg_fund_action'] ) ) {
            return;
        }

        $action = sanitize_text_field( $_REQUEST['mg_fund_action'] );

        if ( $action === 'delete' && isset( $_GET['fund_id'] ) ) {
            $fund_id = absint( $_GET['fund_id'] );
            check_admin_referer( 'maranatha_giving_delete_fund_' . $fund_id );
            $this->funds_db->delete( $fund_id );
            wp_redirect( admin_url( 'admin.php?page=maranatha-giving-funds&deleted=1' ) );
            exit;
        }

        if ( ! isset( $_POST['mg_fund_nonce'] ) || ! wp_verify_nonce( $_POST['mg_fund_nonce'], 'maranatha_giving_fund' ) ) {
            return;
        }

        $data = array(
            'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'is_active'   => isset( $_POST['is_active'] ) ? 1 : 0,
            'sort_order'  => (int) ( $_POST['sort_order'] ?? 0 ),
        );

        if ( $action === 'update' && isset( $_POST['fund_id'] ) ) {
            $this->funds_db->update_fund( absint( $_POST['fund_id'] ), $data );
            wp_redirect( admin_url( 'admin.php?page=maranatha-giving-funds&updated=1' ) );
            exit;
        }

        if ( $action === 'create' && ! empty( $data['name'] ) ) {
            $this->funds_db->create_fund( $data );
            wp_redirect( admin_url( 'admin.php?page=maranatha-giving-funds&created=1' ) );
            exit;
        }
    }
}
