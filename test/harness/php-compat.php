<?php
/**
 * Test-only script (never shipped): loads every class of the plugin and runs the code that needs
 * no WordPress, with every notice and deprecation a failure. Two ways, same checks:
 *
 * - From the command line, for a PHP version WordPress Playground can't run yet; CI runs it in the
 *   official php image: php test/harness/php-compat.php maisongr-sitemap
 * - In Playground, on each PHP version of the test matrix: test/plugin.test.mjs fetches it.
 *
 * It prints "ok - …" per check, or "Failed: …" and stops. Plain PHP 7.4, like the plugin.
 */

$cli = 'cli' === PHP_SAPI;
if ( ! $cli ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
}
$fail = static function ( string $what ) use ( $cli ): void {
	echo "Failed: {$what}\n";
	exit( $cli ? 1 : 0 );
};

error_reporting( E_ALL );
set_error_handler(
	static function ( int $level, string $message, string $file, int $line ) use ( $fail ): bool {
		$fail( "PHP error {$level}: {$message} in {$file}:{$line}" );
		return true;
	}
);

define( 'ABSPATH', __DIR__ . '/' );
$plugin = rtrim( $argv[1] ?? '/wordpress/wp-content/plugins/maisongr-sitemap', '/' ) . '/';

// Every class file: a syntax change or a deprecated declaration fails here.
foreach ( glob( $plugin . 'includes/*.php' ) as $file ) {
	require_once $file;
}

use MaisonGR\Sitemap\Page;
use MaisonGR\Sitemap\Theme;
use MaisonGR\Sitemap\Xml;

$check = static function ( bool $ok, string $what ) use ( $fail ): void {
	if ( ! $ok ) {
		$fail( $what );
	}
	echo "ok - {$what}\n";
};

$check( abs( Theme::contrast( '#000000', '#ffffff' ) - 21.0 ) < 0.01, 'contrast of black on white is 21:1' );
$faint = Theme::contrast( '#0000004d', '#ffffff' );
$check( $faint > 2 && $faint < 2.3, 'a translucent colour is measured as it shows' );
$palette = Theme::complete( '#fcfcfc', '#000000bf' );
$check( 'color-mix(in srgb, #000000bf 68%, #fcfcfc)' === $palette['muted'], 'a palette is made from a pair with alpha' );

$theme   = array( 'name' => 'Exemple', 'light' => Theme::DEFAULT_PALETTE, 'fonts' => array() );
$entries = array(
	array( 'loc' => 'https://exemple.ca/', 'lastmod' => '2026-11-25' ),
	array( 'loc' => 'https://exemple.ca/a-propos/', 'lastmod' => '2026-11-20' ),
);
$options = array( 'styles' => '', 'scripts' => '' );
$check( false !== strpos( Xml::urlset( $entries, '/sitemap.xml?asset=xml.css' ), '<loc>https://exemple.ca/a-propos/</loc>' ), 'writes the XML sitemap' );
$check( false !== strpos( Page::urlset( $entries, $theme, $options ), 'class="url" href="/a-propos/"' ), 'writes the page people read' );
$check( false !== strpos( Page::css( $theme, '' ), ':root{--bg:#fbfaf7;' ), 'writes the page stylesheet' );
echo "done\n";
