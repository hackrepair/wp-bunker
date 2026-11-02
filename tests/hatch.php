<?php
// Hatch link: creation, email approval flow, access cookie, limits and failure cases.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();

define( 'THRG_TEST_LOGIN', 'https://example.test/wp-login.php' );

THRG_Bunker_Options::update(
    array(
        'confirmed' => 1,
        'ip_list'   => '203.0.113.5',
        'ip_saved'  => 1,
        'ip_proxy'  => 'remote_addr',
    )
);
$GLOBALS['opts']['admin_email'] = 'owner@example.test';
$GLOBALS['pagenow']             = 'wp-login.php';
$_SERVER['REMOTE_ADDR']         = '198.51.100.9';
$_SERVER['HTTP_USER_AGENT']     = 'Browser/1';

/**
 * Run the IP gate as a visitor and report how the request ended.
 *
 * @param array      $get  Query string.
 * @param array|null $post Form fields, or null for a GET request.
 * @param array      $jar  Cookies the browser sends.
 * @return array{0:?THRG_Test_Exit,1:array,2:string} Exit, cookies set, page body.
 */
function hit( $get = array(), $post = null, $jar = array() ) {
    $_GET                      = $get;
    $_POST                     = null === $post ? array() : $post;
    $_COOKIE                   = $jar;
    $_SERVER['REQUEST_METHOD'] = null === $post ? 'GET' : 'POST';
    $GLOBALS['cookies']        = array();
    ob_start();
    $exit = null;
    try {
        THRG_Bunker_Lockdown::ip_gate();
    } catch ( THRG_Test_Exit $e ) {
        $exit = $e;
    }
    $html = ob_get_clean();
    return array( $exit, $GLOBALS['cookies'], (string) $html );
}

function kind( $result ) {
    return $result[0] ? $result[0]->getMessage() : 'passed';
}

function title( $result ) {
    return $result[0] && 'hatch_page' === $result[0]->getMessage() ? $result[0]->payload[0] : '';
}

function reset_flow() {
    delete_option( THRG_Bunker_Hatch::REQUEST_OPTION );
    delete_option( THRG_Bunker_Hatch::LOG_OPTION );
    $GLOBALS['mails'] = array();
}

/** Create a link as the administrator and return its token. */
function make_link() {
    $GLOBALS['can'] = true;
    $_POST          = array( 'hatch_do' => 'create' );
    thrg_catch( array( 'THRG_Bunker_Hatch', 'handle_admin' ) );
    $link = THRG_Bunker_Hatch::take_shown();
    preg_match( '/thrg_hatch=([a-f0-9]+)/', $link, $m );
    return array( $link, isset( $m[1] ) ? $m[1] : '' );
}

// --- Off by default: the hatch does nothing and the IP lock is unchanged. ---
check( 'the hatch is off by default', ! THRG_Bunker_Hatch::enabled() );
$r = hit( array( 'thrg_hatch' => 'abc123' ) );
check( 'with the hatch off, a hatch link is just a refused visit', 'wp_die' === kind( $r ) && 403 === $r[0]->status && array() === $GLOBALS['mails'] );

// --- Administrator buttons. ---
$GLOBALS['nonce_ok'] = false;
$_POST               = array( 'hatch_do' => 'create' );
$e                   = thrg_catch( array( 'THRG_Bunker_Hatch', 'handle_admin' ) );
check( 'a bad nonce stops the hatch buttons', $e && 'bad nonce' === $e->getMessage() && ! THRG_Bunker_Hatch::enabled() );
$GLOBALS['nonce_ok'] = true;
$GLOBALS['can']      = false;
$e                   = thrg_catch( array( 'THRG_Bunker_Hatch', 'handle_admin' ) );
check( 'only an administrator can create the link', $e && 403 === $e->status && ! THRG_Bunker_Hatch::enabled() );

