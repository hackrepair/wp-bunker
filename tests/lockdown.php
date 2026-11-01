<?php
// Each lockdown blocks what it should and leaves the three doors working.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();

// REST allowlist.
check( 'route allowed by exact prefix', THRG_Bunker_Lockdown::route_allowed( '/chat/v1', "/chat/v1\n" ) );
check( 'route allowed below the prefix', THRG_Bunker_Lockdown::route_allowed( '/chat/v1/send', '/chat/v1' ) );
check( 'prefix match is on whole path segments', ! THRG_Bunker_Lockdown::route_allowed( '/chat/v10/send', '/chat/v1' ) );
check( 'other routes refused', ! THRG_Bunker_Lockdown::route_allowed( '/wp/v2/users', '/chat/v1' ) );
check( 'empty allowlist refuses everything', ! THRG_Bunker_Lockdown::route_allowed( '/chat/v1', '' ) );
check( 'a bare slash never allows everything', ! THRG_Bunker_Lockdown::route_allowed( '/wp/v2/users', '/' ) );

THRG_Bunker_Options::update( array( 'confirmed' => 1, 'rest_allow' => '/chat/v1' ) );
$GLOBALS['wp']   = (object) array( 'query_vars' => array( 'rest_route' => '/wp/v2/users' ) );
$GLOBALS['user'] = 0;
$out = THRG_Bunker_Lockdown::rest_gate( null );
check( 'anonymous visitor refused on a locked route with 401', $out instanceof WP_Error && 401 === $out->data['status'] );
$GLOBALS['wp']->query_vars['rest_route'] = '/chat/v1/send';
check( 'anonymous visitor allowed on an allowlisted route', null === THRG_Bunker_Lockdown::rest_gate( null ) );
$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/users';
$GLOBALS['user'] = 3;
check( 'logged-in user is not affected', null === THRG_Bunker_Lockdown::rest_gate( null ) );
$earlier = new WP_Error( 'x', 'earlier' );
check( 'an earlier error is passed through', $earlier === THRG_Bunker_Lockdown::rest_gate( $earlier ) );

// Usernames.
$endpoints       = array( '/wp/v2/users' => 1, '/wp/v2/users/(?P<id>[\d]+)' => 1, '/wp/v2/posts' => 1 );
$GLOBALS['user'] = 0;
check( 'users routes removed for anonymous visitors', array( '/wp/v2/posts' => 1 ) === THRG_Bunker_Lockdown::hide_user_endpoints( $endpoints ) );
$GLOBALS['user'] = 3;
check( 'users routes kept for logged-in users', $endpoints === THRG_Bunker_Lockdown::hide_user_endpoints( $endpoints ) );
check( 'one login error for both mistakes', THRG_Bunker_Lockdown::generic_login_error() === THRG_Bunker_Lockdown::generic_login_error( 'Invalid username.' ) );
check( 'oEmbed author removed', array( 'title' => 't' ) === THRG_Bunker_Lockdown::strip_oembed_author( array( 'title' => 't', 'author_name' => 'a', 'author_url' => 'u' ) ) );
check( 'user sitemap dropped, others kept', false === THRG_Bunker_Lockdown::drop_user_sitemap( 'p', 'users' ) && 'p' === THRG_Bunker_Lockdown::drop_user_sitemap( 'p', 'posts' ) );
$GLOBALS['user'] = 0;
$_GET['author']  = '1';
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'author_scan' ) );
check( 'an author scan from a stranger gets the 404 page', $e && 'notfound' === $e->getMessage() && 404 === $GLOBALS['status'] );
$GLOBALS['user'] = 3;
check( 'a logged-in user can use ?author=', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'author_scan' ) ) );
unset( $_GET['author'] );
$GLOBALS['user'] = 0;
check( 'no author parameter, no block', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'author_scan' ) ) );

// Front end and signup scripts.
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'front_end' ) );
check( 'a visitor asking for a theme page gets the 404 page', $e && 'notfound' === $e->getMessage() );
$GLOBALS['user'] = 1;
check( 'a logged-in administrator can preview the front end', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'front_end' ) ) );
$GLOBALS['user'] = 0;
$_SERVER['SCRIPT_NAME'] = '/wp-signup.php';
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'signup_scripts' ) );
check( 'wp-signup.php gets the 404 page', $e && 'notfound' === $e->getMessage() );
$_SERVER['SCRIPT_NAME'] = '/wp-activate.php';
check( 'wp-activate.php gets the 404 page', null !== thrg_catch( array( 'THRG_Bunker_Lockdown', 'signup_scripts' ) ) );
$_SERVER['SCRIPT_NAME'] = '/wp-login.php';
check( 'the login page is not a signup script', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'signup_scripts' ) ) );
unset( $_SERVER['SCRIPT_NAME'] );

