<?php
/**
 * Settings page, instant-save AJAX handler, first-run confirmation and notices.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin side of Bunker.
 */
final class THRG_Bunker_Settings {

    const PAGE = 'thrg-bunker';

    /**
     * Register admin hooks.
     *
     * @return void
     */
    public static function boot() {
        add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
        add_action( 'admin_init', array( __CLASS__, 'sync_rules' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
        add_action( 'wp_ajax_thrg_bunker_toggle', array( __CLASS__, 'ajax_toggle' ) );
        add_action( 'admin_post_thrg_bunker_confirm', array( __CLASS__, 'handle_confirm' ) );
        add_action( 'admin_post_thrg_bunker_master', array( __CLASS__, 'handle_master' ) );
        add_action( 'admin_post_thrg_bunker_save', array( __CLASS__, 'handle_save' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( THRG_BUNKER_FILE ), array( __CLASS__, 'action_links' ) );
    }

    /**
     * The settings page address.
     *
     * @param array<string,string> $args Extra query arguments.
     * @return string
     */
    public static function url( $args = array() ) {
        return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'options-general.php' ) );
    }

    /**
     * Add Settings > Bunker.
     *
     * @return void
     */
    public static function add_page() {
        add_options_page(
            __( 'Bunker', 'thrg-bunker' ),
            __( 'Bunker', 'thrg-bunker' ),
            'manage_options',
            self::PAGE,
            array( __CLASS__, 'render' )
        );
    }

    /**
     * Link to the settings page from the Plugins screen.
     *
     * @param string[] $links Existing action links.
     * @return string[]
     */
    public static function action_links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( self::url() ) . '" style="font-weight:600">' . esc_html__( 'Settings', 'thrg-bunker' ) . '</a>' );
        return $links;
    }

    /**
     * Keep the server rules in line with the saved settings (administrators only).
     *
     * @return void
     */
    public static function sync_rules() {
        if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
            return;
        }
        self::sync_and_flash();
    }

    /**
     * Load the page's styles and script.
     *
     * @param string $hook Current admin page.
     * @return void
     */
    public static function assets( $hook ) {
        if ( 'settings_page_' . self::PAGE !== $hook ) {
            return;
        }
        wp_enqueue_style( 'thrg-bunker-brand', plugins_url( 'assets/brand/thrg-brand.css', THRG_BUNKER_FILE ), array(), THRG_BUNKER_VERSION );
        wp_enqueue_style( 'thrg-bunker', plugins_url( 'assets/settings.css', THRG_BUNKER_FILE ), array( 'thrg-bunker-brand' ), THRG_BUNKER_VERSION );
        wp_enqueue_script( 'thrg-bunker', plugins_url( 'assets/settings.js', THRG_BUNKER_FILE ), array(), THRG_BUNKER_VERSION, true );
        wp_localize_script(
            'thrg-bunker',
            'thrgBunker',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'thrg_bunker' ),
                'error'   => __( 'That change could not be saved. Please reload the page and try again.', 'thrg-bunker' ),
                'copied'  => __( 'Hatch link copied.', 'thrg-bunker' ),
            )
        );
    }

    /**
     * Stop with a 403 unless the current user can manage options. Used by every admin-post handler.
     *
     * @return void
     */
    public static function require_admin() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do this.', 'thrg-bunker' ), '', array( 'response' => 403 ) );
        }
    }

    /**
     * Bring the server rules in line with the settings and show any problem on the next page load.
     *
     * @return void
     */
    private static function sync_and_flash() {
        foreach ( THRG_Bunker_Server_Rules::sync() as $note ) {
            self::flash( 'warning', $note );
        }
    }

    /**
     * Store a one-time message for the next page load. THRG_Bunker_Hatch uses this for its button messages too.
     *
     * @param string $type    success, warning, error or info.
     * @param string $message Text (plain; escaped when shown).
     * @return void
     */
    public static function flash( $type, $message ) {
        $key     = 'thrg_bunker_flash_' . get_current_user_id();
        $items   = get_transient( $key );
        $items   = is_array( $items ) ? $items : array();
        $items[] = array(
            'type' => $type,
            'text' => $message,
        );
        set_transient( $key, $items, MINUTE_IN_SECONDS );
    }

    /**
     * Take and clear the stored messages.
     *
     * @return array<int,array<string,string>>
     */
    public static function take_flash() {
        $key   = 'thrg_bunker_flash_' . get_current_user_id();
        $items = get_transient( $key );
        delete_transient( $key );
        return is_array( $items ) ? $items : array();
    }

    /**
     * The message under a card for its current state.
     *
     * @param string $id Lockdown id.
     * @return string
     */
    public static function card_text( $id ) {
        $registry = THRG_Bunker_Options::registry();
        if ( ! isset( $registry[ $id ] ) ) {
            return '';
        }
        if ( ! THRG_Bunker_Options::is_on( $id ) ) {
            return $registry[ $id ]['off'];
        }
        if ( THRG_Bunker_Options::pending( $id ) ) {
            return self::pending_text();
        }
        return $registry[ $id ]['on'];
    }

    /**
     * Text shown while the IP lock waits for the owner.
     *
     * @return string
     */
    public static function pending_text() {
        return __( 'Waiting for you to review your IP list. Nothing is blocked until you save the list below.', 'thrg-bunker' );
    }

    /**
     * Instant save for one checkbox.
     *
     * @return void
     */
    public static function ajax_toggle() {
        check_ajax_referer( 'thrg_bunker', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to change this.', 'thrg-bunker' ) ), 403 );
        }
        $registry = THRG_Bunker_Options::registry();
        $id       = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
        $on       = isset( $_POST['on'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['on'] ) );
        if ( ! isset( $registry[ $id ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Unknown option.', 'thrg-bunker' ) ), 400 );
        }

        $message = '';
        $state   = THRG_Bunker_Options::state();
        if ( $on && 'known_actions' === $id && '' === trim( (string) $state['actions_allow'] ) ) {
            $on      = false;
            $message = __( 'Add at least one allowed action below and save it first. With an empty list your static form would stop working.', 'thrg-bunker' );
        }

        THRG_Bunker_Options::set( $id, $on );
        $notes = THRG_Bunker_Server_Rules::sync();
        if ( $notes ) {
            $message = implode( ' ', $notes );
        }

        wp_send_json_success(
            array(
                'id'      => $id,
                'on'      => THRG_Bunker_Options::is_on( $id ),
                'pending' => THRG_Bunker_Options::pending( $id ),
                'text'    => self::card_text( $id ),
                'message' => $message,
            )
        );
    }

    /**
     * First-run confirmation: "Lock it down" or "Not yet".
     *
     * @return void
     */
    public static function handle_confirm() {
        check_admin_referer( 'thrg_bunker_confirm' );
        self::require_admin();
        $decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
        if ( 'lock' === $decision ) {
            self::lock_it_down();
        } else {
            THRG_Bunker_Options::update( array( 'declined' => 1 ) );
            self::flash( 'warning', __( 'Nothing is enforced yet. You can still choose your options; they take effect when you lock it down.', 'thrg-bunker' ) );
        }
        wp_safe_redirect( self::url() );
        exit;
    }

    /**
     * First confirmation: switch Bunker on, and pre-fill the IP list with the administrator's address.
     *
     * @return void
     */
    private static function lock_it_down() {
        $state   = THRG_Bunker_Options::state();
        $changes = array(
            'confirmed' => 1,
            'declined'  => 0,
            'paused'    => 0,
        );
        if ( '' === trim( (string) $state['ip_list'] ) ) {
            $detected = THRG_Bunker_Ip::detect( (string) $state['ip_proxy'] );
            if ( '' !== $detected['ip'] ) {
                $changes['ip_list'] = $detected['ip'];
            }
        }
        THRG_Bunker_Options::update( $changes );
        if ( ! get_option( THRG_Bunker_Cron::SINCE, 0 ) || empty( $state['confirmed'] ) ) {
            update_option( THRG_Bunker_Cron::SINCE, time(), false );
        }
        self::sync_and_flash();
        self::flash( 'success', __( 'Bunker is on. Review your IP list below: the login lock starts only after you save it.', 'thrg-bunker' ) );
    }

    /**
     * The On | Off switch in the left column.
     *
     * Off pauses every lockdown and removes the rules Bunker wrote to .htaccess; the settings are kept.
     * On switches everything back on (and acts as the first confirmation if there has not been one).
     * The Hatch link switch kills the link in one click (Off) or makes a fresh one, shown once (On).
     * Pressing the side of a switch that is already current changes nothing and says so.
     *
     * @return void
     */
    public static function handle_master() {
        check_admin_referer( 'thrg_bunker_master' );
        self::require_admin();
        $want  = isset( $_POST['state'] ) ? sanitize_key( wp_unslash( $_POST['state'] ) ) : '';
        $ip    = isset( $_POST['ip_state'] ) ? sanitize_key( wp_unslash( $_POST['ip_state'] ) ) : '';
        $hatch = isset( $_POST['hatch_state'] ) ? sanitize_key( wp_unslash( $_POST['hatch_state'] ) ) : '';
        $state = THRG_Bunker_Options::state();
        if ( in_array( $hatch, array( 'on', 'off' ), true ) ) {
            if ( 'off' === $hatch ) {
                THRG_Bunker_Hatch::turn_off();
            } elseif ( THRG_Bunker_Hatch::switch_on() ) {
                wp_safe_redirect( self::url() . '#bunker-hatch' );
                exit;
            }
        } elseif ( in_array( $ip, array( 'on', 'off' ), true ) ) {
            if ( ( 'on' === $ip ) === THRG_Bunker_Options::is_on( 'ip_lock' ) ) {
                self::flash( 'info', 'on' === $ip ? __( 'The IP lock is already on.', 'thrg-bunker' ) : __( 'The IP lock is already off.', 'thrg-bunker' ) );
            } else {
                self::switch_ip_lock( 'on' === $ip );
            }
        } elseif ( in_array( $want, array( 'on', 'off' ), true ) && ( 'on' === $want ) === THRG_Bunker_Options::enforcing() ) {
            self::flash( 'info', 'on' === $want ? __( 'Bunker is already on.', 'thrg-bunker' ) : __( 'Bunker is already off.', 'thrg-bunker' ) );
        } elseif ( 'on' === $want ) {
            if ( empty( $state['confirmed'] ) ) {
                self::lock_it_down();
            } else {
                THRG_Bunker_Options::update( array( 'paused' => 0 ) );
                self::sync_and_flash();
                self::flash( 'success', __( 'Bunker is on again with your saved settings.', 'thrg-bunker' ) );
            }
        } elseif ( 'off' === $want ) {
            THRG_Bunker_Options::update( array( 'paused' => 1 ) );
            THRG_Bunker_Server_Rules::sync();
            self::flash( 'warning', __( 'Bunker is off. Every lockdown is paused and its rules were removed from .htaccess. Your settings are kept; switch it on to use them again.', 'thrg-bunker' ) );
        }
        wp_safe_redirect( self::url() );
        exit;
    }

    /**
     * The IP lock's On | Off switch: the same as ticking its checkbox.
     *
     * @param bool $on New state.
     * @return void
     */
    private static function switch_ip_lock( $on ) {
        THRG_Bunker_Options::set( 'ip_lock', $on );
        self::sync_and_flash();
        if ( ! $on ) {
            $registry = THRG_Bunker_Options::registry();
            self::flash( 'warning', $registry['ip_lock']['off'] );
        } elseif ( THRG_Bunker_Options::pending( 'ip_lock' ) ) {
            self::flash( 'success', __( 'The IP lock is on. It starts only after you save your IP list.', 'thrg-bunker' ) );
        } else {
            self::flash( 'success', __( 'The IP lock is on.', 'thrg-bunker' ) );
        }
    }

    /**
     * Turn a route allowlist into clean "/namespace/version" lines.
     *
     * @param string $text Raw text.
     * @return string
     */
    public static function clean_routes( $text ) {
        $out = array();
        foreach ( THRG_Bunker_Options::lines( $text ) as $line ) {
            $line = '/' . trim( (string) preg_replace( '/[^A-Za-z0-9_\-\/\.]/', '', $line ), '/' );
            if ( '/' !== $line ) {
                $out[] = $line;
            }
        }
        return implode( "\n", array_values( array_unique( $out ) ) );
    }

    /**
     * Turn an action allowlist into clean action names.
     *
     * @param string $text Raw text.
     * @return string
     */
    public static function clean_actions( $text ) {
        $out = array();
        foreach ( THRG_Bunker_Options::lines( $text ) as $line ) {
            $key = preg_match( '/^[a-zA-Z0-9_-]+$/D', $line ) ? $line : '';
            if ( '' !== $key ) {
                $out[] = $key;
            }
        }
        return implode( "\n", array_values( array_unique( $out ) ) );
    }

    /**
     * Save the form. Both buttons save every field, so nothing typed on the page is lost.
     *
     * The general fields always save. The IP list is checked and saved when "Save list" was pressed
     * or when the list or proxy setting was edited; an untouched list is left alone. A rejected IP
     * list never stops the other fields from saving.
     *
     * @return void
     */
    public static function handle_save() {
        check_admin_referer( 'thrg_bunker_save' );
        self::require_admin();
        $section = isset( $_POST['save_section'] ) ? sanitize_key( wp_unslash( $_POST['save_section'] ) ) : 'general';

        // The page always posts every field. A bare "Save list" post (no other fields) leaves the general settings alone.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        if ( 'ip' !== $section || isset( $_POST['rest_allow'] ) ) {
            self::save_general();
        }
        if ( 'ip' === $section || self::ip_edited() ) {
            self::save_ip();
        }
        self::sync_and_flash();
        wp_safe_redirect( self::url() );
        exit;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Missing -- handle_save() verifies the nonce with check_admin_referer() before either save method runs.

    /**
     * Whether the posted IP list or proxy setting differs from what is saved.
     *
     * @return bool
     */
    private static function ip_edited() {
        if ( ! isset( $_POST['ip_list'] ) ) {
            return false;
        }
        $state  = THRG_Bunker_Options::state();
        $posted = implode( "\n", THRG_Bunker_Options::lines( sanitize_textarea_field( wp_unslash( $_POST['ip_list'] ) ) ) );
        $saved  = implode( "\n", THRG_Bunker_Options::lines( (string) $state['ip_list'] ) );
        $mode   = isset( $_POST['ip_proxy'] ) ? sanitize_key( wp_unslash( $_POST['ip_proxy'] ) ) : (string) $state['ip_proxy'];
        return $posted !== $saved || $mode !== (string) $state['ip_proxy'];
    }

    /**
     * Save the IP list (the "Save list" button, or an edited list on any save).
     *
     * @return void
     */
    private static function save_ip() {
        $raw   = isset( $_POST['ip_list'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ip_list'] ) ) : '';
        $mode  = isset( $_POST['ip_proxy'] ) ? sanitize_key( wp_unslash( $_POST['ip_proxy'] ) ) : 'remote_addr';
        $mode  = in_array( $mode, array( 'remote_addr', 'cloudflare' ), true ) ? $mode : 'remote_addr';
        $check = THRG_Bunker_Ip::check_list( $raw );

        if ( $check['invalid'] ) {
            self::flash(
                'error',
                sprintf(
                    /* translators: %s: list of rejected entries. */
                    __( 'The list was not saved. These entries are not valid IP addresses or ranges: %s', 'thrg-bunker' ),
                    implode( ', ', $check['invalid'] )
                )
            );
            return;
        }
        if ( ! $check['valid'] ) {
            self::flash( 'error', __( 'The list was not saved. Add at least one IP address, or untick the lock.', 'thrg-bunker' ) );
            return;
        }
        $detected  = THRG_Bunker_Ip::detect( $mode );
        $confirmed = ! empty( $_POST['ip_confirm_self'] );
        if ( '' !== $detected['ip'] && ! THRG_Bunker_Ip::in_list( $detected['ip'], $raw ) && ! $confirmed ) {
            self::flash(
                'error',
                sprintf(
                    /* translators: %s: the administrator's current IP address. */
                    __( 'The list was not saved because it does not include your current address (%s). Add it, or tick the box saying you understand you may lock yourself out.', 'thrg-bunker' ),
                    $detected['ip']
                )
            );
            return;
        }
        THRG_Bunker_Options::update(
            array(
                'ip_list'  => implode( "\n", $check['valid'] ),
                'ip_saved' => 1,
                'ip_proxy' => $mode,
            )
        );
        self::flash( 'success', __( 'IP list saved. The login and dashboard lock is now enforced.', 'thrg-bunker' ) );
    }

    /**
     * Save the general fields (the main Save button and the no-JavaScript fallback).
     *
     * @return void
     */
    private static function save_general() {
        $registry = THRG_Bunker_Options::registry();
        $posted   = isset( $_POST['thrg_bunker_toggle'] ) && is_array( $_POST['thrg_bunker_toggle'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['thrg_bunker_toggle'] ) )
            : array();
        $toggles  = array();
        foreach ( array_keys( $registry ) as $id ) {
            $toggles[ $id ] = isset( $posted[ $id ] ) ? 1 : 0;
        }

        $actions = self::clean_actions( isset( $_POST['actions_allow'] ) ? sanitize_textarea_field( wp_unslash( $_POST['actions_allow'] ) ) : '' );
        if ( '' === $actions && ! empty( $toggles['known_actions'] ) ) {
            $toggles['known_actions'] = 0;
            self::flash( 'warning', __( 'Only allowing known form actions was left off because the allowed list is empty.', 'thrg-bunker' ) );
        }

        $source = isset( $_POST['notfound_source'] ) ? sanitize_key( wp_unslash( $_POST['notfound_source'] ) ) : 'auto';

        THRG_Bunker_Options::update(
            array(
                'toggles'         => $toggles,
                'rest_allow'      => self::clean_routes( isset( $_POST['rest_allow'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rest_allow'] ) ) : '' ),
                'actions_allow'   => $actions,
                'notfound_source' => 'builtin' === $source ? 'builtin' : 'auto',
                'cron_email'      => empty( $_POST['cron_email'] ) ? 0 : 1,
                'delete_data'     => empty( $_POST['delete_data'] ) ? 0 : 1,
            )
        );
        self::flash( 'success', __( 'Settings saved.', 'thrg-bunker' ) );
    }

    // phpcs:enable WordPress.Security.NonceVerification.Missing

    /**
     * Banners on the dashboard and Plugins screen, and always for safe mode.
     *
     * @return void
     */
    public static function notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $id     = $screen ? $screen->id : '';
        $here   = in_array( $id, array( 'dashboard', 'plugins', 'settings_page_' . self::PAGE ), true );

        if ( THRG_Bunker_Options::safe_mode() ) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Bunker is in safe mode.', 'thrg-bunker' ) . '</strong> ';
            echo esc_html__( 'THRG_BUNKER_SAFE_MODE is set in wp-config.php, so every Bunker lockdown is paused. Remove that line to turn them back on.', 'thrg-bunker' ) . '</p></div>';
        }
        if ( is_multisite() && $here ) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Bunker does not support multisite networks yet.', 'thrg-bunker' ) . '</strong> ';
            echo esc_html__( 'It is installed but enforces nothing here.', 'thrg-bunker' ) . '</p></div>';
        }
        $state = THRG_Bunker_Options::state();
        if ( $here && 'settings_page_' . self::PAGE !== $id && ! empty( $state['confirmed'] ) && ! empty( $state['paused'] ) && ! is_multisite() ) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Bunker is switched off.', 'thrg-bunker' ) . '</strong> ';
            echo '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Switch it on', 'thrg-bunker' ) . '</a></p></div>';
        }
        if ( $here && 'settings_page_' . self::PAGE !== $id && empty( $state['confirmed'] ) && ! is_multisite() ) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Bunker is installed but not protecting this site yet.', 'thrg-bunker' ) . '</strong> ';
            echo '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Review and lock it down', 'thrg-bunker' ) . '</a></p></div>';
        }
        if ( $here && THRG_Bunker_Options::enforcing() && THRG_Bunker_Options::is_on( 'cron_watch' ) && THRG_Bunker_Cron::stalled() ) {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'WordPress\'s scheduled tasks have stalled.', 'thrg-bunker' ) . '</strong> ';
            echo esc_html__( 'They have not run for more than 24 hours, so automatic updates may have stopped.', 'thrg-bunker' ) . ' ';
            echo '<a href="' . esc_url( self::url() . '#bunker-cron' ) . '">' . esc_html__( 'See the fix', 'thrg-bunker' ) . '</a></p></div>';
        }
    }

    /**
     * Draw the settings page.
     *
     * @return void
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require __DIR__ . '/view-settings.php';
    }
}
