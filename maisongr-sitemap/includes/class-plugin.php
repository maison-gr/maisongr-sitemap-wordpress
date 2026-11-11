<?php
/**
 * One address, two readers. A browser opening /sitemap.xml announces a page
 * visit with Sec-Fetch-Dest: document and asks for HTML, and gets the designed
 * page; crawlers send neither and get the XML. Both list exactly the same
 * addresses (one list presented two ways, not cloaking). The page is noindex
 * and private, and Vary keeps any cache from handing one to the other.
 *
 * @package MaisonGR\Sitemap
 */

namespace MaisonGR\Sitemap;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private const QUERY_VAR = 'maisongr_sitemap';

	/** Bumped when the rewrite rules change, so they are flushed once after an update. */
	private const RULES_VERSION = '1';

	public static function boot(): void {
		add_action( 'init', array( self::class, 'rewrite' ) );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'template_redirect', array( self::class, 'serve' ), 0 );
		// WordPress would add a trailing slash to /sitemap.xml/ on some permalink structures.
		add_filter( 'redirect_canonical', array( self::class, 'keep_address' ) );
		// One sitemap: WordPress's own (wp-sitemap.xml) is turned off and its address sent here.
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		add_filter( 'robots_txt', array( self::class, 'robots' ), 20, 2 );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
		foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'created_term', 'edited_term', 'delete_term', 'switch_theme', 'customize_save_after', 'update_option_blogname', 'update_option_show_on_front', 'update_option_page_on_front', 'update_option_permalink_structure' ) as $hook ) {
			add_action( $hook, array( Entries::class, 'forget' ) );
		}
	}

	public static function rewrite(): void {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		add_rewrite_rule( '^wp-sitemap(?:-[a-z0-9-]+)?\.(?:xml|xsl)$', 'index.php?' . self::QUERY_VAR . '=moved', 'top' );
		if ( get_option( 'maisongr_sitemap_rules' ) !== self::RULES_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'maisongr_sitemap_rules', self::RULES_VERSION );
		}
	}

	public static function activate(): void {
		self::rewrite();
		flush_rewrite_rules( false );
		Entries::forget();
	}

	public static function deactivate(): void {
		delete_option( 'maisongr_sitemap_rules' );
		Entries::forget();
		flush_rewrite_rules( false );
	}

	/** @param string[] $vars */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/** @param string|false $redirect */
	public static function keep_address( $redirect ) {
		return get_query_var( self::QUERY_VAR ) ? false : $redirect;
	}

	public static function sitemap_url(): string {
		return home_url( '/sitemap.xml' );
	}

	/** robots.txt names the sitemap, which is how crawlers that never read Search Console find it. */
	public static function robots( string $output, $public ): string {
		if ( ! $public || false !== stripos( $output, 'sitemap:' ) ) {
			return $output;
		}
		return rtrim( $output ) . "\n\nSitemap: " . self::sitemap_url() . "\n";
	}

	/** Two sitemaps confuse search engines: name the SEO plugin that makes another one. */
	public static function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$other = null;
		if ( class_exists( 'WPSEO_Options' ) && \WPSEO_Options::get( 'enable_xml_sitemap' ) ) {
			$other = __( 'Yoast SEO makes a sitemap too. Turn off its XML sitemaps (Yoast SEO, Settings, Site features), so search engines read one list.', 'maisongr-sitemap' );
		} elseif ( class_exists( '\RankMath\Helper' ) && \RankMath\Helper::is_module_active( 'sitemap' ) ) {
			$other = __( 'Rank Math makes a sitemap too. Turn off its Sitemap module (Rank Math, Dashboard), so search engines read one list.', 'maisongr-sitemap' );
		} elseif ( function_exists( 'aioseo' ) && aioseo()->options->sitemap->general->enable ) {
			$other = __( 'All in One SEO makes a sitemap too. Turn off its sitemap (All in One SEO, Sitemaps), so search engines read one list.', 'maisongr-sitemap' );
		}
		if ( $other ) {
			printf( '<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>', esc_html__( 'Maison GR Sitemap:', 'maisongr-sitemap' ), esc_html( $other ) );
		}
	}

	/**
	 * A person opening the address, not a crawler: a browser that opens a page sends both
	 * Sec-Fetch-Dest: document and an Accept that asks for HTML. No user agent is read.
	 */
	private static function is_browser(): bool {
		$dest   = isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ) : '';
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		return 'document' === $dest && (bool) preg_match( '/\btext\/html\b/i', $accept );
	}

	private static function rules(): string {
		$css = (string) file_get_contents( MAISONGR_SITEMAP_DIR . 'assets/page-rules.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		// The generated file's banner comment is for people reading the source, not for browsers.
		return (string) preg_replace( '#^/\*.*?\*/\n#s', '', $css, 1 );
	}

	private static function script(): string {
		$js = (string) file_get_contents( MAISONGR_SITEMAP_DIR . 'assets/page.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return (string) preg_replace( '#^/\*.*?\*/\n#s', '', $js, 1 );
	}

	/** The page's options: its language from the site's, its home, and the credit line. */
	private static function page_options(): array {
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		/**
		 * Filters whether the page shows its "Made with maisongr-sitemap" line. Default false:
		 * a credit on the public site is the site owner's choice (WordPress.org guideline 10).
		 *
		 * @param bool $credit Whether to show it.
		 */
		$credit = (bool) apply_filters( 'maisongr_sitemap_credit', false );
		return array(
			'credit' => $credit,
			'home'   => '' !== $home ? $home : '/',
			'lang'   => get_locale(),
		);
	}

	public static function serve(): void {
		global $wp;
		$what = get_query_var( self::QUERY_VAR );
		// WordPress's own sitemap addresses, whose rules come before this plugin's: they now live at /sitemap.xml.
		if ( ! $what && isset( $wp->request ) && preg_match( '/^wp-sitemap(?:-[a-z0-9-]+)?\.(?:xml|xsl)$/', (string) $wp->request ) ) {
			$what = 'moved';
		}
		if ( ! $what ) {
			return;
		}
		if ( 'moved' === $what ) {
			wp_safe_redirect( self::sitemap_url(), 301, 'Maison GR Sitemap' );
			exit;
		}
		$asset = isset( $_GET['asset'] ) ? sanitize_text_field( wp_unslash( $_GET['asset'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public, read-only address.
		$part  = isset( $_GET['part'] ) ? absint( $_GET['part'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$theme = Theme::read();
		$page  = self::page_options();

		if ( 'page.css' === $asset ) {
			self::send( Page::css( $theme, self::rules() ), 'text/css', array( 'Cache-Control' => 'public, max-age=86400' ) );
		}
		if ( 'page.js' === $asset ) {
			self::send( self::script(), 'text/javascript', array( 'Cache-Control' => 'public, max-age=86400' ) );
		}
		if ( 'xml.css' === $asset ) {
			self::send( Page::xml_css( $theme, array( 'lang' => $page['lang'] ) ), 'text/css', array( 'Cache-Control' => 'public, max-age=86400' ) );
		}

		$browser = self::is_browser();
		$path    = (string) wp_parse_url( self::sitemap_url(), PHP_URL_PATH );
		$self    = "{$path}?asset=xml.css";
		// The page is private: a shared cache that ignores Vary must never store it and hand it to a crawler.
		$page_headers = array(
			'X-Robots-Tag'  => 'noindex',
			'Cache-Control' => 'private, max-age=3600',
		);
		$xml_headers  = array( 'Cache-Control' => 'public, max-age=3600' );

		$chunks = Xml::chunk( Entries::all() );
		if ( 1 === count( $chunks ) && ! $part ) {
			$entries = $chunks[0];
			$browser
				? self::send( Page::urlset( $entries, $theme, $page ), 'text/html', $page_headers, Xml::newest( $entries ) )
				: self::send( Xml::urlset( $entries, $self ), 'application/xml', $xml_headers, Xml::newest( $entries ) );
		}
		if ( ! $part ) {
			$parts = array();
			foreach ( $chunks as $i => $c ) {
				$parts[] = array(
					'loc'     => self::sitemap_url() . '?part=' . ( $i + 1 ),
					'lastmod' => Xml::newest( $c ),
					'count'   => count( $c ),
				);
			}
			$lastmod = Xml::newest( $parts );
			$browser
				? self::send( Page::index( $parts, $theme, $page ), 'text/html', $page_headers, $lastmod )
				: self::send( Xml::index( $parts, $self ), 'application/xml', $xml_headers, $lastmod );
		}
		if ( ! isset( $chunks[ $part - 1 ] ) ) {
			status_header( 404 );
			header( 'Vary: Sec-Fetch-Dest' );
			exit;
		}
		$entries = $chunks[ $part - 1 ];
		$browser
			? self::send( Page::urlset( $entries, $theme, $page ), 'text/html', $page_headers, Xml::newest( $entries ) )
			: self::send( Xml::urlset( $entries, $self ), 'application/xml', $xml_headers, Xml::newest( $entries ) );
	}

	/**
	 * Sends a body with its headers, or 304 when the request's If-None-Match still names it,
	 * so a crawler skips an unchanged file. Only the ETag decides: a date would miss an address
	 * that was taken out.
	 *
	 * @param array<string, string> $headers
	 */
	private static function send( string $body, string $type, array $headers, ?string $lastmod = null ): void {
		$tag = '"' . substr( hash( 'sha256', $body ), 0, 32 ) . '"';
		status_header( 200 );
		header( "Content-Type: {$type}; charset=utf-8" );
		header( 'ETag: ' . $tag );
		if ( 'text/html' === $type || 'application/xml' === $type ) {
			header( 'Vary: Sec-Fetch-Dest' );
		}
		foreach ( $headers as $name => $value ) {
			header( "{$name}: {$value}" );
		}
		if ( $lastmod ) {
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', (int) strtotime( $lastmod . ' 00:00:00 UTC' ) ) . ' GMT' );
		}
		$match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
		foreach ( explode( ',', $match ) as $candidate ) {
			$candidate = preg_replace( '#^W/#', '', trim( $candidate ) );
			if ( '*' === $candidate || $tag === $candidate ) {
				status_header( 304 );
				exit;
			}
		}
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === $_SERVER['REQUEST_METHOD'] ) {
			exit;
		}
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by Page and Xml, which escape every value they write.
		exit;
	}
}
