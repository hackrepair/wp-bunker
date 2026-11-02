<?php
/**
 * Minimal WordPress stubs shared by the standalone tests.
 * Each test is run on its own: php tests/<name>.php
 * They check the plugin logic only; verify behavior in a real WordPress install too.
 */

error_reporting( E_ALL );
ob_start(); // Keep headers unsent so header() calls in the plugin do not warn in the CLI.

$GLOBALS['thrg_tmp'] = sys_get_temp_dir() . '/thrg-bunker-test-' . getmypid() . '/';
mkdir( $GLOBALS['thrg_tmp'] . 'wp-content/uploads', 0777, true );
register_shutdown_function(
    static function () {
        $dir = $GLOBALS['thrg_tmp'];
        if ( is_dir( $dir ) ) {
            $items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
            foreach ( $items as $item ) {
                $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
            }
            rmdir( $dir );
        }
    }
);

define( 'ABSPATH', $GLOBALS['thrg_tmp'] );
define( 'THRG_BUNKER_SKIP_UPDATER', true );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['opts']      = array();
$GLOBALS['transient'] = array();
$GLOBALS['hooks']     = array();
$GLOBALS['filters']   = array();
$GLOBALS['can']       = true;
$GLOBALS['user']      = 1;
$GLOBALS['multisite'] = false;
$GLOBALS['nonce_ok']  = true;
$GLOBALS['http']      = 200;
$GLOBALS['mails']     = array();
$GLOBALS['cookies']   = array();
$GLOBALS['is_apache'] = true;
$is_apache            = true;

/** Thrown by wp_die, wp_send_json_*, redirects and exit-like stubs. */
class THRG_Test_Exit extends Exception {
    /** @var mixed */
    public $payload;
    /** @var int */
    public $status = 0;
}

/** Minimal WP_Error (several codes supported). */
class WP_Error {
    public $code;
    public $message;
    public $data;
    public $errors = array();
    public function __construct( $code = '', $message = '', $data = '' ) {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
        if ( '' !== $code ) {
            $this->errors[ $code ][] = $message;
        }
    }
    public function get_error_code() { $codes = array_keys( $this->errors ); return $codes ? $codes[0] : ''; }
    public function get_error_codes() { return array_keys( $this->errors ); }
    public function get_error_message( $code = '' ) {
        $code = '' === $code ? $this->get_error_code() : $code;
        return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
    }
    public function add( $code, $message, $data = '' ) { $this->errors[ $code ][] = $message; }
    public function remove( $code ) { unset( $this->errors[ $code ] ); }
}

