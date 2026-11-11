<?php
/**
 * Test-only must-use plugin (never shipped): a site that opts in to the credit line,
 * for requests that carry ?credit=1, so the tests can check both sides.
 */

if ( isset( $_GET['credit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	add_filter( 'maisongr_sitemap_credit', '__return_true' );
}