list( $link, $token ) = make_link();
$state                = THRG_Bunker_Options::state();
check( 'creating makes a link with a long random token', 48 === strlen( $token ) && 0 === strpos( $link, THRG_TEST_LOGIN . '?thrg_hatch=' ) );
check( 'the hatch is on after creating', THRG_Bunker_Hatch::enabled() && 1 === $state['hatch_version'] );
check( 'only a hash of the token is stored', hash( 'sha256', $token ) === $state['hatch_hash'] && false === strpos( serialize( $state ), $token ) );
check( 'the link is shown once', '' === THRG_Bunker_Hatch::take_shown() );

// --- Wrong, missing and misplaced links. ---
$r = hit( array( 'thrg_hatch' => str_repeat( 'a', 48 ) ) );
check( 'a wrong token gets Bunker\'s 404', 'notfound' === kind( $r ) && array() === $GLOBALS['mails'] );
check( 'a wrong token is counted', 1 === THRG_Bunker_Options::state()['hatch_failed'] );
$GLOBALS['pagenow'] = 'index.php';
$r                  = hit( array( 'thrg_hatch' => $token ) );
check( 'the hatch only works on the login page', 'wp_die' === kind( $r ) && array() === $GLOBALS['mails'] );
$GLOBALS['pagenow'] = 'wp-login.php';
$r                  = hit( array( 'thrg_hatch_review' => str_repeat( 'b', 48 ) ) );
check( 'an unknown review secret gets the 404', 'notfound' === kind( $r ) );

// --- Allowed IP never needs the hatch. ---
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
$r                      = hit( array( 'thrg_hatch' => $token ) );
check( 'an allowed IP using the link is just sent to the login page, no email', 'redirect' === kind( $r ) && THRG_TEST_LOGIN === $r[0]->payload && array() === $GLOBALS['mails'] );
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';

// --- Start: the right link emails the administrator. ---
reset_flow();
$r   = hit( array( 'thrg_hatch' => $token ) );
$jar = $r[1];
check( 'the right link redirects to the waiting page without the token in the address', 'redirect' === kind( $r ) && false !== strpos( $r[0]->payload, 'thrg_hatch_wait=1' ) && false === strpos( $r[0]->payload, $token ) );
check( 'one approval email goes to the administrator', 1 === count( $GLOBALS['mails'] ) && 'owner@example.test' === $GLOBALS['mails'][0][0] );
$body = $GLOBALS['mails'][0][2];
check( 'the email shows the IP address, the browser and the review link', false !== strpos( $body, '198.51.100.9' ) && false !== strpos( $body, 'Browser/1' ) && 1 === preg_match( '/thrg_hatch_review=([a-f0-9]{48})/', $body, $m ) );
$secret = $m[1];
check( 'the email never contains the hatch token', false === strpos( $body, $token ) );
check( 'the requesting browser gets a request cookie', ! empty( $jar[ THRG_Bunker_Hatch::REQUEST_COOKIE ] ) );
$stored = get_option( THRG_Bunker_Hatch::REQUEST_OPTION );
check( 'the request is pending and only hashes are stored', 'pending' === $stored['status'] && false === strpos( serialize( $stored ), $secret ) && false === strpos( serialize( $stored ), $jar[ THRG_Bunker_Hatch::REQUEST_COOKIE ] ) );

// --- Waiting page does not let anyone in. ---
$r = hit( array( 'thrg_hatch_wait' => '1' ), null, $jar );
check( 'the waiting page asks the visitor to check email', 'Check your email' === title( $r ) );
$r = hit( array(), null, $jar );
check( 'a waiting browser is still refused at the login page', 'wp_die' === kind( $r ) && 403 === $r[0]->status );

// --- Scanner safety: opening the email link approves nothing. ---
$r = hit( array( 'thrg_hatch_review' => $secret ) );
check( 'the review page shows Approve and Deny buttons', 'Approve this hatch request?' === title( $r ) && false !== strpos( $r[0]->payload[1], 'value="approve"' ) && false !== strpos( $r[0]->payload[1], 'value="deny"' ) );
check( 'opening the review link did not approve anything', 'pending' === get_option( THRG_Bunker_Hatch::REQUEST_OPTION )['status'] );
$r = hit( array( 'thrg_hatch_wait' => '1' ), null, $jar );
check( 'so the waiting browser is still waiting', 'Check your email' === title( $r ) );

