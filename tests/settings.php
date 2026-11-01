<?php
// Settings: instant-save AJAX, first-run confirmation, saving the lists.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();

function thrg_toggle( $id, $on ) {
    $_POST = array( 'id' => $id, 'on' => $on ? '1' : '0' );
    return thrg_catch( array( 'THRG_Bunker_Settings', 'ajax_toggle' ) );
}

// Capability and nonce are required.
$GLOBALS['nonce_ok'] = false;
$e = thrg_toggle( 'xmlrpc', false );
check( 'a bad nonce stops the toggle', $e && 'bad nonce' === $e->getMessage() && THRG_Bunker_Options::is_on( 'xmlrpc' ) );
$GLOBALS['nonce_ok'] = true;
$GLOBALS['can']      = false;
$e = thrg_toggle( 'xmlrpc', false );
check( 'without manage_options the toggle is refused with 403', $e && false === $e->payload['success'] && 403 === $e->status && THRG_Bunker_Options::is_on( 'xmlrpc' ) );
$GLOBALS['can'] = true;
$e = thrg_toggle( 'not_a_lockdown', false );
check( 'an unknown id is refused', $e && false === $e->payload['success'] && 400 === $e->status );

// The response returns the new state and the matching text.
$registry = THRG_Bunker_Options::registry();
$e = thrg_toggle( 'xmlrpc', false );
check( 'unticking returns the off text and saves', $e && $e->payload['success'] && false === $e->payload['data']['on'] && $registry['xmlrpc']['off'] === $e->payload['data']['text'] && ! THRG_Bunker_Options::is_on( 'xmlrpc' ) );
$e = thrg_toggle( 'xmlrpc', true );
check( 'ticking again returns the description text', $e && true === $e->payload['data']['on'] && $registry['xmlrpc']['on'] === $e->payload['data']['text'] && THRG_Bunker_Options::is_on( 'xmlrpc' ) );
THRG_Bunker_Options::set( 'rest', false );
check( 'an unticked option always shows its "what happened" text', $registry['rest']['off'] === THRG_Bunker_Settings::card_text( 'rest' ) );
THRG_Bunker_Options::set( 'rest', true );

// IP lock shows its waiting text until the list is saved.
$e = thrg_toggle( 'ip_lock', true );
check( 'the IP lock reports pending with its own text', $e && true === $e->payload['data']['pending'] && THRG_Bunker_Settings::pending_text() === $e->payload['data']['text'] );

// Item 13 cannot be switched on with an empty list.
$e = thrg_toggle( 'known_actions', true );
check( 'known actions cannot be turned on with an empty list', $e && false === $e->payload['data']['on'] && '' !== $e->payload['data']['message'] && ! THRG_Bunker_Options::is_on( 'known_actions' ) );
THRG_Bunker_Options::update( array( 'actions_allow' => 'contact_form' ) );
$e = thrg_toggle( 'known_actions', true );
check( 'known actions can be turned on once a list exists', $e && true === $e->payload['data']['on'] );

// First-run confirmation.
$GLOBALS['opts'] = array();
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
$_POST = array( 'decision' => 'not_yet' );
$e = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_confirm' ) );
check( 'Not yet enforces nothing and keeps asking', $e && 'redirect' === $e->getMessage() && ! THRG_Bunker_Options::enforcing() && 1 === THRG_Bunker_Options::state()['declined'] );
$_POST = array( 'decision' => 'lock' );
$GLOBALS['can'] = false;
$e = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_confirm' ) );
check( 'only an administrator can confirm', $e && 403 === $e->status && ! THRG_Bunker_Options::enforcing() );
$GLOBALS['can'] = true;
$e = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_confirm' ) );
$state = THRG_Bunker_Options::state();
check( 'Lock it down confirms and starts enforcement', $e && 'redirect' === $e->getMessage() && THRG_Bunker_Options::enforcing() && 0 === $state['declined'] );
check( 'the IP list is pre-filled with the administrator\'s address', '203.0.113.5' === $state['ip_list'] );
check( 'confirming does NOT start the IP lock; the owner must save the list', 0 === $state['ip_saved'] && ! THRG_Bunker_Options::active( 'ip_lock' ) );

// Saving the IP list.
function thrg_save_ip( $list, $confirm = false, $mode = 'remote_addr' ) {
    $_POST = array( 'save_section' => 'ip', 'ip_list' => $list, 'ip_proxy' => $mode );
    if ( $confirm ) {
        $_POST['ip_confirm_self'] = '1';
    }
    return thrg_catch( array( 'THRG_Bunker_Settings', 'handle_save' ) );
}
thrg_save_ip( "203.0.113.5\nnot an ip" );
check( 'invalid entries are rejected and nothing is saved', 0 === THRG_Bunker_Options::state()['ip_saved'] );
thrg_save_ip( '' );
check( 'an empty list is rejected', 0 === THRG_Bunker_Options::state()['ip_saved'] );
thrg_save_ip( '198.51.100.0/24' );
check( 'a list without the administrator\'s own address is refused', 0 === THRG_Bunker_Options::state()['ip_saved'] );
thrg_save_ip( '198.51.100.0/24', true );
check( 'the owner can confirm they accept the lockout risk', 1 === THRG_Bunker_Options::state()['ip_saved'] );
thrg_save_ip( "203.0.113.5\n198.51.100.0/24" );
$state = THRG_Bunker_Options::state();
check( 'saving the list starts the IP lock', 1 === $state['ip_saved'] && THRG_Bunker_Options::active( 'ip_lock' ) && "203.0.113.5\n198.51.100.0/24" === $state['ip_list'] );
thrg_save_ip( '203.0.113.5', false, 'evil' );
check( 'an unknown proxy mode falls back to REMOTE_ADDR', 'remote_addr' === THRG_Bunker_Options::state()['ip_proxy'] );
unset( $_SERVER['REMOTE_ADDR'] );

// General save, no-JavaScript fallback.
$_POST = array(
    'save_section'       => 'general',
    'thrg_bunker_toggle' => array( 'front_end' => '1', 'known_actions' => '1' ),
    'rest_allow'         => "chat/v1\n/other<script>/x",
    'actions_allow'      => '',
    'notfound_source'    => 'builtin',
    'cron_email'         => '1',
);
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_save' ) );
$state = THRG_Bunker_Options::state();
check( 'unticked boxes are saved as off in the form fallback', 1 === $state['toggles']['front_end'] && 0 === $state['toggles']['xmlrpc'] );
check( 'known actions is forced off when the list is empty', 0 === $state['toggles']['known_actions'] );
check( 'route list is cleaned', "/chat/v1\n/other/x" === $state['rest_allow'] );
check( 'other fields saved', 'builtin' === $state['notfound_source'] && 1 === $state['cron_email'] && 0 === $state['delete_data'] );
check( 'cleaners: routes and actions', "/a/b" === THRG_Bunker_Settings::clean_routes( 'a/b/' ) && "contact_form\nchat" === THRG_Bunker_Settings::clean_actions( "Contact_Form, chat!\n" ) );

// Nonce on forms.
$GLOBALS['nonce_ok'] = false;
$_POST = array( 'save_section' => 'general' );
$e = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_save' ) );
check( 'saving requires a valid nonce', $e && 'bad nonce' === $e->getMessage() );
$GLOBALS['nonce_ok'] = true;

// Flash messages are one-time.
THRG_Bunker_Settings::take_flash();
THRG_Bunker_Settings::flash( 'success', 'hello' );
check( 'flash messages show once', 1 === count( THRG_Bunker_Settings::take_flash() ) && array() === THRG_Bunker_Settings::take_flash() );
