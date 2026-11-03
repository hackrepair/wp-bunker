<?php
/**
 * Optional administrator and software installation notifications.
 *
 * @package THRG_Bunker
 */
defined( 'ABSPATH' ) || exit;

final class THRG_Bunker_Alerts {
    private static $before = array();

    public static function boot() {
        add_action( 'add_user_role', array( __CLASS__, 'role_added' ), 10, 2 );
        add_filter( 'upgrader_pre_install', array( __CLASS__, 'before_install' ), 10, 2 );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'installed' ), 20, 2 );
    }

    private static function enabled( $type ) {
        $state = THRG_Bunker_Options::state();
        return ! THRG_Bunker_Options::safe_mode() && ! is_multisite() && empty( $state['paused'] ) && ! empty( $state[ 'alert_' . $type ] );
    }

    public static function role_added( $id, $role ) {
        if ( 'administrator' !== $role || ! self::enabled( 'admin' ) ) {
            return;
        }
        $user = get_userdata( $id );
        if ( $user ) {
            self::send( 'admin', __( 'Administrator access added', 'thrg-bunker' ), $user->user_login . ' (ID ' . (int) $id . ')', admin_url( 'users.php' ) );
        }
    }

    private static function inventory( $type ) {
        if ( 'plugin' === $type ) {
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            wp_clean_plugins_cache( false );
            $items = array();
            foreach ( get_plugins() as $file => $data ) {
                $parts              = explode( '/', $file );
                $items[ $parts[0] ] = array( $data['Name'], $data['Version'] );
            }
            return $items;
        }
        wp_clean_themes_cache( false );
        $items = array();
        foreach ( wp_get_themes() as $slug => $theme ) {
            $items[ $slug ] = array( $theme->get( 'Name' ), $theme->get( 'Version' ) );
        }
        return $items;
    }

    public static function before_install( $response, $extra ) {
        $type = isset( $extra['type'] ) ? $extra['type'] : '';
        if ( 'install' === ( isset( $extra['action'] ) ? $extra['action'] : '' ) && in_array( $type, array( 'plugin', 'theme' ), true ) && self::enabled( $type ) ) {
            self::$before[ $type ] = self::inventory( $type );
        }
        return $response;
    }

    public static function installed( $upgrader, $extra ) {
        $type = isset( $extra['type'] ) ? $extra['type'] : '';
        if ( 'install' !== ( isset( $extra['action'] ) ? $extra['action'] : '' ) || ! isset( self::$before[ $type ] ) ) {
            return;
        }
        $before = self::$before[ $type ];
        unset( self::$before[ $type ] );
        if ( ! self::enabled( $type ) || empty( $upgrader->result ) || is_wp_error( $upgrader->result ) || ! is_array( $upgrader->result ) ) {
            return;
        }
        $slug  = isset( $upgrader->result['destination_name'] ) ? $upgrader->result['destination_name'] : '';
        $after = self::inventory( $type );
        if ( isset( $before[ $slug ] ) || ! isset( $after[ $slug ] ) ) {
            return;
        }
        $purpose = 'plugin' === $type ? __( 'New plugin installed', 'thrg-bunker' ) : __( 'New theme installed', 'thrg-bunker' );
        self::send( $type, $purpose, $after[ $slug ][0] . ' ' . $after[ $slug ][1] . ' (' . $slug . ')', admin_url( 'plugin' === $type ? 'plugins.php' : 'themes.php' ) );
    }

    /** Recipient for security alerts, independently of hatch approvals. */
    public static function recipient() {
        $state = THRG_Bunker_Options::state();
        return is_string( $state['alert_email'] ) && is_email( $state['alert_email'] ) ? $state['alert_email'] : (string) get_option( 'admin_email', '' );
    }

    private static function send( $type, $purpose, $details, $url ) {
        $actor      = wp_get_current_user();
        $actor_text = $actor->exists() ? $actor->user_login . ' (ID ' . (int) $actor->ID . ')' : __( 'Unavailable or automated', 'thrg-bunker' );
        $body       = THRG_Bunker_Mail::site_line() . $purpose . "\n" . sanitize_text_field( $details ) . "\n\n";
        $body      .= __( 'Time: ', 'thrg-bunker' ) . current_time( 'mysql' ) . ' ' . wp_timezone_string() . "\n";
        $body      .= __( 'Actor: ', 'thrg-bunker' ) . sanitize_text_field( $actor_text ) . "\n\n" . $url . "\n\n";
        $body      .= __( 'If this change was unexpected, review it in WordPress.', 'thrg-bunker' );
        try {
            $sent = wp_mail( self::recipient(), THRG_Bunker_Mail::subject( $purpose ), $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
        } catch ( Throwable $error ) {
            $sent = false;
        }
        if ( $sent ) {
            // A later success means mail works again, so the old warning no longer applies.
            if ( ! empty( THRG_Bunker_Options::state()['alert_mail_error'] ) ) {
                THRG_Bunker_Options::update(
                    array(
                        'alert_mail_error'      => 0,
                        'alert_mail_error_type' => '',
                    )
                );
            }
        } else {
            THRG_Bunker_Options::update(
                array(
					'alert_mail_error'      => time(),
					'alert_mail_error_type' => $type,
                )
            );
        }
    }
}