function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; }
function apply_filters( $h, $v ) { return $v; }
function is_ssl() { return true; }
function wp_hash( $d ) { return hash_hmac( 'sha256', $d, 'test-salt' ); }
function wp_kses( $h, $a ) { return $h; }
function sanitize_email( $e ) { return trim( strip_tags( (string) $e ) ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function do_action( $h ) {
    $args = func_get_args();
    array_shift( $args );
    if ( 'thrg_bunker_notfound_sent' === $h ) {
        throw new THRG_Test_Exit( 'notfound' );
    }
    if ( 'thrg_bunker_hatch_done' === $h ) {
        $e          = new THRG_Test_Exit( 'hatch_page' );
        $e->payload = $args;
        throw $e;
    }
    if ( 'thrg_bunker_hatch_cookie' === $h ) {
        $GLOBALS['cookies'][ $args[0] ] = $args[1];
    }
}
function register_deactivation_hook( $f, $cb ) { $GLOBALS['hooks']['deactivate'][] = $cb; }
function register_activation_hook( $f, $cb ) { $GLOBALS['hooks']['activate'][] = $cb; }
function load_plugin_textdomain() { return true; }
function __( $t ) { return $t; }
function esc_html__( $t ) { return htmlspecialchars( $t, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_url( $t ) { return (string) $t; }
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function _n( $s, $p, $n ) { return 1 === $n ? $s : $p; }
function wp_unslash( $v ) { return $v; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return ( is_array( $t ) || is_object( $t ) ) ? '' : trim( strip_tags( (string) $t ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://example.test/wp-content/plugins/' . basename( dirname( $f ) ) . '/'; }
function plugins_url( $p, $f = '' ) { return 'https://example.test/wp-content/plugins/thrg-bunker/' . $p; }
function is_multisite() { return $GLOBALS['multisite']; }
function is_user_logged_in() { return $GLOBALS['user'] > 0; }
function get_current_user_id() { return $GLOBALS['user']; }
function current_user_can( $c ) { return $GLOBALS['can']; }
function wp_doing_ajax() { return ! empty( $GLOBALS['doing_ajax'] ); }
function is_customize_preview() { return false; }
function is_admin() { return false; }
function status_header( $c ) { $GLOBALS['status'] = $c; }
function nocache_headers() {}
function rest_authorization_required_code() { return is_user_logged_in() ? 403 : 401; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['transient'] ) ? $GLOBALS['transient'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['transient'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['transient'][ $k ] ); return true; }
function wp_upload_dir( $t = null, $c = true ) { return array( 'basedir' => ABSPATH . 'wp-content/uploads', 'baseurl' => 'https://example.test/uploads' ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function site_url( $p = '' ) { return 'https://example.test/' . ltrim( $p, '/' ); }
function wp_login_url() { return 'https://example.test/wp-login.php'; }
function wp_remote_get( $u, $a = array() ) { if ( isset( $GLOBALS['http_callback'] ) ) { return $GLOBALS['http_callback']( $u, $a ); } return $GLOBALS['http'] instanceof WP_Error ? $GLOBALS['http'] : array( 'response' => array( 'code' => $GLOBALS['http'] ) ); }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code']; }
function wp_mail( $to, $subject, $body ) { if ( isset( $GLOBALS['mail_ok'] ) && ! $GLOBALS['mail_ok'] ) { return false; } $GLOBALS['mails'][] = array( $to, $subject, $body ); return true; }
function get_bloginfo( $k ) { return 'Test Site'; }
function wp_get_current_user() { return isset( $GLOBALS['current_user_obj'] ) ? $GLOBALS['current_user_obj'] : (object) array( 'ID' => 0, 'user_email' => '' ); }
function wp_specialchars_decode( $t ) { return $t; }
function human_time_diff( $a, $b = 0 ) { return 'a while'; }
function wp_die( $message = '', $title = '', $args = array() ) {
    $e          = new THRG_Test_Exit( 'wp_die' );
    $e->payload = $message;
    $e->status  = is_array( $args ) && isset( $args['response'] ) ? $args['response'] : (int) $args;
    throw $e;
}
function wp_send_json_success( $d = null ) { $e = new THRG_Test_Exit( 'json' ); $e->payload = array( 'success' => true, 'data' => $d ); throw $e; }
function wp_send_json_error( $d = null, $s = 200 ) { $e = new THRG_Test_Exit( 'json' ); $e->payload = array( 'success' => false, 'data' => $d ); $e->status = $s; throw $e; }
function check_ajax_referer( $a, $k ) { if ( ! $GLOBALS['nonce_ok'] ) { throw new THRG_Test_Exit( 'bad nonce' ); } return 1; }
function check_admin_referer( $a ) { if ( ! $GLOBALS['nonce_ok'] ) { throw new THRG_Test_Exit( 'bad nonce' ); } return 1; }
function wp_safe_redirect( $u ) { $e = new THRG_Test_Exit( 'redirect' ); $e->payload = $u; throw $e; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $args, $url = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }

$GLOBALS['fail'] = 0;
/**
 * Record a check and print its result.
 *
 * @param string $name Description.
 * @param bool   $cond Pass or fail.
 */
function check( $name, $cond ) {
    echo ( $cond ? 'PASS' : 'FAIL' ) . " $name\n";
    if ( ! $cond ) {
        $GLOBALS['fail']++;
    }
}
register_shutdown_function(
    static function () {
        if ( $GLOBALS['fail'] ) {
            fwrite( STDERR, $GLOBALS['fail'] . " check(s) failed\n" );
            exit( 1 );
        }
    }
);

/**
 * Run a callable that ends in one of the stub exits and return the exit.
 *
 * @param callable $fn Code to run.
 * @return THRG_Test_Exit|null
 */
function thrg_catch( $fn ) {
    ob_start();
    try {
        $fn();
    } catch ( THRG_Test_Exit $e ) {
        ob_end_clean();
        return $e;
    }
    ob_end_clean();
    return null;
}

/**
 * Load the plugin (classes only, without enforcement hooks being checked).
 */
function thrg_load_plugin() {
    require __DIR__ . '/../index.php';
}

function wp_remote_retrieve_body( $r ) { return isset( $r['body'] ) ? $r['body'] : ''; }
