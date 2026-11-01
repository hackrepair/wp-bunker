<?php
/**
 * Bunker's own 404 page, used for everything the front-end lockdown refuses.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds and sends the 404 response.
 */
final class THRG_Bunker_NotFound {

    const MAX_BYTES = 262144;

    /**
     * The only files eligible as the owner's own 404 page.
     *
     * @return string[]
     */
    public static function candidates() {
        $paths = array( ABSPATH . '404.html' );
        if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
            $paths[] = rtrim( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ), '/\\' ) . '/404.html'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used only to build a fixed file name that must exist on disk.
        }
        return array_values( array_unique( $paths ) );
    }

    /**
     * The owner's 404.html, if one exists and is a sensible size.
     *
     * @return string Path, or an empty string.
     */
    public static function find_file() {
        foreach ( self::candidates() as $path ) {
            if ( is_file( $path ) && is_readable( $path ) && filesize( $path ) > 0 && filesize( $path ) <= self::MAX_BYTES ) {
                return $path;
            }
        }
        return '';
    }

    /**
     * The built-in page, with the heart mascot.
     *
     * @return string
     */
    public static function builtin_html() {
        $mascot = plugins_url( 'assets/brand/thrg-heart-128.png', THRG_BUNKER_FILE );
        $html   = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>' . esc_html__( 'Page not found', 'thrg-bunker' ) . '</title>';
        $html  .= '<style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f7f9;color:#18354b;display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center}main{padding:24px}img{width:96px;height:96px}h1{font-size:28px;margin:16px 0 8px}p{margin:0;color:#4f6577}</style></head><body><main>';
        $html  .= '<img src="' . esc_url( $mascot ) . '" alt="" width="96" height="96">';
        $html  .= '<h1>' . esc_html__( 'Page not found', 'thrg-bunker' ) . '</h1><p>' . esc_html__( 'The page you are looking for is not here.', 'thrg-bunker' ) . '</p></main></body></html>';
        return $html;
    }

    /**
     * The page body: the owner's file when allowed and present, otherwise the built-in page.
     *
     * The file is read as plain text and sent as-is. It is never executed.
     *
     * @return string
     */
    public static function html() {
        $state = THRG_Bunker_Options::state();
        if ( 'builtin' !== $state['notfound_source'] ) {
            $path = self::find_file();
            if ( '' !== $path ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the owner's local static 404 page, not a remote URL.
                $contents = file_get_contents( $path );
                if ( false !== $contents && '' !== $contents ) {
                    return $contents;
                }
            }
        }
        return self::builtin_html();
    }

    /**
     * Send the 404 and stop. No WordPress version, generator tag or theme output.
     *
     * @return void
     */
    public static function send() {
        status_header( 404 );
        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow' );
        header_remove( 'X-Powered-By' );
        // The body is the owner's own static file or Bunker's fixed page, deliberately not escaped.
        echo self::html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        /**
         * Fires after the 404 page has been sent, just before the request ends.
         */
        do_action( 'thrg_bunker_notfound_sent' );
        exit;
    }
}
