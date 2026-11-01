<?php
/**
 * The View details dialog on the Plugins screen, built from the installed readme.txt.
 *
 * @package THRG_Bunker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Expose a native WordPress plugin-information dialog on the Plugins screen.
 * The installed readme supplies the dialog, so no network access is needed.
 *
 * @param string[] $links Existing row links.
 * @param string   $plugin_file Relative plugin entry point.
 * @return string[]
 */
function thrg_bunker_plugin_row_meta( $links, $plugin_file ) {
    if ( plugin_basename( THRG_BUNKER_FILE ) !== $plugin_file ) {
        return $links;
    }

    // Core or the update checker (which hooks in on admin_init, hence the later priority) already added a View details link.
    foreach ( $links as $existing ) {
        if ( is_string( $existing ) && false !== strpos( $existing, 'tab=plugin-information' ) ) {
            return $links;
        }
    }

    $name = "The Hack Repair Guy's Bunker";
    $url  = add_query_arg(
        array(
            'tab'       => 'plugin-information',
            'plugin'    => thrg_bunker_slug(),
            'TB_iframe' => 'true',
            'width'     => 600,
            'height'    => 550,
        ),
        admin_url( 'plugin-install.php' )
    );

    $links[] = sprintf(
        '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
        esc_url( $url ),
        esc_attr( sprintf( 'More information about %s', $name ) ),
        esc_attr( $name ),
        esc_html__( 'View details', 'thrg-bunker' )
    );

    return $links;
}
add_filter( 'plugin_row_meta', 'thrg_bunker_plugin_row_meta', 20, 2 );

/**
 * Make sure the WordPress details dialog is available on the Plugins screen.
 *
 * @param string $hook Current administration page.
 * @return void
 */
function thrg_bunker_enqueue_details( $hook ) {
    if ( 'plugins.php' === $hook ) {
        add_thickbox();
    }
}
add_action( 'admin_enqueue_scripts', 'thrg_bunker_enqueue_details' );

/**
 * Format inline readme markup (**bold** and [text](https://link "tooltip")) after escaping.
 *
 * @param string $text One line or paragraph of readme text.
 * @return string Safe HTML.
 */
function thrg_bunker_format_readme_inline( $text ) {
    $text = esc_html( $text );
    $text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
    return preg_replace_callback(
        '/\[([^\]]+)\]\((https?:\/\/[^\s)"\'<>]+)(?:\s+&quot;(.+?)&quot;)?\)/',
        static function ( $m ) {
            $title = isset( $m[3] ) && '' !== $m[3] ? ' title="' . $m[3] . '"' : '';
            return '<a href="' . $m[2] . '"' . $title . ' target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
        },
        $text
    );
}

/**
 * Render basic WordPress readme markup safely in the information dialog.
 * Handles paragraphs, = headings =, * bullet lists, numbered steps, **bold**
 * and [text](https://link) links. Everything is escaped first.
 *
 * @param string $source Text from the locally installed readme.
 * @return string HTML for a plugin information tab.
 */
function thrg_bunker_format_readme_section( $source ) {
    $lines     = preg_split( '/\r\n|\r|\n/', trim( $source ) );
    $output    = '';
    $paragraph = array();
    $list_type = '';

    $flush_paragraph = static function () use ( &$output, &$paragraph ) {
        if ( $paragraph ) {
            $output   .= wpautop( thrg_bunker_format_readme_inline( implode( "\n", $paragraph ) ) );
            $paragraph = array();
        }
    };
    $close_list      = static function () use ( &$output, &$list_type ) {
        if ( $list_type ) {
            $output   .= '</' . $list_type . '>';
            $list_type = '';
        }
    };

    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( '' === $line ) {
            $flush_paragraph();
            $close_list();
        } elseif ( preg_match( '/^=\s*(.*?)\s*=$/', $line, $heading ) ) {
            $flush_paragraph();
            $close_list();
            $output .= '<h4>' . esc_html( $heading[1] ) . '</h4>';
        } elseif ( preg_match( '/^(?:\*\s+|([0-9]+)\.\s+)(.*)$/', $line, $item ) ) {
            $flush_paragraph();
            $type = isset( $item[1] ) && '' !== $item[1] ? 'ol' : 'ul';
            if ( $list_type !== $type ) {
                $close_list();
                $list_type = $type;
                $output   .= '<' . $list_type . '>';
            }
            $output .= '<li>' . thrg_bunker_format_readme_inline( $item[2] ) . '</li>';
        } else {
            $close_list();
            $paragraph[] = $line;
        }
    }

    $flush_paragraph();
    $close_list();
    return $output;
}

