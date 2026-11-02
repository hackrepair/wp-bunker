<?php
/**
 * The hatch: a spare key for the login page, opened only by the owner's email approval.
 *
 * Flow: someone opens the secret hatch link, Bunker emails the administrator,
 * the administrator approves (or denies) on a confirmation page, and only the
 * browser that asked is let through to the login page for a few hours.
 *
 * It never touches admin-post.php, admin-ajax.php or wp-cron.php, and it only
 * does anything while the IP lock is enforcing.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hatch link, approval flow and access cookie.
 */
final class THRG_Bunker_Hatch {

    const REQUEST_OPTION = 'thrg_bunker_hatch_request';
    const LOG_OPTION     = 'thrg_bunker_hatch_log';
    const ACCESS_COOKIE  = 'thrg_bunker_hatch';
    const REQUEST_COOKIE = 'thrg_bunker_hatch_req';
    const REQUEST_TTL    = 900;
    const CLAIM_TTL      = 120;
    const MIN_GAP        = 60;
    const MAX_PER_HOUR   = 5;

    /**
     * Register the admin handler.
     *
     * @return void
     */
    public static function boot() {
        add_action( 'admin_post_thrg_bunker_hatch', array( __CLASS__, 'handle_admin' ) );
    }

    /**
     * Whether a hatch link exists and is switched on.
     *
     * @return bool
     */
    public static function enabled() {
        $state = THRG_Bunker_Options::state();
        return ! empty( $state['hatch_on'] ) && '' !== (string) $state['hatch_hash'];
    }

    /**
     * Session lengths the owner can choose, in hours.
     *
     * @return int[]
     */
    public static function hour_choices() {
        return array( 1, 4, 12, 24 );
    }

    /**
     * The address the approval email goes to.
     *
     * @return string
     */
    public static function recipient() {
        $state = THRG_Bunker_Options::state();
        if ( '' !== (string) $state['hatch_email'] && is_email( $state['hatch_email'] ) ) {
            return (string) $state['hatch_email'];
        }
        return (string) get_option( 'admin_email', '' );
    }

    /**
     * The full hatch link for a token.
     *
     * @param string $token Secret token.
     * @return string
     */
    public static function link( $token ) {
        return add_query_arg( array( 'thrg_hatch' => $token ), site_url( 'wp-login.php', 'login' ) );
    }

    /**
     * Take (and forget) the link that was just created, so it is shown once.
     *
     * @return string
     */
    public static function take_shown() {
        $key  = 'thrg_bunker_hatch_show_' . get_current_user_id();
        $link = get_transient( $key );
        delete_transient( $key );
        return is_string( $link ) ? $link : '';
    }

    /**
     * Handle hatch requests on the login page. Called by the IP gate before it decides.
     *
     * Ends the request (redirect or page) when it handles something; returns otherwise.
     *
     * @param bool   $allowed Whether the visitor's IP is on the allowed list.
     * @param string $script  The script WordPress is running.
     * @return void
     */
    public static function route( $allowed, $script ) {
        if ( 'wp-login.php' !== $script || ! self::enabled() ) {
            return;
        }
        $review = self::query( 'thrg_hatch_review' );
        if ( '' !== $review ) {
            self::review( $review );
        }
        if ( '' !== self::query( 'thrg_hatch_wait' ) ) {
            if ( $allowed ) {
                self::go_login();
            }
            self::wait();
        }
        $token = self::query( 'thrg_hatch' );
        if ( '' !== $token ) {
            if ( $allowed ) {
                self::go_login();
            }
            self::start( $token );
        }
    }

