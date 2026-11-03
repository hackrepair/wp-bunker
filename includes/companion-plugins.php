<?php
/**
 * Companion plugins for the native View Details dialog.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * The other THRG plugins shown in the guide: title, description and link.
 *
 * @return array<int,array<string,string>>
 */
function thrg_bunker_companions() {
    return array(
        array(
            'name' => 'Site Inspector',
            'desc' => 'Check updates, admin accounts, uploads, exposed backups and more in a few seconds, and keep a history of your reports.',
            'url'  => 'https://github.com/hackrepair/thrg-site-inspector',
        ),
        array(
            'name' => 'Group Password Reset',
            'desc' => 'Reset passwords by role, choose who to exclude, and optionally send secure reset links.',
            'url'  => 'https://github.com/hackrepair/group-password-reset',
        ),
        array(
            'name' => 'Disable Other Plugin Notices',
            'desc' => 'Group plugin and theme notices into a collapsible panel while keeping WordPress core notices visible.',
            'url'  => 'https://github.com/hackrepair/disable-other-plugin-notices',
        ),
        array(
            'name' => 'Plugin Detective',
            'desc' => 'Find the plugin behind a conflict, with recovery tools for when your dashboard is unavailable.',
            'url'  => 'https://github.com/hackrepair/hackrepair-plugin-detective',
        ),
        array(
            'name' => 'Post Update Email Notifier',
            'desc' => 'Notify selected team members when published content changes, with configurable update emails.',
            'url'  => 'https://github.com/tvcnet/hackrepair-post-update-email-notifier',
        ),
        array(
            'name' => 'Plugin Archiver',
            'desc' => 'Move unused plugins out of the Plugins list without deleting them, and restore their files when needed.',
            'url'  => 'https://wordpress.org/plugins/hackrepair-plugin-archiver/',
        ),
    );
}

/**
 * Append the companion guide only to this plugin's information.
 *
 * @param mixed  $result Plugin information or the original API result.
 * @param string $action Requested API action.
 * @param object $args   API arguments.
 * @return mixed
 */
function thrg_bunker_companion_plugin_info( $result, $action, $args ) {
    if ( 'plugin_information' !== $action || ! is_object( $args ) || ! isset( $args->slug ) || 'thrg-bunker' !== $args->slug || ! is_object( $result ) || ! isset( $result->sections ) || ! is_array( $result->sections ) ) {
        return $result;
    }
    wp_enqueue_style( 'thrg-bunker-brand', plugins_url( 'assets/brand/thrg-brand.css', dirname( __DIR__ ) . '/index.php' ), array(), THRG_BUNKER_VERSION );
    wp_enqueue_style( 'thrg-bunker-brand-details', plugins_url( 'assets/brand/thrg-details.css', dirname( __DIR__ ) . '/index.php' ), array( 'thrg-bunker-brand' ), THRG_BUNKER_VERSION );
    wp_enqueue_style( 'thrg_bunker-plugin-details', plugins_url( 'assets/css/thrg_bunker-plugin-details.css', dirname( __DIR__ ) . '/index.php' ), array(), THRG_BUNKER_VERSION );
    $result->version = THRG_BUNKER_VERSION;

    $html = '<div class="thrg_bunker-toolkit"><p class="thrg_bunker-toolkit-intro">More tools from The Hack Repair Guy</p><p>Six focused ways to look after your WordPress site. Use only what you need.</p>';
    foreach ( thrg_bunker_companions() as $item ) {
        $html .= '<div class="thrg_bunker-toolkit-card"><h3>' . esc_html( $item['name'] ) . '</h3><p>' . esc_html( $item['desc'] ) . '</p><p><a href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener noreferrer">Explore ' . esc_html( $item['name'] ) . ' &rarr;</a></p></div>';
    }
    $html .= '<p>' . esc_html__( 'If you found these plugins helpful, please consider a donation.', 'thrg-bunker' ) . ' <a href="https://hackrepair.com/donations/buy-jim-a-coffee" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Buy Jim a cup of coffee', 'thrg-bunker' ) . '</a>.</p>';
    $html .= '<p class="thrg_bunker-toolkit-note">Each plugin works independently. Links open in a new tab.</p></div>';

    $result->sections['other_THRG_plugins'] = $html;
    return $result;
}
add_filter( 'plugins_api', 'thrg_bunker_companion_plugin_info', 100, 3 );
