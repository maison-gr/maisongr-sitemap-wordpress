<?php
/**
 * Every public address on the site, as sitemap entries: the home page, each
 * published post, page and public custom post type, and the archive of each
 * category, tag and public taxonomy term in use. Each is dated from when its
 * content last changed, and linked to its other language versions (Polylang,
 * WPML). Pages an SEO plugin marks noindex, and password-protected ones, are
 * left out: a sitemap that lists a noindex page asks search engines two
 * opposite things.
 *
 * @package MaisonGR\Sitemap
 */

namespace MaisonGR\Sitemap;

defined( 'ABSPATH' ) || exit;

final class Entries {

	private const CACHE = 'maisongr_sitemap_entries';

	/** Posts read per query: enough to be quick, few enough for a small memory limit. */
	private const BATCH = 500;

	/** @return array<int, array<string, mixed>> */
	public static function all(): array {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$entries = self::collect();
		set_transient( self::CACHE, $entries, HOUR_IN_SECONDS );
		return $entries;
	}

	/** The list is read again on the next request. Called when content changes. */
	public static function forget(): void {
		delete_transient( self::CACHE );
	}

	/** @return array<int, array<string, mixed>> */
	public static function collect(): array {
		$entries = array_merge( self::home(), self::posts(), self::terms() );
		$seen    = array();
		$once    = array();
		foreach ( $entries as $e ) {
			if ( ! isset( $seen[ $e['loc'] ] ) ) {
				$seen[ $e['loc'] ] = true;
				$once[]            = $e;
			}
		}
		/**
		 * Filters the addresses the sitemap lists, after they are read from the site.
		 * Each entry has loc, and optionally lastmod (YYYY-MM-DD), lang, alternates and images.
		 *
		 * @param array $entries The entries.
		 */
		return array_values( (array) apply_filters( 'maisongr_sitemap_entries', $once ) );
	}

	private static function day( ?string $gmt ): ?string {
		return $gmt && '0000-00-00 00:00:00' !== $gmt ? substr( $gmt, 0, 10 ) : null;
	}

	/** @return string[] */
	private static function post_types(): array {
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
		/**
		 * Filters the post types listed in the sitemap. Default: every public post type but attachments.
		 *
		 * @param string[] $types Post type names.
		 */
		return (array) apply_filters( 'maisongr_sitemap_post_types', $types );
	}

	/** @return string[] */
	private static function taxonomies(): array {
		$taxonomies = array_values( array_diff( get_taxonomies( array( 'public' => true ) ), array( 'post_format' ) ) );
		/**
		 * Filters the taxonomies whose term archives are listed. Return an empty array to list none.
		 *
		 * @param string[] $taxonomies Taxonomy names.
		 */
		return (array) apply_filters( 'maisongr_sitemap_taxonomies', $taxonomies );
	}

	/** @return array<int, array<string, mixed>> */
	private static function home(): array {
		$front = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		if ( $front ) {
			$lastmod = self::day( get_post_field( 'post_modified_gmt', $front ) );
		} else {
			$latest  = get_posts(
				array(
					'numberposts'      => 1,
					'post_type'        => 'post',
					'orderby'          => 'modified',
					'suppress_filters' => true,
				)
			);
			$lastmod = $latest ? self::day( $latest[0]->post_modified_gmt ) : null;
		}
		$entry = array( 'loc' => home_url( '/' ) );
		if ( $lastmod ) {
			$entry['lastmod'] = $lastmod;
		}
		if ( function_exists( 'pll_languages_list' ) && function_exists( 'pll_home_url' ) ) {
			$entries = array();
			$homes   = array();
			foreach ( (array) pll_languages_list( array( 'fields' => 'slug' ) ) as $slug ) {
				$homes[ $slug ] = pll_home_url( $slug );
			}
			foreach ( $homes as $slug => $url ) {
				$entries[] = array_merge( $entry, self::languages( $url, $slug, $homes ) );
			}
			return $entries ? $entries : array( $entry );
		}
		return array( $entry );
	}

	/**
	 * Language fields for an address: its lang, and alternates naming every version and x-default.
	 *
	 * @param array<string, string> $versions URL by language code, this one included.
	 * @return array<string, mixed>
	 */
	private static function languages( string $loc, string $lang, array $versions ): array {
		$fields = array(
			'loc'  => $loc,
			'lang' => $lang,
		);
		if ( count( $versions ) < 2 ) {
			return $fields;
		}
		$alternates = array();
		foreach ( $versions as $code => $url ) {
			$alternates[] = array(
				'hreflang' => $code,
				'href'     => $url,
			);
		}
		$default = self::default_language();
		if ( $default && isset( $versions[ $default ] ) ) {
			$alternates[] = array(
				'hreflang' => 'x-default',
				'href'     => $versions[ $default ],
			);
		}
		$fields['alternates'] = $alternates;
		return $fields;
	}

