<?php
/**
 * Item 12: watch WordPress's scheduled tasks.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Records cron runs and reports when they stall.
 */
final class THRG_Bunker_Cron {

    const LAST  = 'thrg_bunker_cron_last';
    const SINCE = 'thrg_bunker_cron_since';
    const LIMIT = DAY_IN_SECONDS;

    /**
     * Hook the watcher. Runs in every context, including cron itself.
     *
     * @return void
     */
    public static function boot() {
        add_action( 'init', array( __CLASS__, 'tick' ), 20 );
    }

    /**
     * Record a cron run, or occasionally check for a stall.
     *
     * @return void
     */
    public static function tick() {
        if ( ! THRG_Bunker_Options::enforcing() || ! THRG_Bunker_Options::is_on( 'cron_watch' ) ) {
            return;
        }
        if ( false === get_option( self::SINCE, false ) ) {
            update_option( self::SINCE, time(), false );
        }
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            update_option( self::LAST, time(), false );
            return;
        }
        if ( false !== get_transient( 'thrg_bunker_cron_check' ) ) {
            return;
        }
        set_transient( 'thrg_bunker_cron_check', 1, HOUR_IN_SECONDS );
        self::maybe_email();
    }

    /**
     * Whether scheduled tasks have not run for more than 24 hours.
     *
     * @param int $now Current time (injectable for tests).
     * @return bool
     */
    public static function stalled( $now = 0 ) {
        $now   = $now ? (int) $now : time();
        $last  = (int) get_option( self::LAST, 0 );
        $since = (int) get_option( self::SINCE, 0 );
        $from  = max( $last, $since );
        return $from > 0 && ( $now - $from ) > self::LIMIT;
    }

    /**
     * The ready-to-paste cron line.
     *
     * @return string
     */
    public static function cron_line() {
        return '*/15 * * * * wget -q -O - ' . "'" . site_url( 'wp-cron.php' ) . '?doing_wp_cron' . "'" . ' >/dev/null 2>&1';
    }

    /**
     * The wp-config.php line to pair with the cron job.
     *
     * @return string
     */
    public static function config_line() {
        return "define( 'DISABLE_WP_CRON', true );";
    }

    /**
     * Email the site administrator once a week while tasks stay stalled, if the owner asked for it.
     *
     * @return void
     */
    public static function maybe_email() {
        $state = THRG_Bunker_Options::state();
        if ( empty( $state['cron_email'] ) || ! self::stalled() || false !== get_transient( 'thrg_bunker_cron_mailed' ) ) {
            return;
        }
        set_transient( 'thrg_bunker_cron_mailed', 1, WEEK_IN_SECONDS );
        $subject = THRG_Bunker_Mail::subject( __( 'Scheduled tasks stalled', 'thrg-bunker' ) );
        $body    = __( 'WordPress has not run its scheduled tasks for more than 24 hours. Automatic updates may have stopped.', 'thrg-bunker' ) . "\n\n";
        $body   .= THRG_Bunker_Mail::site_line();
        $body   .= __( 'To fix it, add this cron job in your hosting control panel:', 'thrg-bunker' ) . "\n" . self::cron_line() . "\n\n";
        $body   .= __( 'Then add this line to wp-config.php:', 'thrg-bunker' ) . "\n" . self::config_line() . "\n\n";
        $body   .= __( 'Sent by The Hack Repair Guy\'s Bunker. You can turn this email off on the Bunker settings page.', 'thrg-bunker' ) . "\n";
        wp_mail( get_option( 'admin_email' ), $subject, $body );
    }
}
