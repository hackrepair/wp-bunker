<?php
// The plugin loads; versions and requirements agree everywhere; the details renderer is safe.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();

$header = file_get_contents( __DIR__ . '/../index.php', false, null, 0, 2000 );
$readme = file_get_contents( __DIR__ . '/../readme.txt' );
preg_match( '/Version:\s*(\S+)/', $header, $h_version );
preg_match( '/Stable tag:\s*(\S+)/', $readme, $r_version );
check( 'header, constant and readme agree on the version', $h_version[1] === THRG_BUNKER_VERSION && $h_version[1] === $r_version[1] );
check( 'the readme has a changelog entry for this version', false !== strpos( $readme, '= ' . THRG_BUNKER_VERSION . ' =' ) );
preg_match( '/Requires at least:\s*(\S+)/', $header, $h_wp );
preg_match( '/Requires PHP:\s*(\S+)/', $header, $h_php );
$meta = thrg_bunker_readme_meta( $readme );
check( 'readme compatibility fields parsed', isset( $meta['requires'], $meta['tested'], $meta['requires_php'] ) );
check( 'readme and header agree on requirements', $meta['requires'] === $h_wp[1] && $meta['requires_php'] === $h_php[1] );
check( 'PHP 7.4 or newer is required', '7.4' === $h_php[1] );
check( 'plugin slug is thrg-bunker', 'thrg-bunker' === basename( dirname( __DIR__ ) ) );
check( 'the plugin header names the right repository', false !== strpos( $header, 'https://github.com/hackrepair/wp-bunker' ) && false !== strpos( $header, 'Update URI' ) );
check( 'text domain matches the slug', false !== strpos( $header, 'Text Domain:       thrg-bunker' ) );

// No PHP 8-only functions.
$bad = array();
foreach ( glob( __DIR__ . '/../includes/*.php' ) as $file ) {
    $code = file_get_contents( $file );
    foreach ( array( 'str_starts_with', 'str_ends_with', 'str_contains', 'array_is_list', 'match (' ) as $needle ) {
        if ( false !== strpos( $code, $needle ) ) {
            $bad[] = basename( $file ) . ':' . $needle;
        }
    }
}
check( 'no PHP 8-only functions in the includes', array() === $bad );

// Links in the readme carry tooltips and cannot break out of the attribute.
$html = thrg_bunker_format_readme_inline( 'See [Help](https://hackrepair.com/ "Get help") and [plain](https://example.com/).' );
check( 'readme link tooltip becomes a title attribute', false !== strpos( $html, '<a href="https://hackrepair.com/" title="Get help" target="_blank"' ) );
check( 'readme link without a tooltip has no title', false !== strpos( $html, '<a href="https://example.com/" target="_blank"' ) );
$evil = thrg_bunker_format_readme_inline( '[x](https://e.com/ "a\" onmouseover=\"b")' );
check( 'a tooltip cannot break out of the attribute', 1 === preg_match( '/^<a href="https:\/\/e\.com\/" title="[^"]*" target="_blank" rel="noopener noreferrer">x<\/a>$/', $evil ) );
check( 'readme fields are ignored outside the header', array() === thrg_bunker_readme_meta( "== Description ==\nTested up to: 9.9\n" ) );

// Nothing is enforced on a fresh install.
$GLOBALS['hooks'] = array();
THRG_Bunker_Lockdown::boot();
check( 'a fresh install registers no enforcement hooks', empty( $GLOBALS['hooks'] ) );

// Deactivation removes rules and keeps settings.
THRG_Bunker_Options::update( array( 'confirmed' => 1, 'server_rules' => array( 'uploads_php' => 1 ) ) );
file_put_contents( ABSPATH . '.htaccess', "# BEGIN THRG Bunker\nrule\n# END THRG Bunker\n" );
thrg_bunker_deactivate();
check( 'deactivation removes the .htaccess rules', false === strpos( file_get_contents( ABSPATH . '.htaccess' ), 'THRG Bunker' ) );
check( 'deactivation keeps the settings', 1 === THRG_Bunker_Options::state()['confirmed'] && array() === THRG_Bunker_Options::state()['server_rules'] );

// Deactivation switches everything off, kills the hatch link and keeps the data.
THRG_Bunker_Options::update( array( 'confirmed' => 1, 'paused' => 0, 'ip_list' => '203.0.113.5', 'delete_data' => 0 ) );
$token = THRG_Bunker_Hatch::create();
check( 'the hatch link is on before deactivation', THRG_Bunker_Hatch::enabled() );
thrg_bunker_deactivate();
$after = THRG_Bunker_Options::state();
check( 'deactivation leaves Bunker switched off', 1 === $after['paused'] && ! THRG_Bunker_Options::enforcing() );
check( 'deactivation kills the hatch link', ! THRG_Bunker_Hatch::enabled() && '' === $after['hatch_hash'] );
check( 'deactivation never deletes the data', '203.0.113.5' === $after['ip_list'] && 1 === $after['confirmed'] );
check( 'and the data option is still there', false !== get_option( THRG_Bunker_Options::OPTION, false ) );