// The three doors are never locked.
check( 'admin-post.php is exempt', THRG_Bunker_Lockdown::exempt_script( 'admin-post.php' ) );
check( 'admin-ajax.php is exempt', THRG_Bunker_Lockdown::exempt_script( 'admin-ajax.php' ) );
check( 'wp-cron.php is exempt', THRG_Bunker_Lockdown::exempt_script( 'wp-cron.php' ) );
check( 'the login page is not exempt', ! THRG_Bunker_Lockdown::exempt_script( 'wp-login.php' ) && ! THRG_Bunker_Lockdown::exempt_script( 'index.php' ) );
check( 'a name cannot fake an exempt script', ! THRG_Bunker_Lockdown::exempt_script( 'admin-post.php.evil' ) );

// IP lock.
THRG_Bunker_Options::update( array( 'ip_list' => '203.0.113.5', 'ip_saved' => 1, 'ip_proxy' => 'remote_addr' ) );
$GLOBALS['pagenow']     = 'wp-login.php';
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
check( 'allowed IP passes the lock', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'ip_gate' ) ) );
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'ip_gate' ) );
check( 'other IP is refused with 403', $e && 403 === $e->status );
$GLOBALS['pagenow'] = 'admin-post.php';
check( 'exempt endpoint still works for a refused IP', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'ip_gate' ) ) );
$GLOBALS['pagenow']    = 'index.php';
$GLOBALS['doing_ajax'] = true;
check( 'ajax requests still work for a refused IP', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'ip_gate' ) ) );
$GLOBALS['doing_ajax'] = false;
unset( $_SERVER['REMOTE_ADDR'] );
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'ip_gate' ) );
check( 'an unreadable address is refused', $e && 403 === $e->status );

// Known form actions.
check( 'listed action allowed', THRG_Bunker_Lockdown::action_allowed( 'contact_form', "contact_form\nchat_send" ) );
check( 'unlisted action refused', ! THRG_Bunker_Lockdown::action_allowed( 'wpforms_submit', 'contact_form' ) );
check( 'empty action refused', ! THRG_Bunker_Lockdown::action_allowed( '', 'contact_form' ) );
check( 'empty allowlist refuses everything', ! THRG_Bunker_Lockdown::action_allowed( 'contact_form', '' ) );
THRG_Bunker_Options::update( array( 'actions_allow' => 'contact_form' ) );
$GLOBALS['user']    = 0;
$GLOBALS['pagenow'] = 'admin-post.php';
$_REQUEST['action'] = 'contact_form';
check( 'stranger may run a listed action', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ) ) );
$_REQUEST['action'] = 'other_plugin_action';
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ) );
check( 'stranger refused on any other action', $e && 403 === $e->status );
unset( $_REQUEST['action'] );
$e = thrg_catch( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ) );
check( 'stranger refused when no action is given', $e && 403 === $e->status );
$GLOBALS['user']    = 4;
$_REQUEST['action'] = 'other_plugin_action';
check( 'logged-in users are not affected', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ) ) );
$GLOBALS['user']    = 0;
$GLOBALS['pagenow'] = 'index.php';
check( 'other scripts are not touched', null === thrg_catch( array( 'THRG_Bunker_Lockdown', 'known_actions_gate' ) ) );
unset( $_REQUEST['action'] );

// Detect actions.
$GLOBALS['wp_filter'] = array( 'admin_post_nopriv_contact_form' => 1, 'wp_ajax_nopriv_chat_send' => 1, 'wp_ajax_chat_send' => 1, 'init' => 1, 'admin_post_nopriv_' => 1 );
check( 'detect_actions lists only logged-out actions', array( 'chat_send', 'contact_form' ) === THRG_Bunker_Lockdown::detect_actions() );

// Hooks: nothing before confirmation, the right ones after.
$GLOBALS['opts']  = array();
$GLOBALS['hooks'] = array();
THRG_Bunker_Lockdown::boot();
check( 'no hooks before confirmation', empty( $GLOBALS['hooks'] ) );
THRG_Bunker_Options::update( array( 'confirmed' => 1 ) );
THRG_Bunker_Lockdown::boot();
check( 'front end hook registered', isset( $GLOBALS['hooks']['template_redirect'] ) );
check( 'REST hook registered', isset( $GLOBALS['hooks']['rest_authentication_errors'] ) );
check( 'comments, registration and XML-RPC hooks registered', isset( $GLOBALS['hooks']['pre_comment_on_post'], $GLOBALS['hooks']['pre_option_users_can_register'], $GLOBALS['hooks']['xmlrpc_enabled'] ) );
check( 'IP gate not hooked while the list is unsaved', ! isset( $GLOBALS['hooks']['login_init'] ) );
check( 'item 13 is not hooked by default', empty( $GLOBALS['hooks']['admin_init'] ) );
check( 'file editor lockdown defined the constant', defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT );
