<?php
/*
Plugin Name: WP Visitor
Plugin URI: http://connectpx.com/
Description: WordPress visitor info e.g IP, Location, Weather info etc.
Version: 1.0.0
Author: ConnectPX
Author URI: http://connectpx.com/
Text Domain: wp_visitor
Domain Path: /lang
*/

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('WP_Visitor') ) :

class WP_Visitor {
	
	/** @var string The plugin version number. */
	var $version = '1.0.0';
	
	/** @var array The plugin settings array. */
	var $settings = array();
	
	/** @var array The plugin data array. */
	var $data = array();
	
	/** @var array Storage for class instances. */
	var $instances = array();
	
	/**
	 * __construct
	 *
	 * Class constructor
	 *
	 * @since	1.0.0
	 *
	 * @param	void
	 * @return	void
	 */	
	function __construct() {
		// Define constants.
		if(!defined('WP_VISITOR_PATH')) {
			define( 'WP_VISITOR_PATH', plugin_dir_path( __FILE__ ) );
		}
		if(!defined('WP_VISITOR_BASENAME')) {
			define( 'WP_VISITOR_BASENAME', plugin_basename( __FILE__ ) );
		}	
		if(!defined('WP_VISITOR_IPINFO_KEY')) {
			define('WP_VISITOR_IPINFO_KEY', 'wp_visitor_ip_info_');
		}	
		if(!defined('WP_VISITOR_WEATHER_KEY')) {
			define('WP_VISITOR_WEATHER_KEY', 'wp_visitor_weather_info_');
		}	

		// Plugin settings
		$this->settings = apply_filters('wp_visitor_settings', [
			'owm_url' => 'http://api.openweathermap.org/data/2.5/',
			'ip_info_url' => 'https://ipinfo.io/{IP}/json',
			'owm_app_id' => '485dc310b42fe2df461c3e67e1ee61a4',
			'default_city' => 'New York',
			'default_country' => 'US',
			'temprature_unit' => 'F',
			'temprature_template' => '{TEMP}&#8457;, {CITY}',
			'weather_cache_time' => 1800, // cache weather detail for 30 mins
			'ip_info_cache_time' => (12 * HOUR_IN_SECONDS), // cache ip for 12 hours max
			'welcome_messages' => [
				'good_day' => 'Good Day',
				'good_morning' => 'Good Morning',
				'good_afternoon' => 'Good Afternoon',
				'good_evening' => 'Good Evening',
				'good_night' => 'Good Night',
			],
			'welcome_message_template' => 'Welcom, {MESSAGE}',
			'visitor_info_template' => '{IP}, {CITY}',
		]);

		// Include utility functions.
		include_once( WP_VISITOR_PATH . 'includes/wpv-utility-functions.php');

		// Actions
		add_action('init', array( $this, 'init' ), 1);

		// Shortcodes
		add_shortcode('wp_visitor_temprature', array( $this, 'temprature_shortcode' ) );
		add_shortcode('wp_visitor_welcome_message', array( $this, 'welcome_message_shortcode' ) );
		add_shortcode('wp_visitor_info', array( $this, 'visitor_info_shortcode' ) );
	}
	
	/**
	 * init
	 *
	 * init callback
	 *
	 * @since	1.0.0
	 *
	 * @param	void
	 * @return	void
	 */	
	function init() {

	}
	
	/**
	 * get_setting
	 *
	 * Get setting
	 *
	 * @since	1.0.0
	 *
	 * @param	$key: settings key, $default: default value
	 * @return	Setting value
	 */	
	function get_setting($key, $default = null) {
		return isset($this->settings[$key]) ? $this->settings[$key] : $default;
	}
	
	/**
	 * get_data
	 *
	 * Get value from array
	 *
	 * @since	1.0.0
	 *
	 * @param	$data: data array, $key: data key, $default: default value
	 * @return	data value
	 */	
	function get_data($data, $key, $default = null) {
		return isset($data[$key]) ? $data[$key] : $default;
	}

