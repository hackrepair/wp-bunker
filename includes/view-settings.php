<?php
/**
 * The Settings > Bunker page.
 *
 * Included by THRG_Bunker_Settings::render(); not a standalone entry point.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

$thrg_state     = THRG_Bunker_Options::state();
$thrg_registry  = THRG_Bunker_Options::registry();
$thrg_mascot    = plugins_url( 'assets/brand/thrg-heart-128.png', THRG_BUNKER_FILE );
$thrg_detected  = THRG_Bunker_Ip::detect( (string) $thrg_state['ip_proxy'] );
$thrg_confirmed = ! empty( $thrg_state['confirmed'] );
$thrg_apache    = THRG_Bunker_Server_Rules::supported();
$thrg_flash     = THRG_Bunker_Settings::take_flash();
$thrg_file404   = THRG_Bunker_NotFound::find_file();
$thrg_cron_last = (int) get_option( THRG_Bunker_Cron::LAST, 0 );
$thrg_help_url  = (string) apply_filters( 'thrg_bunker_help_url', 'https://hackrepair.com/' );
$thrg_shared    = THRG_Bunker_Checks::shares_folder_with_static_site();
$thrg_bad_pill  = '<b class="bunker-pill bunker-pill-inline bunker-pill-bad">' . esc_html__( 'Bad', 'thrg-bunker' ) . '</b>';
$thrg_pill_tags = array(
    'b' => array( 'class' => array() ),
);

$thrg_rail_switch = false;
if ( THRG_Bunker_Options::safe_mode() ) {
    $thrg_rail_bunker = array( 'wait', __( 'Safe mode (paused)', 'thrg-bunker' ) );
} elseif ( is_multisite() ) {
    $thrg_rail_bunker = array( 'wait', __( 'Not supported here', 'thrg-bunker' ) );
} else {
    $thrg_rail_switch = true;
    $thrg_rail_is_on  = THRG_Bunker_Options::enforcing();
}
$thrg_rail_ip_on      = THRG_Bunker_Options::is_on( 'ip_lock' );
$thrg_rail_ip_pending = THRG_Bunker_Options::pending( 'ip_lock' );
$thrg_rail_hatch_on   = THRG_Bunker_Hatch::enabled();
$thrg_rail_links      = array(
    'bunker-lockdowns' => __( 'Lockdowns', 'thrg-bunker' ),
    'bunker-ip'        => __( 'IP list', 'thrg-bunker' ),
    'bunker-hatch'     => __( 'Hatch link', 'thrg-bunker' ),
    'bunker-404'       => __( '404 page', 'thrg-bunker' ),
    'bunker-checks'    => __( 'Review checks', 'thrg-bunker' ),
    'bunker-alerts'    => __( 'Email Alerts', 'thrg-bunker' ),
    'bunker-better'    => __( 'Same directory check', 'thrg-bunker' ),
    'bunker-uninstall' => __( 'Uninstall', 'thrg-bunker' ),
);
?>
<div class="wrap thrg-bunker">
    <div class="bunker-head">
        <img class="bunker-mascot thrg-brand-heart" src="<?php echo esc_url( $thrg_mascot ); ?>" alt="" width="56" height="56" />
        <div>
            <p class="bunker-eyebrow"><?php echo esc_html( "The Hack Repair Guy's" ); ?></p>
            <h1><?php esc_html_e( 'Bunker', 'thrg-bunker' ); ?> <span class="bunker-version"><?php echo esc_html( THRG_BUNKER_VERSION ); ?></span></h1>
            <p class="bunker-byline"><?php esc_html_e( 'WordPress Utility Lockdown. Your static website faces the public; WordPress stays underground.', 'thrg-bunker' ); ?></p>
        </div>
    </div>
    <hr class="wp-header-end" />

    <div class="bunker-layout">
    <aside class="bunker-rail" aria-label="<?php esc_attr_e( 'Bunker summary', 'thrg-bunker' ); ?>">
        <div class="bunker-rail-box">
            <p class="bunker-rail-title"><?php esc_html_e( 'Status', 'thrg-bunker' ); ?></p>
            <dl class="bunker-status">
                <dt><?php esc_html_e( 'Bunker', 'thrg-bunker' ); ?></dt>
                <?php if ( $thrg_rail_switch ) : ?>
                    <dd class="bunker-switch" role="group" aria-label="<?php esc_attr_e( 'Bunker on or off', 'thrg-bunker' ); ?>">
                        <button type="submit" form="bunker-master-form" name="state" value="on" class="bunker-seg is-on<?php echo $thrg_rail_is_on ? ' is-current' : ''; ?>" aria-pressed="<?php echo $thrg_rail_is_on ? 'true' : 'false'; ?>"><?php esc_html_e( 'On', 'thrg-bunker' ); ?></button><button type="submit" form="bunker-master-form" name="state" value="off" class="bunker-seg is-off<?php echo $thrg_rail_is_on ? '' : ' is-current'; ?>" aria-pressed="<?php echo $thrg_rail_is_on ? 'false' : 'true'; ?>"><?php esc_html_e( 'Off', 'thrg-bunker' ); ?></button>
                    </dd>
                <?php else : ?>
                    <dd class="is-<?php echo esc_attr( $thrg_rail_bunker[0] ); ?>"><?php echo esc_html( $thrg_rail_bunker[1] ); ?></dd>
                <?php endif; ?>
                <dt><?php esc_html_e( 'IP lock', 'thrg-bunker' ); ?></dt>
                <dd class="bunker-switch" role="group" aria-label="<?php esc_attr_e( 'IP lock on or off', 'thrg-bunker' ); ?>">
                    <button type="submit" form="bunker-master-form" name="ip_state" value="on" class="bunker-seg is-on<?php echo $thrg_rail_ip_on ? ' is-current' : ''; ?>" aria-pressed="<?php echo $thrg_rail_ip_on ? 'true' : 'false'; ?>"><?php esc_html_e( 'On', 'thrg-bunker' ); ?></button><button type="submit" form="bunker-master-form" name="ip_state" value="off" class="bunker-seg is-off<?php echo $thrg_rail_ip_on ? '' : ' is-current'; ?>" aria-pressed="<?php echo $thrg_rail_ip_on ? 'false' : 'true'; ?>"><?php esc_html_e( 'Off', 'thrg-bunker' ); ?></button>
                    <?php if ( $thrg_rail_ip_on && $thrg_rail_ip_pending ) : ?>
                        <span class="bunker-wait"><?php esc_html_e( 'waiting for your list', 'thrg-bunker' ); ?></span>
                    <?php endif; ?>
                </dd>
                <dt><?php esc_html_e( 'Hatch link', 'thrg-bunker' ); ?></dt>
                <dd class="bunker-switch" role="group" aria-label="<?php esc_attr_e( 'Hatch link on or off', 'thrg-bunker' ); ?>">
                    <button type="submit" form="bunker-master-form" name="hatch_state" value="on" class="bunker-seg is-on<?php echo $thrg_rail_hatch_on ? ' is-current' : ''; ?>" aria-pressed="<?php echo $thrg_rail_hatch_on ? 'true' : 'false'; ?>"><?php esc_html_e( 'On', 'thrg-bunker' ); ?></button><button type="submit" form="bunker-master-form" name="hatch_state" value="off" class="bunker-seg is-off<?php echo $thrg_rail_hatch_on ? '' : ' is-current'; ?>" aria-pressed="<?php echo $thrg_rail_hatch_on ? 'false' : 'true'; ?>"><?php esc_html_e( 'Off', 'thrg-bunker' ); ?></button>
                </dd>
            </dl>
        </div>
        <nav class="bunker-rail-box" aria-label="<?php esc_attr_e( 'On this page', 'thrg-bunker' ); ?>">
            <p class="bunker-rail-title"><?php esc_html_e( 'On this page', 'thrg-bunker' ); ?></p>
            <ul class="bunker-jump">
                <?php foreach ( $thrg_rail_links as $thrg_anchor => $thrg_label ) : ?>
                    <li><a href="#<?php echo esc_attr( $thrg_anchor ); ?>">
                    <?php
                    if ( 'bunker-better' === $thrg_anchor ) {
                        echo wp_kses( $thrg_bad_pill, $thrg_pill_tags );
                    }
                    echo esc_html( $thrg_label );
                    ?>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="bunker-rail-box">
            <button type="submit" form="bunker-form" name="save_section" value="general" class="button button-primary bunker-rail-save"><?php esc_html_e( 'Save changes', 'thrg-bunker' ); ?></button>
        </div>
        <?php if ( $thrg_shared ) : ?>
            <div class="bunker-rail-teaser">
                <?php echo wp_kses( $thrg_bad_pill, $thrg_pill_tags ); ?>
                <span><?php esc_html_e( 'Sharing your static web page in the same directory as WordPress is not recommended.', 'thrg-bunker' ); ?> <a href="#bunker-better"><?php esc_html_e( 'Learn more', 'thrg-bunker' ); ?></a></span>
            </div>
        <?php endif; ?>
    </aside>
    <div class="bunker-main<?php echo ! empty( $thrg_state['paused'] ) && $thrg_confirmed ? ' is-paused' : ''; ?>">

    <?php foreach ( $thrg_flash as $thrg_item ) : ?>
        <div class="notice notice-<?php echo esc_attr( in_array( $thrg_item['type'], array( 'success', 'warning', 'error' ), true ) ? $thrg_item['type'] : 'info' ); ?> inline"><p><?php echo esc_html( $thrg_item['text'] ); ?></p></div>
    <?php endforeach; ?>

    <?php if ( ! $thrg_confirmed && ! is_multisite() ) : ?>
        <section class="bunker-confirm" aria-labelledby="bunker-confirm-title">
            <h2 id="bunker-confirm-title"><?php esc_html_e( 'Bunker is for WordPress running behind a static website. Is that how this site works?', 'thrg-bunker' ); ?></h2>
            <p><?php esc_html_e( 'Every lockdown below that is ticked turns on when you choose "Lock it down". Untick anything you want to keep open first. Nothing is enforced until you choose.', 'thrg-bunker' ); ?></p>
            <?php if ( ! empty( $thrg_state['declined'] ) ) : ?>
                <p class="bunker-banner"><strong><?php esc_html_e( 'Bunker is installed but not protecting this site yet.', 'thrg-bunker' ); ?></strong></p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="thrg_bunker_confirm" />
                <?php wp_nonce_field( 'thrg_bunker_confirm' ); ?>
                <button type="submit" name="decision" value="lock" class="button button-primary"><?php esc_html_e( 'Lock it down', 'thrg-bunker' ); ?></button>
                <button type="submit" name="decision" value="not_yet" class="button"><?php esc_html_e( 'Not yet', 'thrg-bunker' ); ?></button>
            </form>
        </section>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bunker-form">
        <input type="hidden" name="action" value="thrg_bunker_save" />
        <?php wp_nonce_field( 'thrg_bunker_save' ); ?>

        <section class="bunker-block" id="bunker-lockdowns">
            <h2 class="bunker-section"><?php esc_html_e( 'Lockdowns', 'thrg-bunker' ); ?></h2>
            <div class="bunker-cards">
            <?php foreach ( $thrg_registry as $thrg_id => $thrg_item ) : ?>
                <?php
                $thrg_on      = THRG_Bunker_Options::is_on( $thrg_id );
                $thrg_pending = THRG_Bunker_Options::pending( $thrg_id );
                $thrg_class   = $thrg_on && ! $thrg_pending ? 'is-on' : 'is-off';
                ?>
                <div class="bunker-card <?php echo esc_attr( $thrg_class ); ?>" data-id="<?php echo esc_attr( $thrg_id ); ?>">
                    <label class="bunker-toggle">
                        <input type="checkbox" class="bunker-check" name="thrg_bunker_toggle[<?php echo esc_attr( $thrg_id ); ?>]" value="1" <?php checked( $thrg_on ); ?> />
                        <span class="bunker-title"><?php echo esc_html( $thrg_item['title'] ); ?></span>
                    </label>
                    <div class="bunker-text">
                        <p class="bunker-msg"><?php echo esc_html( THRG_Bunker_Settings::card_text( $thrg_id ) ); ?></p>
                    </div>
                </div>

                <?php if ( 'rest' === $thrg_id ) : ?>
                    <div class="bunker-extra">
                        <label for="bunker-rest-allow"><strong><?php esc_html_e( 'REST routes visitors may still use', 'thrg-bunker' ); ?></strong></label>
                        <textarea id="bunker-rest-allow" name="rest_allow" rows="3" class="large-text code" placeholder="/mychat/v1"><?php echo esc_textarea( $thrg_state['rest_allow'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One route prefix per line, for example /mychat/v1. Everything under that prefix stays public. Leave empty if your static site does not use the REST API. Save with the button at the bottom.', 'thrg-bunker' ); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ( 'ip_lock' === $thrg_id ) : ?>
                    <div class="bunker-extra" id="bunker-ip">
                        <?php if ( '' === $thrg_detected['ip'] ) : ?>
                            <p><?php esc_html_e( 'Bunker could not read your address.', 'thrg-bunker' ); ?></p>
                        <?php endif; ?>
                        <p>
                            <label for="bunker-ip-proxy"><strong><?php esc_html_e( 'Where do visitors come from?', 'thrg-bunker' ); ?></strong></label><br />
                            <select id="bunker-ip-proxy" name="ip_proxy">
                                <option value="remote_addr" <?php selected( $thrg_state['ip_proxy'], 'remote_addr' ); ?>><?php esc_html_e( 'Directly to my server', 'thrg-bunker' ); ?></option>
                                <option value="cloudflare" <?php selected( $thrg_state['ip_proxy'], 'cloudflare' ); ?>><?php esc_html_e( 'My site is behind Cloudflare', 'thrg-bunker' ); ?></option>
                            </select>
                        </p>
                        <label for="bunker-ip-list"><strong><?php esc_html_e( 'Allowed IP addresses', 'thrg-bunker' ); ?></strong></label>
                        <textarea id="bunker-ip-list" name="ip_list" rows="4" class="large-text code"><?php echo esc_textarea( $thrg_state['ip_list'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One address or range per line. IPv4, IPv6 and ranges such as 203.0.113.0/24 are accepted.', 'thrg-bunker' ); ?></p>
                        <p class="bunker-warning"><strong><?php esc_html_e( 'Warning:', 'thrg-bunker' ); ?></strong> <?php esc_html_e( 'If your home or office address changes, you will be locked out of the login page. To get back in, rename this plugin\'s folder (thrg-bunker) in File Manager. That removes the lock at once. Or add define( \'THRG_BUNKER_SAFE_MODE\', true ); to wp-config.php.', 'thrg-bunker' ); ?></p>
                        <p><label><input type="checkbox" name="ip_confirm_self" value="1" /> <?php esc_html_e( 'I understand my current address may not be in this list and I may lock myself out.', 'thrg-bunker' ); ?></label></p>
                        <p><button type="submit" name="save_section" value="ip" class="button button-primary"><?php esc_html_e( 'Save list', 'thrg-bunker' ); ?></button></p>
                    </div>
                <?php endif; ?>

                <?php if ( in_array( $thrg_id, array( 'version_files', 'uploads_php' ), true ) && ! $thrg_apache ) : ?>
                    <div class="bunker-extra">
                        <p><?php esc_html_e( 'This server does not look like Apache or LiteSpeed, so Bunker cannot write the rules for you. Add these to your Nginx configuration instead:', 'thrg-bunker' ); ?></p>
                        <pre class="bunker-code"><?php echo esc_html( THRG_Bunker_Server_Rules::nginx_text( $thrg_id ) ); ?></pre>
                    </div>
                <?php endif; ?>

                <?php if ( 'cron_watch' === $thrg_id ) : ?>
                    <div class="bunker-extra" id="bunker-cron">
                        <p>
                            <?php
                            if ( $thrg_cron_last ) {
                                /* translators: %s: how long ago WordPress last ran its scheduled tasks. */
                                echo esc_html( sprintf( __( 'WordPress last ran its scheduled tasks %s ago.', 'thrg-bunker' ), human_time_diff( $thrg_cron_last ) ) );
                            } else {
                                esc_html_e( 'Bunker has not seen WordPress run its scheduled tasks yet.', 'thrg-bunker' );
                            }
                            ?>
                        </p>
                        <p><?php esc_html_e( 'To keep them running without visitors, add this cron job in your hosting control panel (Cron Jobs):', 'thrg-bunker' ); ?></p>
                        <pre class="bunker-code"><?php echo esc_html( THRG_Bunker_Cron::cron_line() ); ?></pre>
                        <p><?php esc_html_e( 'Then add this line to wp-config.php:', 'thrg-bunker' ); ?></p>
                        <pre class="bunker-code"><?php echo esc_html( THRG_Bunker_Cron::config_line() ); ?></pre>
                        <p><label><input type="checkbox" name="cron_email" value="1" <?php checked( ! empty( $thrg_state['cron_email'] ) ); ?> /> <?php esc_html_e( 'Also email the site administrator (at most once a week) when scheduled tasks stall', 'thrg-bunker' ); ?></label></p>
                    </div>
                <?php endif; ?>

                <?php if ( 'stealth' === $thrg_id ) : ?>
                    <div class="bunker-extra">
                        <p><?php esc_html_e( 'This is obscurity, not protection: it hides WordPress from scanners and quick guesses, and it works best on top of the other lockdowns. What it does:', 'thrg-bunker' ); ?></p>
                        <ul class="bunker-list">
                            <li><?php esc_html_e( 'Login page and dashboard: visitors who are not allowed get the 404 instead of the "Access limited" page. This needs the IP lock above to be enforcing, and you will see the 404 too if you are locked out.', 'thrg-bunker' ); ?></li>
                            <li><?php esc_html_e( 'Core files such as wp-load.php, plugin and theme readmes, version files, and the wp-content, plugins and themes folders answer 404 for everyone. Needs Apache or LiteSpeed.', 'thrg-bunker' ); ?></li>
                            <li><?php esc_html_e( 'XML-RPC answers 404 instead of 403.', 'thrg-bunker' ); ?></li>
                            <li><?php esc_html_e( 'Not hidden: files WordPress\'s own screens load, such as /wp-includes/js/. Hiding them would break your login page. Your form\'s admin-post.php address also stays reachable, because the form posts there.', 'thrg-bunker' ); ?></li>
                        </ul>
                        <?php if ( ! $thrg_apache ) : ?>
                            <p><?php esc_html_e( 'This server does not look like Apache or LiteSpeed, so Bunker cannot write the file rules for you. Add these to your Nginx configuration instead:', 'thrg-bunker' ); ?></p>
                            <pre class="bunker-code"><?php echo esc_html( THRG_Bunker_Server_Rules::nginx_text( 'version_files' ) ); ?></pre>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ( 'known_actions' === $thrg_id ) : ?>
                    <?php $thrg_detected_actions = THRG_Bunker_Lockdown::detect_actions(); ?>
                    <div class="bunker-extra">
                        <label for="bunker-actions"><strong><?php esc_html_e( 'Allowed form and chat actions', 'thrg-bunker' ); ?></strong></label>
                        <textarea id="bunker-actions" name="actions_allow" rows="3" class="large-text code" placeholder="my_contact_form"><?php echo esc_textarea( $thrg_state['actions_allow'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One action name per line: the value of the hidden "action" field in your static form. Save with the button at the bottom, then tick the box above.', 'thrg-bunker' ); ?></p>
                        <p>
                            <strong><?php esc_html_e( 'Detected:', 'thrg-bunker' ); ?></strong>
                            <?php
                            if ( $thrg_detected_actions ) {
                                echo wp_kses( '<code>' . implode( '</code>, <code>', array_map( 'esc_html', $thrg_detected_actions ) ) . '</code>', array( 'code' => array() ) );
                            } else {
                                esc_html_e( 'No actions are registered for logged-out visitors.', 'thrg-bunker' );
                            }
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            </div>
        </section>

        <?php
        $thrg_hatch_on   = THRG_Bunker_Hatch::enabled();
        $thrg_hatch_link = THRG_Bunker_Hatch::take_shown();
        $thrg_hatch_note = '';
        if ( ! THRG_Bunker_Options::enforcing() ) {
            $thrg_hatch_note = __( 'Bunker is not enforcing anything right now (it is switched off, in safe mode or not set up yet), so the hatch does nothing yet.', 'thrg-bunker' );
        } elseif ( ! THRG_Bunker_Options::is_on( 'ip_lock' ) ) {
            $thrg_hatch_note = __( 'The IP lock is off, so there is nothing for the hatch to open. Switch the IP lock On to use it.', 'thrg-bunker' );
        } elseif ( ! THRG_Bunker_Options::active( 'ip_lock' ) ) {
            $thrg_hatch_note = __( 'The IP lock is waiting for you to save your IP list above. The hatch only matters once the lock is enforcing.', 'thrg-bunker' );
        }
        ?>
        <section class="bunker-block" id="bunker-hatch">
            <h2 class="bunker-section"><?php esc_html_e( 'Hatch link (spare key)', 'thrg-bunker' ); ?></h2>
            <div class="bunker-panel">
                <p><?php esc_html_e( 'If your IP address changes and you cannot reach the login page, the hatch link is a way back in. Opening it sends an approval email to the administrator. Nothing happens until someone presses Approve in that email, and then only the browser that asked is let through to the login page. You still need your password.', 'thrg-bunker' ); ?></p>
                <?php if ( '' !== $thrg_hatch_note ) : ?>
                    <p class="description"><?php echo esc_html( $thrg_hatch_note ); ?></p>
                <?php endif; ?>

                <?php if ( '' !== $thrg_hatch_link ) : ?>
                    <p><strong><?php esc_html_e( 'Your hatch link (shown once):', 'thrg-bunker' ); ?></strong></p>
                    <p><input type="text" id="bunker-hatch-link" class="large-text code" readonly="readonly" value="<?php echo esc_attr( $thrg_hatch_link ); ?>" />
                    <button type="button" class="button bunker-copy" data-target="bunker-hatch-link"><?php esc_html_e( 'Copy', 'thrg-bunker' ); ?></button></p>
                    <p class="description"><?php esc_html_e( 'Bookmark it in a password manager, not an email. Anyone with this link can ask for approval, but cannot get in without you.', 'thrg-bunker' ); ?></p>
                <?php elseif ( $thrg_hatch_on ) : ?>
                    <p><strong><?php esc_html_e( 'The hatch link is on.', 'thrg-bunker' ); ?></strong>
                        <?php
                        /* translators: %s: how long ago the link was made. */
                        echo esc_html( sprintf( __( 'Made %s ago. The link itself is not stored, so it cannot be shown again; make a new one if you lost it.', 'thrg-bunker' ), human_time_diff( (int) $thrg_state['hatch_created'] ) ) );
                        ?>
                    </p>
                <?php else : ?>
                    <p><strong><?php esc_html_e( 'The hatch is off.', 'thrg-bunker' ); ?></strong></p>
                <?php endif; ?>

                <?php if ( ! empty( $thrg_state['hatch_mail_error'] ) ) : ?>
                    <p class="bunker-warning"><strong><?php esc_html_e( 'Warning:', 'thrg-bunker' ); ?></strong> <?php esc_html_e( 'The last approval email could not be sent, so the hatch could not open. Check that this site can send email. The fallback is renaming this plugin\'s folder or setting THRG_BUNKER_SAFE_MODE in wp-config.php.', 'thrg-bunker' ); ?></p>
                <?php endif; ?>
                <?php if ( $thrg_hatch_on && ! empty( $thrg_state['hatch_failed'] ) ) : ?>
                    <p class="description">
                        <?php
                        /* translators: %d: number of wrong-link attempts. */
                        echo esc_html( sprintf( _n( '%d visit used a wrong hatch link.', '%d visits used a wrong hatch link.', (int) $thrg_state['hatch_failed'], 'thrg-bunker' ), (int) $thrg_state['hatch_failed'] ) );
                        ?>
                    </p>
                <?php endif; ?>
                <p>
                    <label for="bunker-hatch-email"><strong><?php esc_html_e( 'Send approval emails to', 'thrg-bunker' ); ?></strong></label><br />
                    <input form="bunker-hatch-form" type="email" id="bunker-hatch-email" name="hatch_email" class="regular-text" value="<?php echo esc_attr( $thrg_state['hatch_email'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email', '' ) ); ?>" />
                    <span class="description"><?php echo esc_html( sprintf( /* translators: %s: email address. */ __( 'Leave empty to use the site administrator address (%s).', 'thrg-bunker' ), (string) get_option( 'admin_email', '' ) ) ); ?></span>
                </p>
                <p>
                    <label for="bunker-hatch-hours"><strong><?php esc_html_e( 'How long an approved browser stays in', 'thrg-bunker' ); ?></strong></label><br />
                    <select form="bunker-hatch-form" id="bunker-hatch-hours" name="hatch_hours">
                        <?php foreach ( THRG_Bunker_Hatch::hour_choices() as $thrg_hours ) : ?>
                            <?php /* translators: %d: number of hours. */ ?>
                            <option value="<?php echo esc_attr( (string) $thrg_hours ); ?>" <?php selected( (int) $thrg_state['hatch_hours'], $thrg_hours ); ?>><?php echo esc_html( sprintf( _n( '%d hour', '%d hours', $thrg_hours, 'thrg-bunker' ), $thrg_hours ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <button form="bunker-hatch-form" type="submit" name="hatch_do" value="save" class="button"><?php esc_html_e( 'Save hatch settings', 'thrg-bunker' ); ?></button>
                    <button form="bunker-hatch-form" type="submit" name="hatch_do" value="create" class="button button-primary"><?php echo esc_html( $thrg_hatch_on ? __( 'Make a new link (the old one stops working)', 'thrg-bunker' ) : __( 'Create hatch link', 'thrg-bunker' ) ); ?></button>
                    <?php if ( $thrg_hatch_on ) : ?>
                        <button form="bunker-hatch-form" type="submit" name="hatch_do" value="disable" class="button"><?php esc_html_e( 'Turn the hatch off', 'thrg-bunker' ); ?></button>
                    <?php endif; ?>
                </p>
                <p class="description"><?php esc_html_e( 'If approval emails stop arriving, the hatch stays shut. Your fallbacks are renaming this plugin\'s folder (thrg-bunker) in File Manager, or adding define( \'THRG_BUNKER_SAFE_MODE\', true ); to wp-config.php.', 'thrg-bunker' ); ?></p>
            </div>
        </section>

        <section class="bunker-block" id="bunker-404">
            <h2 class="bunker-section"><?php esc_html_e( '404 page', 'thrg-bunker' ); ?></h2>
            <div class="bunker-panel">
                <p><?php esc_html_e( 'Everything Bunker refuses gets this page, so visitors never see WordPress\'s own 404 or theme.', 'thrg-bunker' ); ?></p>
                <p><label><input type="radio" name="notfound_source" value="auto" <?php checked( 'builtin' !== $thrg_state['notfound_source'] ); ?> /> <?php esc_html_e( 'Use my site\'s own 404.html if there is one (otherwise the built-in page)', 'thrg-bunker' ); ?></label></p>
                <p><label><input type="radio" name="notfound_source" value="builtin" <?php checked( 'builtin', $thrg_state['notfound_source'] ); ?> /> <?php esc_html_e( 'Always use the built-in page', 'thrg-bunker' ); ?></label></p>
                <p class="description">
                    <?php
                    if ( '' !== $thrg_file404 ) {
                        esc_html_e( 'A 404.html was found in your site folder.', 'thrg-bunker' );
                    } else {
                        esc_html_e( 'No 404.html was found in your site folder, so the built-in page is used.', 'thrg-bunker' );
                    }
                    echo ' ';
                    esc_html_e( 'Links inside your 404.html should start with / (for example /style.css) so they work at any address.', 'thrg-bunker' );
                    ?>
                </p>
            </div>
        </section>

        <section class="bunker-block" id="bunker-checks">
            <h2 class="bunker-section"><?php esc_html_e( 'Review checks', 'thrg-bunker' ); ?></h2>
            <div class="bunker-panel">
                <ul class="bunker-checks">
                <?php foreach ( THRG_Bunker_Checks::run() as $thrg_check ) : ?>
                    <li class="is-<?php echo esc_attr( $thrg_check['status'] ); ?>">
                        <b class="bunker-pill"><?php echo esc_html( THRG_Bunker_Checks::label( $thrg_check['status'] ) ); ?></b>
                        <strong><?php echo esc_html( $thrg_check['title'] ); ?></strong>
                        <span><?php echo esc_html( $thrg_check['detail'] ); ?></span>
                    </li>
                <?php endforeach; ?>
                </ul>
                <p class="description"><?php esc_html_e( 'These are read-only. Bunker never changes accounts, plugins or files for you. With the REST API and front end locked, WordPress Site Health may report REST or loopback errors; that is expected.', 'thrg-bunker' ); ?></p>
            </div>
        </section>

        <section class="bunker-block" id="bunker-alerts">
            <h2 class="bunker-section"><?php esc_html_e( 'Security alerts', 'thrg-bunker' ); ?></h2>
            <div class="bunker-panel">
                <p><?php echo esc_html( __( 'Send optional alerts to ', 'thrg-bunker' ) . get_option( 'admin_email' ) ); ?></p>
                <p><label><input type="checkbox" name="alert_admin" value="1" <?php checked( ! empty( $thrg_state['alert_admin'] ) ); ?> /> <?php esc_html_e( 'Email when an account gains the administrator role', 'thrg-bunker' ); ?></label></p>
                <p><label><input type="checkbox" name="alert_plugin" value="1" <?php checked( ! empty( $thrg_state['alert_plugin'] ) ); ?> /> <?php esc_html_e( 'Email when a new plugin is installed', 'thrg-bunker' ); ?></label></p>
                <p><label><input type="checkbox" name="alert_theme" value="1" <?php checked( ! empty( $thrg_state['alert_theme'] ) ); ?> /> <?php esc_html_e( 'Email when a new theme is installed', 'thrg-bunker' ); ?></label></p>
                <p><?php esc_html_e( 'Save changes to apply. Alerts work after explicit opt-in, including before lockdown confirmation. Pausing Bunker or enabling safe mode stops alerts. WordPress-managed installs are monitored; direct file copies and custom privileged roles are outside this coverage. Multisite is unsupported.', 'thrg-bunker' ); ?></p>
                <?php if ( ! empty( $thrg_state['alert_mail_error'] ) ) : ?>
                    <p role="status"><?php esc_html_e( 'A security alert could not be handed to mail. Check mail delivery. The original operation was allowed to finish. No automatic retry is queued.', 'thrg-bunker' ); ?></p>
                <?php endif; ?>
            </div>
        </section>

        <section class="bunker-block" id="bunker-better">
            <h2 class="bunker-section">
                <?php
                if ( $thrg_shared ) {
                    echo wp_kses( $thrg_bad_pill, $thrg_pill_tags ) . ' ';
                }
                esc_html_e( 'Heads up: Sharing your static web page in the same directory as WordPress is not recommended.', 'thrg-bunker' );
                ?>
            </h2>
            <div class="bunker-panel">
                <p>
                    <?php
                    if ( $thrg_shared ) {
                        esc_html_e( 'Right now WordPress shares a directory with your website. If WordPress were ever broken into, the attacker could rewrite your homepage too. Moving WordPress to its own subdomain, such as forms.yoursite.com, keeps it apart. Putting it in a separate hosting account is stronger still, because a break-in there can\'t touch your website\'s files at all. Your form keeps working: you only change its action address to the new location.', 'thrg-bunker' );
                    } else {
                        esc_html_e( 'WordPress is not sharing a directory with your website, which is the safer setup. If it ever does, a break-in could let an attacker rewrite your homepage too, so keep WordPress on its own subdomain, such as forms.yoursite.com, or better still in a separate hosting account. Your form keeps working: you only change its action address to the new location.', 'thrg-bunker' );
                    }
                    ?>
                </p>
                <p>
                    <strong>
                    <?php
                    if ( $thrg_shared ) {
                        esc_html_e( 'This install shares a directory with an index.html.', 'thrg-bunker' );
                    } else {
                        esc_html_e( 'No index.html was found next to WordPress.', 'thrg-bunker' );
                    }
                    ?>
                    </strong>
                    <a href="<?php echo esc_url( $thrg_help_url ); ?>" title="<?php esc_attr_e( 'Get help moving WordPress to its own address', 'thrg-bunker' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ask for help with this', 'thrg-bunker' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'thrg-bunker' ); ?></span></a>
                </p>
            </div>
        </section>



        <section class="bunker-block" id="bunker-uninstall">
            <h2 class="bunker-section"><?php esc_html_e( 'Uninstall', 'thrg-bunker' ); ?></h2>
            <div class="bunker-panel">
                <label><input type="checkbox" name="delete_data" value="1" <?php checked( ! empty( $thrg_state['delete_data'] ) ); ?> /> <?php esc_html_e( 'Delete all plugin data from the database when the plugin is deleted', 'thrg-bunker' ); ?></label>
                <p class="description">
                    <?php esc_html_e( 'Removes Bunker\'s settings. Leave unchecked to keep them in case you reinstall.', 'thrg-bunker' ); ?><br /><br />
                    <em><?php esc_html_e( 'Deactivating the plugin always switches everything off (the rules Bunker added to .htaccess are removed and the hatch link is killed) but keeps your settings; after you activate it again, switch Bunker On when you are ready.', 'thrg-bunker' ); ?></em>
                </p>
            </div>
        </section>

        <p class="submit"><button type="submit" name="save_section" value="general" class="button button-primary"><?php esc_html_e( 'Save changes', 'thrg-bunker' ); ?></button> <span class="description"><?php esc_html_e( 'The lockdown checkboxes save the moment you change them. This button saves everything else on the page, including your IP list.', 'thrg-bunker' ); ?></span></p>
    </form>

    <?php
    // Shown only when Bunker is holding a backup of a rules file; otherwise there is nothing for the owner to do.
    $thrg_retained = array();
    foreach ( THRG_Bunker_Server_Rules::ids() as $thrg_rule_id ) {
        $thrg_recovery = THRG_Bunker_Server_Rules::recovery_status( $thrg_rule_id );
        if ( $thrg_recovery['backup'] ) {
            $thrg_retained[ $thrg_rule_id ] = $thrg_recovery;
        }
    }
    ?>
    <?php if ( $thrg_retained ) : ?>
    <section class="bunker-block" id="bunker-recovery">
        <h2><?php esc_html_e( 'Server rules backup', 'thrg-bunker' ); ?></h2>
        <p><?php esc_html_e( 'Bunker kept a backup of a server rules file because it could not confirm that your site still worked after the last change. Your current rules are unchanged. Once you have checked your site, set the backup aside so Bunker can try again.', 'thrg-bunker' ); ?></p>
        <?php foreach ( $thrg_retained as $thrg_rule_id => $thrg_recovery ) : ?>
            <p><code><?php echo esc_html( $thrg_recovery['path'] ); ?></code>
            <?php if ( ! $thrg_recovery['writable'] ) : ?>
                <br /><?php esc_html_e( 'The folder is not writable, so Bunker cannot update this file. Check its permissions in your hosting File Manager.', 'thrg-bunker' ); ?>
            <?php endif; ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="thrg_bunker_archive_backup" />
                <input type="hidden" name="rule_id" value="<?php echo esc_attr( $thrg_rule_id ); ?>" />
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'thrg_bunker_archive_backup' ) ); ?>" />
                <button class="button" type="submit"><?php esc_html_e( 'Set the backup aside and allow a retry', 'thrg-bunker' ); ?></button>
            </form>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bunker-master-form">
        <input type="hidden" name="action" value="thrg_bunker_master" />
        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'thrg_bunker_master' ) ); ?>" />
    </form>

    <?php /* The hatch buttons sit inside the page above but post here, because forms cannot nest. */ ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bunker-hatch-form">
        <input type="hidden" name="action" value="thrg_bunker_hatch" />
        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'thrg_bunker_hatch' ) ); ?>" />
    </form>

    <footer class="bunker-footer">
        <img class="thrg-brand-heart" src="<?php echo esc_url( $thrg_mascot ); ?>" alt="" width="44" height="44" />
        <p>
            <?php esc_html_e( 'Need a deeper look or someone to assist with updates and security?', 'thrg-bunker' ); ?><br />
            <?php
            $thrg_contact = sprintf(
                /* translators: %s: link to The Hack Repair Guy's website. */
                esc_html__( 'Contact %s', 'thrg-bunker' ),
                '<a href="https://hackrepair.com/" title="' . esc_attr( 'The Hack Repair Guy: WordPress security and hack repair' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( 'The Hack Repair Guy' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'thrg-bunker' ) . '</span></a>'
            );
            echo wp_kses(
                $thrg_contact,
                array(
                    'a'    => array(
                        'href'   => array(),
                        'title'  => array(),
                        'target' => array(),
                        'rel'    => array(),
                    ),
                    'span' => array( 'class' => array() ),
                )
            );
            ?>
        </p>
    </footer>

    </div>
    </div>

    <div id="bunker-live" class="screen-reader-text" aria-live="polite" role="status"></div>
</div>
