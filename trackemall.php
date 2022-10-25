<?php
/**
 * @package trackemall
 * @version 0.1 
 */
/*
Plugin Name: trackemall
Plugin URI: https://wordpress.org/plugins/hello-dolly/
Description: GDPR compliant tracking solution, that bridges server side tracking to google analytics
Author: Dennis Kroeger
Version: 0.0.1
Author URI: https://
Text Domain: trck 
*/

//plugin activation
register_activation_hook( __FILE__, 'prefix_create_table' );
function prefix_create_table() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE trackemall (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		ip varchar(16) NOT NULL,
		user_agent varchar(224) NOT NULL,
		cid varchar(37) NOT NULL,
		lang varchar(64),
		PRIMARY KEY (id)
	) $charset_collate;";

	if ( ! function_exists('dbDelta') ) {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	}

	dbDelta( $sql );
}


//Parse the GA Cookie
function gaParseCookie() {
	if (isset($_COOKIE['_ga'])) {
		list($version, $domainDepth, $cid1, $cid2) = explode('.', $_COOKIE["_ga"], 4);
		$contents = array('version' => $version, 'domainDepth' => $domainDepth, 'cid' => $cid1 . '.' . $cid2);
		$cid = $contents['cid'];
	} else {
		$cid = gaGenerateUUID();
	}
	return $cid;
}

//Generate UUID
function gaGenerateUUID() {
	return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
		mt_rand(0, 0xffff), mt_rand(0, 0xffff),
		mt_rand(0, 0xffff),
		mt_rand(0, 0x0fff) | 0x4000,
		mt_rand(0, 0x3fff) | 0x8000,
		mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
	);
}

//Send Data to Google Analytics
//https://developers.google.com/analytics/devguides/collection/protocol/v1/devguide#event
function gaSendData($data) {
	$getString = 'https://ssl.google-analytics.com/collect';
	$getString .= '?payload_data&';
	$getString .= http_build_query($data);
	$result = wp_remote_get($getString);
	return $result;
}

//Send Pageview Function for Server-Side Google Analytics
function ga_send_pageview($hostname=null, $page=null, $title=null, $cid=null,$ip,$ua,$lang) {
	$tid = 'UA-108425811-3';
	$data = array(
		'v' => 1,
		'tid' => $tid, //@TODO: Change this to your Google Analytics Tracking ID.
		'cid' => $cid,
		//'uid' => $cid, //user id, alternative to cid
		't' => 'pageview',
		'dh' => $hostname, //Document Hostname "gearside.com"
		'dp' => $page, //Page "/something"
		'dt' => $title, //Title
		//'aip' => 1, //anonymize ip
		'ds' => 'web', //data source app/web/crm/etc
		'uip' => $ip, //user ip address
		'ua' => $ua, //user agent
		'ul' => $lang //user language
		//'dr' =>  //document referrer
	);
	gaSendData($data);
}

//Send Event Function for Server-Side Google Analytics
function ga_send_event($category=null, $action=null, $label=null, $cid=null) {
	$data = array(
		'v' => 1,
		'tid' => $tid, //@TODO: Change this to your Google Analytics Tracking ID.
		'cid' => $cid,
		't' => 'event',
		'ec' => $category, //Category (Required)
		'ea' => $action, //Action (Required)
		'el' => $label //Label
	);
	gaSendData($data);
}

function test(){
	global $wp;
	global $wpdb;
	$path = $_SERVER['REQUEST_URI']; //pfad
	$user = $_SERVER["HTTP_USER_AGENT"];
	$lang = locale_accept_from_http($_SERVER["HTTP_ACCEPT_LANGUAGE"]); //lang function to delimit and interpret lang string
	//$lang = locale_accept_from_http($_SERVER["HTTP_ACCEPT_LANGUAGE"]); //lang function to delimit and interpret lang string
	$ip = $_SERVER["REMOTE_ADDR"];//ip	
	$serverName = $_SERVER["SERVER_NAME"];
	var_dump($_SERVER);

	$cid = $wpdb->get_var( 
		$wpdb->prepare( "SELECT cid from trackemall where ip = %s and user_agent = %s", $ip, $user )
	);


	if ($cid) {

	} else {
		$cid = gaGenerateUUID();
		$wpdb->insert('trackemall', 
			array( 
				'ip' => $ip, 
				'lang' => $lang,
				'user_agent' => $user,
				'cid' => $cid
			)
		);
	}
/*
*/

    //$current_slug = add_query_arg( array(), $wp->request );
    ga_send_pageview($serverName,$path, get_the_title(),$cid,$ip,$user,$lang);
}
add_action( 'wp', 'test' );
?>