	/**
	 * ip_address
	 *
	 * Get visitor IP Address
	 *
	 * @since	1.0.0
	 *
	 * @param	void
	 * @return	IP Address
	 */	
	function ip_address() {
		$ip = '';

	    if (isset($_SERVER['HTTP_CLIENT_IP']))
	        $ip = $_SERVER['HTTP_CLIENT_IP'];
	    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
	        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	    else if(isset($_SERVER['HTTP_X_FORWARDED']))
	        $ip = $_SERVER['HTTP_X_FORWARDED'];
	    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
	        $ip = $_SERVER['HTTP_FORWARDED_FOR'];
	    else if(isset($_SERVER['HTTP_FORWARDED']))
	        $ip = $_SERVER['HTTP_FORWARDED'];
	    else if(isset($_SERVER['REMOTE_ADDR']))
	        $ip = $_SERVER['REMOTE_ADDR'];
	    else
	        $ip = 'UNKNOWN';

	    return apply_filters( 'wp_visitor_ip', $ip );
	}

	/**
	 * ip_hash
	 *
	 * Convert IP into hash
	 *
	 * @since	1.0.0
	 *
	 * @param	$ip
	 * @return	Hashed IP Address
	 */	
	function ip_hash( $ip = null ) {
		$ip = $ip ? $ip : $this->ip_address();
	    return str_replace('.', '', $ip);
	}

	/**
	 * str_to_hash
	 *
	 * Convert string to hash
	 *
	 * @since	1.0.0
	 *
	 * @param	$str
	 * @return	Hashed value
	 */	
	function str_to_hash( $str ) {
	    return str_replace(' ', '_', strtolower(trim($str)));
	}
	
	/**
	 * call_weather_api
	 *
	 * Call weather API
	 *
	 * @since	1.0.0
	 *
	 * @param	$city: visitor city, $country: visitor country
	 * @return	Weather data
	 */	
	function call_weather_api( $city, $country, $ip = null ) {
		// Filter params
		$weather_params = apply_filters( 'wp_visitor_weather_api_params', [
			'city' => $city, 
			'country' => $country,
			'ip' => $ip
		]);

		extract($weather_params);

		// Create transient name to store data in cache based on input provided
		$transient_name = WP_VISITOR_WEATHER_KEY . $this->ip_hash( $ip ) . '_' . $this->str_to_hash( $city );

		// Get weather info from transient if already existed
		if( get_transient( $transient_name ) )  {
			$weather_detail = get_transient( $transient_name );

			if( !empty( $weather_detail ) )
				return $weather_detail;
		}

		// Create Open Weather Map API Url
		$owm_app_id = $this->get_setting('owm_app_id');
		$remote_url = $this->get_setting('owm_url') . "weather?q={$city},{$country}&APPID={$owm_app_id}";

		// GET Weather detail from API
		$response = wp_remote_get( $remote_url );

		if( ! is_wp_error( $response ) && isset($response['body']) && $response['body'] != '' )  {
			$weather_detail = json_decode( $response['body'] );
			if( $weather_detail && !empty( $weather_detail->main->temp ) ) {

				// Filter response
				$weather_detail = apply_filters( 'wp_visitor_weather_api_response', $weather_detail);

				// Save Weather info in transient for later use
				set_transient( $transient_name, $weather_detail, apply_filters( 'wp_visitor_weather_detail_cache', $this->get_setting('weather_cache_time', 1800) ) );

				return $weather_detail;
			}
		}
	}

	/**
	 * get_ip_info
	 *
	 * Get IP info from visitor IP
	 *
	 * @since	1.0.0
	 *
	 * @param	$city: visitor city, $country: visitor country
	 * @return	Weather data
	 */	
	function get_ip_info( $ip = null ) {
		// Visitot IP
		$ip_address = ( $ip ) ? $ip : $this->ip_address();
		$transient_name = WP_VISITOR_IPINFO_KEY . $this->ip_hash( $ip_address );

		// Get IP info from transient if already existed
		if( get_transient( $transient_name ) )  {
			$ip_info = get_transient( $transient_name );
		}

		// Refetch if ip info not existed in session
		if( empty($ip_info['city']) || empty($ip_info['country']) || empty($ip_info['timezone'])) {
			$ip_address = '110.39.163.114';

			$remote_url = @str_replace("{IP}", $ip_address, $this->get_setting('ip_info_url'));

			$request  = @file_get_contents($remote_url);
			$ip_info  =  !empty($request) ? json_decode($request, true) : [];
			
			// Filter response
			$ip_info = apply_filters( 'wp_visitor_ip_info', $ip_info);

			// GET ip detail from API
			// $request = wp_remote_get( $remote_url );
			// $response = wp_remote_retrieve_body( $request );

			if( $ip_info ) {
				// Save IP info in transient for later use
				set_transient( $transient_name, $ip_info, apply_filters( 'wp_visitor_ip_info_cache', $this->get_setting('ip_info_cache_time', 31104000) ) );
			}
		}

		return $ip_info;
	}