	private static function default_language(): ?string {
		if ( function_exists( 'pll_default_language' ) ) {
			return (string) pll_default_language( 'slug' );
		}
		$wpml = apply_filters( 'wpml_default_language', null );
		return $wpml ? (string) $wpml : null;
	}

	/**
	 * A post's language and its translations' addresses, from Polylang or WPML.
	 *
	 * @return array{0: string|null, 1: array<string, string>}
	 */
	private static function post_languages( \WP_Post $post ): array {
		if ( function_exists( 'pll_get_post_language' ) && function_exists( 'pll_get_post_translations' ) ) {
			$lang     = pll_get_post_language( $post->ID, 'slug' );
			$versions = array();
			foreach ( (array) pll_get_post_translations( $post->ID ) as $code => $id ) {
				if ( 'publish' === get_post_status( $id ) && ! self::noindex( (int) $id ) ) {
					$versions[ $code ] = get_permalink( $id );
				}
			}
			return array( $lang ? (string) $lang : null, $versions );
		}
		$type = 'post_' . $post->post_type;
		$trid = apply_filters( 'wpml_element_trid', null, $post->ID, $type );
		if ( $trid ) {
			$lang     = null;
			$versions = array();
			foreach ( (array) apply_filters( 'wpml_get_element_translations', null, $trid, $type ) as $t ) {
				$id = (int) ( $t->element_id ?? 0 );
				if ( $id === $post->ID ) {
					$lang = (string) $t->language_code;
				}
				if ( $id && 'publish' === get_post_status( $id ) && ! self::noindex( $id ) ) {
					$url = apply_filters( 'wpml_permalink', get_permalink( $id ), $t->language_code, true );
					$versions[ (string) $t->language_code ] = $url;
				}
			}
			return array( $lang, $versions );
		}
		return array( null, array() );
	}

	/** Whether an SEO plugin marks the post noindex: Yoast SEO, Rank Math, SEOPress, The SEO Framework. */
	public static function noindex( int $id ): bool {
		$robots = get_post_meta( $id, 'rank_math_robots', true );
		return '1' === (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', true )
			|| ( is_array( $robots ) && in_array( 'noindex', $robots, true ) )
			|| 'yes' === (string) get_post_meta( $id, '_seopress_robots_index', true )
			|| '1' === (string) get_post_meta( $id, '_genesis_noindex', true );
	}

	/** @return array<int, array<string, mixed>> */
	private static function posts(): array {
		$types = self::post_types();
		if ( ! $types ) {
			return array();
		}
		$front   = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		$entries = array();
		for ( $page = 1;; $page++ ) {
			$query = new \WP_Query(
				array(
					'post_type'              => $types,
					'post_status'            => 'publish',
					'has_password'           => false,
					'posts_per_page'         => self::BATCH,
					'paged'                  => $page,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'update_post_term_cache' => false,
					// Every language, not only the current one (WPML and Polylang filter queries by language).
					'suppress_filters'       => true,
					'lang'                   => '',
				)
			);
			foreach ( $query->posts as $post ) {
				if ( $post->ID === $front || self::noindex( $post->ID ) ) {
					continue;
				}
				$loc = get_permalink( $post );
				if ( ! $loc ) {
					continue;
				}
				$entry = array( 'loc' => $loc );
				list( $lang, $versions ) = self::post_languages( $post );
				if ( $lang ) {
					$entry = self::languages( $loc, $lang, $versions + array( $lang => $loc ) );
				}
				$lastmod = self::day( $post->post_modified_gmt );
				if ( $lastmod ) {
					$entry['lastmod'] = $lastmod;
				}
				$image = get_the_post_thumbnail_url( $post, 'full' );
				if ( $image ) {
					$entry['images'] = array( $image );
				}
				$entries[] = $entry;
			}
			if ( count( $query->posts ) < self::BATCH ) {
				break;
			}
		}
		return $entries;
	}

	/** @return array<int, array<string, mixed>> */
	private static function terms(): array {
		$taxonomies = self::taxonomies();
		if ( ! $taxonomies ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => true,
				'lang'       => '',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$entries = array();
		foreach ( $terms as $term ) {
			$loc = get_term_link( $term );
			if ( is_wp_error( $loc ) ) {
				continue;
			}
			$entry = array( 'loc' => $loc );
			if ( function_exists( 'pll_get_term_language' ) && function_exists( 'pll_get_term_translations' ) ) {
				$lang = pll_get_term_language( $term->term_id, 'slug' );
				if ( $lang ) {
					$versions = array();
					foreach ( (array) pll_get_term_translations( $term->term_id ) as $code => $id ) {
						$link = get_term_link( (int) $id );
						if ( ! is_wp_error( $link ) ) {
							$versions[ $code ] = $link;
						}
					}
					$entry = self::languages( $loc, (string) $lang, $versions + array( $lang => $loc ) );
				}
			}
			$entries[] = $entry;
		}
		return $entries;
	}
}
