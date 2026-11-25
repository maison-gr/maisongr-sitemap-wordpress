<?php
/**
 * Test-only endpoint, mounted into Playground beside WordPress (never shipped): the palette
 * Theme::complete() makes from a pair of colours, and the contrast it measures, so
 * test/plugin.test.mjs can check colours no installed theme happens to use.
 *
 * POST a JSON body: { "background": "#fff", "foreground": "#000", "accent": null, "muted": null }.
 */

define( 'ABSPATH', __DIR__ . '/' );

require '/wordpress/wp-content/plugins/maisongr-sitemap/includes/class-theme.php';

use MaisonGR\Sitemap\Theme;

$input = json_decode( (string) file_get_contents( 'php://input' ), true );

header( 'Content-Type: application/json' );
echo json_encode(
	array(
		'palette'  => Theme::complete( $input['background'], $input['foreground'], $input['accent'] ?? null, $input['muted'] ?? null ),
		'contrast' => Theme::contrast( $input['muted'] ?? $input['foreground'], $input['background'] ),
	),
	JSON_UNESCAPED_SLASHES
);
