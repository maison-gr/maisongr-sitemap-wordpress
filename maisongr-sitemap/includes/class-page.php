<?php
/**
 * The sitemap as a person sees it, as the maisongr-sitemap package renders it
 * (src/page.ts): the same addresses the XML lists, set in the site's own colours
 * and type, grouped by section, with a filter. No inline script or style: both
 * load from the sitemap's own address (?asset=page.css, ?asset=page.js).
 *
 * The output must match the package's byte for byte; test/parity.mjs checks it.
 * The stylesheet's rules and the script are generated from the package
 * (scripts/assets.mjs), never edited here.
 *
 * A theme is an array: name, light (palette), dark (palette, optional), fonts
 * (list of role, family, files), icon (optional). A palette has background,
 * foreground, muted, border, accent and surface.
 *
 * @package MaisonGR\Sitemap
 */

namespace MaisonGR\Sitemap;

defined( 'ABSPATH' ) || exit;

final class Page {

	/** Above this many rows the page gets its filter and sort. */
	private const TOOLS_FROM = 10;

	private const TOOL   = '<a href="https://github.com/maison-gr/maisongr-sitemap" rel="noopener">maisongr-sitemap</a>';
	private const STUDIO = '<a href="https://www.maisongr.com" rel="noopener">Maison GR</a>';

	private const STACK = array(
		'display' => 'ui-serif, Georgia, serif',
		'body'    => 'ui-sans-serif, system-ui, sans-serif',
		'mono'    => 'ui-monospace, SFMono-Regular, Menlo, monospace',
	);

	/** Escapes for text and double-quoted attributes, as the package does. */
	public static function esc( string $s ): string {
		return str_replace( array( '&', '<', '>', '"' ), array( '&amp;', '&lt;', '&gt;', '&quot;' ), $s );
	}

	public static function path_of( string $loc ): string {
		$path = (string) preg_replace( '#^https?://[^/]+#i', '', $loc );
		return '' === $path ? '/' : $path;
	}

	private static function pad( int $n, int $width ): string {
		return str_pad( (string) $n, $width, '0', STR_PAD_LEFT );
	}

