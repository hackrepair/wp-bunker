<?php
// Workplan item 05: public action dispatch. Only the exact listed name may reach a callback, on both dispatchers.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();

THRG_Bunker_Options::update( array( 'confirmed' => 1, 'actions_allow' => "contact_form\nchat-send" ) );
THRG_Bunker_Options::set( 'known_actions', true );
$GLOBALS['user'] = 0;

/**
 * Stand-in for WordPress core's dispatch: it runs the callbacks registered under the exact request value,
 * the way do_action( 'wp_ajax_nopriv_' . $action ) and do_action( 'admin_post_nopriv_' . $action ) do.
 */
$GLOBALS['fixtures'] = array();
foreach ( array( 'contact_form', 'Contact_Form', 'CONTACT_FORM', 'contact-form', 'chat-send', 'other_plugin_action' ) as $name ) {
    $GLOBALS['fixtures'][ $name ] = 0;
}
function dispatch( $raw, $script ) {
    foreach ( $GLOBALS['fixtures'] as $k => $v ) {
        $GLOBALS['fixtures'][ $k ] = 0;
    }
    $GLOBALS['pagenow'] = $script;
    $_REQUEST           = array( 'action' => $raw );
    $exit               = thrg_catch( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ) );
    if ( null === $exit && is_string( $raw ) && isset( $GLOBALS['fixtures'][ $raw ] ) ) {
        ++$GLOBALS['fixtures'][ $raw ]; // The gate let it through and core would run this exact callback.
    }
    return array( 'exit' => $exit, 'calls' => array_filter( $GLOBALS['fixtures'] ) );
}

foreach ( array( 'wp-admin/admin-ajax.php' => 'admin-ajax.php', 'admin-post.php' => 'admin-post.php' ) as $label => $script ) {
    $GLOBALS['doing_ajax'] = 'admin-ajax.php' === $script;

    $r = dispatch( 'contact_form', $script );
    check( "$label: the exact listed name runs its callback once", null === $r['exit'] && array( 'contact_form' => 1 ) === $r['calls'] );
    $r = dispatch( 'chat-send', $script );
    check( "$label: a listed name with a hyphen runs its callback", null === $r['exit'] && array( 'chat-send' => 1 ) === $r['calls'] );

    // Case variants have their own registered callbacks in the fixtures; none may run.
    foreach ( array( 'Contact_Form', 'CONTACT_FORM' ) as $variant ) {
        $r = dispatch( $variant, $script );
        check( "$label: $variant is refused and its separate callback never runs", $r['exit'] && 403 === $r['exit']->status && array() === $r['calls'] );
    }
    $r = dispatch( 'contact-form', $script );
    check( "$label: hyphen for underscore is a different action and is refused", $r['exit'] && array() === $r['calls'] );
    $r = dispatch( 'other_plugin_action', $script );
    check( "$label: an unlisted registered callback does not run", $r['exit'] && array() === $r['calls'] );

    $bad = array(
        'trailing space'    => 'contact_form ',
        'leading space'     => ' contact_form',
        'trailing newline'  => "contact_form\n",
        'trailing CR LF'    => "contact_form\r\n",
        'null byte'         => "contact_form\0",
        'null byte inside'  => "contact\0_form",
        'tab'               => "contact_form\t",
        'percent null'      => 'contact_form%00',
        'slash'             => 'contact_form/',
        'dot'               => 'contact_form.',
        'path traversal'    => '../contact_form',
        'semicolon'         => 'contact_form;other_plugin_action',
        'comma list'        => 'contact_form,other_plugin_action',
        'html'              => 'contact_form<b>',
        'unicode lookalike' => "c\u{043E}ntact_form",
        'accent'            => "cont\u{00E4}ct_form",
        'fullwidth'         => "contact_form\u{FF3F}",
        'very long'         => str_repeat( 'a', 100000 ),
        'empty'             => '',
        'zero'              => '0',
    );
    foreach ( $bad as $why => $raw ) {
        $r = dispatch( $raw, $script );
        check( "$label: refused ($why)", $r['exit'] && 403 === $r['exit']->status && array() === $r['calls'] );
    }
    foreach ( array( 'array' => array( 'contact_form' ), 'nested array' => array( array( 'contact_form' ) ), 'keyed array' => array( 'x' => 'contact_form' ), 'null' => null, 'int' => 5, 'bool' => true ) as $why => $raw ) {
        $r = dispatch( $raw, $script );
        check( "$label: refused ($why input)", $r['exit'] && 403 === $r['exit']->status && array() === $r['calls'] );
    }
}
unset( $GLOBALS['doing_ajax'] );

