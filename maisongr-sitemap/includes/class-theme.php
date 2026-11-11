<?php
/**
 * The site's look, read from WordPress: the colours and fonts of its theme
 * (theme.json and global styles, as the Site Editor sets them), its name and
 * its icon. The page is set in them, so it looks like the site.
 *
 * @package MaisonGR\Sitemap
 */

namespace MaisonGR\Sitemap;

defined( 'ABSPATH' ) || exit;

final class Theme {

	/** The package's neutral palette, for a theme that defines no colours. */
	public const DEFAULT_PALETTE = array(
		'background' => '#fbfaf7',
		'foreground' => '#141414',
		'muted'      => '#5c5c58',
		'border'     => '#e3e1db',
		'accent'     => '#141414',
		'surface'    => '#ffffff',
	);

	/** Palette slugs themes use for each role, most common first (Twenty Twenty-Five, -Four, -Three, and others). */
	private const SLUGS = array(
		'background' => array( 'base', 'background', 'white' ),
		'foreground' => array( 'contrast', 'foreground', 'text', 'black' ),
		'muted'      => array( 'contrast-2', 'muted', 'secondary-text', 'contrast-3' ),
		'accent'     => array( 'primary', 'accent', 'accent-1', 'vivid-cyan-blue', 'secondary' ),
		'surface'    => array( 'base-2', 'surface', 'tertiary', 'accent-5' ),
	);

	/** @return array<string, mixed> The theme, as Page reads it. */
	public static function read(): array {
		$name = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		if ( '' === trim( $name ) ) {
			$name = (string) preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		}
		$theme = array(
			'name'  => $name,
			'light' => self::palette(),
			'fonts' => self::fonts(),
		);
		$icon = get_site_icon_url( 64 );
		if ( $icon ) {
			$theme['icon'] = $icon;
		}
		/**
		 * Filters the theme the sitemap page is set in: name, light (and dark) palette, fonts, icon.
		 *
		 * @param array $theme The theme read from the site.
		 */
		return apply_filters( 'maisongr_sitemap_theme', $theme );
	}

	/** A colour value safe to write into the page's stylesheet, or null. */
	public static function safe_color( $value ): ?string {
		$value = trim( (string) $value );
		// Keywords that name no colour of their own (a link set to currentColor) say nothing about the palette.
		if ( in_array( strtolower( $value ), array( 'currentcolor', 'inherit', 'initial', 'unset', 'transparent', 'none' ), true ) ) {
			return null;
		}
		return '' !== $value && preg_match( '/^(#[0-9a-f]{3,8}|[a-z]+\([0-9a-z.,%\s\/-]*\)|[a-z]+)$/i', $value ) ? $value : null;
	}

	/** @return array<string, string> Palette colours by slug: theme first, then the user's, then WordPress's. */
	private static function presets(): array {
		$palette = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings( array( 'color', 'palette' ) ) : array();
		$colors  = array();
		foreach ( array( 'default', 'theme', 'custom' ) as $origin ) {
			foreach ( (array) ( $palette[ $origin ] ?? array() ) as $c ) {
				if ( isset( $c['slug'], $c['color'] ) && self::safe_color( $c['color'] ) ) {
					$colors[ $c['slug'] ] = $c['color'];
				}
			}
		}
		return $colors;
	}

	/** A global styles value as a colour: "var:preset|color|base", "var(--wp--preset--color--base)" or a colour. */
	private static function resolve( $value, array $presets ): ?string {
		$value = (string) $value;
		if ( preg_match( '/^var:preset\|color\|([\w-]+)$/', $value, $m ) || preg_match( '/^var\(--wp--preset--color--([\w-]+)\)$/', $value, $m ) ) {
			return $presets[ $m[1] ] ?? null;
		}
		return self::safe_color( $value );
	}

	private static function by_slug( string $role, array $presets ): ?string {
		foreach ( self::SLUGS[ $role ] as $slug ) {
			if ( isset( $presets[ $slug ] ) ) {
				return $presets[ $slug ];
			}
		}
		return null;
	}

	/** @return array<string, string> */
	public static function palette(): array {
		$presets = self::presets();
		$styles  = function_exists( 'wp_get_global_styles' ) ? wp_get_global_styles() : array();

		$background = self::resolve( $styles['color']['background'] ?? '', $presets ) ?? self::by_slug( 'background', $presets );
		$foreground = self::resolve( $styles['color']['text'] ?? '', $presets ) ?? self::by_slug( 'foreground', $presets );
		// A classic theme: its custom background, if the site set one.
		if ( ! $background && get_background_color() ) {
			$background = self::safe_color( '#' . get_background_color() );
		}
		if ( ! $background || ! $foreground ) {
			return $background ? array_merge( self::DEFAULT_PALETTE, array( 'background' => $background, 'surface' => $background ) ) : self::DEFAULT_PALETTE;
		}
		return self::complete( $background, $foreground, self::resolve( $styles['elements']['link']['color']['text'] ?? '', $presets ) ?? self::by_slug( 'accent', $presets ), self::by_slug( 'muted', $presets ), self::by_slug( 'surface', $presets ) );
	}

