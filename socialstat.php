<?php

/**
 * @package SocialStat
 */
/*
Plugin Name: SocialStat
Plugin URI: https://socialstat.co.uk/
Description: Social stat returns your follower count for various social networking sites.
Version: 0.0.1
Author: Lee Crosdale
Author URI: https://leecrosdale.com
License: GPLv2 or later
Text Domain: leecrosdale
*/

register_activation_hook( __FILE__, 'socialstat_install' );

$ss_db_version = '1.0';

function socialstat_install() {

    global $wpdb;
	global $ss_db_version;

    $table_name = $wpdb->prefix . "socialstat_api";
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE " . $table_name . " (id mediumint(9) NOT NULL AUTO_INCREMENT, api_token tinytext NOT NULL,PRIMARY KEY  (id)) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	add_option( 'socialstat_db_version', $ss_db_version );

}


// Admin Menu
add_action('admin_menu', 'socialstat_setup_menu');
function socialstat_setup_menu() {
    add_menu_page('SocialStat - Configuration', 'SocialStat', 'manage_options','social-stat','socialstat_init');
}

function socialstat_check_update_key() {

    global $wpdb;

    if (isset($_POST['socialstat_api_key'])) {

        $table = $wpdb->prefix . 'socialstat_api';

        // Check that the row doesn't exist
        $rows = $wpdb->get_results('SELECT * from ' . $table);

        if (empty($rows)) {
            $wpdb->insert($table,['api_token' => $_POST['socialstat_api_key']]);
        } else {
            $wpdb->update($table,['api_token' => $_POST['socialstat_api_key']], ['api_token' => $rows[0]->api_token]);
        }

    }
}


// Admin Panel Setup
function socialstat_init() {

    global $wpdb;
	$table = $wpdb->prefix . 'socialstat_api';

	socialstat_check_update_key();

	$rows = $wpdb->get_results('SELECT * from ' . $table);

	if (empty($rows)) {
		$token = '';
	} else {
		$token = $rows[0]->api_token;
	}

    echo "<h1>SocialStat</h1>";

    ?>

    <form method="post">
        <label for="ss_key">SocialStat Key</label>
        <input type="password" name="socialstat_api_key" id="socialstat_api_key" value="<?=$token?>"/>
        <button type="submit">Update</button>
    </form>

    <hr />

    <?php

}

function social_stat_get_stat($type) {

	global $wpdb;
	global $wp_version;


	$table = $wpdb->prefix . 'socialstat_api';
	$rows = $wpdb->get_results('SELECT * from ' . $table);

	if (empty($rows)) {
		return "Token Undefined";
	} else {

		$token = $rows[0]->api_token;
		$url = 'https://socialstat.co.uk/' . $type . '/followers';

		$args = array(
			'timeout'     => 5,
			'redirection' => 5,
			'httpversion' => '1.0',
			'user-agent'  => 'WordPress/' . $wp_version . '; ' . home_url(),
			'blocking'    => true,
			'headers'     => array('Authorization' => 'Bearer ' . $token),
			'cookies'     => array(),
			'body'        => null,
			'compress'    => false,
			'decompress'  => true,
			'sslverify'   => true,
			'stream'      => false,
			'filename'    => null
		);

		$response = wp_remote_get( $url, $args );
		if ( is_array( $response ) ) {
			$body   = $response['body']; // use the content
            $data = json_decode($body);
            return $data->followers;

		} else {
		    return "Unable to Contact Site";
        }
	}
}


// Shortcodes for Pages
add_shortcode('socialstat', 'socialstat_process_shortcode');

function socialstat_process_shortcode($atts) {
	$a = shortcode_atts(array('service'=>'-1'), $atts);

	// No ID value
	if(strcmp($a['service'], '-1') == 0){
		return "";
	}

	$service = $a['service'];

    return "<span style='color:blue;'>" . $service  . " - " . social_stat_get_stat($service) . "</span>";
}