	private static function lower( string $s ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	/** The path without its language prefix, split: "/en/blog/post" gives ["blog", "post"]. */
	private static function parts_of( array $e ): array {
		$parts = array_values( array_filter( explode( '/', self::path_of( $e['loc'] ) ), 'strlen' ) );
		if ( ! empty( $e['lang'] ) && isset( $parts[0] ) && $parts[0] === $e['lang'] ) {
			array_shift( $parts );
		}
		return $parts;
	}

	/**
	 * Sections are the site's real folders: a first segment holding more than one page.
	 * Everything else is one "Pages" section.
	 *
	 * @return array<int, array{0: string, 1: array}> Section key and its entries, in order.
	 */
	private static function sections_of( array $entries ): array {
		$pages_under = array();
		foreach ( $entries as $e ) {
			$parts = self::parts_of( $e );
			if ( count( $parts ) < 2 ) {
				continue;
			}
			$pages_under[ $parts[0] ][ implode( '/', $parts ) ] = true;
		}
		$sections = array( '' => array() );
		foreach ( $entries as $e ) {
			$first = self::parts_of( $e )[0] ?? '';
			$key   = '' !== $first && count( $pages_under[ $first ] ?? array() ) > 1 ? $first : '';
			// A numeric folder name would become an integer key: keep keys as strings with a prefix.
			$sections[ $key ][] = $e;
		}
		if ( ! $sections[''] ) {
			unset( $sections[''] );
		}
		$out = array();
		foreach ( $sections as $key => $list ) {
			$out[] = array( (string) $key, $list );
		}
		return $out;
	}

	private static function title_of( string $section, Strings $t ): string {
		return '' !== $section ? '/' . rawurldecode( $section ) . '/' : $t->get( 'pages_section' );
	}

	private static function primary( ?string $lang ): string {
		return (string) preg_split( '/[-_]/', strtolower( (string) $lang ) )[0];
	}

	/**
	 * Language versions of one page share a row: entries whose alternates name each other,
	 * one per language. The row shows the version in the page's language (or the first).
	 *
	 * @return array<int, array{main: int, versions: int[]}> Indexes into $entries.
	 */
	private static function rows_of( array $entries, ?string $lang ): array {
		$index = array();
		foreach ( $entries as $i => $e ) {
			$index[ $e['loc'] ] = $i;
		}
		$root = array_keys( $entries );
		$find = static function ( int $i ) use ( &$root, &$find ): int {
			if ( $root[ $i ] === $i ) {
				return $i;
			}
			$root[ $i ] = $find( $root[ $i ] );
			return $root[ $i ];
		};
		foreach ( $entries as $i => $e ) {
			if ( empty( $e['lang'] ) ) {
				continue;
			}
			foreach ( $e['alternates'] ?? array() as $a ) {
				$j = $index[ $a['href'] ] ?? null;
				if ( null !== $j && $j !== $i && ! empty( $entries[ $j ]['lang'] ) ) {
					$root[ $find( $j ) ] = $find( $i );
				}
			}
		}
		$rows = array();
		$open = array();
		foreach ( $entries as $i => $e ) {
			$r = $open[ $find( $i ) ] ?? null;
			if ( null !== $r && ! empty( $e['lang'] ) ) {
				$taken = false;
				foreach ( $rows[ $r ]['versions'] as $v ) {
					if ( ( $entries[ $v ]['lang'] ?? null ) === $e['lang'] ) {
						$taken = true;
					}
				}
				// Two entries in the same language can't share a row: the second gets its own.
				if ( ! $taken ) {
					$rows[ $r ]['versions'][] = $i;
					continue;
				}
			}
			$rows[] = array(
				'main'     => $i,
				'versions' => array( $i ),
			);
			if ( null === $r ) {
				$open[ $find( $i ) ] = count( $rows ) - 1;
			}
		}
		$own = self::primary( $lang );
		foreach ( $rows as &$row ) {
			$row['main'] = $row['versions'][0];
			foreach ( $row['versions'] as $v ) {
				if ( self::primary( $entries[ $v ]['lang'] ?? null ) === $own ) {
					$row['main'] = $v;
					break;
				}
			}
		}
		unset( $row );
		return $rows;
	}

	private static function latest( array $entries ): ?string {
		$max = null;
		foreach ( $entries as $e ) {
			if ( ! empty( $e['lastmod'] ) && ( null === $max || $e['lastmod'] > $max ) ) {
				$max = $e['lastmod'];
			}
		}
		return $max;
	}

	private static function row_html( array $entries, array $row, int $n, int $width ): string {
		$main     = $entries[ $row['main'] ];
		$versions = array_map(
			static function ( $v ) use ( $entries ) {
				return $entries[ $v ];
			},
			$row['versions']
		);
		$path     = self::path_of( $main['loc'] );
		$date     = self::latest( $versions );
		$search   = implode(
			' ',
			array_map(
				static function ( $v ) {
					return self::lower( self::path_of( $v['loc'] ) );
				},
				$versions
			)
		);
		$langs    = implode( ' ', array_values( array_filter( array_column( $versions, 'lang' ) ) ) );
		$data_v   = '';
		if ( count( $versions ) > 1 ) {
			$pairs  = array_map(
				static function ( $v ) {
					return array( $v['lang'] ?? null, self::path_of( $v['loc'] ) );
				},
				$versions
			);
			$data_v = ' data-v="' . self::esc( (string) json_as_js( $pairs ) ) . '"';
		}
		$tags = '';
		foreach ( $row['versions'] as $v ) {
			$version = $entries[ $v ];
			if ( empty( $version['lang'] ) ) {
				continue;
			}
			$code  = self::esc( strtoupper( $version['lang'] ) );
			$tags .= $v === $row['main']
				? "<span class=\"lang is-current\">{$code}</span>"
				: '<a class="lang" href="' . self::esc( self::path_of( $version['loc'] ) ) . '" hreflang="' . self::esc( $version['lang'] ) . "\">{$code}</a>";
		}
		// Many languages take their own line on a phone; two or three fit beside the address.
		return '<li class="row' . ( count( $versions ) > 3 ? ' many' : '' ) . '" data-u="' . self::esc( $search ) . '"'
			. ( '' !== $langs ? ' data-l="' . self::esc( $langs ) . '"' : '' )
			. ( $date ? ' data-d="' . self::esc( $date ) . '"' : '' )
			. $data_v . ">\n"
			. '<span class="num">' . self::pad( $n, $width ) . '</span><a class="url" href="' . self::esc( $path ) . '"'
			. ( ! empty( $main['lang'] ) ? ' hreflang="' . self::esc( $main['lang'] ) . '"' : '' ) . '>' . self::esc( $path ) . '</a>'
			. "<span class=\"langs\">{$tags}</span><span class=\"date\">" . ( $date ? self::esc( $date ) : '' ) . "</span>\n</li>";
	}

	/** The newest of a list's dates, as a UTC timestamp, or null when none is valid. */
	private static function newest_date( array $dates ): ?int {
		$times = array();
		foreach ( $dates as $d ) {
			if ( $d && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
				$times[] = (int) gmmktime( 0, 0, 0, (int) substr( $d, 5, 2 ), (int) substr( $d, 8, 2 ), (int) substr( $d, 0, 4 ) );
			}
		}
		return $times ? max( $times ) : null;
	}

	/**
	 * @param array{credit?: bool, assets?: array{css: string, js: string}, home?: string, lang?: string} $options
	 */
	private static function shell( array $theme, string $title, string $body, array $options, ?int $updated = null ): string {
		$assets  = $options['assets'] ?? array(
			'css' => '?asset=page.css',
			'js'  => '?asset=page.js',
		);
		$t       = new Strings( $options['lang'] ?? null );
		$about   = ( null !== $updated ? '<span>' . self::esc( $t->updated( $updated ) ) . '</span>' : '' ) . '<span>' . self::esc( $t->get( 'footer' ) ) . '</span>';
		$preload = array();
		foreach ( $theme['fonts'] as $f ) {
			if ( ! empty( $f['files'][0] ) ) {
				$preload[] = '<link rel="preload" href="' . self::esc( $f['files'][0]['url'] ) . '" as="font" type="font/woff2" crossorigin>';
			}
		}
		$credit = ( $options['credit'] ?? true ) !== false ? '<p class="credit">' . $t->credit( self::TOOL, self::STUDIO ) . '</p>' : '';
		return "<!doctype html>\n<html lang=\"" . self::esc( $t->lang ) . "\">\n<head>\n<meta charset=\"utf-8\">\n"
			. "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<meta name=\"robots\" content=\"noindex\">\n"
			. '<meta name="color-scheme" content="' . ( ! empty( $theme['dark'] ) ? 'light dark' : 'light' ) . "\">\n"
			. '<title>' . self::esc( $title ) . ' · ' . self::esc( $theme['name'] ) . "</title>\n"
			. ( ! empty( $theme['icon'] ) ? '<link rel="icon" href="' . self::esc( $theme['icon'] ) . '">' : '' ) . "\n"
			. implode( "\n", $preload ) . "\n"
			// The sitemap page is a document of its own at /sitemap.xml, outside any theme: nothing is enqueued there.
			. '<link rel="stylesheet" href="' . self::esc( $assets['css'] ) . "\">\n</head>\n<body>\n<main class=\"page\">\n" // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
			. '<a class="brand" href="' . self::esc( $options['home'] ?? '/' ) . '">' . self::esc( $theme['name'] ) . "</a>\n"
			. $body . "\n"
			. "<footer class=\"foot\"><p class=\"about\">{$about}</p>{$credit}</footer>\n</main>\n"
			. '<script src="' . self::esc( $assets['js'] ) . "\" defer></script>\n</body>\n</html>"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
	}

	/**
	 * @param array<int, array<string, mixed>> $entries
	 * @param array<string, mixed>             $theme
	 * @param array<string, mixed>             $options
	 */
	public static function urlset( array $entries, array $theme, array $options = array() ): string {
		$entries = array_values( $entries );
		$t       = new Strings( $options['lang'] ?? null );
		$langs   = array_values( array_unique( array_filter( array_column( $entries, 'lang' ) ) ) );
		$rows    = self::rows_of( $entries, $options['lang'] ?? null );
		$width   = max( 2, strlen( (string) count( $rows ) ) );
		$row_of  = array();
		foreach ( $rows as $r ) {
			$row_of[ $r['main'] ] = $r;
		}
		$mains = array();
		foreach ( $rows as $r ) {
			$mains[] = $entries[ $r['main'] ] + array( '_i' => $r['main'] );
		}
		$sections = self::sections_of( $mains );

		$n      = 0;
		$groups = array();
		foreach ( $sections as $i => list( $key, $list ) ) {
			$id    = "s-{$i}";
			$items = array();
			foreach ( $list as $m ) {
				$items[] = self::row_html( $entries, $row_of[ $m['_i'] ], ++$n, $width );
			}
			$groups[] = "<section class=\"group\" id=\"{$id}\" aria-labelledby=\"{$id}-h\"><header class=\"group-head\"><h2 id=\"{$id}-h\">" . self::esc( self::title_of( $key, $t ) ) . '</h2><span class="count">' . count( $list ) . "</span></header>\n<ol class=\"rows\">\n" . implode( "\n", $items ) . "\n</ol></section>";
		}

		$counts = '';
		foreach ( $langs as $l ) {
			$total   = count(
				array_filter(
					$entries,
					static function ( $e ) use ( $l ) {
						return ( $e['lang'] ?? null ) === $l;
					}
				)
			);
			$counts .= "<span><b>{$total}</b> " . self::esc( strtoupper( $l ) ) . '</span>';
		}
		// When language versions share rows, the addresses are counted too: they are what the XML lists.
		$addresses = count( $rows ) < count( $entries ) ? '<span><b>' . count( $entries ) . '</b> ' . self::esc( $t->addresses( count( $entries ) ) ) . '</span>' : '';
		$chips     = '';
		if ( count( $langs ) > 1 ) {
			$chips = '<div class="chips" role="group" aria-label="' . self::esc( $t->get( 'language_group' ) ) . '"><button type="button" class="chip" data-f="" aria-pressed="true">' . self::esc( $t->get( 'all' ) ) . '</button>';
			foreach ( $langs as $l ) {
				$chips .= '<button type="button" class="chip" data-f="' . self::esc( $l ) . '" aria-pressed="false">' . self::esc( strtoupper( $l ) ) . '</button>';
			}
			$chips .= '</div>';
		}

		$heading = '<p class="eyebrow">' . self::esc( $t->get( 'title' ) ) . "</p>\n<h1>" . self::esc( $t->heading( $theme['name'] ) ) . '<span class="mark">.</span></h1>';
		if ( ! $entries ) {
			return self::shell( $theme, $t->get( 'title' ), "{$heading}\n<p class=\"empty\">" . self::esc( $t->get( 'empty' ) ) . '</p>', $options );
		}

		$tools = count( $rows ) > self::TOOLS_FROM;
		$dated = 0;
		foreach ( $rows as $r ) {
			if ( self::latest( array_map( static function ( $v ) use ( $entries ) { return $entries[ $v ]; }, $r['versions'] ) ) ) { // phpcs:ignore
				++$dated;
			}
		}
		$sort = $tools && $dated > 1;

		$body = "{$heading}\n<p class=\"facts\"><span><b>" . count( $rows ) . '</b> ' . self::esc( $t->pages( count( $rows ) ) ) . "</span>{$addresses}{$counts}</p>\n";
		if ( $tools ) {
			$body .= "<div class=\"tools\">\n<input id=\"q\" class=\"search\" type=\"search\" placeholder=\"" . self::esc( $t->get( 'filter_placeholder' ) ) . '" aria-label="' . self::esc( $t->get( 'filter_label' ) ) . "\" autocomplete=\"off\" spellcheck=\"false\">\n"
				. $chips . ( $sort ? '<button type="button" class="chip sort" id="recent" aria-pressed="false">' . self::esc( $t->get( 'recent' ) ) . '</button>' : '' ) . "\n"
				. '<p class="tally" id="tally" aria-live="polite" data-t="' . self::esc( $t->get( 'tally' ) ) . "\"></p>\n</div>";
		}
		$body .= "\n";
		if ( count( $sections ) > 1 && count( $sections ) <= 12 ) {
			$body .= '<nav class="jump" aria-label="' . self::esc( $t->get( 'sections_nav' ) ) . '">';
			foreach ( $sections as $i => list( $key, $list ) ) {
				$body .= "<a href=\"#s-{$i}\">" . self::esc( self::title_of( $key, $t ) ) . ' <span>' . count( $list ) . '</span></a>';
			}
			$body .= '</nav>';
		}
		$body .= "\n" . implode( "\n", $groups ) . "\n";
		$body .= $sort ? '<section class="group" id="recent-list" aria-labelledby="recent-h" hidden><header class="group-head"><h2 id="recent-h">' . self::esc( $t->get( 'recent' ) ) . '</h2></header><ol class="rows"></ol></section>' : '';
		$body .= "\n" . ( $tools ? '<p class="empty" id="empty" hidden>' . self::esc( $t->get( 'no_match' ) ) . '</p>' : '' );

		return self::shell( $theme, $t->get( 'title' ), $body, $options, self::newest_date( array_column( $entries, 'lastmod' ) ) );
	}

	/**
	 * @param array<int, array{loc: string, lastmod?: string, count: int, label?: string}> $parts
	 */
	public static function index( array $parts, array $theme, array $options = array() ): string {
		$t     = new Strings( $options['lang'] ?? null );
		$total = array_sum( array_column( $parts, 'count' ) );
		$rows  = array();
		foreach ( array_values( $parts ) as $i => $p ) {
			$rows[] = '<li class="row"><span class="num">' . self::pad( $i + 1, 2 ) . '</span><a class="url" href="' . self::esc( self::path_of( $p['loc'] ) ) . '">' . self::esc( $p['label'] ?? self::path_of( $p['loc'] ) ) . '</a><span class="lang">' . $p['count'] . '</span><span class="date">' . ( ! empty( $p['lastmod'] ) ? self::esc( $p['lastmod'] ) : '' ) . '</span></li>';
		}
		$body = '<p class="eyebrow">' . self::esc( $t->get( 'index_title' ) ) . "</p>\n<h1>" . self::esc( $t->heading( $theme['name'] ) ) . "<span class=\"mark\">.</span></h1>\n"
			. "<p class=\"facts\"><span><b>{$total}</b> " . self::esc( $t->pages( $total ) ) . '</span><span><b>' . count( $parts ) . '</b> ' . self::esc( $t->files( count( $parts ) ) ) . "</span></p>\n"
			. '<section class="group" aria-labelledby="parts-h"><header class="group-head"><h2 id="parts-h">' . self::esc( $t->get( 'files_section' ) ) . '</h2><span class="count">' . count( $parts ) . "</span></header><ol class=\"rows\">\n"
			. implode( "\n", $rows ) . "\n</ol></section>";
		return self::shell( $theme, $t->get( 'index_title' ), $body, $options, self::newest_date( array_column( $parts, 'lastmod' ) ) );
	}

	private static function font_faces( array $theme ): string {
		$faces = array();
		foreach ( $theme['fonts'] as $f ) {
			foreach ( $f['files'] as $file ) {
				$faces[] = '@font-face{font-family:"' . $f['family'] . '";src:url("' . $file['url'] . '") format("woff2");font-weight:' . ( $file['weight'] ?? '100 900' ) . ';font-style:' . ( $file['style'] ?? 'normal' ) . ';font-display:swap}';
			}
		}
		return implode( "\n", $faces );
	}

	private static function vars( array $p ): string {
		return "--bg:{$p['background']};--fg:{$p['foreground']};--muted:{$p['muted']};--border:{$p['border']};--accent:{$p['accent']};--surface:{$p['surface']};";
	}

	private static function family( array $fonts, string $role ): string {
		$own = null;
		foreach ( $fonts as $f ) {
			if ( $f['role'] === $role ) {
				$own = $f;
				break;
			}
		}
		if ( null === $own && 'display' === $role ) {
			foreach ( $fonts as $f ) {
				if ( 'body' === $f['role'] ) {
					$own = $f;
					break;
				}
			}
		}
		$fallback = 'display' === $role && null === $own ? self::STACK['body'] : self::STACK[ $role ];
		return null !== $own ? "\"{$own['family']}\", {$fallback}" : $fallback;
	}

	/** The page's stylesheet: the theme's fonts and colours, then the package's rules. */
	public static function css( array $theme, string $rules ): string {
		$dark = ! empty( $theme['dark'] ) ? '@media (prefers-color-scheme:dark){:root{' . self::vars( $theme['dark'] ) . 'color-scheme:dark}}' : '';
		return self::font_faces( $theme ) . "\n:root{" . self::vars( $theme['light'] ) . 'color-scheme:light;--display:' . self::family( $theme['fonts'], 'display' ) . ';--body:' . self::family( $theme['fonts'], 'body' ) . ';--mono:' . self::family( $theme['fonts'], 'mono' ) . "}\n{$dark}\n{$rules}";
	}

	/** A CSS string literal: quotes and backslashes escaped, a newline as \A. */
	private static function css_text( string $s ): string {
		return '"' . str_replace( array( '\\', '"', "\n" ), array( '\\\\', '\\"', '\\A ' ), $s ) . '"';
	}

	/**
	 * The XML as a browser shows it when it can't be told apart from a crawler:
	 * set in the site's type and colours, saying what the file is.
	 */
	public static function xml_css( array $theme, array $options = array() ): string {
		$t     = new Strings( $options['lang'] ?? null );
		$root  = 'urlset,sitemapindex';
		$dark  = ! empty( $theme['dark'] ) ? '@media (prefers-color-scheme:dark){:root{' . self::vars( $theme['dark'] ) . 'color-scheme:dark}}' : '';
		$fonts = $theme['fonts'];
		$note  = ! empty( $options['page'] ) ? $t->get( 'xml_note' ) . ' ' . $t->xml_page( $options['page'] ) : $t->get( 'xml_note' );
		return self::font_faces( $theme ) . "\n"
			. ':root{' . self::vars( $theme['light'] ) . "color-scheme:light;background:var(--bg)}\n"
			. "{$dark}\n"
			. "{$root}{display:block;max-width:68rem;margin:0 auto;padding:clamp(2rem,6vw,4.5rem) clamp(1rem,4vw,2.5rem) 4rem;color:var(--fg);font:16px/1.6 " . self::family( $fonts, 'body' ) . ";-webkit-font-smoothing:antialiased}\n"
			. 'urlset::before,sitemapindex::before{content:' . self::css_text( $t->get( 'xml_eyebrow' ) ) . ';display:block;margin-bottom:1rem;font:500 .75rem/1.4 ' . self::family( $fonts, 'mono' ) . ";letter-spacing:.12em;text-transform:uppercase;color:var(--muted)}\n"
			. "url:first-of-type,sitemap:first-of-type{padding-top:0}\n"
			. 'url:first-of-type::before,sitemap:first-of-type::before{content:' . self::css_text( $t->heading( $theme['name'] ) . '.' ) . ';display:block;margin-bottom:clamp(2rem,5vw,3.5rem);padding-bottom:1.25rem;border-bottom:1px solid var(--fg);font:600 clamp(2.25rem,7vw,4.5rem)/1.02 ' . self::family( $fonts, 'display' ) . ";letter-spacing:-.035em;text-wrap:balance}\n"
			. 'urlset::after,sitemapindex::after{content:' . self::css_text( $note ) . ';display:block;margin-top:2.5rem;font:400 .75rem/1.6 ' . self::family( $fonts, 'mono' ) . ";color:var(--muted)}\n"
			. "url,sitemap{display:block;padding:.85rem 0;border-bottom:1px solid var(--border)}\n"
			. "loc{display:block;overflow-wrap:anywhere;font-size:1.0625rem;letter-spacing:-.005em}\n"
			. 'lastmod{display:block;margin-top:.15rem;font:400 .75rem/1.5 ' . self::family( $fonts, 'mono' ) . ";color:var(--muted)}\n"
			. 'url>:not(loc):not(lastmod),sitemap>:not(loc):not(lastmod){display:none}';
	}
}

/**
 * JSON as JavaScript's JSON.stringify writes it: slashes and Unicode unescaped.
 *
 * @param mixed $value
 */
function json_as_js( $value ): string {
	return (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
}
