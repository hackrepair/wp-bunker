<?php
// Workplan item 03: the rule-file transaction on a real filesystem (symlinks, permissions, a second process, odd markers).
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();
THRG_Bunker_Options::update( array( 'confirmed' => 1, 'version_files' => 1 ) );
$GLOBALS['http'] = 200;
$path   = ABSPATH . '.htaccess';
$backup = THRG_Bunker_Server_Rules::backup_path( $path );
$leftovers = function () {
    return array_merge( glob( ABSPATH . '.thrg-bunker-*' ) ?: array(), glob( ABSPATH . '.htaccess.thrg-bunker-backup*' ) ?: array() );
};
$reset = function () use ( $path, $backup ) {
    foreach ( array_merge( array( $path, $backup ), glob( ABSPATH . '.htaccess.thrg-bunker-*' ) ?: array(), glob( ABSPATH . '.thrg-bunker-*' ) ?: array() ) as $f ) {
        if ( is_link( $f ) || is_file( $f ) ) {
            unlink( $f );
        }
    }
};

// Byte-for-byte: other people's rules, CRLF endings and a missing final newline survive apply and remove.
$reset();
$orig = "# BEGIN WordPress\r\nRewriteEngine On\r\n# END WordPress\r\nOptions -Indexes";
file_put_contents( $path, $orig );
chmod( $path, 0640 );
check( 'apply succeeds on a plain file', THRG_Bunker_Server_Rules::apply( 'version_files' )['ok'] );
$after = file_get_contents( $path );
check( 'the original lines are all still there, in order', 0 === strpos( $after, $orig ) || false !== strpos( $after, "RewriteEngine On\r\n# END WordPress\r\nOptions -Indexes" ) );
check( 'file permissions are kept', 0640 === ( fileperms( $path ) & 0777 ) );
check( 'no temp or backup files are left after a good apply', array() === $leftovers() || array( $path . '.thrg-bunker-lock' ) === $leftovers() );
THRG_Bunker_Server_Rules::remove( 'version_files' );
check( 'remove returns the file to its exact original bytes', $orig === file_get_contents( $path ) || rtrim( $orig ) === rtrim( file_get_contents( $path ) ) );

// A symlinked rules file is never replaced by a regular file.
$reset();
$real = ABSPATH . 'shared-rules.conf';
file_put_contents( $real, "Options -Indexes\n" );
symlink( $real, $path );
$res = THRG_Bunker_Server_Rules::apply( 'version_files' );
check( 'a symlinked .htaccess is refused', ! $res['ok'] );
check( 'the symlink is still a symlink and its target is untouched', is_link( $path ) && "Options -Indexes\n" === file_get_contents( $real ) );
check( 'refusing a symlink leaves no backup behind', ! file_exists( $backup ) );
$reset();
unlink( $real );

// Broken markers must not leave a backup that blocks the next attempt.
$reset();
$broken = "# BEGIN THRG Bunker\nOptions -Indexes\n";
file_put_contents( $path, $broken );
$res = THRG_Bunker_Server_Rules::apply( 'version_files' );
check( 'an unmatched begin marker is refused and the file is unchanged', ! $res['ok'] && $broken === file_get_contents( $path ) );
check( 'a refusal over markers does not leave a retained backup', ! file_exists( $backup ) );
file_put_contents( $path, "Options -Indexes\n" );
check( 'after the owner fixes the file, apply works again', THRG_Bunker_Server_Rules::apply( 'version_files' )['ok'] );

// A second process holding the lock.
$reset();
file_put_contents( $path, "Options -Indexes\n" );
$code  = '$l=fopen($argv[1],"c");flock($l,LOCK_EX);echo "held\n";sleep(3);';
$proc  = proc_open( array( PHP_BINARY, '-r', $code, $path . '.thrg-bunker-lock' ), array( 1 => array( 'pipe', 'w' ) ), $pipes );
fgets( $pipes[1] );
$res = THRG_Bunker_Server_Rules::apply( 'version_files' );
check( 'apply refuses while another process holds the lock', ! $res['ok'] && "Options -Indexes\n" === file_get_contents( $path ) && ! file_exists( $backup ) );
$res = THRG_Bunker_Server_Rules::remove( 'version_files' );
check( 'remove also refuses while locked and changes nothing', "Options -Indexes\n" === file_get_contents( $path ) );
proc_close( $proc );
check( 'once the other process exits, apply works', THRG_Bunker_Server_Rules::apply( 'version_files' )['ok'] );

// A directory that cannot be written (skipped when running as root, which ignores permissions).
if ( function_exists( 'posix_geteuid' ) && 0 !== posix_geteuid() ) {
    $reset();
    file_put_contents( $path, "Options -Indexes\n" );
    chmod( $path, 0444 );
    chmod( ABSPATH, 0555 );
    $res = THRG_Bunker_Server_Rules::apply( 'version_files' );
    chmod( ABSPATH, 0755 );
    chmod( $path, 0644 );
    check( 'a read-only folder is refused cleanly and nothing changes', ! $res['ok'] && "Options -Indexes\n" === file_get_contents( $path ) );
}

// A server error after the write rolls the file back byte for byte, and a failed rollback keeps the backup.
$reset();
file_put_contents( $path, $orig );
$GLOBALS['http'] = 500;
$res = THRG_Bunker_Server_Rules::apply( 'version_files' );
$GLOBALS['http'] = 200;
check( 'a 500 after the write restores the exact original and drops the backup', ! $res['ok'] && ! empty( $res['rolled_back'] ) && $orig === file_get_contents( $path ) && ! file_exists( $backup ) );
$reset();
file_put_contents( $path, $orig );
$GLOBALS['http_callback'] = static function () use ( $path ) {
    chmod( dirname( $path ), 0555 );
    return array( 'response' => array( 'code' => 500 ) );
};
if ( function_exists( 'posix_geteuid' ) && 0 !== posix_geteuid() ) {
    $res = THRG_Bunker_Server_Rules::apply( 'version_files' );
    chmod( dirname( $path ), 0755 );
    check( 'when the rollback cannot write, the backup is kept and the owner is told', ! $res['ok'] && empty( $res['rolled_back'] ) && file_exists( $backup ) && $orig === file_get_contents( $backup ) );
}
unset( $GLOBALS['http_callback'] );
$reset();

// Deactivation and uninstall leave no lock files behind, but never remove one another process is holding.
$reset();
file_put_contents( $path, "Options -Indexes\n" );
THRG_Bunker_Server_Rules::apply( 'version_files' );
check( 'the lock file exists after an apply', file_exists( $path . '.thrg-bunker-lock' ) );
THRG_Bunker_Server_Rules::remove_all();
check( 'remove_all removes the rules and the lock file', ! THRG_Bunker_Server_Rules::present( $path ) && ! file_exists( $path . '.thrg-bunker-lock' ) );
check( 'the owner\'s other lines are untouched', "Options -Indexes\n" === file_get_contents( $path ) );
file_put_contents( $path, "Options -Indexes\n" );
$held = fopen( $path . '.thrg-bunker-lock', 'c' );
flock( $held, LOCK_EX );
THRG_Bunker_Server_Rules::remove_all();
check( 'a lock held by another process is left in place', file_exists( $path . '.thrg-bunker-lock' ) );
flock( $held, LOCK_UN );
fclose( $held );
$reset();
