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
check( 'and is not hooked when item 14 is off', ! isset( $GLOBALS['hooks']['init'] ) || ! in_array( array( 'THRG_Bunker_Lockdown', 'early_gate' ), $GLOBALS['hooks']['init'], true ) );
THRG_Bunker_Options::set( 'stealth', true );

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
foreach ( array( 'wp-load', 'wp-trackback', 'wp-links-opml', 'wp-signup', 'readme', 'license', 'wp-config-sample', 'plugins|mu-plugins|themes', 'wp-admin/install' ) as $needle ) {
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
