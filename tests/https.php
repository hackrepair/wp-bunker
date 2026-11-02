<?php
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();
THRG_Bunker_Options::update( array( 'confirmed' => 1, 'https_redirect' => 1 ) );
$lines = implode( "\n", THRG_Bunker_Server_Rules::lines( 'version_files' ) );
check( 'redirect preserves request path and method', false !== strpos( $lines, '%{REQUEST_URI} [R=308,L,NE]' ) );
check( 'direct HTTPS is exempt', false !== strpos( $lines, 'RewriteCond %{HTTPS} !=on' ) && false !== strpos( $lines, 'RewriteCond %{SERVER_PORT} !^443$' ) );
check( 'redirect uses configured host rather than request host', false === strpos( $lines, 'HTTP_HOST' ) );
THRG_Bunker_Options::set( 'version_files', false );
THRG_Bunker_Server_Rules::sync();
check( 'redirect works independently of version file hiding', false !== strpos( file_get_contents( ABSPATH . '.htaccess' ), 'R=308' ) );
THRG_Bunker_Options::update( array( 'paused' => 1 ) );
THRG_Bunker_Server_Rules::sync();
check( 'pausing removes redirect', false === strpos( file_get_contents( ABSPATH . '.htaccess' ), 'R=308' ) );

$GLOBALS['http'] = 200;
$_POST = array( 'https_redirect' => '1' );
$save = new ReflectionMethod( 'THRG_Bunker_Settings', 'save_general' );
$save->invoke( null );
check( 'saving verified direct HTTPS enables the redirect', 1 === THRG_Bunker_Options::state()['https_redirect'] );
$GLOBALS['http'] = new WP_Error( 'certificate', 'certificate validation failed' );
$save->invoke( null );
check( 'failed TLS probe leaves the redirect off', 0 === THRG_Bunker_Options::state()['https_redirect'] );
$GLOBALS['http'] = 200;
THRG_Bunker_Options::update( array( 'ip_proxy' => 'cloudflare' ) );
$save->invoke( null );
check( 'proxy setup requires hosting configuration', 0 === THRG_Bunker_Options::state()['https_redirect'] );
