<?php
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();
$path = ABSPATH . '.htaccess';
file_put_contents( $path, "Options -Indexes\n" );
$lock = fopen( $path . '.thrg-bunker-lock', 'c' );
flock( $lock, LOCK_EX );
check( 'concurrent apply refuses a held transaction lock', ! THRG_Bunker_Server_Rules::apply( 'version_files' )['ok'] );
flock( $lock, LOCK_UN );
fclose( $lock );
$backup = THRG_Bunker_Server_Rules::backup_path( $path );
file_put_contents( $backup, 'recovery snapshot' );
check( 'retry preserves an existing recovery snapshot', ! THRG_Bunker_Server_Rules::apply( 'version_files' )['ok'] && 'recovery snapshot' === file_get_contents( $backup ) );
unlink( $backup );
$GLOBALS['http_callback'] = static function ( $url ) {
    if ( false !== strpos( $url, '/uploads/' ) ) {
        return array( 'response' => array( 'code' => 500 ) );
    }
    return array( 'response' => array( 'code' => 200 ) );
};
$uploads = ABSPATH . 'wp-content/uploads/.htaccess';
check( 'uploads-only server error rolls back a newly created rules file', ! THRG_Bunker_Server_Rules::apply( 'uploads_php' )['ok'] && ! file_exists( $uploads ) );
$GLOBALS['http_callback'] = static function ( $url ) {
    $file = ABSPATH . 'wp-content/uploads/' . basename( $url );
    return array( 'response' => array( 'code' => 200 ), 'body' => is_file( $file ) ? file_get_contents( $file ) : '' );
};
check( 'uploads probe verifies exact static contents', THRG_Bunker_Server_Rules::apply( 'uploads_php' )['ok'] && ! file_exists( THRG_Bunker_Server_Rules::backup_path( $uploads ) ) );
check( 'probe artifacts are removed', array() === glob( ABSPATH . 'wp-content/uploads/bunker-check-*' ) );
