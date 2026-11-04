<?php
// Companion tab contract: seven distinct companions, no network requests, isolation.
require __DIR__ . '/bootstrap.php';
function wp_enqueue_style( ...$args ) {}
thrg_load_plugin();

$fn   = 'thrg_bunker_companion_plugin_info';
$args = (object) array( 'slug' => 'thrg-bunker' );
$info = (object) array( 'version' => '9.9.9', 'sections' => array( 'description' => 'Keep description', 'changelog' => 'Keep changelog' ) );
$out  = $fn( $info, 'plugin_information', $args );
$html = $out->sections['other_THRG_plugins'] ?? '';

check( 'seven companions', 7 === substr_count( $html, '<h3>' ) );
check( 'Bunker does not list itself', false === strpos( $html, '<h3>Bunker</h3>' ) );
check( 'Stickies is listed', false !== strpos( $html, '<h3>Stickies</h3>' ) && false !== strpos( $html, 'https://github.com/hackrepair/thrg-stickies' ) );
check( 'Site Inspector is listed', false !== strpos( $html, '<h3>Site Inspector</h3>' ) );
check( 'original sections kept', 'Keep description' === $out->sections['description'] && 'Keep changelog' === $out->sections['changelog'] );
preg_match_all( '/<a href="([^"]+)"/', $html, $matches );
check( 'seven companions and one donation destination', 8 === count( array_unique( $matches[1] ) ) );
check( 'Plugin Archiver links to WordPress.org', false !== strpos( $html, 'https://wordpress.org/plugins/hackrepair-plugin-archiver/' ) );
check( 'every link opens safely in a new tab', 8 === substr_count( $html, 'target="_blank" rel="noopener noreferrer"' ) );
$again = $fn( $out, 'plugin_information', $args );
check( 'repeated filters do not duplicate content', $again->sections['other_THRG_plugins'] === $html );
$foreign = (object) array( 'sections' => array( 'description' => 'Other' ) );
check( 'another plugin is never modified', $fn( $foreign, 'plugin_information', (object) array( 'slug' => 'another-plugin' ) ) === $foreign && ! isset( $foreign->sections['other_THRG_plugins'] ) );
$wrong = (object) array( 'sections' => array( 'description' => 'Other action' ) );
check( 'other API actions are not modified', $fn( $wrong, 'query_plugins', $args ) === $wrong );
$invalid_ok = true;
foreach ( array( false, null, array(), (object) array( 'errors' => array() ) ) as $invalid ) {
    $invalid_ok = $invalid_ok && $fn( $invalid, 'plugin_information', $args ) === $invalid;
}
check( 'an invalid or error response is returned unchanged', $invalid_ok );
