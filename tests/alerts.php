<?php
// Security alerts: administrator grants and new plugin/theme installs.
require __DIR__ . '/bootstrap.php';

function get_userdata( $id ) {
	return isset( $GLOBALS['users'][ $id ] ) ? (object) $GLOBALS['users'][ $id ] : false;
}
function wp_clean_plugins_cache( $reset = true ) {}
function wp_clean_themes_cache( $reset = true ) {}
function get_plugins() {
	return $GLOBALS['plugins'];
}
function wp_get_themes() {
	$out = array();
	foreach ( $GLOBALS['themes'] as $slug => $data ) {
		$out[ $slug ] = new THRG_Test_Theme( $data );
	}
	return $out;
}
function current_time( $t ) {
	return '2026-11-02 12:00:00';
}
function wp_timezone_string() {
	return 'UTC';
}
class THRG_Test_User {
	public $ID = 1;
	public $user_login = 'boss';
	public function exists() {
		return true;
	}
}
class THRG_Test_Theme {
	private $data;
	public function __construct( $data ) {
		$this->data = $data;
	}
	public function get( $k ) {
		return isset( $this->data[ $k ] ) ? $this->data[ $k ] : '';
	}
}

thrg_load_plugin();

$GLOBALS['opts']['admin_email'] = 'owner@example.test';
$GLOBALS['users']               = array(
	7 => array( 'ID' => 7, 'user_login' => 'newadmin' ),
	8 => array( 'ID' => 8, 'user_login' => "evil\r\nBcc: attacker@example.test" ),
);
$GLOBALS['plugins']             = array( 'old/old.php' => array( 'Name' => 'Old', 'Version' => '1.0' ) );
$GLOBALS['themes']              = array( 'oldtheme' => array( 'Name' => 'Old Theme', 'Version' => '1.0' ) );
$GLOBALS['current_user_obj']    = new THRG_Test_User();

function thrg_enable( $admin, $plugin, $theme ) {
	THRG_Bunker_Options::update( array( 'alert_admin' => $admin, 'alert_plugin' => $plugin, 'alert_theme' => $theme, 'paused' => 0 ) );
	$GLOBALS['mails']   = array();
	$GLOBALS['mail_ok'] = true;
}
function thrg_install( $type, $slug, $result, $name, $version ) {
	$extra = array( 'action' => 'install', 'type' => $type );
	THRG_Bunker_Alerts::before_install( true, $extra );
	if ( 'plugin' === $type ) {
		$GLOBALS['plugins'][ $slug . '/' . $slug . '.php'] = array( 'Name' => $name, 'Version' => $version );
	} else {
		$GLOBALS['themes'][ $slug ] = array( 'Name' => $name, 'Version' => $version );
	}
	$upgrader         = new stdClass();
	$upgrader->result = $result;
	THRG_Bunker_Alerts::installed( $upgrader, $extra );
}

// Off by default.
check( 'every alert is off by default', array( 0, 0, 0 ) === array( THRG_Bunker_Options::defaults()['alert_admin'], THRG_Bunker_Options::defaults()['alert_plugin'], THRG_Bunker_Options::defaults()['alert_theme'] ) );
$GLOBALS['mails'] = array();
THRG_Bunker_Alerts::role_added( 7, 'administrator' );
check( 'nothing is sent until the alert is enabled', array() === $GLOBALS['mails'] );

// Administrator grants.
thrg_enable( 1, 1, 1 );
THRG_Bunker_Alerts::role_added( 7, 'subscriber' );
check( 'a non-administrator role sends nothing', array() === $GLOBALS['mails'] );
THRG_Bunker_Alerts::role_added( 99, 'administrator' );
check( 'an unknown account sends nothing', array() === $GLOBALS['mails'] );
THRG_Bunker_Alerts::role_added( 7, 'administrator' );
check( 'an administrator grant sends one email', 1 === count( $GLOBALS['mails'] ) );
check( 'it goes to the site administration email', 'owner@example.test' === $GLOBALS['mails'][0][0] );
check( 'subject follows the THRG format', '[example.test] [Bunker] Administrator access added' === $GLOBALS['mails'][0][1] );
check( 'body names the account and the actor', false !== strpos( $GLOBALS['mails'][0][2], 'newadmin (ID 7)' ) && false !== strpos( $GLOBALS['mails'][0][2], 'boss' ) );
check( 'body links to the Users screen', false !== strpos( $GLOBALS['mails'][0][2], 'wp-admin/users.php' ) );
THRG_Bunker_Alerts::role_added( 7, 'administrator' );
check( 'a second grant to the same account is a new event', 2 === count( $GLOBALS['mails'] ) );
THRG_Bunker_Alerts::role_added( 8, 'administrator' );
check( 'two different accounts give two messages', 3 === count( $GLOBALS['mails'] ) );

// Hostile account name.
$last = $GLOBALS['mails'][2];
check( 'a hostile account name never reaches the recipient or subject', 'owner@example.test' === $last[0] && false === strpos( $last[1], 'attacker' ) );

