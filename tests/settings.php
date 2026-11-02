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
check( 'cleaners: routes and actions', "/a/b" === THRG_Bunker_Settings::clean_routes( 'a/b/' ) && "Contact_Form" === THRG_Bunker_Settings::clean_actions( "Contact_Form, chat!\n" ) );

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

// The On | Off switch for Bunker itself.
$GLOBALS['opts'] = array();
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
$_POST = array( 'state' => 'on' );
$e = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
check( 'On acts as the first confirmation', $e && 'redirect' === $e->getMessage() && THRG_Bunker_Options::enforcing() && '203.0.113.5' === THRG_Bunker_Options::state()['ip_list'] );
$_POST = array( 'state' => 'off' );
$e = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
check( 'Off pauses every lockdown and keeps the settings', $e && ! THRG_Bunker_Options::enforcing() && ! THRG_Bunker_Options::active( 'front_end' ) && 1 === THRG_Bunker_Options::state()['confirmed'] && THRG_Bunker_Options::is_on( 'front_end' ) );
$GLOBALS['hooks'] = array();
THRG_Bunker_Lockdown::boot();
check( 'while Off, no enforcement hooks are registered', empty( $GLOBALS['hooks'] ) );
$_POST = array( 'state' => 'on' );
$e = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
check( 'On again resumes with the saved settings', THRG_Bunker_Options::enforcing() && THRG_Bunker_Options::active( 'front_end' ) );
$GLOBALS['can'] = false;
$_POST = array( 'state' => 'off' );
$e = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
check( 'only an administrator can use the switch', $e && 403 === $e->status && THRG_Bunker_Options::enforcing() );
$GLOBALS['can']      = true;
$GLOBALS['nonce_ok'] = false;
$e = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
check( 'and it needs a valid nonce', $e && 'bad nonce' === $e->getMessage() && THRG_Bunker_Options::enforcing() );
$GLOBALS['nonce_ok'] = true;

// The On | Off switch for the IP lock.
$_POST = array( 'ip_state' => 'off' );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
check( 'the IP lock switch turns the lockdown off', ! THRG_Bunker_Options::is_on( 'ip_lock' ) && THRG_Bunker_Options::enforcing() );
$_POST = array( 'ip_state' => 'on' );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
check( 'and on again; it waits until the list is saved', THRG_Bunker_Options::is_on( 'ip_lock' ) && THRG_Bunker_Options::pending( 'ip_lock' ) && ! THRG_Bunker_Options::active( 'ip_lock' ) );
THRG_Bunker_Options::update( array( 'ip_saved' => 1 ) );
check( 'with a saved list the switch means enforcing', THRG_Bunker_Options::active( 'ip_lock' ) );
$_POST = array( 'ip_state' => 'bogus' );
$e = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
check( 'an unknown value changes nothing', THRG_Bunker_Options::is_on( 'ip_lock' ) && THRG_Bunker_Options::enforcing() );

// The On | Off switch for the hatch link.
$_POST = array( 'hatch_state' => 'on' );
$e = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
check( 'the hatch switch On makes a link and shows it once', $e && 'redirect' === $e->getMessage() && THRG_Bunker_Hatch::enabled() && false !== get_transient( 'thrg_bunker_hatch_show_' . get_current_user_id() ) );
$v = THRG_Bunker_Options::state()['hatch_version'];
$_POST = array( 'hatch_state' => 'off' );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
check( 'Off kills the link and signs everyone out', ! THRG_Bunker_Hatch::enabled() && '' === THRG_Bunker_Options::state()['hatch_hash'] && THRG_Bunker_Options::state()['hatch_version'] > $v );
check( 'switching the hatch leaves Bunker enforcing', THRG_Bunker_Options::enforcing() );
$GLOBALS['can'] = false;
$_POST          = array( 'hatch_state' => 'on' );
$e              = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
check( 'only an administrator can switch the hatch', $e && 403 === $e->status && ! THRG_Bunker_Hatch::enabled() );
$GLOBALS['can'] = true;

