<?php
// Server rules: marker blocks, atomic writes, rollback.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();

$existing = "# BEGIN WordPress\nRewriteEngine On\n# END WordPress\n";
$lines    = THRG_Bunker_Server_Rules::lines( 'version_files' );
$once     = THRG_Bunker_Server_Rules::with_block( $existing, $lines );
check( 'block is added and existing content preserved', 0 === strpos( $once, $existing ) && false !== strpos( $once, '# BEGIN THRG Bunker' ) && false !== strpos( $once, 'Options -Indexes' ) );
$twice = THRG_Bunker_Server_Rules::with_block( $once, $lines );
check( 'block is added once, not duplicated', $once === $twice && 1 === substr_count( $twice, '# BEGIN THRG Bunker' ) );
$updated = THRG_Bunker_Server_Rules::with_block( $once, array( '# new rule' ) );
check( 'block is updated in place', 1 === substr_count( $updated, '# BEGIN THRG Bunker' ) && false !== strpos( $updated, '# new rule' ) && false === strpos( $updated, 'Options -Indexes' ) && 0 === strpos( $updated, $existing ) );
check( 'strip removes the block cleanly and keeps the rest', $existing === THRG_Bunker_Server_Rules::strip( $once ) );
check( 'strip on a file without the block changes nothing', $existing === THRG_Bunker_Server_Rules::strip( $existing ) );
check( 'block works on an empty file', 1 === substr_count( THRG_Bunker_Server_Rules::with_block( '', $lines ), '# BEGIN THRG Bunker' ) );
$crlf = "a\r\n# BEGIN THRG Bunker\r\nold\r\n# END THRG Bunker\r\nb\r\n";
check( 'a block in a CRLF file is replaced, not duplicated', 1 === substr_count( THRG_Bunker_Server_Rules::with_block( $crlf, $lines ), '# BEGIN THRG Bunker' ) && false === strpos( THRG_Bunker_Server_Rules::strip( $crlf ), 'BEGIN' ) );

check( 'item 10 blocks version files and listings', false !== strpos( implode( "\n", THRG_Bunker_Server_Rules::lines( 'version_files' ) ), 'wp-config-sample' ) );
check( 'item 11 blocks PHP in uploads', false !== strpos( implode( "\n", THRG_Bunker_Server_Rules::lines( 'uploads_php' ) ), 'phtml' ) );
$uploads_rules = implode( "\n", THRG_Bunker_Server_Rules::lines( 'uploads_php' ) );
check( 'rules use both Apache 2.4 and 2.2 syntax', false !== strpos( $uploads_rules, 'Require all denied' ) && false !== strpos( $uploads_rules, 'Deny from all' ) );
check( 'Nginx text is offered for both', '' !== THRG_Bunker_Server_Rules::nginx_text( 'version_files' ) && '' !== THRG_Bunker_Server_Rules::nginx_text( 'uploads_php' ) );
check( 'unknown id has no rules and no file', array() === THRG_Bunker_Server_Rules::lines( 'x' ) && '' === THRG_Bunker_Server_Rules::target( 'x' ) );

// Real files.
$root = ABSPATH . '.htaccess';
file_put_contents( $root, $existing );
chmod( $root, 0640 );
$GLOBALS['http'] = 200;
$result          = THRG_Bunker_Server_Rules::apply( 'version_files' );
check( 'apply writes the block', $result['ok'] && THRG_Bunker_Server_Rules::present( $root ) );
check( 'apply kept the file permissions', 0640 === ( fileperms( $root ) & 0777 ) );
check( 'apply left a backup of the previous file', $existing === file_get_contents( THRG_Bunker_Server_Rules::backup_path( $root ) ) );
check( 'no temp file left behind', ! file_exists( $root . '.thrg-bunker-tmp' ) );
check( 'existing content preserved after apply', 0 === strpos( file_get_contents( $root ), $existing ) );
check( 'remove takes the block out and keeps the rest', THRG_Bunker_Server_Rules::remove( 'version_files' ) && $existing === file_get_contents( $root ) );

// A server error restores the previous file.
$GLOBALS['http'] = 500;
$result          = THRG_Bunker_Server_Rules::apply( 'version_files' );
check( 'a 5xx answer rolls the file back and reports it', ! $result['ok'] && '' !== $result['message'] && $existing === file_get_contents( $root ) );
$GLOBALS['http'] = new WP_Error( 'http', 'no loopback' );
$result          = THRG_Bunker_Server_Rules::apply( 'version_files' );
check( 'an unreachable loopback keeps the rules (inconclusive, not a failure)', $result['ok'] && THRG_Bunker_Server_Rules::present( $root ) );
THRG_Bunker_Server_Rules::remove( 'version_files' );
$GLOBALS['http'] = 200;

// Uploads file is created when missing.
$uploads = ABSPATH . 'wp-content/uploads/.htaccess';
check( 'uploads file does not exist yet', ! file_exists( $uploads ) );
$result = THRG_Bunker_Server_Rules::apply( 'uploads_php' );
check( 'apply creates the uploads file', $result['ok'] && THRG_Bunker_Server_Rules::present( $uploads ) );

// sync(): confirmation, toggles and server errors.
THRG_Bunker_Server_Rules::remove_all();
$GLOBALS['opts'] = array();
$notes           = THRG_Bunker_Server_Rules::sync();
check( 'sync writes nothing before confirmation', array() === $notes && ! THRG_Bunker_Server_Rules::present( $root ) && ! THRG_Bunker_Server_Rules::present( $uploads ) );
THRG_Bunker_Options::update( array( 'confirmed' => 1 ) );
THRG_Bunker_Server_Rules::sync();
check( 'sync writes both rules after confirmation', THRG_Bunker_Server_Rules::present( $root ) && THRG_Bunker_Server_Rules::present( $uploads ) );
THRG_Bunker_Options::set( 'uploads_php', false );
THRG_Bunker_Server_Rules::sync();
check( 'unticking removes only that block', THRG_Bunker_Server_Rules::present( $root ) && ! THRG_Bunker_Server_Rules::present( $uploads ) );
THRG_Bunker_Options::set( 'uploads_php', true );
$GLOBALS['http'] = 503;
$notes           = THRG_Bunker_Server_Rules::sync();
check( 'a server error during sync leaves the option off and explains why', ! THRG_Bunker_Options::is_on( 'uploads_php' ) && 1 === count( $notes ) && ! THRG_Bunker_Server_Rules::present( $uploads ) );
$GLOBALS['http'] = 200;
THRG_Bunker_Server_Rules::remove_all();
check( 'remove_all clears both files', ! THRG_Bunker_Server_Rules::present( $root ) && ! THRG_Bunker_Server_Rules::present( $uploads ) );

// Not Apache: nothing is written.
$GLOBALS['is_apache'] = false;
$is_apache            = false;
THRG_Bunker_Options::update( array( 'server_rules' => array() ) );
THRG_Bunker_Options::set( 'uploads_php', true );
check( 'on Nginx, sync writes nothing', array() === THRG_Bunker_Server_Rules::sync() && ! THRG_Bunker_Server_Rules::present( $root ) );
