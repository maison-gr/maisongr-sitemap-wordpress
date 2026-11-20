<?php
/**
 * Test-only endpoint (never shipped): what an administrator sees in the plugin's admin notice,
 * and whether Jetpack's Sitemaps module is on, as JSON.
 */

define( 'WP_ADMIN', true );
require '/wordpress/wp-load.php';
header( 'Content-Type: application/json; charset=utf-8' );
wp_set_current_user( 1 );
ob_start();
\MaisonGR\Sitemap\Plugin::notice();
echo wp_json_encode(
	array(
		'notice'  => wp_strip_all_tags( (string) ob_get_clean() ),
		'jetpack' => class_exists( 'Jetpack' ) && \Jetpack::is_module_active( 'sitemaps' ),
	)
);
