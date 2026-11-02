<?php
// Uninstall: rules always removed; data only when the owner chose.
require __DIR__ . '/bootstrap.php';
define( 'WP_UNINSTALL_PLUGIN', true );
function delete_site_option( $k ) { $GLOBALS['site_deleted'][] = $k; return true; }
function delete_site_transient( $k ) { $GLOBALS['site_deleted'][] = 'transient:' . $k; return true; }

$root     = ABSPATH . '.htaccess';
$existing = "# BEGIN WordPress\nx\n# END WordPress\n";
$with     = $existing . "\n# BEGIN THRG Bunker\nrule\n# END THRG Bunker\n";

$all = array( 'thrg_bunker_state' => array( 'delete_data' => 0 ), 'thrg_bunker_cron_last' => 1, 'thrg_bunker_cron_since' => 1, 'thrg_bunker_hatch_request' => 1, 'thrg_bunker_hatch_log' => 1, 'other' => 1 );

// Data kept.
file_put_contents( $root, $with );
file_put_contents( ABSPATH . 'wp-content/uploads/.htaccess', "# BEGIN THRG Bunker\nrule\n# END THRG Bunker\n" );
$GLOBALS['opts']         = $all;
$GLOBALS['site_deleted'] = array();
include __DIR__ . '/../uninstall.php';
check( 'data kept when the option is off', $all === $GLOBALS['opts'] && array() === $GLOBALS['site_deleted'] );
check( 'rules removed from .htaccess even when data is kept', false === strpos( file_get_contents( $root ), 'THRG Bunker' ) && 0 === strpos( file_get_contents( $root ), "# BEGIN WordPress" ) );
check( 'rules removed from the uploads .htaccess too', false === strpos( file_get_contents( ABSPATH . 'wp-content/uploads/.htaccess' ), 'THRG Bunker' ) );

// Data deleted.
$GLOBALS['opts'] = $all;
$GLOBALS['opts']['thrg_bunker_state']['delete_data'] = 1;
include __DIR__ . '/../uninstall.php';
check( 'all plugin data deleted when the option is on; other options untouched', array( 'other' => 1 ) === $GLOBALS['opts'] );
check( 'update-check leftovers deleted when the option is on', array( 'external_updates-' . basename( dirname( __DIR__ ) ), 'transient:thrg_bunker_github_limit' ) === $GLOBALS['site_deleted'] );

// Never saved means keep.
$GLOBALS['opts']         = array( 'other' => 1 );
$GLOBALS['site_deleted'] = array();
include __DIR__ . '/../uninstall.php';
check( 'never-saved settings mean keep', array( 'other' => 1 ) === $GLOBALS['opts'] && array() === $GLOBALS['site_deleted'] );
