<?php
// Safe mode pauses all enforcement.
require __DIR__ . '/bootstrap.php';
define( 'THRG_BUNKER_SAFE_MODE', true );
thrg_load_plugin();
THRG_Bunker_Options::update( array( 'confirmed' => 1 ) );
check( 'safe mode on', THRG_Bunker_Options::safe_mode() );
check( 'safe mode stops enforcement even after confirmation', ! THRG_Bunker_Options::enforcing() );
check( 'no lockdown is active in safe mode', ! THRG_Bunker_Options::active( 'front_end' ) && ! THRG_Bunker_Options::active( 'uploads_php' ) );
$GLOBALS['hooks'] = array();
THRG_Bunker_Lockdown::boot();
check( 'no enforcement hooks registered in safe mode', empty( $GLOBALS['hooks'] ) );
