<?php
// Item 14: answer 404 to everything WordPress-related.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();

THRG_Bunker_Options::update(
    array(
        'confirmed' => 1,
        'ip_list'   => '203.0.113.5',
        'ip_saved'  => 1,
        'ip_proxy'  => 'remote_addr',
    )
);
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
$GLOBALS['pagenow']     = 'wp-login.php';

check( 'item 14 is off by default', ! THRG_Bunker_Options::is_on( 'stealth' ) && ! THRG_Bunker_Options::active( 'stealth' ) );

// Off: the old "Access limited" page.
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'ip_gate' ) );
check( 'with item 14 off, a refused visitor sees the 403 page', $e && 'wp_die' === $e->getMessage() && 403 === $e->status );

// On: the 404.
THRG_Bunker_Options::set( 'stealth', true );
check( 'item 14 is active once ticked and confirmed', THRG_Bunker_Options::active( 'stealth' ) );
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'ip_gate' ) );
check( 'with item 14 on, a refused visitor gets Bunker\'s 404 instead', $e && 'notfound' === $e->getMessage() );
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
check( 'an allowed IP still passes', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'ip_gate' ) ) );
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';

// The early gate only looks at the login page and the dashboard.
$GLOBALS['pagenow'] = 'index.php';
check( 'the early gate never touches the front end', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'early_gate' ) ) );
$GLOBALS['pagenow'] = 'wp-login.php';
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'early_gate' ) );
check( 'the early gate 404s a refused visitor on the login page', $e && 'notfound' === $e->getMessage() );
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
check( 'and lets an allowed IP through', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'early_gate' ) ) );
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';

// Hooks.
$GLOBALS['hooks'] = array();
THRG_Bunker_Lockdown::boot();
check( 'the early gate is hooked to init when item 14 and the IP lock are on', isset( $GLOBALS['hooks']['init'] ) );
$GLOBALS['hooks'] = array();
THRG_Bunker_Options::set( 'stealth', false );
THRG_Bunker_Lockdown::boot();
check( 'with item 14 off it is still hooked, for the database repair screen', isset( $GLOBALS['hooks']['init'] ) && in_array( array( 'THRG_Bunker_Lockdown', 'early_gate' ), $GLOBALS['hooks']['init'], true ) );
$GLOBALS['pagenow'] = 'wp-login.php';
check( 'with item 14 off the early gate leaves the login page to the normal gate', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'early_gate' ) ) );
foreach ( array( 'repair.php' ) as $screen ) {
    $GLOBALS['pagenow'] = $screen;
    $e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'early_gate' ) );
    check( "a refused visitor cannot open $screen even with item 14 off", $e && 'wp_die' === $e->getMessage() );
    $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
    check( "an allowed IP can open $screen", null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'early_gate' ) ) );
    $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
    THRG_Bunker_Options::set( 'stealth', true );
    $e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'early_gate' ) );
    check( "with item 14 on a refused visitor gets the 404 on $screen", $e && 'notfound' === $e->getMessage() );
    THRG_Bunker_Options::set( 'stealth', false );
}
THRG_Bunker_Options::set( 'stealth', true );

$GLOBALS['pagenow'] = 'wp-login.php';
// Hatch still works with the earlier, quieter gate.
list( , $token ) = array( null, '' );
$GLOBALS['can'] = true;
$_POST          = array( 'hatch_do' => 'create' );
thrg_catch( array( 'THRG_Bunker_Hatch', 'handle_admin' ) );
preg_match( '/thrg_hatch=([a-f0-9]+)/', THRG_Bunker_Hatch::take_shown(), $m );
$_GET                      = array( 'thrg_hatch' => $m[1] );
$_SERVER['REQUEST_METHOD'] = 'GET';
$_COOKIE                   = array();
$GLOBALS['mails']          = array();
$e                         = thrg_catch( array( 'THRG_Bunker_Lockdown', 'early_gate' ) );
check( 'the hatch link still works through the early gate', $e && 'redirect' === $e->getMessage() && 1 === count( $GLOBALS['mails'] ) );
$_GET = array();

// Server rules.
$stealth_lines = THRG_Bunker_Server_Rules::lines( 'version_files' );
$text          = implode( "\n", $stealth_lines );
check( 'with item 14 on, the root block answers 404 with no 403 rule', false !== strpos( $text, 'RedirectMatch 404' ) && false === strpos( $text, 'Require all denied' ) && false !== strpos( $text, 'Options -Indexes' ) );
foreach ( array( 'wp-load', 'wp-trackback', 'wp-links-opml', 'wp-signup', 'readme', 'license', 'wp-config-sample', 'plugins|mu-plugins|themes', 'wp-admin/(install|setup-config)', 'wp-admin/includes', 'wp-includes/[^/]+' ) as $needle ) {
    check( "the 404 rules cover $needle", false !== strpos( $text, $needle ) );
}
check( 'static files that WordPress screens need are not blocked', false === strpos( $text, 'wp-includes/js' ) && false === strpos( $text, 'wp-includes/css' ) && false === strpos( $text, 'wp-admin/css' ) );
check( 'the Nginx text answers 404 too', false !== strpos( THRG_Bunker_Server_Rules::nginx_text( 'version_files' ), 'return 404' ) );
THRG_Bunker_Options::set( 'stealth', false );
check( 'with item 14 off, the root block is the old 403 rule', false !== strpos( implode( "\n", THRG_Bunker_Server_Rules::lines( 'version_files' ) ), 'Require all denied' ) );