// --- Cross-device: approve from a phone (a different browser with no cookies). ---
$r = hit( array( 'thrg_hatch_review' => $secret ), array( 'decision' => 'approve' ) );
check( 'pressing Approve on another device works', 'Approved' === title( $r ) && 'approved' === get_option( THRG_Bunker_Hatch::REQUEST_OPTION )['status'] );
$r = hit( array( 'thrg_hatch_wait' => '1' ) );
check( 'the approving device was NOT given access', 'This request has ended' === title( $r ) && empty( $r[1][ THRG_Bunker_Hatch::ACCESS_COOKIE ] ) );
$r = hit( array(), null, array() );
check( 'the approving device still cannot reach the login page', 'wp_die' === kind( $r ) );

// --- The browser that asked is let in. ---
$r = hit( array( 'thrg_hatch_wait' => '1' ), null, $jar );
check( 'the requesting browser is sent to the clean login page', 'redirect' === kind( $r ) && THRG_TEST_LOGIN === $r[0]->payload );
check( 'it receives the access cookie and the request is used up', ! empty( $r[1][ THRG_Bunker_Hatch::ACCESS_COOKIE ] ) && false === get_option( THRG_Bunker_Hatch::REQUEST_OPTION, false ) );
$access = array( THRG_Bunker_Hatch::ACCESS_COOKIE => $r[1][ THRG_Bunker_Hatch::ACCESS_COOKIE ] );
check( 'an approved browser passes the gate on the login page', 'passed' === kind( hit( array(), null, $access ) ) );
$GLOBALS['pagenow'] = 'index.php';
check( 'and on the dashboard', 'passed' === kind( hit( array(), null, $access ) ) );
$GLOBALS['pagenow'] = 'wp-login.php';
check( 'the approved cookie keeps working on repeat visits', 'passed' === kind( hit( array(), null, $access ) ) );

// --- The access cookie cannot be borrowed or forged. ---
$_SERVER['HTTP_USER_AGENT'] = 'Other/2';
check( 'another browser cannot reuse the cookie', 'wp_die' === kind( hit( array(), null, $access ) ) );
$_SERVER['HTTP_USER_AGENT'] = 'Browser/1';
$parts                      = explode( '|', $access[ THRG_Bunker_Hatch::ACCESS_COOKIE ] );
$forged                     = array( THRG_Bunker_Hatch::ACCESS_COOKIE => ( (int) $parts[0] + 9999 ) . '|' . $parts[1] . '|' . $parts[2] );
check( 'a longer expiry cannot be forged', 'wp_die' === kind( hit( array(), null, $forged ) ) );
check( 'garbage in the cookie is refused', 'wp_die' === kind( hit( array(), null, array( THRG_Bunker_Hatch::ACCESS_COOKIE => 'x' ) ) ) );
$sign    = new ReflectionMethod( 'THRG_Bunker_Hatch', 'sign' );
$past    = time() - 10;
$expired = array( THRG_Bunker_Hatch::ACCESS_COOKIE => $past . '|1|' . $sign->invoke( null, $past, 1 ) );
check( 'an expired cookie is refused even with a valid signature', 'wp_die' === kind( hit( array(), null, $expired ) ) );

// --- Making a new link ends every session. ---
list( , $token2 ) = make_link();
check( 'a new link signs out every approved browser', 'wp_die' === kind( hit( array(), null, $access ) ) && THRG_Bunker_Options::state()['hatch_version'] > 1 );
check( 'and the old link stops working', 'notfound' === kind( hit( array( 'thrg_hatch' => $token ) ) ) );

