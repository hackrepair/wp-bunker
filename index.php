<?php
/**
 * Plugin Name:       The Hack Repair Guy's Bunker
 * Plugin URI:        https://github.com/hackrepair/wp-bunker
 * Description:       WordPress Utility Lockdown. For WordPress running behind a static website: switches off what a utility install does not need and keeps the few doors your site uses.
 * Version:           1.2.11
 * Author:            The Hack Repair Guy
 * Author URI:        https://hackrepair.com/
 * Update URI:        https://github.com/hackrepair/wp-bunker
 * Requires at least: 6.9.4
 * Requires PHP:      7.4
 * Text Domain:       thrg-bunker
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

const THRG_BUNKER_VERSION = '1.2.11';
const THRG_BUNKER_FILE    = __FILE__;

/*
 * GitHub release updates. Deliberately NOT gated by is_admin(): WordPress's
 * update-check and auto-update machinery runs on cron, where is_admin() is false.
 * THRG_BUNKER_SKIP_UPDATER is only defined by the tests.
 */
if ( ! defined( 'THRG_BUNKER_SKIP_UPDATER' ) ) {
    $thrg_bunker_puc = plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';
    if ( is_readable( $thrg_bunker_puc ) ) {
        require_once $thrg_bunker_puc;
    }
    unset( $thrg_bunker_puc );

    require_once plugin_dir_path( __FILE__ ) . 'includes/class-thrg-bunker-updater.php';
    $thrg_bunker_updater = new THRG_Bunker_Updater();
    $thrg_bunker_updater->init( __FILE__ );
    unset( $thrg_bunker_updater );
}

require_once __DIR__ . '/includes/class-thrg-bunker-options.php';
require_once __DIR__ . '/includes/class-thrg-bunker-ip.php';
require_once __DIR__ . '/includes/class-thrg-bunker-server-rules.php';
require_once __DIR__ . '/includes/class-thrg-bunker-notfound.php';
require_once __DIR__ . '/includes/class-thrg-bunker-hatch.php';
require_once __DIR__ . '/includes/class-thrg-bunker-lockdown.php';
require_once __DIR__ . '/includes/class-thrg-bunker-mail.php';
require_once __DIR__ . '/includes/class-thrg-bunker-alerts.php';
require_once __DIR__ . '/includes/class-thrg-bunker-cron.php';
require_once __DIR__ . '/includes/class-thrg-bunker-welcome.php';
require_once __DIR__ . '/includes/class-thrg-bunker-checks.php';
require_once __DIR__ . '/includes/class-thrg-bunker-settings.php';

/**
 * The folder this plugin is installed in. It is the plugin's slug in WordPress
 * and stays whatever it was when the site installed the plugin.
 *
 * @return string
 */
function thrg_bunker_slug() {
    return dirname( plugin_basename( THRG_BUNKER_FILE ) );
}

THRG_Bunker_Lockdown::boot();
THRG_Bunker_Cron::boot();
THRG_Bunker_Settings::boot();
THRG_Bunker_Hatch::boot();
THRG_Bunker_Alerts::boot();

/**
 * Deactivation switches everything off: the rules Bunker added to .htaccess are removed,
 * the hatch link is killed and Bunker is left paused. Settings are kept; only the
 * "Delete all plugin data" option, used when the plugin is deleted, removes them.
 *
 * Renaming the plugin folder runs no deactivation code, so those rules stay in
 * that case; they are harmless and the recovery notes explain how to delete them.
 *
 * @return void
 */
function thrg_bunker_deactivate() {
    THRG_Bunker_Server_Rules::remove_all();
    if ( false !== get_option( THRG_Bunker_Options::OPTION, false ) ) {
        // Everything is switched off, the hatch link is killed, and the settings are kept.
        // Switching Bunker back on is a deliberate step after the plugin is activated again.
        THRG_Bunker_Hatch::disable();
        THRG_Bunker_Options::update(
            array(
                'server_rules' => array(),
                'paused'       => 1,
            )
        );
    }
    delete_transient( 'thrg_bunker_cron_check' );
}
register_deactivation_hook( __FILE__, 'thrg_bunker_deactivate' );

/**
 * Activation sends the administrator a one-time welcome email.
 *
 * @return void
 */
function thrg_bunker_activate() {
    THRG_Bunker_Welcome::send_once();
}
register_activation_hook( __FILE__, 'thrg_bunker_activate' );

require_once __DIR__ . '/includes/plugin-details.php';
require_once __DIR__ . '/includes/companion-plugins.php';
