<?php
/**
 * Server rules for items 10 and 11, written between marker comments.
 *
 * Apache and LiteSpeed only. On Nginx the rules are shown to copy instead.
 * Writes are atomic, the previous file is backed up first, and a loopback
 * check restores the backup if the site starts returning server errors.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Marker-block writer for .htaccess files.
 */
final class THRG_Bunker_Server_Rules {

    const BEGIN = '# BEGIN THRG Bunker';
    const END   = '# END THRG Bunker';

    /**
     * Lockdown ids that use server rules.
     *
     * @return string[]
     */
    public static function ids() {
        return array( 'version_files', 'uploads_php' );
    }

    /**
     * The rule lines for a lockdown (Apache 2.4 and 2.2 syntax).
     *
     * @param string $id Lockdown id.
     * @return string[]
     */
    public static function lines( $id ) {
        $deny = array(
            '<IfModule mod_authz_core.c>',
            '    Require all denied',
            '</IfModule>',
            '<IfModule !mod_authz_core.c>',
            '    Order allow,deny',
            '    Deny from all',
            '</IfModule>',
        );
        if ( 'version_files' === $id ) {
            return array_merge(
                array( '<FilesMatch "^(readme\.html|license\.txt|wp-config-sample\.php|error_log|debug\.log|php_errorlog)$">' ),
                $deny,
                array(
                    '</FilesMatch>',
                    'Options -Indexes',
                )
            );
        }
        if ( 'uploads_php' === $id ) {
            return array_merge(
                array( '<FilesMatch "\.(?:php[0-9]?|phtml|phar|pht|phps)$">' ),
                $deny,
                array( '</FilesMatch>' )
            );
        }
        return array();
    }

    /**
     * The Nginx equivalent, shown to the owner to copy.
     *
     * @param string $id Lockdown id.
     * @return string
     */
    public static function nginx_text( $id ) {
        if ( 'version_files' === $id ) {
            return "location ~* ^/(readme\\.html|license\\.txt|wp-config-sample\\.php|error_log|debug\\.log|php_errorlog)\$ {\n    deny all;\n}\nautoindex off;";
        }
        if ( 'uploads_php' === $id ) {
            return "location ~* ^/wp-content/uploads/.*\\.(php[0-9]?|phtml|phar|pht|phps)\$ {\n    deny all;\n}";
        }
        return '';
    }

    /**
     * File that holds a lockdown's rules.
     *
     * @param string $id Lockdown id.
     * @return string Empty when unknown.
     */
    public static function target( $id ) {
        if ( 'version_files' === $id ) {
            return ABSPATH . '.htaccess';
        }
        if ( 'uploads_php' === $id ) {
            $uploads = wp_upload_dir( null, false );
            return empty( $uploads['basedir'] ) ? '' : rtrim( $uploads['basedir'], '/\\' ) . '/.htaccess';
        }
        return '';
    }

    /**
     * Whether this server reads .htaccess files.
     *
     * @return bool
     */
    public static function supported() {
        global $is_apache;
        return ! empty( $is_apache );
    }

    /**
     * The marker block for a list of lines.
     *
     * @param string[] $lines Rule lines.
     * @return string
     */
    public static function block( array $lines ) {
        return self::BEGIN . "\n" . implode( "\n", $lines ) . "\n" . self::END . "\n";
    }

    /**
     * Contents with any Bunker block removed. Everything else is untouched.
     *
     * @param string $contents File contents.
     * @return string
     */
    public static function strip( $contents ) {
        $pattern = '/(?:\r?\n)?' . preg_quote( self::BEGIN, '/' ) . '\r?\n.*?' . preg_quote( self::END, '/' ) . '\r?\n?/s';
        return (string) preg_replace( $pattern, '', (string) $contents );
    }

    /**
     * Contents with the Bunker block added once, or updated in place.
     *
     * @param string   $contents File contents.
     * @param string[] $lines    Rule lines.
     * @return string
     */
    public static function with_block( $contents, array $lines ) {
        $contents = (string) $contents;
        $block    = self::block( $lines );
        $pattern  = '/' . preg_quote( self::BEGIN, '/' ) . '\r?\n.*?' . preg_quote( self::END, '/' ) . '\r?\n?/s';
        if ( 1 === preg_match( $pattern, $contents ) ) {
            return (string) preg_replace_callback(
                $pattern,
                static function () use ( $block ) {
                    return $block;
                },
                $contents,
                1
            );
        }
        if ( '' !== $contents && "\n" !== substr( $contents, -1 ) ) {
            $contents .= "\n";
        }
        if ( '' !== $contents ) {
            $contents .= "\n";
        }
        return $contents . $block;
    }

