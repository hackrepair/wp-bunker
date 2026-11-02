<?php
/**
 * Server rules for items 10, 11 and 14, written between marker comments.
 *
 * Apache and LiteSpeed only. On Nginx the rules are shown to copy instead.
 * Writes are atomic, the previous file is backed up first, and a loopback
 * check restores the backup if the site starts returning server errors.
 * The backup is deleted once the check passes, so no copy is left in the web folder.
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
        $lines = self::lockdown_lines( $id );
        if ( 'version_files' !== $id ) {
            return $lines;
        }
        $state    = THRG_Bunker_Options::state();
        $redirect = array();
        if ( THRG_Bunker_Options::enforcing() && ! empty( $state['https_redirect'] ) ) {
            $host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
            if ( is_string( $host ) && preg_match( '/^[a-z0-9.-]+$/iD', $host ) ) {
                $redirect = array( 'RewriteEngine On', 'RewriteCond %{HTTPS} !=on', 'RewriteCond %{SERVER_PORT} !^443$', 'RewriteRule ^ https://' . $host . '%{REQUEST_URI} [R=308,L,NE]' );
            }
        }
        return array_merge( $redirect, $lines );
    }

    private static function lockdown_lines( $id ) {
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
            if ( ! empty( THRG_Bunker_Options::state()['https_redirect'] ) && ! THRG_Bunker_Options::active( 'version_files' ) && ! THRG_Bunker_Options::active( 'stealth' ) ) {
                return array();
            }
            if ( THRG_Bunker_Options::active( 'stealth' ) ) {
                return self::stealth_lines();
            }
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
     * Rules for item 14: a plain 404 (not a 403) for files and folders that only WordPress has.
     *
     * Replaces the item 10 rules in the same block, because a 403 would answer first. Static
     * files WordPress's own screens need (such as /wp-includes/js/) are deliberately not touched.
     *
     * @return string[]
     */
    public static function stealth_lines() {
        return array(
            '<IfModule mod_alias.c>',
            '    RedirectMatch 404 (?i)/(readme\.html|license\.txt|wp-config-sample\.php|error_log|debug\.log|php_errorlog)$',
            '    RedirectMatch 404 (?i)/(wp-load|wp-blog-header|wp-settings|wp-trackback|wp-links-opml|wp-signup|wp-activate|wp-mail|wp-comments-post)\.php$',
            '    RedirectMatch 404 (?i)/wp-admin/install\.php$',
            '    RedirectMatch 404 (?i)/wp-(content|includes)/?$',
            '    RedirectMatch 404 (?i)/wp-content/(index\.php|(plugins|mu-plugins|themes|uploads|upgrade)/?(index\.php)?)$',
            '    RedirectMatch 404 (?i)/wp-content/(plugins|mu-plugins|themes)/[^/]+/?(index\.php)?$',
            '    RedirectMatch 404 (?i)/wp-content/(plugins|mu-plugins|themes)/.+/(readme|changelog|changes|license|licence|upgrade)[^/]*\.(txt|md|html)$',
            '</IfModule>',
            'Options -Indexes',
        );
    }

    /**
     * The Nginx equivalent, shown to the owner to copy.
     *
     * @param string $id Lockdown id.
     * @return string
     */
    public static function nginx_text( $id ) {
        if ( 'version_files' === $id && THRG_Bunker_Options::active( 'stealth' ) ) {
            return "location ~* /(readme\\.html|license\\.txt|wp-config-sample\\.php|error_log|debug\\.log|php_errorlog|wp-load\\.php|wp-blog-header\\.php|wp-settings\\.php|wp-trackback\\.php|wp-links-opml\\.php|wp-signup\\.php|wp-activate\\.php|wp-mail\\.php|wp-comments-post\\.php)\$ {\n    return 404;\n}\nlocation ~* /wp-admin/install\\.php\$ {\n    return 404;\n}\nlocation ~* /wp-content/(plugins|mu-plugins|themes)/.+/(readme|changelog|changes|license|licence|upgrade)[^/]*\\.(txt|md|html)\$ {\n    return 404;\n}\nlocation ~* ^/wp-(content|includes)/?\$ {\n    return 404;\n}\nautoindex off;";
        }
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
        if ( ! self::valid_markers( $contents ) ) {
            return $contents;
        }
        return (string) preg_replace( self::marker_pattern(), '', (string) $contents );
    }

    private static function marker_pattern() {
        return '/^' . preg_quote( self::BEGIN, '/' ) . '\r?\n.*?^' . preg_quote( self::END, '/' ) . '(?:\r?\n|$)/ms';
    }

    private static function valid_markers( $contents ) {
        $begins = substr_count( $contents, self::BEGIN );
        $ends   = substr_count( $contents, self::END );
        return ( 0 === $begins && 0 === $ends ) ||
            ( 1 === $begins && 1 === $ends && 1 === preg_match( self::marker_pattern(), $contents ) );
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
        $pattern  = self::marker_pattern();
        if ( ! self::valid_markers( $contents ) ) {
            return $contents;
        }
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
        $temp  = tempnam( $dir, '.thrg-bunker-' );
        if ( false === $temp ) {
            return false;
        }
        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_system_operations_chmod, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.unlink_unlink -- The atomic write (temp file then rename) is deliberate; WP_Filesystem cannot rename over an existing file atomically.
        // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
        if ( strlen( $contents ) !== file_put_contents( $temp, $contents, LOCK_EX ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
            unlink( $temp );
            return false;
        }
        chmod( $temp, $perms );
        if ( ! rename( $temp, $path ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
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
     * Delete the backup kept next to a rules file, if there is one.
     *
     * @param string $path File path.
     * @return void
     */
    private static function drop_backup( $path ) {
        $backup = self::backup_path( $path );
        if ( file_exists( $backup ) ) {
            unlink( $backup ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removing Bunker's own local backup file.
        }
    }

    /**
     * Add or update one lockdown's rules, then check the site still loads.
     *
     * @param string $id Lockdown id.
     * @return array{ok:bool,message:string}
     */
    public static function apply( $id ) {
        $path = self::target( $id );
        if ( '' === $path ) {
            return array(
				'ok'      => false,
				'message' => __( 'Bunker could not lock the server rules file.', 'thrg-bunker' ),
			);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
        $lock = fopen( $path . '.thrg-bunker-lock', 'c' );
        if ( false === $lock ) {
            return array(
				'ok'      => false,
				'message' => __( 'Bunker could not lock the server rules file.', 'thrg-bunker' ),
			);
        }
        if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
            fclose( $lock );
            return array(
				'ok'      => false,
				'message' => __( 'Bunker could not lock the server rules file.', 'thrg-bunker' ),
			);
        }
        try {
            return self::apply_locked( $id );
        } finally {
            flock( $lock, LOCK_UN );
            // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
            fclose( $lock );
        }
    }

    private static function apply_locked( $id ) {
        $path  = self::target( $id );
        $lines = self::lines( $id );
        if ( '' === $path || ! $lines ) {
            return array(
                'ok'      => false,
                'message' => '',
            );
        }
        $old     = '';
        $existed = file_exists( $path );
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
            if ( file_exists( self::backup_path( $path ) ) || ! self::write_atomic( self::backup_path( $path ), $old ) ) {
                return array(
					'ok'      => false,
					'message' => __( 'Bunker could not create a fresh backup. Preserve or restore the existing backup before retrying.', 'thrg-bunker' ),
				);
            }
        }
        if ( ! self::valid_markers( $old ) ) {
            return array(
				'ok'      => false,
				'message' => __( 'Bunker found incomplete or ambiguous rule markers. Review the file before retrying.', 'thrg-bunker' ),
			);
        }
        if ( ! self::write_atomic( $path, self::with_block( $old, $lines ) ) ) {
            return array(
                'ok'      => false,
                'message' => __( 'Bunker could not write the server rules file, so nothing was changed.', 'thrg-bunker' ),
            );
        }
        $check = self::check_site();
        if ( 'fail' !== $check && 'uploads_php' === $id ) {
            $uploads_check = self::check_uploads();
            $check         = 'ok' === $check ? $uploads_check : ( 'fail' === $uploads_check ? 'fail' : 'unknown' );
        }
        if ( 'fail' === $check ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
            $restored = $existed ? self::write_atomic( $path, $old ) : unlink( $path );
            if ( ! $restored ) {
                return array(
					'ok'      => false,
					/* translators: %s: server rules file path requiring manual recovery. */
                    'message' => sprintf( __( 'Rollback failed. Restore the retained backup for %s manually.', 'thrg-bunker' ), $path ),
				);
            }
            self::drop_backup( $path );
            return array(
                'ok'      => false,
                'message' => __( 'Your server returned an error after the rules were added, so Bunker put your previous file back. This option has been left off.', 'thrg-bunker' ),
            );
        }
        if ( 'ok' === $check ) {
            // The site answered, so the backup is no longer needed. An inconclusive check keeps it for a manual rollback.
            self::drop_backup( $path );
        }
        return array(
            'ok'      => true,
            'message' => 'unknown' === $check ? __( 'The server check was inconclusive. Keep the retained backup until the affected pages are verified.', 'thrg-bunker' ) : '',
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
        if ( '' === $path ) {
            return false;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
        $lock = fopen( $path . '.thrg-bunker-lock', 'c' );
        if ( false === $lock ) {
            return false;
        }
        if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
            fclose( $lock );
            return false;
        }
        try {
            return self::remove_locked( $id );
        } finally {
            flock( $lock, LOCK_UN );
            // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
            fclose( $lock );
        }
    }

    private static function remove_locked( $id ) {
        $path = self::target( $id );
        if ( '' === $path ) {
            return true;
        }
        if ( ! file_exists( $path ) ) {
            return true;
        }
        if ( ! is_readable( $path ) ) {
            return false;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local server-rules file, not a remote URL.
        $old = file_get_contents( $path );
        if ( false === $old || false === strpos( $old, self::BEGIN ) ) {
            return true;
        }
        if ( ! self::valid_markers( $old ) || ! self::write_atomic( $path, self::strip( $old ) ) ) {
            return false;
        }
        self::drop_backup( $path );
        return true;
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
     * Verify a unique static resource below the uploads rules.
     */
    private static function check_uploads() {
        $uploads = wp_upload_dir( null, false );
        if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
            return 'unknown';
        }
        $name = 'bunker-check-' . bin2hex( random_bytes( 16 ) ) . '.txt';
        $body = bin2hex( random_bytes( 24 ) );
        $path = rtrim( $uploads['basedir'], '/\\' ) . '/' . $name;
        // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
        if ( strlen( $body ) !== file_put_contents( $path, $body ) ) {
            if ( file_exists( $path ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
                unlink( $path );
            }
            return 'unknown';
        }
        try {
            $response = wp_remote_get(
                rtrim( $uploads['baseurl'], '/' ) . '/' . $name,
                array(
					'timeout'     => 8,
					'redirection' => 0,
                )
            );
            if ( is_wp_error( $response ) ) {
                return 'unknown';
            }
            $code = (int) wp_remote_retrieve_response_code( $response );
            if ( $code >= 500 ) {
                return 'fail';
            }
            return 200 === $code && wp_remote_retrieve_body( $response ) === $body ? 'ok' : 'unknown';
        } finally {
            // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
            unlink( $path );
        }
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
            if ( 'version_files' === $id && ! $want ) {
                // Item 14 shares this file and brings its own rules.
                $want = THRG_Bunker_Options::active( 'stealth' ) || ( THRG_Bunker_Options::enforcing() && ! empty( $state['https_redirect'] ) );
            }
            $sig  = $want ? md5( implode( "\n", self::lines( $id ) ) ) : '';
            $had  = ! empty( $recorded[ $id ] );
            $path = self::target( $id );
            $live = self::present( $path );
            // A block that was taken out by hand or by another tool is put back; a stray block nobody asked for is removed.
            if ( $want && ( ! $had || ! $live || (string) $recorded[ $id ] !== $sig ) ) {
                $result = self::apply( $id );
                if ( $result['ok'] ) {
                    if ( '' !== $result['message'] ) {
                        $messages[] = $result['message'];
                    }
                    $recorded[ $id ] = $sig;
                } else {
                    if ( 'version_files' === $id ) {
                        THRG_Bunker_Options::update( array( 'https_redirect' => 0 ) );
                    }
                    THRG_Bunker_Options::set( 'version_files' === $id && THRG_Bunker_Options::active( 'stealth' ) ? 'stealth' : $id, false );
                    if ( '' !== $result['message'] ) {
                        $messages[] = $result['message'];
                    }
                }
                $changed = true;
            } elseif ( ! $want && ( $had || $live ) ) {
                if ( ! self::remove( $id ) ) {
                    $messages[] = __( 'Bunker could not remove its server rules. Check file permissions and retained backups.', 'thrg-bunker' );
                    continue;
                }
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