// Recipient override.
THRG_Bunker_Options::update( array( 'alert_email' => 'alerts@example.test' ) );
$GLOBALS['mails'] = array();
THRG_Bunker_Alerts::role_added( 7, 'administrator' );
check( 'a saved alert recipient is used', 'alerts@example.test' === $GLOBALS['mails'][0][0] );
THRG_Bunker_Options::update( array( 'alert_email' => "bad\r\nBcc: x@example.test" ) );
check( 'an invalid stored recipient falls back to the site address', 'owner@example.test' === THRG_Bunker_Alerts::recipient() );
THRG_Bunker_Options::update( array( 'alert_email' => array( 'x@example.test' ) ) );
check( 'a non-string stored recipient falls back to the site address', 'owner@example.test' === THRG_Bunker_Alerts::recipient() );
THRG_Bunker_Options::update( array( 'alert_email' => '' ) );

// Suppression.
thrg_enable( 1, 1, 1 );
THRG_Bunker_Options::update( array( 'paused' => 1 ) );
THRG_Bunker_Alerts::role_added( 7, 'administrator' );
thrg_install( 'plugin', 'p1', array( 'destination_name' => 'p1' ), 'P1', '1.0' );
check( 'a paused Bunker sends nothing', array() === $GLOBALS['mails'] );
thrg_enable( 1, 1, 1 );
$GLOBALS['multisite'] = true;
THRG_Bunker_Alerts::role_added( 7, 'administrator' );
$GLOBALS['multisite'] = false;
check( 'multisite sends nothing', array() === $GLOBALS['mails'] );
thrg_enable( 1, 0, 0 );
thrg_install( 'plugin', 'p2', array( 'destination_name' => 'p2' ), 'P2', '1.0' );
thrg_install( 'theme', 't2', array( 'destination_name' => 't2' ), 'T2', '1.0' );
check( 'each alert type is switched separately', array() === $GLOBALS['mails'] );

// Plugin and theme installs.
thrg_enable( 1, 1, 1 );
thrg_install( 'plugin', 'fresh', array( 'destination_name' => 'fresh' ), 'Fresh Plugin', '2.1' );
check( 'a new plugin sends one email', 1 === count( $GLOBALS['mails'] ) && '[example.test] [Bunker] New plugin installed' === $GLOBALS['mails'][0][1] );
check( 'it names the plugin, version and folder', false !== strpos( $GLOBALS['mails'][0][2], 'Fresh Plugin 2.1 (fresh)' ) );
check( 'it links to the Plugins screen', false !== strpos( $GLOBALS['mails'][0][2], 'wp-admin/plugins.php' ) );
thrg_install( 'theme', 'freshtheme', array( 'destination_name' => 'freshtheme' ), 'Fresh Theme', '3.0' );
check( 'a new theme sends one email', 2 === count( $GLOBALS['mails'] ) && '[example.test] [Bunker] New theme installed' === $GLOBALS['mails'][1][1] );
thrg_install( 'plugin', 'old', array( 'destination_name' => 'old' ), 'Old', '1.1' );
check( 'reinstalling an existing plugin sends nothing', 2 === count( $GLOBALS['mails'] ) );
thrg_install( 'plugin', 'broken', new WP_Error( 'fail' ), 'Broken', '1.0' );
check( 'a failed install sends nothing', 2 === count( $GLOBALS['mails'] ) );
$before = count( $GLOBALS['mails'] );
THRG_Bunker_Alerts::before_install( true, array( 'action' => 'update', 'type' => 'plugin' ) );
$upgrader         = new stdClass();
$upgrader->result = array( 'destination_name' => 'fresh' );
THRG_Bunker_Alerts::installed( $upgrader, array( 'action' => 'update', 'type' => 'plugin' ) );
check( 'an update sends nothing', count( $GLOBALS['mails'] ) === $before );
thrg_install( 'plugin', 'second', array( 'destination_name' => 'second' ), 'Second', '1.0' );
thrg_install( 'plugin', 'third', array( 'destination_name' => 'third' ), 'Third', '1.0' );
check( 'two distinct installs are both reported', $before + 2 === count( $GLOBALS['mails'] ) );

// Hostile plugin metadata.
thrg_enable( 1, 1, 1 );
thrg_install( 'plugin', 'evilmeta', array( 'destination_name' => 'evilmeta' ), "<script>alert(1)</script>Evil\r\nBcc: attacker@example.test", '1.0<b>' );
check( 'hostile plugin metadata is only ever message text', 1 === count( $GLOBALS['mails'] ) && 'owner@example.test' === $GLOBALS['mails'][0][0] && false === strpos( $GLOBALS['mails'][0][1], 'attacker' ) && false === strpos( $GLOBALS['mails'][0][2], '<script>' ) );

// Mail failure bookkeeping.
thrg_enable( 1, 1, 1 );
$GLOBALS['mail_ok'] = false;
THRG_Bunker_Alerts::role_added( 7, 'administrator' );
$state = THRG_Bunker_Options::state();
check( 'a failed send is recorded with its type', ! empty( $state['alert_mail_error'] ) && 'admin' === $state['alert_mail_error_type'] );
$GLOBALS['mail_ok'] = true;
THRG_Bunker_Alerts::role_added( 7, 'administrator' );
$state = THRG_Bunker_Options::state();
check( 'a later successful send clears the failure notice', 0 === $state['alert_mail_error'] && '' === $state['alert_mail_error_type'] );
$GLOBALS['mail_ok'] = false;
THRG_Bunker_Alerts::role_added( 7, 'administrator' );
$GLOBALS['mail_ok'] = true;
check( 'the notice was raised again', ! empty( THRG_Bunker_Options::state()['alert_mail_error'] ) );
