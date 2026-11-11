<?php
/**
 * The page's words, in the site's own language: English and French, as in the
 * maisongr-sitemap package (src/strings.ts). Any other language gets English.
 * A language is chosen by its primary code, so "fr_CA" and "fr" both get French.
 *
 * @package MaisonGR\Sitemap
 */

namespace MaisonGR\Sitemap;

defined( 'ABSPATH' ) || exit;

final class Strings {

	/** @var string */
	public $lang;

	/** @var array<string, string> */
	private $words;

	private const EN = array(
		'title'              => 'Sitemap',
		'index_title'        => 'Sitemap index',
		'pages_section'      => 'Pages',
		'files_section'      => 'Files',
		'filter_placeholder' => 'Filter by path',
		'filter_label'       => 'Filter pages by path',
		'language_group'     => 'Language',
		'all'                => 'All',
		'recent'             => 'Recently updated',
		'sections_nav'       => 'Sections',
		'no_match'           => 'No page matches this filter.',
		'empty'              => 'No pages are listed yet. They appear here, and in the XML, once the site publishes them.',
		'footer'             => 'Search engines read the same list as XML.',
		'tally'              => '{shown} of {total} pages',
		'xml_eyebrow'        => 'Sitemap · XML',
		'xml_note'           => 'This is the file search engines read.',
	);

	private const FR = array(
		'title'              => 'Plan du site',
		'index_title'        => 'Index du plan du site',
		'pages_section'      => 'Pages',
		'files_section'      => 'Fichiers',
		'filter_placeholder' => 'Filtrer par adresse',
		'filter_label'       => 'Filtrer les pages par adresse',
		'language_group'     => 'Langue',
		'all'                => 'Toutes',
		'recent'             => 'Mises à jour récentes',
		'sections_nav'       => 'Sections',
		'no_match'           => 'Aucune page ne correspond à ce filtre.',
		'empty'              => 'Aucune page n’est encore listée. Elles apparaîtront ici, et dans le XML, dès que le site les publiera.',
		'footer'             => 'Les moteurs de recherche lisent la même liste en XML.',
		'tally'              => '{shown} sur {total} pages',
		'xml_eyebrow'        => 'Plan du site · XML',
		'xml_note'           => 'Ce fichier est celui que lisent les moteurs de recherche.',
	);

	private const MONTHS = array(
		'en' => array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ),
		'fr' => array( 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' ),
	);

	/**
	 * @param string|null $lang A language or locale: "fr", "fr-CA", "fr_FR".
	 */
	public function __construct( $lang = null ) {
		$primary     = strtolower( (string) preg_split( '/[-_]/', (string) ( $lang ? $lang : 'en' ) )[0] );
		$this->lang  = 'fr' === $primary ? 'fr' : 'en';
		$this->words = 'fr' === $this->lang ? self::FR : self::EN;
	}

	public function get( string $key ): string {
		return $this->words[ $key ];
	}

	public function heading( string $name ): string {
		if ( 'en' === $this->lang ) {
			return "Every page on {$name}";
		}
		// "d’Atelier", "d’Orbit", but "de Maison GR": de elides before a vowel.
		return 'Toutes les pages ' . ( preg_match( '/^[aeiouyàâäéèêëîïôöùûüæœ]/iu', $name ) ? 'd’' : 'de ' ) . $name;
	}

	public function pages( int $n ): string {
		// French takes the singular for 0 and 1.
		return 'fr' === $this->lang ? ( $n < 2 ? 'page' : 'pages' ) : ( 1 === $n ? 'page' : 'pages' );
	}

	public function addresses( int $n ): string {
		return 'fr' === $this->lang ? ( $n < 2 ? 'adresse' : 'adresses' ) : ( 1 === $n ? 'address' : 'addresses' );
	}

	public function files( int $n ): string {
		return 'fr' === $this->lang ? ( $n < 2 ? 'fichier' : 'fichiers' ) : ( 1 === $n ? 'file' : 'files' );
	}

	/** When the newest page last changed, in UTC: "Updated 5 November 2026", "Mis à jour le 1er novembre 2026". */
	public function updated( int $timestamp ): string {
		$day   = (int) gmdate( 'j', $timestamp );
		$month = self::MONTHS[ $this->lang ][ (int) gmdate( 'n', $timestamp ) - 1 ];
		$year  = gmdate( 'Y', $timestamp );
		if ( 'fr' === $this->lang ) {
			return 'Mis à jour le ' . ( 1 === $day ? '1er' : (string) $day ) . " {$month} {$year}";
		}
		return "Updated {$day} {$month} {$year}";
	}

	/** The credit, around the tool's and the studio's links (already HTML). */
	public function credit( string $tool, string $studio ): string {
		return 'fr' === $this->lang ? "Réalisé avec {$tool}, outil open source de {$studio}" : "Made with {$tool}, open source, by {$studio}";
	}

	public function xml_page( string $path ): string {
		return 'fr' === $this->lang ? "La page à lire est à l’adresse {$path}" : "The page for people is at {$path}";
	}
}
