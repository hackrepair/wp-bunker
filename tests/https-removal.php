<?php
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();
$GLOBALS['opts'][THRG_Bunker_Options::OPTION] = array( 'confirmed' => 1 );
check( 'rules have no HTTPS redirect', false === strpos( implode( "\n", THRG_Bunker_Server_Rules::lines( 'version_files' ) ), 'R=308' ) );
$path = ABSPATH . '.htaccess';
file_put_contents( $path, "Options -Indexes\n" );
file_put_contents( THRG_Bunker_Server_Rules::backup_path( $path ), 'retained recovery' );
THRG_Bunker_Server_Rules::sync();
check( 'a pre-write failure preserves the configured protection', THRG_Bunker_Options::is_on( 'version_files' ) );
check( 'recovery material stays intact', 'retained recovery' === file_get_contents( THRG_Bunker_Server_Rules::backup_path( $path ) ) );
