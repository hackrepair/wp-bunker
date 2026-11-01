<?php
/**
 * Uninstall: always remove the rules Bunker wrote to .htaccess; remove plugin
 * data only if the site owner chose to.
 *
 * The "Delete all plugin data" option on Settings > Bunker decides, per site.
 * When it is off, the settings are kept for a reinstall.
 *
 * @package THRG_Bunker
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-thrg-bunker-server-rules.php';

// The rules are never left behind in the owner's server files, whether or not data is kept.
THRG_Bunker_Server_Rules::remove_all();

if ( ! function_exists( 'thrg_bunker_uninstall_site' ) ) {
    /**
     * Delete this site's plugin data if its owner opted in.
     *
     * @return bool Whether this site opted in.
     */
    function thrg_bunker_uninstall_site() {
        $state = get_option( 'thrg_bunker_state', array() );
        if ( ! is_array( $state ) || empty( $state['delete_data'] ) ) {
            return false;
        }
        foreach ( array( 'thrg_bunker_state', 'thrg_bunker_cron_last', 'thrg_bunker_cron_since' ) as $name ) {
            delete_option( $name );
        }
        foreach ( array( 'thrg_bunker_cron_check', 'thrg_bunker_cron_mailed' ) as $name ) {
            delete_transient( $name );
        }
        return true;
    }
}

$thrg_bunker_delete_shared = false;
if ( is_multisite() ) {
    foreach ( get_sites(
        array(
            'fields' => 'ids',
            'number' => 0,
        )
    ) as $thrg_bunker_site_id ) {
        switch_to_blog( $thrg_bunker_site_id );
        $thrg_bunker_delete_shared = thrg_bunker_uninstall_site() || $thrg_bunker_delete_shared;
        restore_current_blog();
    }
} else {
    $thrg_bunker_delete_shared = thrg_bunker_uninstall_site();
}

// Network-wide leftovers: update-check bookkeeping (keyed by the installed folder name) and the GitHub rate-limit note.
if ( $thrg_bunker_delete_shared ) {
    delete_site_option( 'external_updates-' . basename( __DIR__ ) );
    delete_site_transient( 'thrg_bunker_github_limit' );
}