// --- Deny. ---
reset_flow();
$r      = hit( array( 'thrg_hatch' => $token2 ) );
$jar    = $r[1];
preg_match( '/thrg_hatch_review=([a-f0-9]{48})/', $GLOBALS['mails'][0][2], $m );
$secret = $m[1];
$r      = hit( array( 'thrg_hatch_review' => $secret ), array( 'decision' => 'deny' ) );
check( 'Deny ends the request', 'Denied' === title( $r ) && false === get_option( THRG_Bunker_Hatch::REQUEST_OPTION, false ) );
check( 'a denied browser gets nothing', 'This request has ended' === title( hit( array( 'thrg_hatch_wait' => '1' ), null, $jar ) ) );
check( 'the denied email link is dead', 'notfound' === kind( hit( array( 'thrg_hatch_review' => $secret ) ) ) );

// --- Deny and turn the hatch off. ---
reset_flow();
$r      = hit( array( 'thrg_hatch' => $token2 ) );
preg_match( '/thrg_hatch_review=([a-f0-9]{48})/', $GLOBALS['mails'][0][2], $m );
$r      = hit( array( 'thrg_hatch_review' => $m[1] ), array( 'decision' => 'disable' ) );
check( 'Deny and turn off switches the hatch off', 'The hatch is off' === title( $r ) && ! THRG_Bunker_Hatch::enabled() && '' === THRG_Bunker_Options::state()['hatch_hash'] );
check( 'the link no longer does anything', 'wp_die' === kind( hit( array( 'thrg_hatch' => $token2 ) ) ) );

// --- Limits. ---
list( , $token3 ) = make_link();
reset_flow();
$r1 = hit( array( 'thrg_hatch' => $token3 ) );
$r2 = hit( array( 'thrg_hatch' => $token3 ), null, $r1[1] );
check( 'a second visit from the same browser sends no second email', 1 === count( $GLOBALS['mails'] ) && 'redirect' === kind( $r2 ) );
$r3 = hit( array( 'thrg_hatch' => $token3 ) ); // A different browser: no cookies sent.
check( 'another browser within a minute is asked to wait; no email', 'Approval pending' === title( $r3 ) && 1 === count( $GLOBALS['mails'] ) );
update_option( THRG_Bunker_Hatch::LOG_OPTION, array( time() - 120, time() - 130, time() - 140, time() - 150, time() - 160 ) );
$r4 = hit( array( 'thrg_hatch' => $token3 ) );
check( 'five emails in an hour is the limit', 'Approval pending' === title( $r4 ) && 1 === count( $GLOBALS['mails'] ) );
update_option( THRG_Bunker_Hatch::LOG_OPTION, array( time() - 120, time() - 130, time() - 140, time() - 150 ) );
$r5 = hit( array( 'thrg_hatch' => $token3 ) );
check( 'a second browser preserves the pending request', 'Approval pending' === title( $r5 ) && 1 === count( $GLOBALS['mails'] ) );
delete_option( THRG_Bunker_Hatch::REQUEST_OPTION );
update_option( THRG_Bunker_Hatch::LOG_OPTION, array( time() - 4000 ) );
check( 'emails older than an hour do not count', 'redirect' === kind( hit( array( 'thrg_hatch' => $token3 ) ) ) && 2 === count( $GLOBALS['mails'] ) );

// --- Expiry. ---
reset_flow();
hit( array( 'thrg_hatch' => $token3 ) );
preg_match( '/thrg_hatch_review=([a-f0-9]{48})/', $GLOBALS['mails'][0][2], $m );
$stored            = get_option( THRG_Bunker_Hatch::REQUEST_OPTION );
$stored['expires'] = time() - 1;
update_option( THRG_Bunker_Hatch::REQUEST_OPTION, $stored );
check( 'an expired request cannot be approved', 'notfound' === kind( hit( array( 'thrg_hatch_review' => $m[1] ), array( 'decision' => 'approve' ) ) ) );

