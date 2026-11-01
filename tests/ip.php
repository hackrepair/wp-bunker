<?php
// IP helpers: validation, CIDR matching, proxy handling.
require __DIR__ . '/bootstrap.php';
thrg_load_plugin();

check( 'valid IPv4', THRG_Bunker_Ip::valid_entry( '203.0.113.5' ) );
check( 'valid IPv6', THRG_Bunker_Ip::valid_entry( '2001:db8::1' ) );
check( 'valid IPv4 CIDR', THRG_Bunker_Ip::valid_entry( '203.0.113.0/24' ) );
check( 'valid IPv6 CIDR', THRG_Bunker_Ip::valid_entry( '2001:db8::/32' ) );
check( 'rejects words', ! THRG_Bunker_Ip::valid_entry( 'my house' ) );
check( 'rejects bad octets', ! THRG_Bunker_Ip::valid_entry( '300.1.1.1' ) );
check( 'rejects IPv4 prefix over 32', ! THRG_Bunker_Ip::valid_entry( '10.0.0.0/33' ) );
check( 'rejects IPv6 prefix over 128', ! THRG_Bunker_Ip::valid_entry( '::1/129' ) );
check( 'rejects non-numeric prefix', ! THRG_Bunker_Ip::valid_entry( '10.0.0.0/abc' ) );

check( 'single address matches itself', THRG_Bunker_Ip::matches( '203.0.113.5', '203.0.113.5' ) );
check( 'single address does not match another', ! THRG_Bunker_Ip::matches( '203.0.113.6', '203.0.113.5' ) );
check( '/24 includes its hosts', THRG_Bunker_Ip::matches( '203.0.113.200', '203.0.113.0/24' ) );
check( '/24 excludes outside hosts', ! THRG_Bunker_Ip::matches( '203.0.114.1', '203.0.113.0/24' ) );
check( '/20 uneven prefix works', THRG_Bunker_Ip::matches( '173.245.63.255', '173.245.48.0/20' ) && ! THRG_Bunker_Ip::matches( '173.245.64.0', '173.245.48.0/20' ) );
check( '/0 matches every IPv4', THRG_Bunker_Ip::matches( '8.8.8.8', '0.0.0.0/0' ) );
check( 'IPv6 /32 includes', THRG_Bunker_Ip::matches( '2001:db8:1234::1', '2001:db8::/32' ) );
check( 'IPv6 /32 excludes', ! THRG_Bunker_Ip::matches( '2001:db9::1', '2001:db8::/32' ) );
check( 'IPv4 never matches an IPv6 range', ! THRG_Bunker_Ip::matches( '203.0.113.5', '2001:db8::/32' ) );
check( 'junk never matches', ! THRG_Bunker_Ip::matches( 'junk', '203.0.113.0/24' ) );

$list = "203.0.113.5\n198.51.100.0/24, 2001:db8::/32";
check( 'in_list finds exact, range and IPv6', THRG_Bunker_Ip::in_list( '203.0.113.5', $list ) && THRG_Bunker_Ip::in_list( '198.51.100.77', $list ) && THRG_Bunker_Ip::in_list( '2001:db8::5', $list ) );
check( 'in_list refuses others', ! THRG_Bunker_Ip::in_list( '192.0.2.1', $list ) );
check( 'empty list matches nobody', ! THRG_Bunker_Ip::in_list( '203.0.113.5', '' ) );
$split = THRG_Bunker_Ip::check_list( "203.0.113.5\nnope\n10.0.0.0/8" );
check( 'check_list splits valid and invalid', array( '203.0.113.5', '10.0.0.0/8' ) === $split['valid'] && array( 'nope' ) === $split['invalid'] );

// Proxy handling.
$direct = THRG_Bunker_Ip::detect( 'remote_addr', array( 'REMOTE_ADDR' => '203.0.113.5', 'HTTP_CF_CONNECTING_IP' => '198.51.100.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.9' ) );
check( 'forwarded headers are ignored by default', '203.0.113.5' === $direct['ip'] && 'REMOTE_ADDR' === $direct['source'] );
$cf = THRG_Bunker_Ip::detect( 'cloudflare', array( 'REMOTE_ADDR' => '104.16.1.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.9' ) );
check( 'Cloudflare header trusted when the connection comes from Cloudflare', '198.51.100.9' === $cf['ip'] && 'CF-Connecting-IP' === $cf['source'] );
$spoof = THRG_Bunker_Ip::detect( 'cloudflare', array( 'REMOTE_ADDR' => '203.0.113.5', 'HTTP_CF_CONNECTING_IP' => '198.51.100.9' ) );
check( 'Cloudflare header is NOT trusted from any other address', '203.0.113.5' === $spoof['ip'] && 'REMOTE_ADDR' === $spoof['source'] );
$bad = THRG_Bunker_Ip::detect( 'cloudflare', array( 'REMOTE_ADDR' => '104.16.1.1', 'HTTP_CF_CONNECTING_IP' => 'not-an-ip' ) );
check( 'an invalid forwarded value falls back to REMOTE_ADDR', '104.16.1.1' === $bad['ip'] );
$none = THRG_Bunker_Ip::detect( 'remote_addr', array() );
check( 'missing REMOTE_ADDR gives an empty address', '' === $none['ip'] );
$ranges_ok = true;
foreach ( THRG_Bunker_Ip::cloudflare_ranges() as $range ) {
    $ranges_ok = $ranges_ok && THRG_Bunker_Ip::valid_entry( $range );
}
check( 'every built-in Cloudflare range is valid', $ranges_ok );
