<?php
/**
 * __pre
 *
 * Simple function to debug info
 *
 * @since	1.0.1
 *
 * @param $code code to debug.
 * @return	pretty print code
 */
if( ! function_exists('__pre') ) {
	function __pre( $code, $hidden = false ) {
		$style = ($hidden) ? 'display: none' : "";
		echo '<pre style="'.$style.'">';
		print_r($code);
		echo '</pre>';
	}
}