    /**
     * Whether this browser holds a valid hatch access cookie.
     *
     * @return bool
     */
    public static function has_access() {
        if ( ! self::enabled() || empty( $_COOKIE[ self::ACCESS_COOKIE ] ) ) {
            return false;
        }
        $parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE[ self::ACCESS_COOKIE ] ) ), 3 );
        if ( 3 !== count( $parts ) ) {
            return false;
        }
        $state   = THRG_Bunker_Options::state();
        $expires = (int) $parts[0];
        $version = (int) $parts[1];
        if ( $expires <= time() || $version !== (int) $state['hatch_version'] ) {
            return false;
        }
        return hash_equals( self::sign( $expires, $version ), (string) $parts[2] );
    }

    /**
     * Create a new hatch link (the old one stops working) and return the token.
     *
     * @return string
     */
    public static function create() {
        $state = THRG_Bunker_Options::state();
        $token = bin2hex( random_bytes( 24 ) );
        THRG_Bunker_Options::update(
            array(
                'hatch_on'         => 1,
                'hatch_hash'       => hash( 'sha256', $token ),
                'hatch_version'    => (int) $state['hatch_version'] + 1,
                'hatch_created'    => time(),
                'hatch_failed'     => 0,
                'hatch_failed_at'  => 0,
                'hatch_mail_error' => 0,
            )
        );
        delete_option( self::REQUEST_OPTION );
        return $token;
    }

    /**
     * Turn the hatch off. Every approved browser and every waiting request stops working.
     *
     * @return void
     */
    public static function disable() {
        $state = THRG_Bunker_Options::state();
        THRG_Bunker_Options::update(
            array(
                'hatch_on'         => 0,
                'hatch_hash'       => '',
                'hatch_version'    => (int) $state['hatch_version'] + 1,
                'hatch_failed'     => 0,
                'hatch_failed_at'  => 0,
                'hatch_mail_error' => 0,
            )
        );
        delete_option( self::REQUEST_OPTION );
    }

    /**
     * Make a new hatch link and keep it for the settings page to show once.
     *
     * @return void
     */
    public static function create_and_show() {
        $token = self::create();
        set_transient( 'thrg_bunker_hatch_show_' . get_current_user_id(), self::link( $token ), MINUTE_IN_SECONDS );
        THRG_Bunker_Settings::flash( 'success', __( 'Your hatch link is ready. Copy it now: it is shown only once.', 'thrg-bunker' ) );
    }

    /**
     * The On side of the hatch switch: make a link, unless the hatch is already on (then nothing changes).
     *
     * @return bool Whether a new link was made and is waiting to be shown.
     */
    public static function switch_on() {
        if ( self::enabled() ) {
            THRG_Bunker_Settings::flash( 'info', __( 'The hatch link is already on. To replace the link, use "Make a new link" in the Hatch link section.', 'thrg-bunker' ) );
            return false;
        }
        self::create_and_show();
        return true;
    }

    /**
     * Turn the hatch off and say so. Used by the switch and by the section's button.
     *
     * @return void
     */
    public static function turn_off() {
        if ( ! self::enabled() ) {
            THRG_Bunker_Settings::flash( 'info', __( 'The hatch link is already off.', 'thrg-bunker' ) );
            return;
        }
        self::disable();
        THRG_Bunker_Settings::flash( 'success', __( 'The hatch is off. The old link and every approved browser stopped working.', 'thrg-bunker' ) );
    }

    /**
     * Settings page buttons: create, disable or save the hatch options.
     *
     * @return void
     */
    public static function handle_admin() {
        check_admin_referer( 'thrg_bunker_hatch' );
        THRG_Bunker_Settings::require_admin();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() above verified the nonce.
        $do = isset( $_POST['hatch_do'] ) ? sanitize_key( wp_unslash( $_POST['hatch_do'] ) ) : '';

        if ( 'create' === $do ) {
            self::create_and_show();
        } elseif ( 'disable' === $do ) {
            self::turn_off();
        } else {
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- check_admin_referer() above verified the nonce.
            $email = isset( $_POST['hatch_email'] ) ? sanitize_email( wp_unslash( $_POST['hatch_email'] ) ) : '';
            $hours = isset( $_POST['hatch_hours'] ) ? (int) $_POST['hatch_hours'] : 4;
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            if ( '' !== $email && ! is_email( $email ) ) {
                $email = '';
            }
            THRG_Bunker_Options::update(
                array(
                    'hatch_email' => $email,
                    'hatch_hours' => in_array( $hours, self::hour_choices(), true ) ? $hours : 4,
                )
            );
            THRG_Bunker_Settings::flash( 'success', __( 'Hatch settings saved.', 'thrg-bunker' ) );
        }
        wp_safe_redirect( THRG_Bunker_Settings::url() . '#bunker-hatch' );
        exit;
    }

    /**
     * A hatch link was opened: check it, then email the administrator.
     *
     * @param string $token Token from the link.
     * @return void
     */
    private static function start( $token ) {
        if ( ! self::token_ok( $token ) ) {
            self::count_failure();
            THRG_Bunker_NotFound::send();
        }
        if ( self::has_access() ) {
            self::go_login();
        }
        if ( self::own_request() ) {
            self::go_wait();
        }

        // Hold the creation lock through mail delivery and release it at request shutdown.
        // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
        $lock = fopen( sys_get_temp_dir() . '/thrg-hatch-' . hash( 'sha256', ABSPATH ) . '.lock', 'c' );
        if ( false === $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
            if ( is_resource( $lock ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
                fclose( $lock );
            }
            self::page( __( 'Please try again', 'thrg-bunker' ), '<p>' . esc_html__( 'An approval request is being processed.', 'thrg-bunker' ) . '</p>' );
        }
        try {
            if ( self::stored_request() ) {
                self::page( __( 'Approval pending', 'thrg-bunker' ), '<p>' . esc_html__( 'An earlier browser request is still active. Complete it or wait for it to expire.', 'thrg-bunker' ) . '</p>' );
            }

            $log = self::recent_log();
            if ( $log && ( time() - max( $log ) ) < self::MIN_GAP ) {
                self::page( __( 'Please wait a minute', 'thrg-bunker' ), '<p>' . esc_html__( 'An approval email was just sent. Check your inbox, or try this link again in a minute.', 'thrg-bunker' ) . '</p>' );
            }
            if ( count( $log ) >= self::MAX_PER_HOUR ) {
                self::page( __( 'Too many requests', 'thrg-bunker' ), '<p>' . esc_html__( 'Too many approval emails were sent in the last hour. Try again later.', 'thrg-bunker' ) . '</p>' );
            }

            $state   = THRG_Bunker_Options::state();
            $browser = bin2hex( random_bytes( 24 ) );
            $secret  = bin2hex( random_bytes( 24 ) );
            $request = array(
                'browser' => hash( 'sha256', $browser ),
                'secret'  => hash( 'sha256', $secret ),
                'status'  => 'pending',
                'ip'      => self::visitor_ip(),
                'agent'   => self::agent(),
                'created' => time(),
                'expires' => time() + (int) apply_filters( 'thrg_bunker_hatch_request_ttl', self::REQUEST_TTL ),
            );
            update_option( self::REQUEST_OPTION, $request, false );

            if ( ! self::send_mail( $request, $secret ) ) {
                delete_option( self::REQUEST_OPTION );
                THRG_Bunker_Options::update( array( 'hatch_mail_error' => time() ) );
                self::page( __( 'The email could not be sent', 'thrg-bunker' ), '<p>' . esc_html__( 'This site could not send the approval email, so the hatch stays shut.', 'thrg-bunker' ) . '</p>' );
            }
            if ( ! empty( $state['hatch_mail_error'] ) ) {
                THRG_Bunker_Options::update( array( 'hatch_mail_error' => 0 ) );
            }
            $log[] = time();
            update_option( self::LOG_OPTION, $log, false );

            self::cookie( self::REQUEST_COOKIE, $browser, time() + self::REQUEST_TTL );
            self::go_wait();
        } finally {
            flock( $lock, LOCK_UN );
            // phpcs:ignore WordPress.WP.AlternativeFunctions -- Native filesystem operations provide locking and checked recovery outcomes.
            fclose( $lock );
        }
    }

    /**
     * The waiting page: lets the requesting browser in once the email is approved.
     *
     * @return void
     */
    private static function wait() {
        $request = self::own_request();
        if ( ! $request ) {
            self::page( __( 'This request has ended', 'thrg-bunker' ), '<p>' . esc_html__( 'It was denied or it expired. Open your hatch link again to start over.', 'thrg-bunker' ) . '</p>' );
        }
        if ( 'approved' === $request['status'] ) {
            delete_option( self::REQUEST_OPTION );
            self::cookie( self::REQUEST_COOKIE, '', time() - HOUR_IN_SECONDS );
            self::grant();
            self::go_login();
        }
        self::page(
            __( 'Check your email', 'thrg-bunker' ),
            '<p>' . esc_html__( 'An approval request was sent to the site administrator. Once it is approved, this page lets you through to the login page. You can leave it open.', 'thrg-bunker' ) . '</p>',
            5
        );
    }

    /**
     * The page behind the link in the email: details plus Approve, Deny and Disable.
     *
     * Opening the link changes nothing; only the buttons do, so mail scanners that open
     * links cannot approve anything.
     *
     * @param string $secret Secret from the email link.
     * @return void
     */
    private static function review( $secret ) {
        $request = self::request_by_secret( $secret );
        if ( ! $request ) {
            THRG_Bunker_NotFound::send();
        }
        $post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];
        if ( $post ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Logged-out page: the secret in the emailed link is the credential, and WordPress nonces need a session.
            $decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
            if ( 'approve' === $decision && 'pending' === $request['status'] ) {
                $request['status']  = 'approved';
                $request['expires'] = time() + self::CLAIM_TTL;
                update_option( self::REQUEST_OPTION, $request, false );
                self::page( __( 'Approved', 'thrg-bunker' ), '<p>' . esc_html__( 'Go back to the browser that asked. It will reach the login page in a few seconds. This browser was not given access.', 'thrg-bunker' ) . '</p>' );
            }
            if ( 'deny' === $decision ) {
                delete_option( self::REQUEST_OPTION );
                self::page( __( 'Denied', 'thrg-bunker' ), '<p>' . esc_html__( 'That request was refused and cannot be used.', 'thrg-bunker' ) . '</p>' );
            }
            if ( 'disable' === $decision ) {
                self::disable();
                self::page( __( 'The hatch is off', 'thrg-bunker' ), '<p>' . esc_html__( 'The hatch link no longer works. You can make a new one from Settings > Bunker when you are logged in.', 'thrg-bunker' ) . '</p>' );
            }
        }
        if ( 'approved' === $request['status'] ) {
            self::page( __( 'Already approved', 'thrg-bunker' ), '<p>' . esc_html__( 'This request was approved. The browser that asked will be let in shortly.', 'thrg-bunker' ) . '</p>' );
        }

        $body  = '<p>' . esc_html__( 'Someone opened your hatch link and asked to reach the login page.', 'thrg-bunker' ) . '</p>';
        $body .= '<p><strong>' . esc_html__( 'Time:', 'thrg-bunker' ) . '</strong> ' . esc_html( gmdate( 'Y-m-d H:i:s', (int) $request['created'] ) ) . ' UTC<br />';
        $body .= '<strong>' . esc_html__( 'IP address:', 'thrg-bunker' ) . '</strong> ' . esc_html( (string) $request['ip'] ) . '<br />';
        $body .= '<strong>' . esc_html__( 'Browser:', 'thrg-bunker' ) . '</strong> ' . esc_html( (string) $request['agent'] ) . '</p>';
        $body .= '<p>' . esc_html__( 'Approve only if this was you.', 'thrg-bunker' ) . '</p>';
        $body .= '<form method="post" action="' . esc_url( add_query_arg( array( 'thrg_hatch_review' => $secret ), site_url( 'wp-login.php', 'login' ) ) ) . '">';
        $body .= '<button type="submit" name="decision" value="approve" class="go">' . esc_html__( 'Approve', 'thrg-bunker' ) . '</button> ';
        $body .= '<button type="submit" name="decision" value="deny">' . esc_html__( 'Deny', 'thrg-bunker' ) . '</button> ';
        $body .= '<button type="submit" name="decision" value="disable">' . esc_html__( 'Deny and turn the hatch off', 'thrg-bunker' ) . '</button>';
        $body .= '</form>';
        self::page( __( 'Approve this hatch request?', 'thrg-bunker' ), $body );
    }

    /**
     * Whether a token matches the stored hash.
     *
     * @param string $token Token from the link.
     * @return bool
     */
    private static function token_ok( $token ) {
        $state = THRG_Bunker_Options::state();
        return '' !== (string) $state['hatch_hash'] && hash_equals( (string) $state['hatch_hash'], hash( 'sha256', $token ) );
    }

    /**
     * The pending or approved request that belongs to this browser, if it is still valid.
     *
     * @return array<string,mixed>|null
     */
    private static function own_request() {
        if ( empty( $_COOKIE[ self::REQUEST_COOKIE ] ) ) {
            return null;
        }
        $request = self::stored_request();
        $cookie  = self::clean_hex( sanitize_text_field( wp_unslash( $_COOKIE[ self::REQUEST_COOKIE ] ) ) );
        if ( ! $request || '' === $cookie || ! hash_equals( (string) $request['browser'], hash( 'sha256', $cookie ) ) ) {
            return null;
        }
        return $request;
    }

    /**
     * The request an emailed secret belongs to.
     *
     * @param string $secret Secret from the email link.
     * @return array<string,mixed>|null
     */
    private static function request_by_secret( $secret ) {
        $request = self::stored_request();
        if ( ! $request || ! hash_equals( (string) $request['secret'], hash( 'sha256', $secret ) ) ) {
            return null;
        }
        return $request;
    }

    /**
     * The stored request, dropped if it has expired.
     *
     * @return array<string,mixed>|null
     */
    private static function stored_request() {
        $request = get_option( self::REQUEST_OPTION, array() );
        if ( ! is_array( $request ) || empty( $request['secret'] ) || empty( $request['browser'] ) ) {
            return null;
        }
        if ( (int) $request['expires'] <= time() ) {
            delete_option( self::REQUEST_OPTION );
            return null;
        }
        return $request;
    }

    /**
     * Times of the approval emails sent in the last hour.
     *
     * @return int[]
     */
    private static function recent_log() {
        $log = get_option( self::LOG_OPTION, array() );
        $log = is_array( $log ) ? array_map( 'intval', $log ) : array();
        $now = time();
        return array_values(
            array_filter(
                $log,
                static function ( $at ) use ( $now ) {
                    return ( $now - $at ) < HOUR_IN_SECONDS;
                }
            )
        );
    }

    /**
     * Count a wrong-token attempt (at most one write every few seconds).
     *
     * @return void
     */
    private static function count_failure() {
        $state = THRG_Bunker_Options::state();
        if ( ( time() - (int) $state['hatch_failed_at'] ) < 5 ) {
            return;
        }
        THRG_Bunker_Options::update(
            array(
                'hatch_failed'    => (int) $state['hatch_failed'] + 1,
                'hatch_failed_at' => time(),
            )
        );
    }

    /**
     * Send the approval email.
     *
     * @param array<string,mixed> $request Pending request.
     * @param string              $secret  Secret for the review link.
     * @return bool
     */
    private static function send_mail( array $request, $secret ) {
        $site    = wp_specialchars_decode( (string) get_bloginfo( 'name' ) );
        $subject = THRG_Bunker_Mail::subject( __( 'Approve or deny a hatch request', 'thrg-bunker' ) );
        $review  = add_query_arg( array( 'thrg_hatch_review' => $secret ), site_url( 'wp-login.php', 'login' ) );
        $body    = sprintf(
            /* translators: %s: site name. */
            __( 'Someone opened the Bunker hatch link for %s and asked to reach the login page.', 'thrg-bunker' ),
            $site
        ) . "\n\n";
        $body   .= THRG_Bunker_Mail::site_line();
        $body   .= __( 'Time: ', 'thrg-bunker' ) . gmdate( 'Y-m-d H:i:s', (int) $request['created'] ) . " UTC\n";
        $body   .= __( 'IP address: ', 'thrg-bunker' ) . $request['ip'] . "\n";
        $body   .= __( 'Browser: ', 'thrg-bunker' ) . $request['agent'] . "\n\n";
        $body   .= __( 'To approve or deny it, open this page and press a button:', 'thrg-bunker' ) . "\n" . $review . "\n\n";
        $seconds = isset( $request['expires'], $request['created'] ) ? (int) $request['expires'] - (int) $request['created'] : self::REQUEST_TTL;
        $minutes = max( 1, (int) round( $seconds / 60 ) );
        $body   .= __( 'If this was not you, press Deny, or "Deny and turn the hatch off". Opening the page alone changes nothing.', 'thrg-bunker' ) . ' ';
        /* translators: %d: minutes until the request expires. */
        $body .= sprintf( _n( 'The request expires in %d minute.', 'The request expires in %d minutes.', $minutes, 'thrg-bunker' ), $minutes ) . "\n";
        return (bool) wp_mail( self::recipient(), $subject, $body );
    }

    /**
     * Hand this browser the access cookie.
     *
     * @return void
     */
    private static function grant() {
        $state   = THRG_Bunker_Options::state();
        $hours   = in_array( (int) $state['hatch_hours'], self::hour_choices(), true ) ? (int) $state['hatch_hours'] : 4;
        $expires = time() + $hours * HOUR_IN_SECONDS;
        $version = (int) $state['hatch_version'];
        self::cookie( self::ACCESS_COOKIE, $expires . '|' . $version . '|' . self::sign( $expires, $version ), $expires );
    }

    /**
     * Signature for the access cookie, tied to the expiry, the link version and the browser.
     *
     * @param int $expires Expiry time.
     * @param int $version Link version.
     * @return string
     */
    private static function sign( $expires, $version ) {
        return wp_hash( 'thrg_hatch|' . $expires . '|' . $version . '|' . hash( 'sha256', self::agent() ) );
    }

    /**
     * Set a cookie that scripts cannot read and that is not sent cross-site.
     *
     * @param string $name    Cookie name.
     * @param string $value   Value.
     * @param int    $expires Expiry time.
     * @return void
     */
    private static function cookie( $name, $value, $expires ) {
        do_action( 'thrg_bunker_hatch_cookie', $name, $value, $expires );
        setcookie(
            $name,
            $value,
            array(
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );
    }

    /**
     * Send the browser to the clean login URL.
     *
     * @return void
     */
    private static function go_login() {
        wp_safe_redirect( site_url( 'wp-login.php', 'login' ) );
        exit;
    }

    /**
     * Send the browser to the waiting page (no token in the address).
     *
     * @return void
     */
    private static function go_wait() {
        wp_safe_redirect( add_query_arg( array( 'thrg_hatch_wait' => '1' ), site_url( 'wp-login.php', 'login' ) ) );
        exit;
    }

    /**
     * A plain standalone page for the hatch screens.
     *
     * @param string $title   Heading.
     * @param string $body    Escaped HTML for the body.
     * @param int    $refresh Seconds before the page reloads itself; 0 for never.
     * @return void
     */
    private static function page( $title, $body, $refresh = 0 ) {
        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'Referrer-Policy: no-referrer' );
        header( 'X-Frame-Options: DENY' );
        header( 'X-Robots-Tag: noindex, nofollow' );
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">';
        if ( $refresh > 0 ) {
            echo '<meta http-equiv="refresh" content="' . (int) $refresh . '">';
        }
        echo '<title>' . esc_html( $title ) . '</title>';
        echo '<style>body{margin:0;background:#f0f0f1;color:#1d2327;font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}main{max-width:34rem;margin:12vh auto;padding:1.5rem 1.75rem;background:#fff;border-left:4px solid #2271b1;box-shadow:0 1px 3px rgba(0,0,0,.13)}h1{font-size:1.4rem;margin:0 0 1rem}button{font:inherit;padding:.5rem 1rem;margin:.25rem .25rem 0 0;border:1px solid #2271b1;background:#fff;color:#2271b1;border-radius:3px;cursor:pointer}button.go{background:#2271b1;color:#fff}</style></head><body><main><h1>' . esc_html( $title ) . '</h1>';
        echo wp_kses(
            $body,
            array(
                'p'      => array(),
                'strong' => array(),
                'br'     => array(),
                'form'   => array(
                    'method' => true,
                    'action' => true,
                ),
                'button' => array(
                    'type'  => true,
                    'name'  => true,
                    'value' => true,
                    'class' => true,
                ),
            )
        );
        echo '</main></body></html>';
        do_action( 'thrg_bunker_hatch_done', $title, $body );
        exit;
    }

    /**
     * Read a hex token from the query string.
     *
     * @param string $key Query argument.
     * @return string
     */
    private static function query( $key ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Logged-out visitors have no session for a nonce; the secret token in the link is the credential.
        $value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
        return 'thrg_hatch_wait' === $key ? ( '' === $value ? '' : '1' ) : self::clean_hex( $value );
    }

    /**
     * Keep only hexadecimal characters, and only up to a sane length.
     *
     * @param string $value Raw value.
     * @return string
     */
    private static function clean_hex( $value ) {
        return substr( (string) preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $value ) ), 0, 128 );
    }

    /**
     * The visitor's IP as Bunker reads it.
     *
     * @return string
     */
    private static function visitor_ip() {
        $state    = THRG_Bunker_Options::state();
        $detected = THRG_Bunker_Ip::detect( (string) $state['ip_proxy'] );
        return '' !== $detected['ip'] ? $detected['ip'] : __( 'unknown', 'thrg-bunker' );
    }

    /**
     * The visitor's browser string, shortened.
     *
     * @return string
     */
    private static function agent() {
        return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 200 ) : '';
    }
}
