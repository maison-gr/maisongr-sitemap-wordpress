<?php
/**
 * Removes what the plugin stored: its cached list and its rewrite-rules marker.
 *
 * @package MaisonGR\Sitemap
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_transient( 'maisongr_sitemap_entries' );
delete_option( 'maisongr_sitemap_rules' );