// --- Email failure keeps the hatch shut. ---
reset_flow();
$GLOBALS['mail_ok'] = false;
$r                  = hit( array( 'thrg_hatch' => $token3 ) );
check( 'if the email cannot be sent the hatch stays shut', 'The email could not be sent' === title( $r ) && empty( $r[1] ) && false === get_option( THRG_Bunker_Hatch::REQUEST_OPTION, false ) );
check( 'the failure is recorded for the settings page', 0 < THRG_Bunker_Options::state()['hatch_mail_error'] );
$GLOBALS['mail_ok'] = true;
reset_flow();
hit( array( 'thrg_hatch' => $token3 ) );
check( 'a later success clears the warning', 0 === THRG_Bunker_Options::state()['hatch_mail_error'] );

// --- Settings buttons. ---
$GLOBALS['can'] = true;
$_POST          = array( 'hatch_do' => 'save', 'hatch_email' => 'not-an-email', 'hatch_hours' => '7' );
thrg_catch( array( 'THRG_Bunker_Hatch', 'handle_admin' ) );
$state = THRG_Bunker_Options::state();
check( 'a bad email and an odd session length fall back to the defaults', '' === $state['hatch_email'] && 4 === $state['hatch_hours'] );
$_POST = array( 'hatch_do' => 'save', 'hatch_email' => 'me@example.test', 'hatch_hours' => '12' );
thrg_catch( array( 'THRG_Bunker_Hatch', 'handle_admin' ) );
check( 'a good email and session length are saved', 'me@example.test' === THRG_Bunker_Hatch::recipient() && 12 === THRG_Bunker_Options::state()['hatch_hours'] );
reset_flow();
hit( array( 'thrg_hatch' => $token3 ) );
check( 'approval emails go to the chosen address', 'me@example.test' === $GLOBALS['mails'][0][0] );
$_POST = array( 'hatch_do' => 'disable' );
thrg_catch( array( 'THRG_Bunker_Hatch', 'handle_admin' ) );
check( 'the administrator can turn the hatch off', ! THRG_Bunker_Hatch::enabled() && false === get_option( THRG_Bunker_Hatch::REQUEST_OPTION, false ) );

// --- The three doors stay open. ---
list( , $token4 ) = make_link();
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
$GLOBALS['pagenow']     = 'admin-post.php';
reset_flow();
check( 'admin-post.php is never held up by the hatch', 'passed' === kind( hit( array( 'thrg_hatch' => $token4 ) ) ) && array() === $GLOBALS['mails'] );

// --- Review M7: the email states the real expiry. ---
reset_flow();
$GLOBALS['pagenow'] = 'wp-login.php';
hit( array( 'thrg_hatch' => $token4 ) );
check( 'the email says the request expires in 15 minutes by default', false !== strpos( $GLOBALS['mails'][0][2], 'expires in 15 minutes' ) );
reset_flow();
$ttl = THRG_Bunker_Hatch::REQUEST_TTL;
$req = array( 'created' => 1000, 'expires' => 1000 + 600, 'ip' => '198.51.100.9', 'agent' => 'Browser/1' );
$m   = new ReflectionMethod( 'THRG_Bunker_Hatch', 'send_mail' );
$m->setAccessible( true );
$GLOBALS['mails'] = array();
$m->invoke( null, $req, str_repeat( 'a', 48 ) );
check( 'a different expiry is reflected in the email', false !== strpos( $GLOBALS['mails'][0][2], 'expires in 10 minutes' ) );

// --- Review M8: a new link or switching off resets the old link's counters. ---
THRG_Bunker_Options::update( array( 'hatch_failed' => 12, 'hatch_failed_at' => time(), 'hatch_mail_error' => time() ) );
THRG_Bunker_Hatch::create();
$state = THRG_Bunker_Options::state();
check( 'a new link starts with a clean wrong-link count and no mail warning', 0 === $state['hatch_failed'] && 0 === $state['hatch_failed_at'] && 0 === $state['hatch_mail_error'] );
THRG_Bunker_Options::update( array( 'hatch_failed' => 3, 'hatch_mail_error' => time() ) );
THRG_Bunker_Hatch::disable();
$state = THRG_Bunker_Options::state();
check( 'turning the hatch off clears them too', 0 === $state['hatch_failed'] && 0 === $state['hatch_mail_error'] );
