<?php
/*
Plugin Name: Freifunk Metadata Shortcodes
Plugin URI: http://mschuette.name/
Description: Defines shortcodes to display Freifunk metadata
Version: 0.2
Author: Martin Schuette
Author URI: http://mschuette.name/
*/

define('FF_META_DEFAULT_CACHETIME', 15);
define('FF_META_DEFAULT_DIR', 'https://raw.githubusercontent.com/freifunk/directory.api.freifunk.net/master/directory.json');
define('FF_META_DEFAULT_CITY', 'hamburg');

/* gets metadata from URL, handles caching */
function ff_meta_getmetadata ($url) {
    $url_hash = hash('crc32', $url);
    
    // Caching
    if ( false === ( $metajson = get_transient( "ff_metadata_${url_hash}" ) ) ) {
        $metajson  = wp_remote_retrieve_body( wp_remote_get($url) );
        $cachetime = get_option( 'ff_meta_cachetime', FF_META_DEFAULT_CACHETIME) * MINUTE_IN_SECONDS;
        set_transient( "ff_metadata_${url_hash}", $metajson, $cachetime );
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
// [ff_state hamburg]
function ff_meta_shortcode_handler( $atts, $content, $name ) {
    // $atts[0] holds the city name, if given
    if (empty($atts[0])) {
        $city = get_option( 'ff_meta_city', FF_META_DEFAULT_CITY );
    } else {
        $city = $atts[0];
    }

    if (false === ($directory = ff_meta_getmetadata ( FF_META_DEFAULT_DIR ))
        || empty($directory[$city])) {
        return "<!-- FF Meta Error: cannot get directory.json, or no URL for '$city' -->\n";
    }
    $url = $directory[$city];

    if (false === ($metadata = ff_meta_getmetadata ($url))) {
        return "<!-- FF Meta Error: cannot get metadata from $url -->\n";
    }

    $outstr = "<div class=\"ff $name\">";
    switch ($name) {
        case 'ff_state':
            $state = $metadata['state'];
            $outstr .= sprintf('%s', $state['nodes']);
            break;

        case 'ff_services':
            $outstr .= '<ul>';
            if (isset($metadata['services'])) {
                $services = $metadata['services'];
                foreach ($services as $service) {
                    $outstr .= sprintf('<li>%s (%s): <a href="%s">%s</a></li>',
                                 $service['serviceName'], $service['serviceDescription'],
                                 $service['internalUri'], $service['internalUri']);
                }
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
        'ff_meta_cachetime'       // option name
    );
    register_setting(
        'ff_meta_settings-group', // group name
        'ff_meta_city'            // option name
    );
    add_settings_section(
        'ff_meta_section-one',          // ID
        'Section One',                  // Title
        'ff_meta_section_one_callback', // callback to fill
        'ff_meta_plugin'                // page to display on
    );
    add_settings_field(
        'ff_meta_city',          // ID
        'Default community',     // Title
        'ff_meta_city_callback',  // callback to fill field
        'ff_meta_plugin',        // menu page=slug to display field on
        'ff_meta_section-one',   // section to display the field in
        array('label_for' => 'ff_meta_city_id')  // ID of input element
    );
    add_settings_field(
        'ff_meta_cachetime',     // ID
        'Cache time',            // Title
        'ff_meta_cachetime_callback', // callback to fill field
        'ff_meta_plugin',        // menu page=slug to display field on
        'ff_meta_section-one',   // section to display the field in
        array('label_for' => 'ff_meta_cachetime_id')  // ID of input element
    );
}

function ff_meta_section_one_callback() {
    echo 'This Plugin provides shortcodes to display information from the Freifunk meta.json.';
}

function ff_meta_cachetime_callback() {
    $time = get_option( 'ff_meta_cachetime', FF_META_DEFAULT_CACHETIME );
    echo "<input type='number' name='ff_meta_cachetime' id='ff_meta_cachetime_id' class='small-text code' value='$time' /> minutes"
        ."<p class='description'>Data from external URLs is cached for this number of minutes.</p>";
}

function ff_meta_city_callback() {
    if (false === ($directory = ff_meta_getmetadata ( FF_META_DEFAULT_DIR ))) {
        // TODO: error handling
        return;
    }
    $default_city = get_option( 'ff_meta_city', FF_META_DEFAULT_CITY );

    echo "<select name='ff_meta_city' id='ff_meta_city_id' size='1'>";
    foreach (array_keys($directory) as $city) {
        $prettycity = ucwords(str_replace(array('_', '-'), ' ', $city));
        $selected = selected( $default_city, $city );
        echo "<option value='$city' $selected>$prettycity</option>";
    }
    echo "</select>";
    echo "<p class='description'>This is the default city parameter.</p>";
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

register_uninstall_hook( __FILE__, 'ff_meta_uninstall_hook' );
function ff_meta_uninstall_hook() {
    delete_option( 'ff_meta_city' );
    delete_option( 'ff_meta_cachetime' );
}