	/**
	 * get_location_temprature
	 *
	 * Formated temprature with location name
	 *
	 * @since	1.0.0
	 *
	 * @param	$ip: ip address for which temprature will be fetched
	 * @return	formated temprature with location
	 */
	function get_location_temprature( $ip = null, $template = null ) {
		// GET VISITOR IP INFO
		$ip_info  =  $this->get_ip_info( $ip );

		$city = (isset($ip_info['city'])) ? $ip_info['city'] : "";
		$country = (isset($ip_info['country'])) ? $ip_info['country'] : "";
		
		// IF CITY OR COUNTRY NOT FOUND IN IP INFO, USE DEFAULT
		if( $city == "" || $country == "" ) {
			$city = $this->get_setting('default_city');
			$country = $this->get_setting('default_country');
		}

		// GET WEATHER DETAIL
		$weather_detail = $this->call_weather_api( $city, $country );

		// IF WEATHER DETAIL NOT FOUND TRY WITH DEFAULT CITY AND COUNTRY
		if( ! $weather_detail ) {
			$weather_detail = $this->call_weather_api( $default_city, $default_country );
		}

		if( ! empty( $weather_detail ) ) {
			// GET TEMPRATURE FROM WEATHER DETAIL
			$temprature = (!empty( $weather_detail->main->temp )) ? $weather_detail->main->temp : 0;

			if( $temprature ) {
				$kelvin = $temprature;

				// CONVERT TEMPRATURE FROM KALVIN TO F/C
				$converted = ($this->get_setting('temprature_unit', 'F') == 'F') ? ( 9/5 * ($kelvin - 273.15) + 32 ) : ( $kelvin - 273.15 );

				// REPLACE TOKENS
	    		$template = ($template) ? $template : $this->get_setting('temprature_template');
				$format = str_replace([
					'{TEMP}',
					'{CITY}',
					'{COUNTRY}'
				], [
					round($converted),
					$city,
					$country
				], $template);

				// Filter format
				$format = apply_filters( 'wp_visitor_temprature_format', $format, $converted, $city, $country);

				return sprintf('<span class="wp-visitor-temprature">%s</span>', $format);
			}
		}

		return sprintf("%s&#8457;, %s", 'UNKNOWN', $city);
	}

	/**
	 * welcome_message_by_time
	 *
	 * Formated welcome message by visitor time
	 *
	 * @since	1.0.0
	 *
	 * @param	$ip: visitor ip
	 * @return	formated temprature with location
	 */
	function welcome_message_by_time( $ip = null, $template = null ) {
		// GET VISITOR IP INFO
		$ip_info  =  $this->get_ip_info( $ip );

		// Get timezone from ip info
		$timezone = (isset($ip_info['timezone'])) ? $ip_info['timezone'] : "";

		// Get default timezone so later we can change back
		$default_timezone = date_default_timezone_get();

		// Change default timezone to visitor timezone
		if( $timezone != "" ) {
			date_default_timezone_set( $timezone );
		}

		// Welcome messages array
		$welcome_messages = $this->get_setting('welcome_messages', []);

		// Default message
		$message = $this->get_data($welcome_messages, 'good_day', 'Good Day');

	    $current_time = date("H");
	    # $timezone = date("e");

	    // 6AM - 11AM
	    if ($current_time <= 11 && $current_time >= 6 ) {
			$message = $this->get_data($welcome_messages, 'good_morning', 'Good Morning');
		}
		//12PM - 4PM
		else if ($current_time >= 12 && $current_time <= 16) {
			$message = $this->get_data($welcome_messages, 'good_afternoon', 'Good Afternoon');
		}
		//5PM - 7PM
		elseif ($current_time >= 17 && $current_time <= 19) {
			$message = $this->get_data($welcome_messages, 'good_evening', 'Good Evening');
		}
		//07PM - 05AM
		else {
			$message = $this->get_data($welcome_messages, 'good_night', 'Good Night');
		}

		// Filter messgae
		$message = apply_filters( 'wp_visitor_welcome_message', $message, $current_time, $timezone, $default_timezone);

		// Reset default timezone
	    date_default_timezone_set( $default_timezone );

	    // REPLACE TOKENS
	    $class_name = 'wp-visitor-welcome-message-' . $this->str_to_hash( $message );

	    $template = ($template) ? $template : $this->get_setting('welcome_message_template');
		$format = str_replace([
			'{MESSAGE}',
		], [
			$message
		], $template);

		// Filter format
		$format = apply_filters( 'wp_visitor_welcome_message_format', $format, $message);
		return sprintf('<span class="wp-visitor-welcome-message %s">%s</span>', $class_name, $format);

	    return $message;
	}