// The same name in the query string and the body: WordPress merges them (the body wins), and the gate reads the merged value.
$_GET = array( 'action' => 'other_plugin_action' );
$_POST = array( 'action' => 'contact_form' );
$_REQUEST = array_merge( $_GET, $_POST );
$GLOBALS['pagenow'] = 'admin-post.php';
check( 'a doubled action is judged on the merged value core will use', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ) ) && 'contact_form' === $_REQUEST['action'] );
$_GET = $_POST = array();

// The saved allowlist only holds exact, clean names.
check( 'the allowlist cleaner keeps case and drops anything with punctuation', "Contact_Form\nchat-send" === THRG_Bunker_Settings::clean_actions( "Contact_Form\nbad name\nchat-send\nx;y\n<b>\n../z" ) );
check( 'the allowlist cleaner removes duplicates only when exactly equal', "a\nA" === THRG_Bunker_Settings::clean_actions( "a\nA\na" ) );

// Logged-in users and other scripts are untouched, and an empty allowlist refuses all.
$GLOBALS['user']    = 7;
$GLOBALS['pagenow'] = 'admin-post.php';
$_REQUEST           = array( 'action' => 'other_plugin_action' );
check( 'a logged-in user is not held up', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ) ) );
$GLOBALS['user'] = 0;
THRG_Bunker_Options::update( array( 'actions_allow' => '' ) );
check( 'with an empty allowlist the gate refuses every action', ! THRG_Bunker_Lockdown::action_allowed( 'contact_form', '' ) && ! THRG_Bunker_Options::active( 'known_actions' ) );

// REST: the gate judges the route WordPress will dispatch. Anything that is not an exact segment-wise prefix of a listed route is refused.
THRG_Bunker_Options::update( array( 'rest_allow' => "/chat/v1\n/contact-form/v1/submit" ) );
$GLOBALS['wp']   = (object) array( 'query_vars' => array() );
$GLOBALS['user'] = 0;
$rest = function ( $route ) {
    $GLOBALS['wp']->query_vars['rest_route'] = $route;
    $out = THRG_Bunker_Lockdown::rest_gate( null );
    return null === $out ? 'allowed' : ( $out instanceof WP_Error ? $out->data['status'] : 'odd' );
};
foreach ( array( '/chat/v1', '/chat/v1/', '/chat/v1/send', '/chat/v1/a/b/c', '/contact-form/v1/submit' ) as $ok ) {
    check( "REST $ok is allowed", 'allowed' === $rest( $ok ) );
}
$refused = array(
    'sibling prefix'       => '/chat/v10/send',
    'longer first segment' => '/chat/v1evil',
    'parent'               => '/chat',
    'different case'       => '/CHAT/V1/send',
    'traversal'            => '/chat/v1/../wp/v2/users',
    'encoded traversal'    => '/chat/v1/%2e%2e/wp/v2/users',
    'users'                => '/wp/v2/users',
    'users with dot-slash' => '/chat/v1/./../../wp/v2/users',
    'listed leaf extra'    => '/contact-form/v1/submitted',
    'listed leaf parent'   => '/contact-form/v1',
    'null byte'            => "/chat/v1\0/x",
    'empty'                => '',
    'root'                 => '/',
    'backslash'            => '\\chat\\v1',
    'space'                => '/chat/v1 /x',
);
foreach ( $refused as $why => $route ) {
    $r = $rest( $route );
    // Traversal strings are only "allowed" by prefix; WordPress matches routes literally, so they would 404. Still, none may reach a core route name.
    if ( false !== strpos( $route, '..' ) || false !== stripos( $route, '%2e' ) ) {
        check( "REST $why never reaches core routes literally", 0 !== strpos( ltrim( $route, '/' ), 'wp/v2' ) );
        continue;
    }
    check( "REST refused ($why)", 401 === $r );
}
$GLOBALS['wp']->query_vars = array();
check( 'REST with no route at all is refused for visitors', 401 === $rest( null ) || 401 === THRG_Bunker_Lockdown::rest_gate( null )->data['status'] );
$GLOBALS['wp']->query_vars['rest_route'] = array( '/chat/v1/send' );
$out = @THRG_Bunker_Lockdown::rest_gate( null );
check( 'REST route given as an array is refused, not passed', $out instanceof WP_Error );
