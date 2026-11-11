<?php
/**
 * Plugin Name:       Maison GR Sitemap
 * Plugin URI:        https://github.com/maison-gr/maisongr-sitemap-wordpress
 * Description:       One sitemap, two readers: the XML search engines need at /sitemap.xml, and a page in your theme's own colours and type for people who open it.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Maison GR
 * Author URI:        https://www.maisongr.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       maisongr-sitemap
 *
 * @package MaisonGR\Sitemap
 */

defined( 'ABSPATH' ) || exit;

define( 'MAISONGR_SITEMAP_VERSION', '1.0.0' );
define( 'MAISONGR_SITEMAP_DIR', plugin_dir_path( __FILE__ ) );

require_once MAISONGR_SITEMAP_DIR . 'includes/class-strings.php';
require_once MAISONGR_SITEMAP_DIR . 'includes/class-xml.php';
require_once MAISONGR_SITEMAP_DIR . 'includes/class-page.php';
require_once MAISONGR_SITEMAP_DIR . 'includes/class-theme.php';
require_once MAISONGR_SITEMAP_DIR . 'includes/class-entries.php';
require_once MAISONGR_SITEMAP_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( \MaisonGR\Sitemap\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \MaisonGR\Sitemap\Plugin::class, 'deactivate' ) );

\MaisonGR\Sitemap\Plugin::boot();