// Review H1: pressing the side of a switch that is already current changes nothing.
$GLOBALS['opts'] = array();
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
THRG_Bunker_Options::update( array( 'confirmed' => 1, 'ip_list' => '203.0.113.5', 'ip_saved' => 1 ) );
$_POST = array( 'hatch_state' => 'on' );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
$before = THRG_Bunker_Options::state();
THRG_Bunker_Settings::take_flash();
$_POST = array( 'hatch_state' => 'on' );
$e     = thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
$after = THRG_Bunker_Options::state();
$flash = THRG_Bunker_Settings::take_flash();
check( 'On while the hatch is already on keeps the same link and approved browsers', $before['hatch_hash'] === $after['hatch_hash'] && $before['hatch_version'] === $after['hatch_version'] );
check( 'and says so instead of showing a new link', 1 === count( $flash ) && 'info' === $flash[0]['type'] );
$_POST = array( 'hatch_state' => 'off' );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
$v = THRG_Bunker_Options::state()['hatch_version'];
THRG_Bunker_Settings::take_flash();
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
$flash = THRG_Bunker_Settings::take_flash();
check( 'Off while the hatch is already off changes nothing', THRG_Bunker_Options::state()['hatch_version'] === $v && 1 === count( $flash ) && 'info' === $flash[0]['type'] );
$_POST = array( 'ip_state' => 'on' );
THRG_Bunker_Settings::take_flash();
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
$flash = THRG_Bunker_Settings::take_flash();
check( 'IP lock On while already on only says so', THRG_Bunker_Options::is_on( 'ip_lock' ) && 1 === count( $flash ) && 'info' === $flash[0]['type'] );
$_POST = array( 'state' => 'on' );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
$flash = THRG_Bunker_Settings::take_flash();
check( 'Bunker On while already on only says so', THRG_Bunker_Options::enforcing() && 1 === count( $flash ) && 'info' === $flash[0]['type'] );
$_POST = array( 'state' => 'off' );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
THRG_Bunker_Settings::take_flash();
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );
$flash = THRG_Bunker_Settings::take_flash();
check( 'Bunker Off while already off only says so', ! THRG_Bunker_Options::enforcing() && 1 === count( $flash ) && 'info' === $flash[0]['type'] );
$_POST = array( 'state' => 'on' );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_master' ) );

// Review H4: either Save button saves everything on the page.
THRG_Bunker_Options::update( array( 'ip_list' => '203.0.113.5', 'ip_saved' => 1, 'rest_allow' => '' ) );
THRG_Bunker_Settings::take_flash();
$_POST = array( 'save_section' => 'general', 'ip_list' => "203.0.113.5\n198.51.100.7", 'ip_proxy' => 'remote_addr', 'rest_allow' => '/mychat/v1' );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_save' ) );
$state = THRG_Bunker_Options::state();
check( 'Save changes also saves an edited IP list', "203.0.113.5\n198.51.100.7" === $state['ip_list'] && '/mychat/v1' === $state['rest_allow'] );
$_POST = array( 'save_section' => 'ip', 'ip_list' => "203.0.113.5\n198.51.100.8", 'ip_proxy' => 'remote_addr', 'rest_allow' => '/other/v2' );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_save' ) );
$state = THRG_Bunker_Options::state();
check( 'Save list also saves the other fields on the page', "203.0.113.5\n198.51.100.8" === $state['ip_list'] && '/other/v2' === $state['rest_allow'] );
THRG_Bunker_Settings::take_flash();
$_POST = array( 'save_section' => 'general', 'ip_list' => "203.0.113.5\nnot an ip", 'ip_proxy' => 'remote_addr', 'rest_allow' => '/third/v3' );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_save' ) );
$state = THRG_Bunker_Options::state();
$types = array_column( THRG_Bunker_Settings::take_flash(), 'type' );
check( 'a rejected IP list keeps the old list but the other fields still save', "203.0.113.5\n198.51.100.8" === $state['ip_list'] && '/third/v3' === $state['rest_allow'] && in_array( 'error', $types, true ) );
$_SERVER['REMOTE_ADDR'] = '192.0.2.99';
$_POST = array( 'save_section' => 'general', 'ip_list' => "203.0.113.5\n198.51.100.8", 'ip_proxy' => 'remote_addr', 'rest_allow' => '/fourth/v4' );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_save' ) );
$types = array_column( THRG_Bunker_Settings::take_flash(), 'type' );
check( 'an untouched IP list is never re-checked on Save changes', '/fourth/v4' === THRG_Bunker_Options::state()['rest_allow'] && ! in_array( 'error', $types, true ) );
$_POST = array( 'save_section' => 'ip', 'ip_list' => "203.0.113.5\n198.51.100.8" );
THRG_Bunker_Options::update( array( 'rest_allow' => '/keep/v1' ) );
thrg_catch( array( 'THRG_Bunker_Settings', 'handle_save' ) );
check( 'a bare Save list post leaves the general settings alone', '/keep/v1' === THRG_Bunker_Options::state()['rest_allow'] );
unset( $_SERVER['REMOTE_ADDR'] );