	/**
	 * A palette from the site's own pair, as the package makes one (src/build/palette.ts):
	 * secondary text and rules mixed from it, and muted text kept readable.
	 *
	 * @return array<string, string>
	 */
	public static function complete( string $background, string $foreground, ?string $accent = null, ?string $muted = null, ?string $surface = null ): array {
		$mix = static function ( int $pct ) use ( $foreground, $background ): string {
			return "color-mix(in srgb, {$foreground} {$pct}%, {$background})";
		};
		$ratio = $muted ? self::contrast( $muted, $background ) : null;
		// Secondary text must still read: below AA on the page background, mix one from the site's own pair.
		if ( ! $muted || ( null !== $ratio && $ratio < 4.5 ) ) {
			$muted = $mix( 68 );
		}
		// The accent marks focus and links: below 3:1 on the background (a pale yellow on white), the text colour does it instead.
		$accent_ratio = $accent ? self::contrast( $accent, $background ) : null;
		if ( null !== $accent_ratio && $accent_ratio < 3 ) {
			$accent = null;
		}
		return array(
			'background' => $background,
			'foreground' => $foreground,
			'muted'      => $muted,
			'border'     => $mix( 14 ),
			'accent'     => $accent ? $accent : $foreground,
			'surface'    => $surface ? $surface : $background,
		);
	}

	/** @return array{0:int,1:int,2:int}|null sRGB of a hex colour; null for other formats. */
	private static function rgb( string $color ): ?array {
		if ( ! preg_match( '/^#([0-9a-f]{3,8})$/i', $color, $m ) ) {
			return null;
		}
		$hex = strlen( $m[1] ) <= 4 ? implode( '', array_map( static function ( $c ) { return $c . $c; }, str_split( substr( $m[1], 0, 3 ) ) ) ) : substr( $m[1], 0, 6 ); // phpcs:ignore
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/** WCAG contrast ratio of two hex colours, or null for other formats. */
	public static function contrast( string $a, string $b ): ?float {
		$x = self::rgb( $a );
		$y = self::rgb( $b );
		if ( ! $x || ! $y ) {
			return null;
		}
		$lum = static function ( array $c ): float {
			$v = array_map(
				static function ( $n ) {
					$s = $n / 255;
					return $s <= 0.03928 ? $s / 12.92 : ( ( $s + 0.055 ) / 1.055 ) ** 2.4;
				},
				$c
			);
			return 0.2126 * $v[0] + 0.7152 * $v[1] + 0.0722 * $v[2];
		};
		$hi = max( $lum( $x ), $lum( $y ) );
		$lo = min( $lum( $x ), $lum( $y ) );
		return ( $hi + 0.05 ) / ( $lo + 0.05 );
	}

	/**
	 * The fonts the site sets its text and headings in, with their files: only
	 * WOFF2 files the site serves itself, so a strict CSP allows them.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function fonts(): array {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return array();
		}
		$families = array();
		foreach ( (array) wp_get_global_settings( array( 'typography', 'fontFamilies' ) ) as $origin ) {
			foreach ( (array) $origin as $f ) {
				if ( isset( $f['slug'] ) ) {
					$families[ $f['slug'] ] = $f;
				}
			}
		}
		$styles = wp_get_global_styles();
		$roles  = array(
			'body'    => $styles['typography']['fontFamily'] ?? '',
			'display' => $styles['elements']['heading']['typography']['fontFamily'] ?? ( $styles['elements']['h1']['typography']['fontFamily'] ?? '' ),
		);
		$fonts  = array();
		foreach ( $roles as $role => $value ) {
			if ( ! preg_match( '/(?:var:preset\|font-family\||var\(--wp--preset--font-family--)([\w-]+)\)?$/', (string) $value, $m ) || ! isset( $families[ $m[1] ] ) ) {
				continue;
			}
			$font = self::font( $role, $families[ $m[1] ] );
			if ( $font ) {
				$fonts[] = $font;
			}
		}
		return $fonts;
	}

	/** @return array<string, mixed>|null */
	private static function font( string $role, array $family ): ?array {
		$files = array();
		$name  = null;
		foreach ( (array) ( $family['fontFace'] ?? array() ) as $face ) {
			foreach ( (array) ( $face['src'] ?? array() ) as $src ) {
				$src = (string) $src;
				if ( ! preg_match( '/\.woff2(\?.*)?$/i', $src ) ) {
					continue;
				}
				$url = 0 === strpos( $src, 'file:./' ) ? get_theme_file_uri( substr( $src, 7 ) ) : $src;
				if ( ! wp_http_validate_url( $url ) && 0 !== strpos( $url, '/' ) ) {
					continue;
				}
				$name    = $name ?? trim( (string) ( $face['fontFamily'] ?? '' ), "\"' " );
				$file    = array( 'url' => esc_url_raw( $url ) );
				$weight  = preg_replace( '/[^0-9 ]/', '', (string) ( $face['fontWeight'] ?? '' ) );
				$style   = preg_replace( '/[^a-z]/', '', strtolower( (string) ( $face['fontStyle'] ?? '' ) ) );
				$file   += $weight ? array( 'weight' => $weight ) : array();
				$file   += $style ? array( 'style' => $style ) : array();
				$files[] = $file;
				break;
			}
		}
		// A family name written into the stylesheet in quotes: letters, digits, spaces and dashes only.
		if ( ! $files || ! $name || ! preg_match( '/^[\w \-]+$/u', $name ) ) {
			return null;
		}
		return array(
			'role'   => $role,
			'family' => $name,
			'files'  => $files,
		);
	}
}
