<?php
// Bunker's own 404 page.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();

$file = ABSPATH . '404.html';
check( 'built-in page when no 404.html exists', false !== strpos( THRG_Bunker_NotFound::html(), 'Page not found' ) && '' === THRG_Bunker_NotFound::find_file() );
check( 'built-in page carries noindex without a plugin fingerprint', false !== strpos( THRG_Bunker_NotFound::builtin_html(), 'noindex' ) && false === strpos( THRG_Bunker_NotFound::builtin_html(), 'wp-content/plugins' ) );
check( 'built-in page has no generator tag or theme output', false === stripos( THRG_Bunker_NotFound::builtin_html(), 'generator' ) && false === stripos( THRG_Bunker_NotFound::builtin_html(), 'wp-content/themes' ) );

file_put_contents( $file, '<!doctype html><title>Mine</title><h1>Not here</h1>' );
check( 'the owner\'s 404.html is used when present', false !== strpos( THRG_Bunker_NotFound::html(), 'Not here' ) );
THRG_Bunker_Options::update( array( 'notfound_source' => 'builtin' ) );
check( 'the built-in page can be forced', false !== strpos( THRG_Bunker_NotFound::html(), 'Page not found' ) && false === strpos( THRG_Bunker_NotFound::html(), 'Not here' ) );
THRG_Bunker_Options::update( array( 'notfound_source' => 'auto' ) );

$php = "<h1>Hi</h1><?php echo 'EXECUTED'; ?>";
file_put_contents( $file, $php );
check( 'PHP inside 404.html is sent as text and never executed', $php === THRG_Bunker_NotFound::html() );

file_put_contents( $file, str_repeat( 'x', THRG_Bunker_NotFound::MAX_BYTES + 1 ) );
clearstatcache();
check( 'an oversize 404.html is ignored', '' === THRG_Bunker_NotFound::find_file() && false !== strpos( THRG_Bunker_NotFound::html(), 'Page not found' ) );
file_put_contents( $file, '' );
clearstatcache();
check( 'an empty 404.html is ignored', '' === THRG_Bunker_NotFound::find_file() );
unlink( $file );
check( 'only a file named 404.html is eligible', array( ABSPATH . '404.html' ) === THRG_Bunker_NotFound::candidates() );

// send(): status 404, then the request ends.
$e = null;
ob_start();
try {
    THRG_Bunker_NotFound::send();
} catch ( THRG_Test_Exit $caught ) {
    $e = $caught;
}
$out = ob_get_clean();
check( 'send() answers 404 with the page and ends the request', $e && 'notfound' === $e->getMessage() && 404 === $GLOBALS['status'] && false !== strpos( $out, 'Page not found' ) );
