<?php
/**
 * Test-only endpoint, mounted into the Polylang test site (never shipped): adds English and
 * French, then translated content. Polylang 3.9 builds its model only in a full request in
 * admin context; a blueprint's runPHP step doesn't get one, so test/polylang.test.mjs calls
 * this once, over HTTP, before its checks.
 */

define( 'WP_ADMIN', true );
// A failing setup shows its own error in the test's message, not WordPress's generic page.
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			echo "{$e['message']} in {$e['file']}:{$e['line']}";
		}
	}
);
require '/wordpress/wp-load.php';
header( 'Content-Type: text/plain; charset=utf-8' );

if ( pll_languages_list() ) {
	exit( 'ready' );
}

$model = PLL()->model;
$model->languages->add( array( 'name' => 'English', 'slug' => 'en', 'locale' => 'en_US', 'rtl' => false, 'term_group' => 0 ) );
$model->languages->add( array( 'name' => 'Français', 'slug' => 'fr', 'locale' => 'fr_FR', 'rtl' => false, 'term_group' => 1 ) );
$model->clean_languages_cache();

$add_term = static function ( string $name, string $slug, string $lang ): int {
	$t = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
	pll_set_term_language( $t['term_id'], $lang );
	return (int) $t['term_id'];
};
$categories = array(
	'en' => $add_term( 'Recipes', 'recipes', 'en' ),
	'fr' => $add_term( 'Recettes', 'recettes', 'fr' ),
);
pll_save_term_translations( $categories );

$add_post = static function ( string $title, string $lang, string $type = 'post', string $status = 'publish' ) use ( $categories ): int {
	$id = wp_insert_post(
		array(
			'post_title'    => $title,
			'post_type'     => $type,
			'post_status'   => $status,
			'post_content'  => 'Text',
			'post_category' => 'post' === $type ? array( $categories[ $lang ] ) : array(),
		)
	);
	pll_set_post_language( $id, $lang );
	return $id;
};
pll_save_post_translations( array( 'en' => $add_post( 'Sourdough starter', 'en' ), 'fr' => $add_post( 'Levain', 'fr' ) ) );
pll_save_post_translations( array( 'en' => $add_post( 'About us', 'en', 'page' ), 'fr' => $add_post( 'À propos', 'fr', 'page' ) ) );
$add_post( 'Rye loaf', 'en' );
pll_save_post_translations( array( 'en' => $add_post( 'Focaccia', 'en' ), 'fr' => $add_post( 'Focaccia maison', 'fr', 'post', 'draft' ) ) );

flush_rewrite_rules();
// The start-up check already read the sitemap: read it again with the content above.
\MaisonGR\Sitemap\Entries::forget();
echo 'ready';
