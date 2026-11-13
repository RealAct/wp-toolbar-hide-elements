<?php
/**
 * Plugin Name: Toolbar Hide Elements
 * Description: Hides WordPress Toolbar (Admin Bar) items by their visible name or node ID, for every user and role.
 * Version:     1.0.0
 * Requires PHP: 7.4
 *
 * HOW TO USE
 * ----------
 * 1. Edit the two lists in the CONFIGURATION section below.
 * 2. Install it one of these ways:
 *    - Code Snippets plugin: create a new PHP snippet, paste everything
 *      AFTER the opening "<?php" line, and set it to "Run snippet everywhere".
 *    - Must-use plugin: copy this file to wp-content/mu-plugins/.
 *
 * Names are matched case-insensitively and ignore accents, extra spaces,
 * icons, and counter badges, so "Fluent Forms Pro" also matches
 * "Fluent Forms Pro 22" and "anadir" matches "Añadir".
 */

defined( 'ABSPATH' ) || exit;

/* ==========================================================================
 * CONFIGURATION
 * ======================================================================== */

/**
 * Visible names of the Toolbar items to hide, written as they appear on screen
 * (in whatever language your site uses). Sub-menu items work too.
 */
function thbe_hidden_titles() {
	return array(
		'Theme Options',
		'Rank Math SEO',
		'Smart Slider',
		// 'Fluent Forms Pro',
		// 'Fragmentos de código',
		// 'Escritorio del foro',
		// 'Vaciar caché',
	);
}

/**
 * Optional: node IDs to hide. More reliable than names, because IDs do not
 * change with the language or with counters. Find an ID by inspecting the
 * item in your browser: <li id="wp-admin-bar-XXXX"> means the ID is "XXXX".
 * Core examples: 'wp-logo', 'comments', 'new-content', 'updates', 'search'.
 */
function thbe_hidden_ids() {
	return array(
		'mts-theme-options', // Theme Options
		'smart_slider_3',    // Smart Slider
		'rank-math',         // Rank Math SEO
		// 'fluent_form',               // Fluent Forms Pro
		// 'code-snippets',             // Fragmentos de código
		// 'wpf-community',             // Escritorio del foro
		// 'cache_enabler_clear_cache', // Vaciar caché
		// 'new_content_smart_slider',  // Añadir > Slider [Smart Slider 3]
	);
}

/**
 * Where to hide the items: true = admin area AND the front-end toolbar,
 * false = only inside the admin area (wp-admin).
 */
define( 'THBE_ALSO_ON_FRONTEND', true );

/**
 * The "Screen Options" ("Opciones de pantalla") and "Help" ("Ayuda") tabs
 * shown just below the Toolbar on admin screens. Set to true to hide them.
 */
define( 'THBE_HIDE_SCREEN_OPTIONS', false );
define( 'THBE_HIDE_HELP_TAB', false );

/* ==========================================================================
 * IMPLEMENTATION (no need to edit below this line)
 * ======================================================================== */

/**
 * Turns a node title (which may contain HTML, icons and badges) into a
 * comparable plain string: "Fluent Forms Pro <span>22</span>" -> "fluent forms pro".
 */
function thbe_normalize_label( $label ) {
	$label = (string) $label;

	// Drop text meant only for screen readers (e.g. "0 comments in moderation").
	$label = preg_replace( '#<(span|div)[^>]*class=["\'][^"\']*screen-reader-text[^"\']*["\'][^>]*>.*?</\1>#is', ' ', $label );
	// Keep a space where tags were, so "Pro<span>22</span>" becomes "Pro 22".
	$label = preg_replace( '#<[^>]*>#', ' ', $label );
	$label = html_entity_decode( $label, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	if ( function_exists( 'remove_accents' ) ) {
		$label = remove_accents( $label );
	}
	$label = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label, 'UTF-8' ) : strtolower( $label );

	// Collapse whitespace (including non-breaking spaces).
	$label = trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', $label ) );
	// Remove trailing counters/badges such as "22" or "(3)".
	$label = trim( preg_replace( '/(\s*\(?\d+\)?)+$/u', '', $label ) );

	return $label;
}

function thbe_should_run() {
	return is_admin() || THBE_ALSO_ON_FRONTEND;
}

/**
 * Removes matching nodes, plus all of their descendants, from the Toolbar.
 */
function thbe_remove_nodes( $wp_admin_bar = null ) {
	if ( ! thbe_should_run() ) {
		return;
	}
	if ( ! $wp_admin_bar ) {
		global $wp_admin_bar;
	}
	if ( ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'get_nodes' ) ) {
		return;
	}

	$nodes = $wp_admin_bar->get_nodes();
	if ( empty( $nodes ) ) {
		return;
	}

	$titles = array_filter( array_map( 'thbe_normalize_label', thbe_hidden_titles() ), 'strlen' );
	$ids    = array_map( 'strval', thbe_hidden_ids() );

	$to_remove = array();
	foreach ( $nodes as $id => $node ) {
		if ( in_array( (string) $id, $ids, true )
			|| ( '' !== thbe_normalize_label( $node->title ) && in_array( thbe_normalize_label( $node->title ), $titles, true ) ) ) {
			$to_remove[ $id ] = true;
		}
	}
	$GLOBALS['thbe_debug'][] = array( current_filter(), count( $nodes ), array_keys( $to_remove ) );

	// Include descendants so no orphaned sub-items remain.
	do {
		$added = false;
		foreach ( $nodes as $id => $node ) {
			if ( ! isset( $to_remove[ $id ] ) && $node->parent && isset( $to_remove[ $node->parent ] ) ) {
				$to_remove[ $id ] = true;
				$added            = true;
			}
		}
	} while ( $added );

	foreach ( array_keys( $to_remove ) as $id ) {
		$wp_admin_bar->remove_node( $id );
	}
}
// Pass 1: after every plugin has added its items to the menu.
add_action( 'admin_bar_menu', 'thbe_remove_nodes', PHP_INT_MAX );
// Pass 2: right before the Toolbar is printed, for items added even later.
add_action( 'wp_before_admin_bar_render', 'thbe_remove_nodes', PHP_INT_MAX );

