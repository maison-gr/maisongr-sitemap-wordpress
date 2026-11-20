<?php
// Test-only must-use plugin (never shipped): Jetpack without a WordPress.com connection, as on a
// site that only uses its free modules. Offline mode lets the Sitemaps module run unconnected.
add_filter( 'jetpack_offline_mode', '__return_true' );
