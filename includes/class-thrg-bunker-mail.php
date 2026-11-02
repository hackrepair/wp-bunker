<?php
/**
 * Shared pieces of Bunker's emails, following the THRG plugin email standard.
 *
 * Subjects look like: [example.com] [Bunker] Purpose
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Subject and body helpers for every email Bunker sends.
 */
class THRG_Bunker_Mail {

    /**
     * The site's host, with its port when one is set, from the configured home URL.
     *
     * @return string
     */
    public static function domain() {
        $url  = (string) home_url();
        $host = (string) wp_parse_url( $url, PHP_URL_HOST );
        $port = wp_parse_url( $url, PHP_URL_PORT );
        if ( '' === $host ) {
            $host = (string) wp_parse_url( (string) get_option( 'siteurl', '' ), PHP_URL_HOST );
        }
        return $host . ( $port ? ':' . (int) $port : '' );
    }

    /**
     * Build a standard subject: [domain] [Bunker] Purpose.
     *
     * @param string $purpose Short, translated purpose label. No names, addresses or tokens.
     * @return string
     */
    public static function subject( $purpose ) {
        return '[' . self::domain() . '] [Bunker] ' . $purpose;
    }

    /**
     * A line naming the site, so the origin is clear when an email is forwarded.
     *
     * @return string
     */
    public static function site_line() {
        return __( 'Site: ', 'thrg-bunker' ) . home_url() . "\n\n";
    }
}
