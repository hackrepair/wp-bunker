<?php
// The one-time welcome email sent on activation.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();

$GLOBALS['mails']            = array();
$GLOBALS['opts']['admin_email'] = 'owner@example.test';
$GLOBALS['current_user_obj'] = (object) array( 'ID' => 1, 'user_email' => 'jim@example.test' );

check( 'activation is hooked', function_exists( 'thrg_bunker_activate' ) );
thrg_bunker_activate();
check( 'one email is sent on first activation', 1 === count( $GLOBALS['mails'] ) );
check( 'it goes to the administrator who activated the plugin', 'jim@example.test' === $GLOBALS['mails'][0][0] );
$subject = $GLOBALS['mails'][0][1];
$body    = $GLOBALS['mails'][0][2];
check( 'subject follows the THRG standard: [domain] [Plugin] Purpose', '[example.test] [Bunker] Welcome' === $subject );
check( 'the body names the site address', false !== strpos( $body, 'Site: https://example.test' ) );
check( 'it says nothing is locked yet', false !== strpos( $body, 'Nothing has been locked yet' ) );
check( 'it explains what Bunker is about', false !== strpos( $body, 'static site' ) && false !== strpos( $body, 'login page' ) );
check( 'it links to the settings page', false !== strpos( $body, 'page=thrg-bunker' ) );
check( 'it explains how to get out of a lockout', false !== strpos( $body, 'File Manager' ) && false !== strpos( $body, 'hatch' ) );
check( 'it never mentions cPanel', false === stripos( $body, 'cpanel' ) );
check( 'it carries no secrets or addresses', false === strpos( $body, 'jim@example.test' ) );

thrg_bunker_activate();
check( 'it is sent only once, even after reactivation', 1 === count( $GLOBALS['mails'] ) );
check( 'the time sent is remembered', THRG_Bunker_Options::state()['welcome_sent'] > 0 );

// Falls back to the site administrator address.
$GLOBALS['opts']      = array( 'admin_email' => 'owner@example.test' );
$GLOBALS['mails']     = array();
$GLOBALS['current_user_obj'] = (object) array( 'ID' => 0, 'user_email' => '' );
THRG_Bunker_Welcome::send_once();
check( 'with no signed-in user it goes to the site administrator address', 1 === count( $GLOBALS['mails'] ) && 'owner@example.test' === $GLOBALS['mails'][0][0] );

// A failing mail server cannot cause repeats.
$GLOBALS['opts']      = array( 'admin_email' => 'owner@example.test' );
$GLOBALS['mails']     = array();
$GLOBALS['mail_ok']   = false;
THRG_Bunker_Welcome::send_once();
$GLOBALS['mail_ok']   = true;
THRG_Bunker_Welcome::send_once();
check( 'a failed send is not retried', 0 === count( $GLOBALS['mails'] ) );

// No usable address, nothing is sent and nothing is marked.
$GLOBALS['opts']  = array( 'admin_email' => '' );
$GLOBALS['mails'] = array();
THRG_Bunker_Welcome::send_once();
check( 'no address, no email and no mark', 0 === count( $GLOBALS['mails'] ) && 0 === THRG_Bunker_Options::state()['welcome_sent'] );

// The other two emails follow the same standard.
$GLOBALS['opts'] = array( 'admin_email' => 'owner@example.test' );
$GLOBALS['mails'] = array();
THRG_Bunker_Options::update( array( 'cron_email' => 1 ) );
update_option( THRG_Bunker_Cron::LAST, 1 );
update_option( THRG_Bunker_Cron::SINCE, 1 );
THRG_Bunker_Cron::maybe_email();
check( 'stalled-tasks subject follows the standard', '[example.test] [Bunker] Scheduled tasks stalled' === $GLOBALS['mails'][0][1] && false !== strpos( $GLOBALS['mails'][0][2], 'Site: https://example.test' ) );
$method = new ReflectionMethod( 'THRG_Bunker_Hatch', 'send_mail' );
$method->setAccessible( true );
$method->invoke( null, array( 'created' => time(), 'ip' => '203.0.113.9', 'agent' => 'Test' ), str_repeat( 'cd', 24 ) );
check( 'hatch subject follows the standard and holds no secrets', '[example.test] [Bunker] Approve or deny a hatch request' === $GLOBALS['mails'][1][1] && false === strpos( $GLOBALS['mails'][1][1], 'cdcd' ) && false !== strpos( $GLOBALS['mails'][1][2], 'Site: https://example.test' ) );
