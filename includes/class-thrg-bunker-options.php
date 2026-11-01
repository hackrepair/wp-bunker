<?php
/**
 * The registry of lockdowns and the saved Bunker state.
 *
 * Every other part of the plugin (the settings page, the AJAX handler, the
 * enforcement hooks and the tests) reads from this one list.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lockdown registry and saved state.
 */
final class THRG_Bunker_Options {

    const OPTION = 'thrg_bunker_state';

    /**
     * All lockdowns in display order.
     *
     * Each entry has a title, the text shown while it is on, the text shown the
     * moment it is unticked, and its default. Item 13 ships off.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function registry() {
        return array(
            'front_end'     => array(
                'title'   => __( 'Hide the WordPress front end', 'thrg-bunker' ),
                'on'      => __( 'Visitors and bots who find your WordPress pages, posts, feeds or author archives get a plain "not found." Your static website is the only thing they see.', 'thrg-bunker' ),
                'off'     => __( 'WordPress pages are public again. Anyone can browse your posts, feeds, sitemaps and author archives, which reveal usernames and what\'s installed.', 'thrg-bunker' ),
                'default' => 1,
            ),
            'xmlrpc'        => array(
                'title'   => __( 'Block XML-RPC', 'thrg-bunker' ),
                'on'      => __( 'Closes xmlrpc.php, an old remote-publishing door that bots use to try thousands of passwords in a single request. Untick this if you use Jetpack or the WordPress mobile app.', 'thrg-bunker' ),
                'off'     => __( 'xmlrpc.php is open again. Bots can resume password guessing and pingback attacks through it.', 'thrg-bunker' ),
                'default' => 1,
            ),
            'rest'          => array(
                'title'   => __( 'Lock the REST API to logged-in users', 'thrg-bunker' ),
                'on'      => __( 'Anonymous visitors can\'t use the WordPress API, except the routes you allow below (like a chat). This stops username lookups and plugin fingerprinting. WordPress Site Health may report REST errors; that is expected.', 'thrg-bunker' ),
                'off'     => __( 'The REST API is public again. Anyone can list your usernames at /wp-json/wp/v2/users and see which features are installed.', 'thrg-bunker' ),
                'default' => 1,
            ),
            'usernames'     => array(
                'title'   => __( 'Hide usernames', 'thrg-bunker' ),
                'on'      => __( 'Hides usernames from author-ID scans and makes login errors identical for a wrong username or a wrong password.', 'thrg-bunker' ),
                'off'     => __( 'Usernames can be discovered again, and the login page now tells attackers when they\'ve guessed a real username.', 'thrg-bunker' ),
                'default' => 1,
            ),
            'comments'      => array(
                'title'   => __( 'Turn off comments, pingbacks and trackbacks', 'thrg-bunker' ),
                'on'      => __( 'Closes the comment and trackback endpoints, so bots can\'t fill your database with spam comments you\'ll never use.', 'thrg-bunker' ),
                'off'     => __( 'Comment and trackback endpoints are open again. Expect spam comments to start arriving in your moderation queue.', 'thrg-bunker' ),
                'default' => 1,
            ),
            'registration'  => array(
                'title'   => __( 'Turn off public registration', 'thrg-bunker' ),
                'on'      => __( 'No one can create an account on this site, even if "Anyone can register" gets switched on by mistake or by an attacker.', 'thrg-bunker' ),
                'off'     => __( 'Bunker no longer guards registration. If "Anyone can register" is turned on, strangers can create accounts.', 'thrg-bunker' ),
                'default' => 1,
            ),
            'app_passwords' => array(
                'title'   => __( 'Turn off application passwords', 'thrg-bunker' ),
                'on'      => __( 'Removes application passwords, a second way to log in that skips your normal login page and two-factor. Untick this if your static site pulls data with an application password.', 'thrg-bunker' ),
                'off'     => __( 'Application passwords are available again. Any user can create one and sign in through the API without your login page.', 'thrg-bunker' ),
                'default' => 1,
            ),
            'ip_lock'       => array(
                'title'   => __( 'Lock the login page and dashboard to your IP addresses', 'thrg-bunker' ),
                'on'      => __( 'Only the IP addresses listed below can reach the login page and dashboard. Your form keeps working for everyone.', 'thrg-bunker' ),
                'off'     => __( 'The login page and dashboard are open to the whole internet again, so password guessing can reach them.', 'thrg-bunker' ),
                'default' => 1,
            ),
            'file_editor'   => array(
                'title'   => __( 'Turn off the built-in code editors', 'thrg-bunker' ),
                'on'      => __( 'Removes the theme and plugin file editors, so a stolen admin login can\'t be used to plant code from the dashboard.', 'thrg-bunker' ),
                'off'     => __( 'The file editors are back. Anyone with an admin login can now edit PHP files from inside WordPress.', 'thrg-bunker' ),
                'default' => 1,
            ),
            'version_files' => array(
                'title'   => __( 'Hide version files, logs and folder listings', 'thrg-bunker' ),
                'on'      => __( 'Blocks readme.html, license.txt, wp-config-sample.php and error logs, and stops folders from listing their files.', 'thrg-bunker' ),
                'off'     => __( 'Version and log files are reachable again, and folders without an index file will list their contents.', 'thrg-bunker' ),
                'default' => 1,
            ),
            'uploads_php'   => array(
                'title'   => __( 'Block PHP in the uploads folder', 'thrg-bunker' ),
                'on'      => __( 'Stops PHP files in wp-content/uploads from running. That\'s where most uploaded backdoors try to hide. Untick this if a plugin stops working after you turn it on.', 'thrg-bunker' ),
                'off'     => __( 'PHP can run in the uploads folder again. A malicious file uploaded there would execute.', 'thrg-bunker' ),
                'default' => 1,
            ),
            'cron_watch'    => array(
                'title'   => __( 'Watch WordPress\'s scheduled tasks', 'thrg-bunker' ),
                'on'      => __( 'Your visitors load the static page, not WordPress, so WordPress\'s scheduled tasks, including automatic updates, may rarely run. Bunker warns you when they stall and shows the cPanel cron command that fixes it.', 'thrg-bunker' ),
                'off'     => __( 'Bunker has stopped watching scheduled tasks. If they stall, automatic updates can quietly stop and you won\'t be told.', 'thrg-bunker' ),
                'default' => 1,
            ),
            'known_actions' => array(
                'title'   => __( 'Only allow known form actions (logged-out visitors)', 'thrg-bunker' ),
                'on'      => __( 'Logged-out visitors can only run the form and chat actions you list below. Every other request to admin-post.php and admin-ajax.php from a stranger is refused.', 'thrg-bunker' ),
                'off'     => __( 'admin-post.php and admin-ajax.php accept any action from anyone again, including actions that installed plugins registered for logged-out visitors.', 'thrg-bunker' ),
                'default' => 0,
            ),
        );
    }

    /**
     * Default values for the saved state.
     *
     * @return array<string,mixed>
     */
    public static function defaults() {
        $toggles = array();
        foreach ( self::registry() as $id => $item ) {
            $toggles[ $id ] = (int) $item['default'];
        }
        return array(
            'confirmed'       => 0,
            'declined'        => 0,
            'toggles'         => $toggles,
            'rest_allow'      => '',
            'ip_list'         => '',
            'ip_saved'        => 0,
            'ip_proxy'        => 'remote_addr',
            'actions_allow'   => '',
            'notfound_source' => 'auto',
            'cron_email'      => 0,
            'delete_data'     => 0,
            'server_rules'    => array(),
        );
    }

