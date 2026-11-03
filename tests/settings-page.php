<?php
// The settings page renders, shows each warning only when it applies, and the CSS has no dead rules.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();

// Stubs for the template functions the view uses (the shared bootstrap keeps to the enforcement code).
function esc_attr_e( $t ) { echo htmlspecialchars( $t, ENT_QUOTES ); }
function esc_html_e( $t ) { echo htmlspecialchars( $t, ENT_QUOTES ); }
function esc_attr__( $t ) { return htmlspecialchars( $t, ENT_QUOTES ); }
function selected( $a, $b = true ) { echo (string) $a === (string) $b ? ' selected="selected"' : ''; }
function checked( $a, $b = true ) { echo (string) $a === (string) $b ? ' checked="checked"' : ''; }
function wp_nonce_field() { echo '<input type="hidden" name="_wpnonce" value="n" />'; }
function wp_create_nonce() { return 'n'; }
function get_user_by() { return false; }
function get_plugins() { return array(); }
function is_plugin_active() { return false; }
function is_plugin_active_for_network() { return false; }
function wp_get_themes() { return array(); }
function get_stylesheet() { return 'theme'; }
function get_template() { return 'theme'; }

function render_page() {
    ob_start();
    THRG_Bunker_Settings::render();
    return (string) ob_get_clean();
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
$index = ABSPATH . 'index.html';

// A fresh install that shares a directory with the static site.
file_put_contents( $index, '<html></html>' );
$html = render_page();
check( 'the page renders', false !== strpos( $html, 'class="wrap thrg-bunker"' ) && false !== strpos( $html, 'Lockdowns' ) );
check( 'the address line was removed', false === strpos( $html, 'Your address right now' ) && false === strpos( $html, 'bunker-myip' ) );
check( 'the first-run text no longer claims every option is ticked', false !== strpos( $html, 'Every lockdown below that is ticked' ) && false === strpos( $html, 'Every option below' ) );
check( 'the first-run button is Lock it down', false !== strpos( $html, '>Lock it down<' ) );
check( 'when WordPress shares a directory the card and heading show the red Bad word', 3 === substr_count( $html, 'bunker-pill-bad' ) && false !== strpos( $html, 'bunker-rail-teaser' ) );
check( 'the warning uses the queued wording', false !== strpos( $html, 'Sharing your static web page in the same directory as WordPress is not recommended.' ) && false !== strpos( $html, 'Same directory' ) );
check( 'the old wording is gone', false === strpos( $html, 'Own address' ) && false === strpos( $html, 'page on WordPress is not recommended' ) );
check( 'every form is closed and each button points at a real form', substr_count( $html, '<form' ) === substr_count( $html, '</form>' ) );
preg_match_all( '/form="([^"]+)"/', $html, $refs );
$missing = array();
foreach ( array_unique( $refs[1] ) as $id ) {
    if ( false === strpos( $html, 'id="' . $id . '"' ) ) {
        $missing[] = $id;
    }
}
check( 'forms referenced by buttons exist', array() === $missing );
check( 'the hatch section has no test button and no unused wrapper', false === strpos( $html, 'hatch_do" value="test"' ) && false === strpos( $html, 'bunker-hatch-fields' ) );

// No index.html next to WordPress: the warning does not apply.
unlink( $index );
$html = render_page();
check( 'without an index.html only the sidebar Bad badge remains and no warning card appears', 1 === substr_count( $html, 'bunker-pill-bad' ) && false === strpos( $html, 'bunker-rail-teaser' ) );
check( 'the Same directory section stays, worded as advice', false !== strpos( $html, 'id="bunker-better"' ) && false !== strpos( $html, 'WordPress is not sharing a directory with your website' ) && false === strpos( $html, 'Right now WordPress shares a directory' ) );

// The hatch note names the real reason.
check( 'before the first confirmation the hatch note says Bunker is not enforcing', false !== strpos( render_page(), 'Bunker is not enforcing anything right now' ) );
THRG_Bunker_Options::update( array( 'confirmed' => 1 ) );
THRG_Bunker_Options::set( 'ip_lock', false );
check( 'with the IP lock off the note says so', false !== strpos( render_page(), 'The IP lock is off, so there is nothing for the hatch to open' ) );
THRG_Bunker_Options::set( 'ip_lock', true );
check( 'with an unsaved list the note asks for the list', false !== strpos( render_page(), 'waiting for you to save your IP list' ) );
THRG_Bunker_Options::update( array( 'ip_list' => '203.0.113.5', 'ip_saved' => 1 ) );
$html = render_page();
check( 'with the lock enforcing there is no note', false === strpos( $html, 'Bunker is not enforcing anything' ) && false === strpos( $html, 'The IP lock is off, so' ) && false === strpos( $html, 'waiting for you to save your IP list' ) );

// The Good / Bad / Info word comes from one place.
check( 'check labels', 'Good' === THRG_Bunker_Checks::label( 'good' ) && 'Bad' === THRG_Bunker_Checks::label( 'attention' ) && 'Info' === THRG_Bunker_Checks::label( 'info' ) );

// Every .bunker-* class in the stylesheet is used by the page or the script.
$css   = (string) file_get_contents( __DIR__ . '/../assets/settings.css' );
$used  = (string) file_get_contents( __DIR__ . '/../assets/settings.js' );
foreach ( glob( __DIR__ . '/../includes/*.php' ) as $file ) {
    $used .= (string) file_get_contents( $file );
}
preg_match_all( '/\.(bunker-[a-z0-9-]+)/', $css, $found );
$dead = array();
foreach ( array_unique( $found[1] ) as $class ) {
    if ( false === strpos( $used, $class ) ) {
        $dead[] = $class;
    }
}
check( 'the stylesheet has no rules for classes nothing uses' . ( $dead ? ' (' . implode( ', ', $dead ) . ')' : '' ), array() === $dead );

// Saved text is data: HTML and script markers in every free-text setting must render inert.
$marker = '"><script>alert(1)</script><img src=x onerror=alert(2)>';
THRG_Bunker_Options::update( array( 'confirmed' => 1, 'rest_allow' => '/a' . $marker, 'actions_allow' => 'a' . $marker, 'ip_list' => '203.0.113.9' . "\n" . $marker, 'hatch_email' => 'x' . $marker ) );
$html = render_page();
check( 'saved markers never appear as live script or event-handler markup', false === strpos( $html, '<script>alert' ) && false === strpos( $html, '<img src=x' ) && false === strpos( $html, '"><script' ) );
check( 'the settings page still renders with hostile saved values', false !== strpos( $html, 'class="wrap thrg-bunker"' ) );

// The backup section is for problems only: hidden normally, shown with a button when Bunker holds a backup.
THRG_Bunker_Options::update( array( 'confirmed' => 1 ) );
$backup_file = THRG_Bunker_Server_Rules::backup_path( ABSPATH . '.htaccess' );
@unlink( $backup_file );
$html = render_page();
check( 'with no retained backup the page has no recovery section and shows no server paths', false === strpos( $html, 'bunker-recovery' ) && false === strpos( $html, 'Managed block' ) && false === strpos( $html, ABSPATH . '.htaccess' ) );
file_put_contents( $backup_file, 'old rules' );
$html = render_page();
check( 'with a retained backup the section and the button appear in plain words', false !== strpos( $html, 'bunker-recovery' ) && false !== strpos( $html, 'Set the backup aside and allow a retry' ) && false !== strpos( $html, 'thrg_bunker_archive_backup' ) );
@unlink( $backup_file );
