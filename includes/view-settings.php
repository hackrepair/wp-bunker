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

    <?php foreach ( $thrg_flash as $thrg_item ) : ?>
        <div class="notice notice-<?php echo esc_attr( in_array( $thrg_item['type'], array( 'success', 'warning', 'error' ), true ) ? $thrg_item['type'] : 'info' ); ?> inline"><p><?php echo esc_html( $thrg_item['text'] ); ?></p></div>
    <?php endforeach; ?>

    <?php if ( ! $thrg_confirmed && ! is_multisite() ) : ?>
        <section class="bunker-confirm" aria-labelledby="bunker-confirm-title">
            <h2 id="bunker-confirm-title"><?php esc_html_e( 'Bunker is for WordPress running behind a static website. Is that how this site works?', 'thrg-bunker' ); ?></h2>
            <p><?php esc_html_e( 'Every option below is already ticked, so choosing "Lock it down" turns them all on. Untick anything you want to keep open first. Nothing is enforced until you choose.', 'thrg-bunker' ); ?></p>
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

        <section class="bunker-block">
            <h2 class="bunker-section"><?php esc_html_e( 'Lockdowns', 'thrg-bunker' ); ?></h2>
            <div class="bunker-cards">
            <?php foreach ( $thrg_registry as $thrg_id => $thrg_item ) : ?>
                <?php
                $thrg_on      = THRG_Bunker_Options::is_on( $thrg_id );
                $thrg_pending = THRG_Bunker_Options::pending( $thrg_id );
                $thrg_class   = $thrg_on && ! $thrg_pending ? 'is-on' : 'is-off';
                $thrg_link    = $thrg_item['default'] ? __( 'Undo', 'thrg-bunker' ) : __( 'Turn on', 'thrg-bunker' );
                ?>
                <div class="bunker-card <?php echo esc_attr( $thrg_class ); ?>" data-id="<?php echo esc_attr( $thrg_id ); ?>" data-link="<?php echo esc_attr( $thrg_link ); ?>">
                    <label class="bunker-toggle">
                        <input type="checkbox" class="bunker-check" name="thrg_bunker_toggle[<?php echo esc_attr( $thrg_id ); ?>]" value="1" <?php checked( $thrg_on ); ?> />
                        <span class="bunker-title"><?php echo esc_html( $thrg_item['title'] ); ?></span>
                    </label>
                    <div class="bunker-text">
                        <p class="bunker-msg"><?php echo esc_html( THRG_Bunker_Settings::card_text( $thrg_id ) ); ?></p>
                    </div>
                    <a href="#" class="bunker-undo"<?php echo $thrg_on && ! $thrg_pending ? ' hidden' : ''; ?>><?php echo esc_html( $thrg_link ); ?></a>
                </div>

                <?php if ( 'rest' === $thrg_id ) : ?>
                    <div class="bunker-extra">
                        <label for="bunker-rest-allow"><strong><?php esc_html_e( 'REST routes visitors may still use', 'thrg-bunker' ); ?></strong></label>
                        <textarea id="bunker-rest-allow" name="rest_allow" rows="3" class="large-text code" placeholder="/mychat/v1"><?php echo esc_textarea( $thrg_state['rest_allow'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One route prefix per line, for example /mychat/v1. Everything under that prefix stays public. Leave empty if your static site does not use the REST API. Save with the button at the bottom.', 'thrg-bunker' ); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ( 'ip_lock' === $thrg_id ) : ?>
                    <div class="bunker-extra">
                        <p>
                            <?php
                            if ( '' !== $thrg_detected['ip'] ) {
                                /* translators: 1: IP address, 2: where it was read from. */
                                echo esc_html( sprintf( __( 'Your address right now: %1$s (read from %2$s).', 'thrg-bunker' ), $thrg_detected['ip'], $thrg_detected['source'] ) );
                            } else {
                                esc_html_e( 'Bunker could not read your address.', 'thrg-bunker' );
                            }
                            ?>
                        </p>
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
                        <p class="bunker-warning"><strong><?php esc_html_e( 'Warning:', 'thrg-bunker' ); ?></strong> <?php esc_html_e( 'If your home or office address changes, you will be locked out of the login page. To get back in, rename this plugin\'s folder (thrg-bunker) in cPanel File Manager. That removes the lock at once. Or add define( \'THRG_BUNKER_SAFE_MODE\', true ); to wp-config.php.', 'thrg-bunker' ); ?></p>
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
                        <p><?php esc_html_e( 'To keep them running without visitors, add this cron job in cPanel (Cron Jobs):', 'thrg-bunker' ); ?></p>
                        <pre class="bunker-code"><?php echo esc_html( THRG_Bunker_Cron::cron_line() ); ?></pre>
                        <p><?php esc_html_e( 'Then add this line to wp-config.php:', 'thrg-bunker' ); ?></p>
                        <pre class="bunker-code"><?php echo esc_html( THRG_Bunker_Cron::config_line() ); ?></pre>
                        <p><label><input type="checkbox" name="cron_email" value="1" <?php checked( ! empty( $thrg_state['cron_email'] ) ); ?> /> <?php esc_html_e( 'Also email the site administrator (at most once a week) when scheduled tasks stall', 'thrg-bunker' ); ?></label></p>
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

        <section class="bunker-block">
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

        <section class="bunker-block">
            <h2 class="bunker-section"><?php esc_html_e( 'Review checks', 'thrg-bunker' ); ?></h2>
            <div class="bunker-panel">
                <ul class="bunker-checks">
                <?php foreach ( THRG_Bunker_Checks::run() as $thrg_check ) : ?>
                    <li class="is-<?php echo esc_attr( $thrg_check['status'] ); ?>">
                        <strong><?php echo esc_html( $thrg_check['title'] ); ?></strong>
                        <span><?php echo esc_html( $thrg_check['detail'] ); ?></span>
                    </li>
                <?php endforeach; ?>
                </ul>
                <p class="description"><?php esc_html_e( 'These are read-only. Bunker never changes accounts, plugins or files for you. With the REST API and front end locked, WordPress Site Health may report REST or loopback errors; that is expected.', 'thrg-bunker' ); ?></p>
            </div>
        </section>

        <section class="bunker-block">
            <h2 class="bunker-section"><?php esc_html_e( 'Even better: give WordPress its own address', 'thrg-bunker' ); ?></h2>
            <div class="bunker-panel">
                <p><?php esc_html_e( 'Right now WordPress shares a folder with your website. If WordPress were ever broken into, the attacker could rewrite your homepage too. Moving WordPress to its own subdomain, such as forms.yoursite.com, keeps it apart. Putting it in a separate cPanel account is stronger still, because a break-in there can\'t touch your website\'s files at all. Your form keeps working: you only change its action address to the new location.', 'thrg-bunker' ); ?></p>
                <p>
                    <strong>
                    <?php
                    if ( THRG_Bunker_Checks::shares_folder_with_static_site() ) {
                        esc_html_e( 'This install shares a folder with an index.html.', 'thrg-bunker' );
                    } else {
                        esc_html_e( 'No index.html was found next to WordPress.', 'thrg-bunker' );
                    }
                    ?>
                    </strong>
                    <a href="<?php echo esc_url( $thrg_help_url ); ?>" title="<?php esc_attr_e( 'Get help moving WordPress to its own address', 'thrg-bunker' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ask for help with this', 'thrg-bunker' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'thrg-bunker' ); ?></span></a>
                </p>
            </div>
        </section>

        <section class="bunker-block">
            <h2 class="bunker-section"><?php esc_html_e( 'Uninstall', 'thrg-bunker' ); ?></h2>
            <div class="bunker-panel">
                <label><input type="checkbox" name="delete_data" value="1" <?php checked( ! empty( $thrg_state['delete_data'] ) ); ?> /> <?php esc_html_e( 'Delete all plugin data from the database when the plugin is deleted', 'thrg-bunker' ); ?></label>
                <p class="description"><?php esc_html_e( 'Removes Bunker\'s settings. Leave unchecked to keep them in case you reinstall. Deactivating or deleting the plugin always removes the rules Bunker added to .htaccess.', 'thrg-bunker' ); ?></p>
            </div>
        </section>

        <p class="submit"><button type="submit" name="save_section" value="general" class="button button-primary"><?php esc_html_e( 'Save changes', 'thrg-bunker' ); ?></button> <span class="description"><?php esc_html_e( 'The lockdown checkboxes save the moment you change them. This button saves the lists and other fields.', 'thrg-bunker' ); ?></span></p>
    </form>

    <footer class="bunker-footer">
        <img class="thrg-brand-heart" src="<?php echo esc_url( $thrg_mascot ); ?>" alt="" width="44" height="44" />
        <p>
            <?php esc_html_e( 'Need a deeper look or someone to assist with updates and security?', 'thrg-bunker' ); ?><br />
            <?php esc_html_e( 'Contact', 'thrg-bunker' ); ?> <a href="https://hackrepair.com/" title="<?php echo esc_attr( 'The Hack Repair Guy: WordPress security and hack repair' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( 'The Hack Repair Guy' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'thrg-bunker' ); ?></span></a>
        </p>
    </footer>

    <div id="bunker-live" class="screen-reader-text" aria-live="polite" role="status"></div>
</div>