// sync(): the shared root file follows both ticks.
$root            = ABSPATH . '.htaccess';
$GLOBALS['http'] = 200;
file_put_contents( $root, "# BEGIN WordPress\nx\n# END WordPress\n" );
THRG_Bunker_Options::update( array( 'server_rules' => array() ) );
THRG_Bunker_Options::set( 'version_files', false );
THRG_Bunker_Options::set( 'uploads_php', false );
THRG_Bunker_Server_Rules::sync();
check( 'both off: no rules', ! THRG_Bunker_Server_Rules::present( $root ) );
THRG_Bunker_Options::set( 'stealth', true );
THRG_Bunker_Server_Rules::sync();
check( 'item 14 alone writes the 404 rules', THRG_Bunker_Server_Rules::present( $root ) && false !== strpos( file_get_contents( $root ), 'RedirectMatch 404' ) );
THRG_Bunker_Options::set( 'version_files', true );
THRG_Bunker_Server_Rules::sync();
check( 'item 10 as well keeps one block with the 404 rules', 1 === substr_count( file_get_contents( $root ), '# BEGIN THRG Bunker' ) && false !== strpos( file_get_contents( $root ), 'RedirectMatch 404' ) );
THRG_Bunker_Options::set( 'stealth', false );
THRG_Bunker_Server_Rules::sync();
check( 'unticking item 14 switches the block back to the 403 rule', false === strpos( file_get_contents( $root ), 'RedirectMatch' ) && false !== strpos( file_get_contents( $root ), 'Require all denied' ) );
THRG_Bunker_Options::set( 'version_files', false );
THRG_Bunker_Server_Rules::sync();
check( 'both off again removes the block and keeps the rest', "# BEGIN WordPress\nx\n# END WordPress\n" === file_get_contents( $root ) );
THRG_Bunker_Options::set( 'stealth', true );
$GLOBALS['http'] = 503;
$notes           = THRG_Bunker_Server_Rules::sync();
check( 'a server error leaves item 14 off and explains', ! THRG_Bunker_Options::is_on( 'stealth' ) && 1 === count( $notes ) && "# BEGIN WordPress\nx\n# END WordPress\n" === file_get_contents( $root ) );
$GLOBALS['http'] = 200;

// Review check (private row, read through reflection because the other rows need a full WordPress).
$method = new ReflectionMethod( 'THRG_Bunker_Checks', 'stealth' );
if ( PHP_VERSION_ID < 80100 ) {
    $method->setAccessible( true );
}
THRG_Bunker_Options::set( 'stealth', false );
$row = $method->invoke( null );
check( 'the review checks mention it, informational while off', 'stealth' === $row['id'] && 'info' === $row['status'] );
THRG_Bunker_Options::set( 'stealth', true );
$row = $method->invoke( null );
check( 'and good while on', 'good' === $row['status'] );

// XML-RPC answers 404 (the old 403 path ends the script, so only the new one is exercised here).
define( 'XMLRPC_REQUEST', true );
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'xmlrpc' ) );
check( 'XML-RPC answers 404 with item 14 on', $e && 'notfound' === $e->getMessage() );

// Review M4: the comment and known-action refusals answer 404 too once item 14 is active.
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'refuse_comment' ) );
check( 'with item 14 on, a refused comment gets the 404', $e && 'notfound' === $e->getMessage() );
THRG_Bunker_Options::update( array( 'actions_allow' => 'contact_form' ) );
$GLOBALS['user']    = 0;
$GLOBALS['pagenow'] = 'admin-post.php';
$_REQUEST['action'] = 'other_plugin_action';
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ) );
check( 'with item 14 on, a refused form action gets the 404', $e && 'notfound' === $e->getMessage() );
$_REQUEST['action'] = 'contact_form';
check( 'a listed form action still works', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ) ) );
THRG_Bunker_Options::set( 'stealth', false );
$_REQUEST['action'] = 'other_plugin_action';
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ) );
check( 'with item 14 off they still answer 403', $e && 'wp_die' === $e->getMessage() && 403 === $e->status );
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'refuse_comment' ) );
check( 'and a refused comment answers 403', $e && 'wp_die' === $e->getMessage() && 403 === $e->status );
unset( $_REQUEST['action'] );

// A scanner must not learn WordPress is here from the REST refusal, the bare admin-ajax answer or core PHP files.
THRG_Bunker_Options::update( array( 'confirmed' => 1, 'rest' => 1, 'rest_allow' => '/chat/v1' ) );
THRG_Bunker_Options::set( 'stealth', true );
$GLOBALS['user'] = 0;
$GLOBALS['wp']   = (object) array( 'query_vars' => array( 'rest_route' => '/' ) );
$e = thrg_catch( function () { THRG_Bunker_Lockdown::rest_gate( null ); } );
check( 'with item 14 on, a refused REST request gets the 404, not a JSON 401', $e && 'notfound' === $e->getMessage() );
$GLOBALS['wp']->query_vars['rest_route'] = '/chat/v1/send';
check( 'an allowed route still works with item 14 on', null === THRG_Bunker_Lockdown::rest_gate( null ) );
THRG_Bunker_Options::set( 'stealth', false );
$GLOBALS['wp']->query_vars['rest_route'] = '/';
$out = THRG_Bunker_Lockdown::rest_gate( null );
check( 'with item 14 off, a refused REST request keeps the normal 401', $out instanceof WP_Error && 401 === $out->data['status'] );
THRG_Bunker_Options::set( 'stealth', true );
$GLOBALS['hooks'] = array();
THRG_Bunker_Options::set( 'known_actions', true );
THRG_Bunker_Lockdown::boot();
check( 'the known-actions gate runs on init, before admin-ajax.php answers a bare request with its own 0', in_array( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ), $GLOBALS['hooks']['init'], true ) && ! in_array( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ), isset( $GLOBALS['hooks']['admin_init'] ) ? $GLOBALS['hooks']['admin_init'] : array(), true ) );
