<?php
/**
 * PHP-level enforcement for the lockdowns.
 *
 * Nothing here ever touches admin-post.php, admin-ajax.php, wp-cron.php or the
 * REST routes the owner allows, except the optional item 13 which only limits
 * what logged-out visitors can ask those two files to do.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enforcement hooks.
 */
final class THRG_Bunker_Lockdown {

    /**
     * Register hooks for every lockdown that is active right now.
     *
     * @return void
     */
    public static function boot() {
        if ( ! THRG_Bunker_Options::enforcing() ) {
            return;
        }

        if ( THRG_Bunker_Options::active( 'front_end' ) ) {
            add_action( 'template_redirect', array( __CLASS__, 'front_end' ), 0 );
        }
        if ( THRG_Bunker_Options::active( 'xmlrpc' ) ) {
            add_action( 'plugins_loaded', array( __CLASS__, 'xmlrpc' ), 0 );
            add_filter( 'xmlrpc_enabled', '__return_false' );
            add_filter( 'xmlrpc_methods', '__return_empty_array' );
            add_filter( 'wp_headers', array( __CLASS__, 'remove_pingback_header' ) );
        }
        if ( THRG_Bunker_Options::active( 'rest' ) ) {
            add_filter( 'rest_authentication_errors', array( __CLASS__, 'rest_gate' ), 20 );
        }
        if ( THRG_Bunker_Options::active( 'usernames' ) ) {
            add_action( 'init', array( __CLASS__, 'author_scan' ), 1 );
            add_filter( 'rest_endpoints', array( __CLASS__, 'hide_user_endpoints' ), 999 );
            add_filter( 'login_errors', array( __CLASS__, 'generic_login_error' ) );
            add_filter( 'oembed_response_data', array( __CLASS__, 'strip_oembed_author' ) );
            add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'drop_user_sitemap' ), 10, 2 );
        }
        if ( THRG_Bunker_Options::active( 'comments' ) ) {
            add_filter( 'comments_open', '__return_false', 20 );
            add_filter( 'pings_open', '__return_false', 20 );
            add_action( 'pre_comment_on_post', array( __CLASS__, 'refuse_comment' ) );
            add_action( 'admin_menu', array( __CLASS__, 'remove_comment_menus' ), 999 );
            add_action( 'admin_bar_menu', array( __CLASS__, 'remove_comment_bar' ), 999 );
        }
        if ( THRG_Bunker_Options::active( 'registration' ) ) {
            add_filter( 'pre_option_users_can_register', '__return_zero' );
            add_action( 'init', array( __CLASS__, 'signup_scripts' ), 0 );
        }
        if ( THRG_Bunker_Options::active( 'app_passwords' ) ) {
            add_filter( 'wp_is_application_passwords_available', '__return_false' );
        }
        if ( THRG_Bunker_Options::active( 'ip_lock' ) ) {
            add_action( 'login_init', array( __CLASS__, 'ip_gate' ), 0 );
            add_action( 'admin_init', array( __CLASS__, 'ip_gate' ), 0 );
        }
        if ( THRG_Bunker_Options::active( 'file_editor' ) && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
            // DISALLOW_FILE_MODS is deliberately not offered: it also stops plugin and core updates.
            define( 'DISALLOW_FILE_EDIT', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- DISALLOW_FILE_EDIT is WordPress core's own constant.
        }
        if ( THRG_Bunker_Options::active( 'known_actions' ) ) {
            add_action( 'admin_init', array( __CLASS__, 'known_actions_gate' ), 0 );
        }
    }

    /**
     * Item 1: every theme request gets Bunker's 404. Logged-in administrators can preview.
     *
     * @return void
     */
    public static function front_end() {
        if ( defined( 'DOING_CRON' ) || ( is_user_logged_in() && current_user_can( 'manage_options' ) ) || is_customize_preview() ) {
            return;
        }
        THRG_Bunker_NotFound::send();
    }

    /**
     * Item 2: refuse XML-RPC.
     *
     * @return void
     */
    public static function xmlrpc() {
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            status_header( 403 );
            exit;
        }
    }

    /**
     * Item 2: no pingback advertisement.
     *
     * @param array $headers Response headers.
     * @return array
     */
    public static function remove_pingback_header( $headers ) {
        unset( $headers['X-Pingback'] );
        return $headers;
    }

    /**
     * Whether a REST route is on the owner's allowlist (prefix match on whole path segments).
     *
     * @param string $route     Requested route such as /chat/v1/send.
     * @param string $allowlist Raw allowlist text, one route prefix per line.
     * @return bool
     */
    public static function route_allowed( $route, $allowlist ) {
        $route = '/' . trim( (string) $route, '/' );
        foreach ( THRG_Bunker_Options::lines( $allowlist ) as $prefix ) {
            $prefix = '/' . trim( $prefix, '/' );
            if ( '/' === $prefix ) {
                continue;
            }
            if ( $route === $prefix || 0 === strpos( $route, $prefix . '/' ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Item 3: anonymous visitors only reach allowed REST routes.
     *
     * @param mixed $result Earlier authentication result.
     * @return mixed
     */
    public static function rest_gate( $result ) {
        if ( is_wp_error( $result ) || is_user_logged_in() ) {
            return $result;
        }
        $route = '';
        if ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
            $route = (string) $GLOBALS['wp']->query_vars['rest_route'];
        }
        $state = THRG_Bunker_Options::state();
        if ( self::route_allowed( $route, $state['rest_allow'] ) ) {
            return $result;
        }
        return new WP_Error(
            'rest_forbidden',
            __( 'The REST API is not available to visitors on this site.', 'thrg-bunker' ),
            array( 'status' => rest_authorization_required_code() )
        );
    }

    /**
     * Item 4: ?author= scans get the 404 page.
     *
     * @return void
     */
    public static function author_scan() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only the presence of the parameter is checked; nothing is read or saved.
        if ( ! is_admin() && isset( $_GET['author'] ) && ! is_user_logged_in() ) {
            THRG_Bunker_NotFound::send();
        }
    }

    /**
     * Item 4: no users routes for anonymous visitors.
     *
     * @param array $endpoints REST endpoints.
     * @return array
     */
    public static function hide_user_endpoints( $endpoints ) {
        if ( is_user_logged_in() ) {
            return $endpoints;
        }
        foreach ( array_keys( $endpoints ) as $route ) {
            if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
                unset( $endpoints[ $route ] );
            }
        }
        return $endpoints;
    }

    /**
     * Item 4: one message for a wrong username and a wrong password.
     *
     * @return string
     */
    public static function generic_login_error() {
        return esc_html__( 'The username or password you entered is incorrect.', 'thrg-bunker' );
    }

    /**
     * Item 4: oEmbed responses name the author.
     *
     * @param array $data oEmbed data.
     * @return array
     */
    public static function strip_oembed_author( $data ) {
        unset( $data['author_name'], $data['author_url'] );
        return $data;
    }

    /**
     * Item 4: no user sitemap.
     *
     * @param mixed  $provider Sitemap provider.
     * @param string $name     Provider name.
     * @return mixed
     */
    public static function drop_user_sitemap( $provider, $name ) {
        return 'users' === $name ? false : $provider;
    }

    /**
     * Item 5: stop comment submissions.
     *
     * @return void
     */
    public static function refuse_comment() {
        wp_die( esc_html__( 'Comments are closed.', 'thrg-bunker' ), '', array( 'response' => 403 ) );
    }

    /**
     * Item 5: remove the comment menu items. Existing comments are left alone.
     *
     * @return void
     */
    public static function remove_comment_menus() {
        remove_menu_page( 'edit-comments.php' );
    }

    /**
     * Item 5: remove the comment bubble from the admin bar.
     *
     * @param object $bar Admin bar.
     * @return void
     */
    public static function remove_comment_bar( $bar ) {
        if ( is_object( $bar ) && method_exists( $bar, 'remove_node' ) ) {
            $bar->remove_node( 'comments' );
        }
    }

    /**
     * Item 6: wp-signup.php and wp-activate.php get the 404 page.
     *
     * @return void
     */
    public static function signup_scripts() {
        $script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
        if ( in_array( $script, array( 'wp-signup.php', 'wp-activate.php' ), true ) ) {
            THRG_Bunker_NotFound::send();
        }
    }

    /**
     * Whether a script is one of the three that must never be locked.
     *
     * @param string $script Script file name.
     * @return bool
     */
    public static function exempt_script( $script ) {
        return in_array( basename( (string) $script ), array( 'admin-post.php', 'admin-ajax.php', 'wp-cron.php' ), true );
    }

    /**
     * The script WordPress is running now.
     *
     * @return string
     */
    private static function current_script() {
        if ( ! empty( $GLOBALS['pagenow'] ) ) {
            return (string) $GLOBALS['pagenow'];
        }
        return isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
    }

    /**
     * Item 8: only listed addresses reach the login page and dashboard.
     *
     * @return void
     */
    public static function ip_gate() {
        if ( wp_doing_ajax() || defined( 'DOING_CRON' ) || defined( 'WP_CLI' ) || self::exempt_script( self::current_script() ) ) {
            return;
        }
        $state    = THRG_Bunker_Options::state();
        $detected = THRG_Bunker_Ip::detect( (string) $state['ip_proxy'] );
        if ( '' !== $detected['ip'] && THRG_Bunker_Ip::in_list( $detected['ip'], $state['ip_list'] ) ) {
            return;
        }
        wp_die(
            esc_html__( 'Access to this page is limited to approved IP addresses.', 'thrg-bunker' ),
            esc_html__( 'Access limited', 'thrg-bunker' ),
            array( 'response' => 403 )
        );
    }

    /**
     * Whether a form action is on the owner's allowlist.
     *
     * @param string $action    Requested action.
     * @param string $allowlist Raw allowlist text, one action per line.
     * @return bool
     */
    public static function action_allowed( $action, $allowlist ) {
        $action = sanitize_key( (string) $action );
        if ( '' === $action ) {
            return false;
        }
        foreach ( THRG_Bunker_Options::lines( $allowlist ) as $allowed ) {
            if ( sanitize_key( $allowed ) === $action ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Item 13: logged-out visitors can only run the listed actions.
     *
     * @return void
     */
    public static function known_actions_gate() {
        if ( is_user_logged_in() || ( ! wp_doing_ajax() && 'admin-post.php' !== self::current_script() ) ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only the action name is compared with the owner's list; the real handler checks its own nonce.
        $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
        $state  = THRG_Bunker_Options::state();
        if ( ! self::action_allowed( $action, $state['actions_allow'] ) ) {
            wp_die( esc_html__( 'Forbidden.', 'thrg-bunker' ), '', array( 'response' => 403 ) );
        }
    }

    /**
     * The form and chat actions registered for logged-out visitors, for the "Detect actions" helper.
     *
     * @return string[]
     */
    public static function detect_actions() {
        global $wp_filter;
        $found = array();
        if ( ! is_array( $wp_filter ) && ! is_object( $wp_filter ) ) {
            return $found;
        }
        foreach ( array_keys( (array) $wp_filter ) as $hook ) {
            foreach ( array( 'admin_post_nopriv_', 'wp_ajax_nopriv_' ) as $prefix ) {
                if ( 0 === strpos( $hook, $prefix ) && strlen( $hook ) > strlen( $prefix ) ) {
                    $found[] = substr( $hook, strlen( $prefix ) );
                }
            }
        }
        $found = array_values( array_unique( $found ) );
        sort( $found );
        return $found;
    }
}