    /**
     * Whether the file currently holds a Bunker block.
     *
     * @param string $path File path.
     * @return bool
     */
    public static function present( $path ) {
        if ( '' === $path || ! is_readable( $path ) ) {
            return false;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local server-rules file, not a remote URL.
        $contents = file_get_contents( $path );
        return false !== $contents && false !== strpos( $contents, self::BEGIN );
    }

    /**
     * Write a file atomically after backing up the old one.
     *
     * @param string $path     File path.
     * @param string $contents New contents.
     * @return bool
     */
    public static function write_atomic( $path, $contents ) {
        $dir = dirname( $path );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Checking a local file's permissions before an atomic write; WP_Filesystem cannot rename over a file atomically.
        if ( ! is_dir( $dir ) || ( file_exists( $path ) && ! is_writable( $path ) ) || ( ! file_exists( $path ) && ! is_writable( $dir ) ) ) {
            return false;
        }
        $perms = file_exists( $path ) ? ( fileperms( $path ) & 0777 ) : 0644;
        $temp  = $path . '.thrg-bunker-tmp';
        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_system_operations_chmod, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.unlink_unlink -- The atomic write (temp file then rename) is deliberate; WP_Filesystem cannot rename over an existing file atomically.
        if ( false === file_put_contents( $temp, $contents, LOCK_EX ) ) {
            return false;
        }
        chmod( $temp, $perms );
        if ( ! rename( $temp, $path ) ) {
            unlink( $temp );
            return false;
        }
        // phpcs:enable
        return true;
    }

    /**
     * Path of the backup kept next to a rules file.
     *
     * @param string $path File path.
     * @return string
     */
    public static function backup_path( $path ) {
        return $path . '.thrg-bunker-backup';
    }

    /**
     * Add or update one lockdown's rules, then check the site still loads.
     *
     * @param string $id Lockdown id.
     * @return array{ok:bool,message:string}
     */
    public static function apply( $id ) {
        $path  = self::target( $id );
        $lines = self::lines( $id );
        if ( '' === $path || ! $lines ) {
            return array(
                'ok'      => false,
                'message' => '',
            );
        }
        $old = '';
        if ( file_exists( $path ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local server-rules file, not a remote URL.
            $old = file_get_contents( $path );
            if ( false === $old ) {
                return array(
                    'ok'      => false,
                    'message' => __( 'Bunker could not read the server rules file, so nothing was changed.', 'thrg-bunker' ),
                );
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local backup of the file about to change.
            file_put_contents( self::backup_path( $path ), $old );
        }
        if ( ! self::write_atomic( $path, self::with_block( $old, $lines ) ) ) {
            return array(
                'ok'      => false,
                'message' => __( 'Bunker could not write the server rules file, so nothing was changed.', 'thrg-bunker' ),
            );
        }
        if ( 'fail' === self::check_site() ) {
            self::write_atomic( $path, $old );
            return array(
                'ok'      => false,
                'message' => __( 'Your server returned an error after the rules were added, so Bunker put your previous file back. This option has been left off.', 'thrg-bunker' ),
            );
        }
        return array(
            'ok'      => true,
            'message' => '',
        );
    }

    /**
     * Remove one lockdown's rules, keeping every other line of the file.
     *
     * @param string $id Lockdown id.
     * @return bool
     */
    public static function remove( $id ) {
        $path = self::target( $id );
        if ( '' === $path || ! is_readable( $path ) ) {
            return true;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local server-rules file, not a remote URL.
        $old = file_get_contents( $path );
        if ( false === $old || false === strpos( $old, self::BEGIN ) ) {
            return true;
        }
        return self::write_atomic( $path, self::strip( $old ) );
    }

    /**
     * Remove both rule blocks (deactivation, uninstall and safe mode).
     *
     * @return void
     */
    public static function remove_all() {
        foreach ( self::ids() as $id ) {
            self::remove( $id );
        }
    }

    /**
     * Request the home page and login page. 'fail' only on a 5xx answer; a failed request is 'unknown'.
     *
     * @return string ok, fail or unknown.
     */
    public static function check_site() {
        $result = 'ok';
        foreach ( array( home_url( '/' ), wp_login_url() ) as $url ) {
            $response = wp_remote_get(
                $url,
                array(
                    'timeout'     => 8,
                    'redirection' => 0,
                    'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core's own filter for loopback requests.
                )
            );
            if ( is_wp_error( $response ) ) {
                $result = 'unknown';
                continue;
            }
            if ( (int) wp_remote_retrieve_response_code( $response ) >= 500 ) {
                return 'fail';
            }
        }
        return $result;
    }

    /**
     * Bring the files in line with what should be enforced now.
     *
     * Called after confirmation, after a toggle, and on admin page loads. Safe
     * mode, an unconfirmed install and unticked boxes all remove the rules;
     * leaving safe mode restores them.
     *
     * @return string[] Messages for the owner (empty when nothing went wrong).
     */
    public static function sync() {
        $messages = array();
        if ( ! self::supported() ) {
            return $messages;
        }
        $state    = THRG_Bunker_Options::state();
        $recorded = is_array( $state['server_rules'] ) ? $state['server_rules'] : array();
        $changed  = false;
        foreach ( self::ids() as $id ) {
            $want = THRG_Bunker_Options::active( $id );
            $had  = ! empty( $recorded[ $id ] );
            if ( $want && ! $had ) {
                $result = self::apply( $id );
                if ( $result['ok'] ) {
                    $recorded[ $id ] = 1;
                } else {
                    THRG_Bunker_Options::set( $id, false );
                    if ( '' !== $result['message'] ) {
                        $messages[] = $result['message'];
                    }
                }
                $changed = true;
            } elseif ( ! $want && $had ) {
                self::remove( $id );
                unset( $recorded[ $id ] );
                $changed = true;
            }
        }
        if ( $changed ) {
            THRG_Bunker_Options::update( array( 'server_rules' => $recorded ) );
        }
        return $messages;
    }
}
