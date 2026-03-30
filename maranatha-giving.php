<?php
/**
 * Plugin Name:       Church Giving
 * Plugin URI:        https://github.com/MaranathaTech/church-giving
 * Description:       Lightweight donation plugin with Stripe and PayPal support, recurring giving, email receipts, and a donor portal.
 * Version:           1.2.6
 * Author:            Maranatha Technologies
 * Author URI:        https://maranathatechnologies.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       maranatha-giving
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MARANATHA_GIVING_VERSION', '1.2.6' );
define( 'MARANATHA_GIVING_PLUGIN_FILE', __FILE__ );
define( 'MARANATHA_GIVING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MARANATHA_GIVING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MARANATHA_GIVING_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoload Stripe SDK.
$autoload = MARANATHA_GIVING_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
}

require_once MARANATHA_GIVING_PLUGIN_DIR . 'includes/class-maranatha-giving.php';
require_once MARANATHA_GIVING_PLUGIN_DIR . 'includes/class-activator.php';
require_once MARANATHA_GIVING_PLUGIN_DIR . 'includes/class-deactivator.php';

register_activation_hook( __FILE__, array( 'Maranatha_Giving_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Maranatha_Giving_Deactivator', 'deactivate' ) );

/**
 * Returns the singleton plugin instance.
 */
function maranatha_giving() {
    return Maranatha_Giving::instance();
}

maranatha_giving();
