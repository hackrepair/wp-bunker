<?php
/**
 * Read-only review checks shown below the lockdowns.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Status checks. Each returns a status of good, attention or info plus advice.
 */
final class THRG_Bunker_Checks {

    /**
     * Run every check.
     *
     * @return array<int,array<string,string>> Rows with id, status, title, detail.
     */
    public static function run() {
        return array(
            self::admin_account(),
            self::inactive_items(),
            self::debug_mode(),
            self::error_log(),
            self::shares_folder(),
            self::hackguard(),
        );
    }

    /**
     * Whether WordPress shares a folder with the static site's index.html.
     *
     * @return bool
     */
    public static function shares_folder_with_static_site() {
        return file_exists( ABSPATH . 'index.html' ) || file_exists( ABSPATH . 'index.htm' );
    }

    /**
     * An account named admin.
     *
     * @return array<string,string>
     */
    private static function admin_account() {
        $exists = false !== get_user_by( 'login', 'admin' );
        return array(
            'id'     => 'admin_account',
            'status' => $exists ? 'attention' : 'good',
            'title'  => __( 'No account named "admin"', 'thrg-bunker' ),
            'detail' => $exists
                ? __( 'An account named admin exists. It is the first username bots try. Create a new administrator with a different name, then delete this one.', 'thrg-bunker' )
                : __( 'There is no account named admin.', 'thrg-bunker' ),
        );
    }

    /**
     * Inactive plugins and themes.
     *
     * @return array<string,string>
     */
    private static function inactive_items() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins  = array_keys( get_plugins() );
        $inactive = 0;
        foreach ( $plugins as $file ) {
            if ( ! is_plugin_active( $file ) && ! is_plugin_active_for_network( $file ) ) {
                ++$inactive;
            }
        }
        $themes    = wp_get_themes();
        $active    = array( get_stylesheet(), get_template() );
        $inactive += count( array_diff( array_keys( $themes ), $active ) );
        return array(
            'id'     => 'inactive_items',
            'status' => $inactive > 0 ? 'attention' : 'good',
            'title'  => __( 'No unused plugins or themes', 'thrg-bunker' ),
            'detail' => $inactive > 0
                ? sprintf(
                    /* translators: %d: number of inactive plugins and themes. */
                    _n( '%d inactive plugin or theme is installed. Delete it; a utility install needs very few.', '%d inactive plugins and themes are installed. Delete them; a utility install needs very few.', $inactive, 'thrg-bunker' ),
                    $inactive
                )
                : __( 'Everything installed is in use.', 'thrg-bunker' ),
        );
    }

    /**
     * WP_DEBUG and WP_DEBUG_DISPLAY.
     *
     * @return array<string,string>
     */
    private static function debug_mode() {
        $on = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY && defined( 'WP_DEBUG' ) && WP_DEBUG );
        return array(
            'id'     => 'debug_mode',
            'status' => $on ? 'attention' : 'good',
            'title'  => __( 'Debug mode is off', 'thrg-bunker' ),
            'detail' => $on
                ? __( 'WP_DEBUG is on. It can show file paths and errors to visitors. Set it to false in wp-config.php.', 'thrg-bunker' )
                : __( 'WP_DEBUG is off.', 'thrg-bunker' ),
        );
    }

    /**
     * An error_log file in the web root.
     *
     * @return array<string,string>
     */
    private static function error_log() {
        $exists = file_exists( ABSPATH . 'error_log' );
        return array(
            'id'     => 'error_log',
            'status' => $exists ? 'attention' : 'good',
            'title'  => __( 'No error_log file in the web folder', 'thrg-bunker' ),
            'detail' => $exists
                ? __( 'An error_log file is in the web folder. It can reveal paths and plugin problems. Read what it says, fix the cause, then delete it.', 'thrg-bunker' )
                : __( 'No error_log file was found in the web folder.', 'thrg-bunker' ),
        );
    }

    /**
     * WordPress in the same folder as the static site.
     *
     * @return array<string,string>
     */
    private static function shares_folder() {
        $shared = self::shares_folder_with_static_site();
        return array(
            'id'     => 'shares_folder',
            'status' => $shared ? 'attention' : 'good',
            'title'  => __( 'WordPress has its own folder', 'thrg-bunker' ),
            'detail' => $shared
                ? __( 'WordPress shares a folder with an index.html. If WordPress were ever broken into, your homepage could be rewritten too. See "Even better: give WordPress its own address" below.', 'thrg-bunker' )
                : __( 'No index.html was found next to WordPress.', 'thrg-bunker' ),
        );
    }

    /**
     * HackGuard Security detected (informational).
     *
     * @return array<string,string>
     */
    private static function hackguard() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $found = false;
        foreach ( get_plugins() as $file => $data ) {
            if ( isset( $data['Name'] ) && false !== stripos( $data['Name'], 'hackguard' ) && is_plugin_active( $file ) ) {
                $found = true;
                break;
            }
        }
        return array(
            'id'     => 'hackguard',
            'status' => 'info',
            'title'  => __( 'HackGuard Security', 'thrg-bunker' ),
            'detail' => $found
                ? __( 'HackGuard Security is active. Bunker and HackGuard can run together; Bunker adds no firewall, scanning or login-attempt rules, so nothing is duplicated.', 'thrg-bunker' )
                : __( 'HackGuard Security is not active. Bunker does not include a firewall, malware scanning, login-attempt limiting or two-factor; those belong to HackGuard Security.', 'thrg-bunker' ),
        );
    }
}
