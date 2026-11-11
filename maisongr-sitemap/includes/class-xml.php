<?php
/**
 * The XML search engines read, as the maisongr-sitemap package writes it
 * (src/xml.ts): one <url> per address, language versions as xhtml:link, the
 * date it really changed, its images. Past the protocol's limits (50,000
 * addresses or 50 MB a file) the sitemap is split into parts.
 *
 * An entry is an array: loc (absolute URL), and optionally lastmod
 * (YYYY-MM-DD), lang, alternates (list of hreflang and href) and images.
 *
 * @package MaisonGR\Sitemap
 */

namespace MaisonGR\Sitemap;

defined( 'ABSPATH' ) || exit;

final class Xml {

	public const MAX_URLS  = 50000;
	public const MAX_BYTES = 52428800;

	/** Google reads at most this many images a page. */
	private const MAX_IMAGES = 1000;

	public static function escape( string $s ): string {
		return str_replace( array( '&', '<', '>', '"', "'" ), array( '&amp;', '&lt;', '&gt;', '&quot;', '&apos;' ), $s );
	}

	private static function head( ?string $stylesheet ): string {
		return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . ( $stylesheet ? '<?xml-stylesheet type="text/css" href="' . self::escape( $stylesheet ) . "\"?>\n" : '' );
	}

	/** @param array<string, mixed> $e */
	public static function url( array $e ): string {
		$lines = array( '    <loc>' . self::escape( $e['loc'] ) . '</loc>' );
		foreach ( $e['alternates'] ?? array() as $a ) {
			$lines[] = '    <xhtml:link rel="alternate" hreflang="' . self::escape( $a['hreflang'] ) . '" href="' . self::escape( $a['href'] ) . '"/>';
		}
		if ( ! empty( $e['lastmod'] ) ) {
			$lines[] = '    <lastmod>' . self::escape( $e['lastmod'] ) . '</lastmod>';
		}
		foreach ( array_slice( array_values( array_unique( $e['images'] ?? array() ) ), 0, self::MAX_IMAGES ) as $image ) {
			$lines[] = '    <image:image><image:loc>' . self::escape( $image ) . '</image:loc></image:image>';
		}
		return "  <url>\n" . implode( "\n", $lines ) . "\n  </url>";
	}

	/**
	 * @param array<int, array<string, mixed>> $entries
	 */
	public static function urlset( array $entries, ?string $stylesheet = null ): string {
		$body = implode( "\n", array_map( array( self::class, 'url' ), $entries ) );
		// Only the namespaces the file uses.
		$ns = array( 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' );
		if ( false !== strpos( $body, '<xhtml:' ) ) {
			$ns[] = 'xmlns:xhtml="http://www.w3.org/1999/xhtml"';
		}
		if ( false !== strpos( $body, '<image:' ) ) {
			$ns[] = 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
		}
		return self::head( $stylesheet ) . '<urlset ' . implode( ' ', $ns ) . ">\n{$body}\n</urlset>\n";
	}

	/**
	 * @param array<int, array<string, mixed>> $parts Each with loc and an optional lastmod.
	 */
	public static function index( array $parts, ?string $stylesheet = null ): string {
		$items = array_map(
			static function ( $p ) {
				return "  <sitemap>\n    <loc>" . self::escape( $p['loc'] ) . '</loc>' . ( ! empty( $p['lastmod'] ) ? "\n    <lastmod>" . self::escape( $p['lastmod'] ) . '</lastmod>' : '' ) . "\n  </sitemap>";
			},
			$parts
		);
		return self::head( $stylesheet ) . "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n" . implode( "\n", $items ) . "\n</sitemapindex>\n";
	}

	/**
	 * The entries in files within both limits. 1 KB a file is kept for its head and namespaces.
	 *
	 * @param array<int, array<string, mixed>> $entries
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	public static function chunk( array $entries, int $max_urls = self::MAX_URLS, int $max_bytes = self::MAX_BYTES ): array {
		$room    = $max_bytes - 1024;
		$chunks  = array();
		$current = array();
		$size    = 0;
		foreach ( $entries as $e ) {
			$bytes = strlen( self::url( $e ) ) + 1;
			if ( $current && ( count( $current ) === $max_urls || $size + $bytes > $room ) ) {
				$chunks[] = $current;
				$current  = array();
				$size     = 0;
			}
			$current[] = $e;
			$size     += $bytes;
		}
		if ( $current || ! $chunks ) {
			$chunks[] = $current;
		}
		return $chunks;
	}

	/**
	 * The newest date among the entries.
	 *
	 * @param array<int, array<string, mixed>> $entries
	 */
	public static function newest( array $entries ): ?string {
		$max = null;
		foreach ( $entries as $e ) {
			if ( ! empty( $e['lastmod'] ) && ( null === $max || $e['lastmod'] > $max ) ) {
				$max = $e['lastmod'];
			}
		}
		return $max;
	}
}
