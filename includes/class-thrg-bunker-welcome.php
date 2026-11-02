<?php
/**
 * The welcome email sent when Bunker is first activated.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * One friendly email, once, to the administrator who activated the plugin.
 */
class THRG_Bunker_Welcome {

    /**
     * Send the welcome email the first time Bunker is activated on a site.
     *
     * @return bool True when an email was handed to wp_mail().
     */
    public static function send_once() {
        $state = THRG_Bunker_Options::state();
        if ( ! empty( $state['welcome_sent'] ) ) {
            return false;
        }
        $to = self::recipient();
        if ( '' === $to ) {
            return false;
        }
        // Mark it first so a slow or failing mail server can never cause a second email.
        THRG_Bunker_Options::update( array( 'welcome_sent' => time() ) );
        return (bool) wp_mail( $to, self::subject(), self::body() );
    }

    /**
     * The administrator who activated the plugin, or the site's administrator address.
     *
     * @return string
     */
    public static function recipient() {
        $user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
        if ( $user && ! empty( $user->user_email ) && is_email( $user->user_email ) ) {
            return (string) $user->user_email;
        }
        $admin = (string) get_option( 'admin_email', '' );
        return is_email( $admin ) ? $admin : '';
    }

    /**
     * Subject line.
     *
     * @return string
     */
    public static function subject() {
        return THRG_Bunker_Mail::subject( __( 'Welcome', 'thrg-bunker' ) );
    }

    /**
     * Plain-text body.
     *
     * @return string
     */
    public static function body() {
        $site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
        $url  = THRG_Bunker_Settings::url();

        $body  = __( 'Hello, and welcome to Bunker!', 'thrg-bunker' ) . "\n\n";
        $body .= sprintf(
            /* translators: %s: site name. */
            __( 'This is a quick note to say that Bunker, The Hack Repair Guy\'s WordPress Utility Lockdown, was just installed and switched on for %s.', 'thrg-bunker' ),
            $site
        ) . "\n\n";

        $body .= THRG_Bunker_Mail::site_line();
        $body .= __( 'WHAT JUST HAPPENED', 'thrg-bunker' ) . "\n";
        $body .= __( 'Nothing has been locked yet, and nothing on your website has changed. Bunker waits for you to look things over first, so there are no surprises.', 'thrg-bunker' ) . "\n\n";

        $body .= __( 'WHAT BUNKER IS ABOUT', 'thrg-bunker' ) . "\n";
        $body .= __( 'Your website is a fast, plain static site that faces the public. WordPress is only there to run your forms and tools behind the scenes. Bunker keeps it that way: it hides WordPress from visitors and bots, shuts the doors attackers like to knock on, and lets only you reach the login page.', 'thrg-bunker' ) . "\n\n";

        $body .= __( 'YOUR NEXT STEP (about two minutes)', 'thrg-bunker' ) . "\n";
        $body .= __( 'Open the Bunker settings page, read through the lockdowns, and press "Lock it down" when you are happy. Every lockdown is explained in plain words, and you can switch any of them off again with one click.', 'thrg-bunker' ) . "\n";
        $body .= $url . "\n\n";

        $body .= __( 'IF YOU EVER GET LOCKED OUT', 'thrg-bunker' ) . "\n";
        $body .= __( 'Open your File Manager or log in via SFTP and rename the plugin folder (thrg-bunker). That switches Bunker off at once, and nothing is lost. You can also set up a hatch link in the settings, a spare key to the login page that needs your email approval before it lets anyone in.', 'thrg-bunker' ) . "\n\n";

        $body .= __( 'You will only hear from Bunker by email when it needs something from you, such as approving a hatch request, or if you ask it to warn you when WordPress\'s scheduled tasks stall. This welcome note is sent once and never again.', 'thrg-bunker' ) . "\n\n";

        $body .= __( 'Glad to have you on board,', 'thrg-bunker' ) . "\n";
        $body .= __( 'The Hack Repair Guy', 'thrg-bunker' ) . "\n";
        return $body;
    }
}
