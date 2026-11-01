<?php
// Option registry and saved state.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();

$registry = THRG_Bunker_Options::registry();
check( 'thirteen lockdowns', 13 === count( $registry ) );
$complete = true;
foreach ( $registry as $item ) {
    foreach ( array( 'title', 'on', 'off' ) as $key ) {
        if ( empty( $item[ $key ] ) || ! is_string( $item[ $key ] ) ) {
            $complete = false;
        }
    }
    if ( ! isset( $item['default'] ) ) {
        $complete = false;
    }
}
check( 'every lockdown has a title, on text, off text and default', $complete );
$off = array();
foreach ( $registry as $id => $item ) {
    if ( ! $item['default'] ) {
        $off[] = $id;
    }
}
check( 'every default is on except item 13', array( 'known_actions' ) === $off );

// Nothing is enforced until the administrator confirms.
check( 'not enforcing before confirmation', ! THRG_Bunker_Options::enforcing() );
check( 'no lockdown active before confirmation', ! THRG_Bunker_Options::active( 'front_end' ) );
THRG_Bunker_Options::update( array( 'confirmed' => 1 ) );
check( 'enforcing after confirmation', THRG_Bunker_Options::enforcing() );
check( 'ticked lockdown is active', THRG_Bunker_Options::active( 'front_end' ) );
THRG_Bunker_Options::set( 'front_end', false );
check( 'unticked lockdown is not active', ! THRG_Bunker_Options::active( 'front_end' ) );
check( 'unticked choice survives reload', ! THRG_Bunker_Options::is_on( 'front_end' ) );
THRG_Bunker_Options::set( 'front_end', true );

// Multisite is unsupported in 1.0.
$GLOBALS['multisite'] = true;
check( 'multisite enforces nothing', ! THRG_Bunker_Options::enforcing() && ! THRG_Bunker_Options::active( 'xmlrpc' ) );
$GLOBALS['multisite'] = false;

// IP lock waits for the owner to save the list once.
check( 'ip lock ticked but pending before the list is saved', THRG_Bunker_Options::is_on( 'ip_lock' ) && THRG_Bunker_Options::pending( 'ip_lock' ) );
check( 'ip lock does not enforce while pending', ! THRG_Bunker_Options::active( 'ip_lock' ) );
THRG_Bunker_Options::update( array( 'ip_list' => '203.0.113.5' ) );
check( 'a pre-filled list alone does not start the lock', ! THRG_Bunker_Options::active( 'ip_lock' ) );
THRG_Bunker_Options::update( array( 'ip_saved' => 1 ) );
check( 'ip lock enforces once the list is saved', THRG_Bunker_Options::active( 'ip_lock' ) && ! THRG_Bunker_Options::pending( 'ip_lock' ) );

// Item 13 needs an allowlist.
THRG_Bunker_Options::set( 'known_actions', true );
check( 'known actions inactive with an empty list', ! THRG_Bunker_Options::active( 'known_actions' ) );
THRG_Bunker_Options::update( array( 'actions_allow' => "contact_form\n" ) );
check( 'known actions active with a list', THRG_Bunker_Options::active( 'known_actions' ) );

// Unknown ids and junk in the saved option are ignored.
$GLOBALS['opts'][ THRG_Bunker_Options::OPTION ] = array( 'toggles' => array( 'bogus' => 1, 'xmlrpc' => 0 ), 'confirmed' => 1 );
$state = THRG_Bunker_Options::state();
check( 'unknown toggle ids dropped, saved choices kept', ! isset( $state['toggles']['bogus'] ) && 0 === $state['toggles']['xmlrpc'] && 1 === $state['toggles']['rest'] );
$GLOBALS['opts'][ THRG_Bunker_Options::OPTION ] = 'garbage';
check( 'a corrupted option falls back to defaults', 0 === THRG_Bunker_Options::state()['confirmed'] );

check( 'lines() cleans, splits and de-duplicates', array( 'a', 'b', 'c' ) === THRG_Bunker_Options::lines( " a\r\nb, c\n\na\n" ) );