    /**
     * The saved state merged with defaults. Toggle ids that are not in the registry are dropped.
     *
     * @return array<string,mixed>
     */
    public static function state() {
        $saved = get_option( self::OPTION, array() );
        $saved = is_array( $saved ) ? $saved : array();
        $state = array_merge( self::defaults(), $saved );

        $toggles = self::defaults()['toggles'];
        if ( isset( $saved['toggles'] ) && is_array( $saved['toggles'] ) ) {
            foreach ( $toggles as $id => $default ) {
                if ( isset( $saved['toggles'][ $id ] ) ) {
                    $toggles[ $id ] = empty( $saved['toggles'][ $id ] ) ? 0 : 1;
                }
            }
        }
        $state['toggles'] = $toggles;
        return $state;
    }

    /**
     * Save part of the state.
     *
     * @param array<string,mixed> $changes Keys to replace.
     * @return void
     */
    public static function update( array $changes ) {
        $state = self::state();
        foreach ( $changes as $key => $value ) {
            $state[ $key ] = $value;
        }
        update_option( self::OPTION, $state, false );
    }

    /**
     * Whether the lockdown is ticked.
     *
     * @param string $id Lockdown id.
     * @return bool
     */
    public static function is_on( $id ) {
        $state = self::state();
        return ! empty( $state['toggles'][ $id ] );
    }

    /**
     * Tick or untick a lockdown.
     *
     * @param string $id Lockdown id.
     * @param bool   $on New state.
     * @return void
     */
    public static function set( $id, $on ) {
        $state                   = self::state();
        $state['toggles'][ $id ] = $on ? 1 : 0;
        update_option( self::OPTION, $state, false );
    }

    /**
     * Whether safe mode is on (wp-config.php constant).
     *
     * @return bool
     */
    public static function safe_mode() {
        return defined( 'THRG_BUNKER_SAFE_MODE' ) && THRG_BUNKER_SAFE_MODE;
    }

    /**
     * Whether Bunker is allowed to enforce anything right now.
     *
     * Nothing is enforced until the administrator has confirmed once, never
     * in safe mode, and never on a multisite network (unsupported in 1.0).
     *
     * @return bool
     */
    public static function enforcing() {
        if ( self::safe_mode() || is_multisite() ) {
            return false;
        }
        $state = self::state();
        return ! empty( $state['confirmed'] );
    }

    /**
     * Whether a particular lockdown is actively enforced.
     *
     * The IP lock waits until the owner has saved the list once. Known form
     * actions need at least one allowed action.
     *
     * @param string $id Lockdown id.
     * @return bool
     */
    public static function active( $id ) {
        if ( ! self::enforcing() || ! self::is_on( $id ) ) {
            return false;
        }
        $state = self::state();
        if ( 'ip_lock' === $id ) {
            return ! empty( $state['ip_saved'] ) && '' !== trim( (string) $state['ip_list'] );
        }
        if ( 'known_actions' === $id ) {
            return '' !== trim( (string) $state['actions_allow'] );
        }
        return true;
    }

    /**
     * Whether a ticked lockdown is waiting for the owner (shown amber with its own message).
     *
     * @param string $id Lockdown id.
     * @return bool
     */
    public static function pending( $id ) {
        if ( 'ip_lock' !== $id || ! self::is_on( $id ) ) {
            return false;
        }
        $state = self::state();
        return empty( $state['ip_saved'] ) || '' === trim( (string) $state['ip_list'] );
    }

    /**
     * Turn a multi-line list into clean unique lines.
     *
     * @param string $text Raw text.
     * @return string[]
     */
    public static function lines( $text ) {
        $lines = preg_split( '/[\r\n,]+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
        $lines = array_map( 'trim', $lines );
        return array_values( array_unique( array_filter( $lines, 'strlen' ) ) );
    }
}
