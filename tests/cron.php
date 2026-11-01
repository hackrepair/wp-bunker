<?php
// Item 12: scheduled-task watcher.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();
THRG_Bunker_Options::update( array( 'confirmed' => 1 ) );

$now = 1800000000;
check( 'not stalled when nothing has been recorded yet', ! THRG_Bunker_Cron::stalled( $now ) );
update_option( THRG_Bunker_Cron::SINCE, $now - 3600 );
check( 'not stalled one hour after install', ! THRG_Bunker_Cron::stalled( $now ) );
check( 'stalled 25 hours after install with no run', THRG_Bunker_Cron::stalled( $now + 25 * 3600 - 3600 ) );
update_option( THRG_Bunker_Cron::LAST, $now - 2 * 3600 );
check( 'a recent run clears the stall', ! THRG_Bunker_Cron::stalled( $now ) );
update_option( THRG_Bunker_Cron::LAST, $now - 26 * 3600 );
update_option( THRG_Bunker_Cron::SINCE, $now - 100 * 3600 );
check( 'stalled when the last run is over 24 hours ago', THRG_Bunker_Cron::stalled( $now ) );

check( 'cron line is ready to paste', false !== strpos( THRG_Bunker_Cron::cron_line(), 'wp-cron.php?doing_wp_cron' ) && 0 === strpos( THRG_Bunker_Cron::cron_line(), '*/15 * * * *' ) );
check( 'wp-config line is exact', "define( 'DISABLE_WP_CRON', true );" === THRG_Bunker_Cron::config_line() );

// Email only when asked, and at most weekly.
$GLOBALS['mails'] = array();
THRG_Bunker_Cron::maybe_email();
check( 'no email unless the owner asked', array() === $GLOBALS['mails'] );
THRG_Bunker_Options::update( array( 'cron_email' => 1 ) );
update_option( THRG_Bunker_Cron::LAST, 1 );
update_option( THRG_Bunker_Cron::SINCE, 1 );
THRG_Bunker_Cron::maybe_email();
check( 'one email when stalled and asked for', 1 === count( $GLOBALS['mails'] ) && false !== strpos( $GLOBALS['mails'][0][2], 'DISABLE_WP_CRON' ) );
THRG_Bunker_Cron::maybe_email();
check( 'not again within the week', 1 === count( $GLOBALS['mails'] ) );

// tick() records real cron runs.
$GLOBALS['opts'] = array();
THRG_Bunker_Options::update( array( 'confirmed' => 1 ) );
define( 'DOING_CRON', true );
THRG_Bunker_Cron::tick();
check( 'a cron run is recorded', (int) get_option( THRG_Bunker_Cron::LAST, 0 ) > 0 );
THRG_Bunker_Options::set( 'cron_watch', false );
delete_option( THRG_Bunker_Cron::LAST );
THRG_Bunker_Cron::tick();
check( 'nothing is recorded when the watch is unticked', 0 === (int) get_option( THRG_Bunker_Cron::LAST, 0 ) );
