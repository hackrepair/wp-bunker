<?php
/**
 * IP address helpers for the login and dashboard lock.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Client address detection and IPv4/IPv6/CIDR matching.
 */
final class THRG_Bunker_Ip {

    /**
     * Cloudflare's published ranges. Used only to decide whether to believe CF-Connecting-IP.
     * Filterable with thrg_bunker_trusted_proxies.
     *
     * @return string[]
     */
    public static function cloudflare_ranges() {
        return array(
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        );
    }

    /**
     * Whether a string is a valid IPv4 or IPv6 address.
     *
     * @param string $ip Address.
     * @return bool
     */
    public static function is_ip( $ip ) {
        return false !== filter_var( $ip, FILTER_VALIDATE_IP );
    }

    /**
     * Whether a list entry is a valid address or CIDR range.
     *
     * @param string $entry One list line.
     * @return bool
     */
    public static function valid_entry( $entry ) {
        $entry = trim( (string) $entry );
        if ( self::is_ip( $entry ) ) {
            return true;
        }
        if ( false === strpos( $entry, '/' ) ) {
            return false;
        }
        list( $net, $bits ) = explode( '/', $entry, 2 );
        if ( ! self::is_ip( $net ) || ! ctype_digit( $bits ) ) {
            return false;
        }
        $max = false !== strpos( $net, ':' ) ? 128 : 32;
        return (int) $bits >= 0 && (int) $bits <= $max;
    }

    /**
     * Whether an address is inside a CIDR range, or equal to a single address.
     *
     * @param string $ip    Address.
     * @param string $entry Address or CIDR range.
     * @return bool
     */
    public static function matches( $ip, $entry ) {
        $entry = trim( (string) $entry );
        if ( ! self::is_ip( $ip ) || ! self::valid_entry( $entry ) ) {
            return false;
        }
        $bits = null;
        $net  = $entry;
        if ( false !== strpos( $entry, '/' ) ) {
            list( $net, $bits ) = explode( '/', $entry, 2 );
            $bits               = (int) $bits;
        }
        $packed_ip  = inet_pton( $ip );
        $packed_net = inet_pton( $net );
        if ( false === $packed_ip || false === $packed_net || strlen( $packed_ip ) !== strlen( $packed_net ) ) {
            return false;
        }
        if ( null === $bits ) {
            return $packed_ip === $packed_net;
        }
        $bytes = intdiv( $bits, 8 );
        $rest  = $bits % 8;
        if ( $bytes > 0 && substr( $packed_ip, 0, $bytes ) !== substr( $packed_net, 0, $bytes ) ) {
            return false;
        }
        if ( 0 === $rest ) {
            return true;
        }
        $mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
        return ( ord( $packed_ip[ $bytes ] ) & $mask ) === ( ord( $packed_net[ $bytes ] ) & $mask );
    }

    /**
     * Whether an address is on a saved list (one entry per line or comma separated).
     *
     * @param string $ip   Address.
     * @param string $entries Raw list text.
     * @return bool
     */
    public static function in_list( $ip, $entries ) {
        foreach ( THRG_Bunker_Options::lines( $entries ) as $entry ) {
            if ( self::matches( $ip, $entry ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Split a list into valid and invalid entries.
     *
     * @param string $entries Raw list text.
     * @return array{valid:string[],invalid:string[]}
     */
    public static function check_list( $entries ) {
        $valid   = array();
        $invalid = array();
        foreach ( THRG_Bunker_Options::lines( $entries ) as $entry ) {
            if ( self::valid_entry( $entry ) ) {
                $valid[] = $entry;
            } else {
                $invalid[] = $entry;
            }
        }
        return array(
            'valid'   => $valid,
            'invalid' => $invalid,
        );
    }

    /**
     * The visitor's address and where it came from.
     *
     * REMOTE_ADDR is used by default. A forwarded header is believed only when
     * the owner chose "behind Cloudflare" and REMOTE_ADDR is in Cloudflare's ranges.
     *
     * @param string               $mode   remote_addr or cloudflare.
     * @param array<string,string> $server Server variables (defaults to $_SERVER).
     * @return array{ip:string,source:string}
     */
    public static function detect( $mode = 'remote_addr', $server = null ) {
        if ( null === $server ) {
            $server = $_SERVER; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read only here; every value is validated as an IP address below.
        }
        $remote = isset( $server['REMOTE_ADDR'] ) ? trim( (string) $server['REMOTE_ADDR'] ) : '';
        $remote = self::is_ip( $remote ) ? $remote : '';

        if ( 'cloudflare' === $mode && '' !== $remote && isset( $server['HTTP_CF_CONNECTING_IP'] ) ) {
            $forwarded = trim( (string) $server['HTTP_CF_CONNECTING_IP'] );
            $trusted   = (array) apply_filters( 'thrg_bunker_trusted_proxies', self::cloudflare_ranges() );
            $is_proxy  = false;
            foreach ( $trusted as $range ) {
                if ( self::matches( $remote, $range ) ) {
                    $is_proxy = true;
                    break;
                }
            }
            if ( $is_proxy && self::is_ip( $forwarded ) ) {
                return array(
                    'ip'     => $forwarded,
                    'source' => 'CF-Connecting-IP',
                );
            }
        }

        return array(
            'ip'     => $remote,
            'source' => 'REMOTE_ADDR',
        );
    }
}
