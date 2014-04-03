<?php
/*
Plugin Name: Freifunk Metadata Shortcodes
Plugin URI: http://mschuette.name/
Description: Defines shortcodes to display Freifunk metadata
Version: 0.1
Author: Martin Schuette
Author URI: http://mschuette.name/
*/

/* gets metadata from URL, handles caching */
function ff_meta_getmetadata ($url) {
    $url_hash = hash('crc32', $url);
    
    // Caching
    if ( false === ( $metajson = get_transient( "ff_metadata_${url_hash}" ) ) ) {
        $metajson = wp_remote_retrieve_body( wp_remote_get($url) );
        set_transient( "ff_metadata_${url_hash}", $metajson, 1 * HOUR_IN_SECONDS );
    }
    $metadata = json_decode ( $metajson, $assoc = true );
    return $metadata;
}

if ( ! shortcode_exists( 'ff_state' ) ) {
    add_shortcode( 'ff_state',    'ff_meta_shortcode_handler');
}
if ( ! shortcode_exists( 'ff_services' ) ) {
    add_shortcode( 'ff_services', 'ff_meta_shortcode_handler');
}
if ( ! shortcode_exists( 'ff_contact' ) ) {
    add_shortcode( 'ff_contact',  'ff_meta_shortcode_handler');
}
// Example:
// [ff_state]
// [ff_state url="http://meta.hamburg.freifunk.net/ffhh.json"]
function ff_meta_shortcode_handler( $atts, $content, $name ) {
    $default_url = get_option( 'ff_meta_url' );
    extract(shortcode_atts( array(
        'url' => $default_url,
    ), $atts));

    if (empty($url) || false === ($metadata = ff_meta_getmetadata ($url))) {
        return '';
    }

    $outstr = "<div class=\"ff $name\">";
    switch ($name) {
        case 'ff_state':
            $state = $metadata['state'];
            $outstr .= sprintf('%s', $state['nodes']);
            break;

        case 'ff_services':
            $services = $metadata['services'];
            $outstr .= '<ul>';
            foreach ($services as $service) {
                $outstr .= sprintf('<li>%s (%s): <a href="%s">%s</a></li>',
                             $service['serviceName'], $service['serviceDescription'],
                             $service['internalUri'], $service['internalUri']);
            }
            $outstr .= '</ul>';
            break;

        case 'ff_contact':
            $outstr .= '<p>';
            $contact = $metadata['contact'];
            // Output -- rather ugly but the data is not uniform, some fields are URIs, some are usernames, ...
            if (!empty($contact['email'])) {
              $outstr .= sprintf("E-Mail: <a href=\"mailto:%s\">%s</a><br />\n", $contact['email'], $contact['email']);
            }
            if (!empty($contact['ml'])) {
              $outstr .= sprintf("Mailingliste: <a href=\"mailto:%s\">%s</a><br />\n", $contact['ml'], $contact['ml']);
            }
            if (!empty($contact['irc'])) {
              $outstr .= sprintf("IRC: <a href=\"%s\">%s</a><br />\n", $contact['irc'], $contact['irc']);
            }
            if (!empty($contact['twitter'])) {
              // catch username instead of URI
              if ($contact['twitter'][0] === "@") {
                $twitter_url = 'http://twitter.com/'.ltrim($contact['twitter'], "@");
                $twitter_handle = $contact['twitter'];
              } else {
                $twitter_url = $contact['twitter'];
                $twitter_handle = '@' . substr($contact['twitter'], strrpos($contact['twitter'], '/') + 1);
              }
              $outstr .= sprintf("Twitter: <a href=\"%s\">%s</a><br />\n", $twitter_url, $twitter_handle);
            }
            if (!empty($contact['facebook'])) {
              $outstr .= sprintf("Facebook: <a href=\"%s\">%s</a><br />\n", $contact['facebook'], $contact['facebook']);
            }
            if (!empty($contact['googleplus'])) {
              $outstr .= sprintf("G+: <a href=\"%s\">%s</a><br />\n", $contact['googleplus'], $contact['googleplus']);
            }
            if (!empty($contact['jabber'])) {
              $outstr .= sprintf("XMPP: <a href=\"xmpp:%s\">%s</a><br />\n", $contact['jabber'], $contact['jabber']);
            }
            $outstr .= '</p>';
            break;

        default:
            return "";
            break;
    }

    // Output
    $outstr .= "</div>";
    return $outstr;
}


// Options Page:
add_action( 'admin_menu', 'ff_meta_admin_menu' );
function ff_meta_admin_menu() {
    add_options_page(
        'FF Meta Plugin',      // page title
        'FF Meta',             // menu title
        'manage_options',      // req'd capability
        'ff_meta_plugin',      // menu slug
        'ff_meta_options_page' // callback function
    );
}

add_action( 'admin_init', 'ff_meta_admin_init' );
function ff_meta_admin_init() {
    register_setting(
        'ff_meta_settings-group', // group name
        'ff_meta_url'             // option name
    );
    add_settings_section(
        'ff_meta_section-one',          // ID
        'Section One',                  // Title
        'ff_meta_section_one_callback', // callback to fill
        'ff_meta_plugin'                // page to display on
    );
    add_settings_field(
        'ff_meta_url',           // ID
        'URL of meta.json',      // Title
        'ff_meta_url_callback',  // callback to fill field
        'ff_meta_plugin',        // menu page=slug to display field on
        'ff_meta_section-one'    // section to display the field in
    );
}

function ff_meta_section_one_callback() {
    echo 'This Plugin provides shortcodes to display information from the Freifunk meta.json.';
}

function ff_meta_url_callback() {
    $url = get_option( 'ff_meta_url' );
    echo "<input type='text' name='ff_meta_url' value='$url' />";
}

function ff_meta_options_page() {
    ?>
    <div class="wrap">
        <h2>Freifunk Meta Plugin Options</h2>
        <form action="options.php" method="POST">
            <?php settings_fields( 'ff_meta_settings-group' ); ?>
            <?php do_settings_sections( 'ff_meta_plugin' ); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