	/**
	 * show_visitor_info
	 *
	 * Show visitor info e.g IP, city or country etc
	 *
	 * @since	1.0.0
	 *
	 * @param	$ip: visitor ip
	 * @return	formated info
	 */
	function show_visitor_info( $ip = null, $template = null ) {
		// GET VISITOR IP INFO
		$ip_info  =  $this->get_ip_info( $ip );

		$template = ($template) ? $template : $this->get_setting('visitor_info_template');
		$format = str_replace([
			'{IP}',
			'{CITY}',
			'{REGION}',
			'{COUNTRY}',
			'{LAT_LONG}',
			'{POSTAL_CODE}',
			'{TIMEZONE}',
		], [
			$this->get_data($ip_info, 'ip', ''),
			$this->get_data($ip_info, 'city', ''),
			$this->get_data($ip_info, 'region', ''),
			$this->get_data($ip_info, 'country', ''),
			$this->get_data($ip_info, 'loc', ''),
			$this->get_data($ip_info, 'postal', ''),
			$this->get_data($ip_info, 'timezone', ''),
		], $template);

		// Filter format
		$format = apply_filters( 'wp_visitor_info_format', $format, $ip_info);
		return sprintf('<span class="wp-visitor-info">%s</span>', $format);
	}

	/**
	 * temprature_shortcode
	 *
	 * Temprature shortcode callback
	 *
	 * @since	1.0.0
	 *
	 * @param	$atts: shortcode atts
	 * @return	formated temprature with location
	 */
	function temprature_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'ip' => null,
			'template' => '',
		), $atts );

		extract($atts);

		return $this->get_location_temprature( $ip, $template );
	}

	/**
	 * welcome_message_shortcode
	 *
	 * Welcome message shortcode callback
	 *
	 * @since	1.0.0
	 *
	 * @param	$atts: shortcode atts
	 * @return	formated welcome message
	 */
	function welcome_message_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'ip' => null,
			'template' => '',
		), $atts );

		extract($atts);

		return $this->welcome_message_by_time( $ip, $template );
	}

	/**
	 * visitor_info_shortcode
	 *
	 * Visitor info shortcode callback
	 *
	 * @since	1.0.0
	 *
	 * @param	$atts: shortcode atts
	 * @return	formated visitor info
	 */
	function visitor_info_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'ip' => null,
			'template' => '',
		), $atts );

		extract($atts);

		return $this->show_visitor_info( $ip, $template );
	}
}

/*
 * WP_Visitor
 *
 * The main function responsible for returning the one true WP_Visitor Instance to functions everywhere.
 * Use this function like you would a global variable, except without needing to declare the global.
 *
 * Example: <?php $WP_Visitor = WP_Visitor(); ?>
 *
 * @since	1.0.0
 *
 * @param	void
 * @return	WP_Visitor
 */
function WP_Visitor() {
	global $WP_Visitor;
	
	// Instantiate only once.
	if( !isset($WP_Visitor) ) {
		$WP_Visitor = new WP_Visitor();
	}
	return $WP_Visitor;
}

// Instantiate.
WP_Visitor();

endif; // class_exists check
