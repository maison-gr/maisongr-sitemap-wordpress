<?php
/**
 * Test-only endpoint, mounted into Playground beside WordPress (never shipped): renders a
 * fixture with the plugin's pure classes, so test/plugin.test.mjs can compare the output
 * with the maisongr-sitemap package's, byte for byte.
 *
 * POST a JSON body: { "entries": [...], "parts": [...], "theme": {...}, "options": {...} }.
 */

define( 'ABSPATH', __DIR__ . '/' );

$plugin = '/wordpress/wp-content/plugins/maisongr-sitemap/';
require $plugin . 'includes/class-strings.php';
require $plugin . 'includes/class-xml.php';
require $plugin . 'includes/class-page.php';

use MaisonGR\Sitemap\Page;
use MaisonGR\Sitemap\Xml;

$input   = json_decode( (string) file_get_contents( 'php://input' ), true );
$entries = $input['entries'] ?? array();
$parts   = $input['parts'] ?? array();
$theme   = $input['theme'];
$options = $input['options'] ?? array();
$rules   = (string) preg_replace( '#^/\*.*?\*/\n#s', '', (string) file_get_contents( $plugin . 'assets/page-rules.css' ), 1 );

header( 'Content-Type: application/json' );
echo json_encode(
	array(
		'page'     => Page::urlset( $entries, $theme, $options ),
		'index'    => Page::index( $parts, $theme, $options ),
		'css'      => Page::css( $theme, $rules ),
		'xmlCss'   => Page::xml_css( $theme, $options ),
		'xml'      => Xml::urlset( $entries, '/sitemap.xml?asset=xml.css' ),
		'indexXml' => Xml::index( $parts, '/sitemap.xml?asset=xml.css' ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