/**
 * Read the compatibility fields from the readme header.
 *
 * @param string $readme Full readme.txt contents.
 * @return array<string,string> Keys requires, tested, requires_php (only those present).
 */
function thrg_bunker_readme_meta( $readme ) {
    $fields = array(
        'requires'     => 'Requires at least',
        'tested'       => 'Tested up to',
        'requires_php' => 'Requires PHP',
    );
    $head   = preg_split( '/^==\s*Description\s*==\s*$/mi', (string) $readme, 2 );
    $meta   = array();
    foreach ( $fields as $key => $label ) {
        if ( preg_match( '/^' . preg_quote( $label, '/' ) . ':\s*([0-9][0-9.]*)\s*$/mi', $head[0], $m ) ) {
            $meta[ $key ] = $m[1];
        }
    }
    return $meta;
}

/**
 * Supply plugin details directly from the installed readme and local artwork.
 * Only intercept information requests for our unique slug, and only while no
 * newer release is pending (then the update checker describes that release).
 * Runs after the update checker so View details always matches what is installed.
 *
 * @param mixed  $result Previous API response.
 * @param string $action Requested API action.
 * @param object $args   API arguments.
 * @return mixed
 */
function thrg_bunker_local_plugin_info( $result, $action, $args ) {
    if ( 'plugin_information' !== $action ||
        ! is_object( $args ) || ! isset( $args->slug ) ||
        thrg_bunker_slug() !== $args->slug ) {
        return $result;
    }

    // A newer release is waiting: let the update checker describe that release instead.
    $updates = get_site_transient( 'update_plugins' );
    $base    = plugin_basename( THRG_BUNKER_FILE );
    if ( is_object( $updates ) && isset( $updates->response[ $base ]->new_version ) &&
        version_compare( (string) $updates->response[ $base ]->new_version, THRG_BUNKER_VERSION, '>' ) ) {
        return $result;
    }

    $readme_path = plugin_dir_path( THRG_BUNKER_FILE ) . 'readme.txt';
    if ( ! is_readable( $readme_path ) ) {
        return $result;
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local bundled file, not a remote URL.
    $readme = file_get_contents( $readme_path );
    if ( false === $readme ) {
        return $result;
    }

    $parts    = preg_split( '/^==\s*([^\r\n]+?)\s*==\s*$/m', $readme, -1, PREG_SPLIT_DELIM_CAPTURE );
    $tabs     = array(
        'description'                => 'description',
        'installation'               => 'installation',
        'frequently asked questions' => 'faq',
        'changelog'                  => 'changelog',
    );
    $sections = array();

    $part_count = count( $parts );
    for ( $i = 1; $i + 1 < $part_count; $i += 2 ) {
        $key = strtolower( trim( $parts[ $i ] ) );
        if ( isset( $tabs[ $key ] ) ) {
            $sections[ $tabs[ $key ] ] = thrg_bunker_format_readme_section( $parts[ $i + 1 ] );
        }
    }

    if ( ! isset( $sections['description'] ) ) {
        return $result;
    }

    $info           = new stdClass();
    $info->name     = "The Hack Repair Guy's Bunker";
    $info->slug     = thrg_bunker_slug();
    $info->version  = THRG_BUNKER_VERSION;
    $info->author   = '<a href="https://hackrepair.com/" title="The Hack Repair Guy: WordPress security and hack repair">The Hack Repair Guy</a>';
    $info->homepage = 'https://github.com/hackrepair/wp-bunker';
    $info->external = true;
    foreach ( thrg_bunker_readme_meta( $readme ) as $key => $value ) {
        $info->$key = $value;
    }
    // No last_updated: the installed files only know when they were unpacked, not when the release was published.
    $info->sections = $sections;
    $asset_url      = plugin_dir_url( THRG_BUNKER_FILE ) . 'assets/';
    $info->banners  = array(
        'low'  => $asset_url . 'banner-772x250.png',
        'high' => $asset_url . 'banner-1544x500.png',
    );
    $info->icons    = array(
        '1x' => $asset_url . 'icon-128x128.png',
        '2x' => $asset_url . 'icon-256x256.png',
    );

    return $info;
}
add_filter( 'plugins_api', 'thrg_bunker_local_plugin_info', 99, 3 );