/**
 * Fallback for the rare item injected into the Toolbar by JavaScript after
 * the page loads: hides it in the browser using the same name/ID rules.
 */
function thbe_print_js_fallback() {
	if ( ! thbe_should_run() || ! is_admin_bar_showing() ) {
		return;
	}
	$config = array(
		'titles' => array_values( array_filter( array_map( 'thbe_normalize_label', thbe_hidden_titles() ), 'strlen' ) ),
		'ids'    => array_values( array_map( 'strval', thbe_hidden_ids() ) ),
	);
	?>
	<script>
	(function () {
		var cfg = <?php echo wp_json_encode( $config ); ?>;
		if (!cfg.titles.length && !cfg.ids.length) { return; }
		function norm(s) {
			s = (s || '').normalize ? (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '') : (s || '');
			return s.toLowerCase().replace(/[\s ]+/g, ' ').trim().replace(/(\s*\(?\d+\)?)+$/, '').trim();
		}
		function labelOf(li) {
			var a = li.querySelector(':scope > .ab-item');
			if (!a) { return ''; }
			var c = a.cloneNode(true);
			c.querySelectorAll('.screen-reader-text').forEach(function (n) { n.remove(); });
			c.querySelectorAll('*').forEach(function (n) { n.insertAdjacentText('beforebegin', ' '); });
			return norm(c.textContent);
		}
		function sweep() {
			var bar = document.getElementById('wpadminbar');
			if (!bar) { return; }
			bar.querySelectorAll('li[id^="wp-admin-bar-"]').forEach(function (li) {
				var id = li.id.replace(/^wp-admin-bar-/, '');
				if (cfg.ids.indexOf(id) !== -1 || cfg.titles.indexOf(labelOf(li)) !== -1) {
					li.style.setProperty('display', 'none', 'important');
				}
			});
		}
		sweep();
		var bar = document.getElementById('wpadminbar');
		if (bar && window.MutationObserver) {
			new MutationObserver(sweep).observe(bar, { childList: true, subtree: true });
		}
	})();
	</script>
	<?php
}
add_action( 'admin_footer', 'thbe_print_js_fallback', PHP_INT_MAX );
add_action( 'wp_footer', 'thbe_print_js_fallback', PHP_INT_MAX );

/**
 * Optionally hides the "Screen Options" and "Help" tabs below the Toolbar.
 */
if ( THBE_HIDE_SCREEN_OPTIONS ) {
	add_filter( 'screen_options_show_screen', '__return_false', PHP_INT_MAX );
}
if ( THBE_HIDE_HELP_TAB ) {
	add_action(
		'admin_head',
		function () {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen ) {
				$screen->remove_help_tabs();
				$screen->set_help_sidebar( '' );
			}
		},
		PHP_INT_MAX
	);
}

/**
 * CSS safety net: hides configured IDs (and their sub-items) even if a plugin
 * re-adds them after the removal passes above. Printed in <head>, so no flicker.
 */
function thbe_print_css() {
	if ( ! thbe_should_run() ) {
		return;
	}
	$selectors = array();
	foreach ( thbe_hidden_ids() as $id ) {
		$id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id );
		if ( '' !== $id ) {
			$selectors[] = '#wpadminbar #wp-admin-bar-' . $id;
		}
	}
	if ( $selectors ) {
		echo '<style id="thbe-css">' . implode( ',', $selectors ) . '{display:none!important}</style>' . "\n";
	}
}
add_action( 'admin_head', 'thbe_print_css', PHP_INT_MAX );
add_action( 'wp_head', 'thbe_print_css', PHP_INT_MAX );

/**
 * Troubleshooting: add ?thbe_debug=1 to any admin URL (as an administrator)
 * to see whether the snippet ran and what it removed.
 */
add_action(
	'admin_notices',
	function () {
		if ( empty( $_GET['thbe_debug'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		echo '<div class="notice notice-info"><p><strong>Toolbar Hide Elements is running.</strong></p><ul>';
		foreach ( (array) ( $GLOBALS['thbe_debug'] ?? array() ) as $row ) {
			printf(
				'<li><code>%s</code>: %d items checked, removed: <code>%s</code></li>',
				esc_html( $row[0] ),
				(int) $row[1],
				esc_html( $row[2] ? implode( ', ', $row[2] ) : '(none)' )
			);
		}
		if ( empty( $GLOBALS['thbe_debug'] ) ) {
			echo '<li>The removal step never ran (another plugin may be replacing the toolbar).</li>';
		}
		echo '</ul></div>';
	}
);
