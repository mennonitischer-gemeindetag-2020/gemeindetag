<?php
/**
 * Gutenberg Blocks setup
 *
 * @package GemeindetagTheme
 */

namespace GemeindetagTheme\Blocks;

use GemeindetagTheme\Blocks\Example;
use GemeindetagTheme\Utility;

/**
 * Set up blocks
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'enqueue_block_editor_assets', $n( 'blocks_editor_styles' ) );
	add_action( 'init', $n( 'register_theme_block_patterns' ) );
	add_action( 'init', $n( 'register_block_pattern_categories' ) );
	add_filter( 'should_load_separate_core_block_assets', '__return_true' );
}

/**
 * Filter the plugins_url to allow us to use assets from theme.
 *
 * @param string $url  The plugins url
 * @param string $path The path to the asset.
 *
 * @return string The overridden url to the block asset.
 */
function filter_plugins_url( $url, $path ) {
	$file = preg_replace( '/\.\.\//', '', $path );
	return trailingslashit( get_stylesheet_directory_uri() ) . $file;
}

/**
 * Enqueue editor-only JavaScript/CSS for blocks.
 *
 * @return void
 */
function blocks_editor_styles() {
	wp_enqueue_style(
		'gemeindetag-theme-editor-style-overrides',
		GEMEINDETAG_THEME_TEMPLATE_URL . '/dist/css/editor-style-overrides.css',
		[],
		Utility\get_asset_info( 'editor-style-overrides', 'version' )
	);

	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		wp_enqueue_script(
			'gemeindetag-theme-editor-style-overrides-js',
			GEMEINDETAG_THEME_TEMPLATE_URL . '/dist/js/editor-style-overrides.js',
			Utility\get_asset_info( 'editor-style-overrides', 'dependencies' ),
			Utility\get_asset_info( 'editor-style-overrides', 'version' ),
			true
		);
	}

}

/**
 * Manage block pattern categories
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-patterns/
 *
 * @return void
 */
function register_block_pattern_categories() {

	// Register a block pattern category
	register_block_pattern_category(
		'gemeindetag',
		array( 'label' => __( 'Gemeindetag', 'gemeindetag-theme' ) )
	);
}

/**
 * This function will likely ship with WordPress 6.0 at which point we can remove it from the theme
 */
if ( ! function_exists( 'register_theme_block_patterns' ) ) :

	/**
	 * Register any patterns that the active theme may provide under its
	 * `./patterns/` directory. Each pattern is defined as a PHP file and defines
	 * its metadata using plugin-style headers. The minimum required definition is:
	 *
	 *     /**
	 *      * Title: My Pattern
	 *      * Slug: my-theme/my-pattern
	 *      *
	 *
	 * The output of the PHP source corresponds to the content of the pattern, e.g.:
	 *
	 *     <main><p><?php echo "Hello"; ?></p></main>
	 *
	 * Other settable fields include:
	 *
	 *   - Description
	 *   - Viewport Width
	 *   - Categories       (comma-separated values)
	 *   - Keywords         (comma-separated values)
	 *   - Block Types      (comma-separated values)
	 *   - Inserter         (yes/no)
	 */
	function register_theme_block_patterns() {
		$default_headers = array(
			'title'         => 'Title',
			'slug'          => 'Slug',
			'description'   => 'Description',
			'viewportWidth' => 'Viewport Width',
			'categories'    => 'Categories',
			'keywords'      => 'Keywords',
			'blockTypes'    => 'Block Types',
			'inserter'      => 'Inserter',
		);

		$dirpath = GEMEINDETAG_THEME_PATH . '/patterns/';
		if ( file_exists( $dirpath ) ) {
			$files = glob( $dirpath . '*.php' );
			if ( $files ) {
				foreach ( $files as $file ) {
					$pattern_data = get_file_data( $file, $default_headers );

					if ( empty( $pattern_data['slug'] ) ) {
						trigger_warning(
							sprintf(
								/* translators: %s: file name. */
								esc_html__( 'Could not register file "%s" as a block pattern ("Slug" field missing)', 'gemeindetag-theme' ),
								esc_attr( $file )
							)
						);
						continue;
					}

					if ( ! preg_match( '/^[A-z0-9\/_-]+$/', $pattern_data['slug'] ) ) {
						trigger_warning(
							sprintf(
								/* translators: %1s: file name; %2s: slug value found. */
								esc_html__( 'Could not register file "%1$s" as a block pattern (invalid slug "%2$s")', 'gemeindetag-theme' ),
								esc_attr( $file ),
								esc_attr( $pattern_data['slug'] )
							)
						);
					}

					if ( WP_Block_Patterns_Registry::get_instance()->is_registered( $pattern_data['slug'] ) ) {
						continue;
					}

					// Title is a required property.
					if ( ! $pattern_data['title'] ) {
						trigger_warning(
							sprintf(
								/* translators: %1s: file name; %2s: slug value found. */
								esc_html__( 'Could not register file "%s" as a block pattern ("Title" field missing)', 'gemeindetag-theme' ),
								esc_attr( $file )
							)
						);
						continue;
					}

					// For properties of type array, parse data as comma-separated.
					foreach ( array( 'categories', 'keywords', 'blockTypes' ) as $property ) {
						if ( ! empty( $pattern_data[ $property ] ) ) {
							$pattern_data[ $property ] = array_filter(
								preg_split(
									'/[\s,]+/',
									(string) $pattern_data[ $property ]
								)
							);
						} else {
							unset( $pattern_data[ $property ] );
						}
					}

					// Parse properties of type int.
					foreach ( array( 'viewportWidth' ) as $property ) {
						if ( ! empty( $pattern_data[ $property ] ) ) {
							$pattern_data[ $property ] = (int) $pattern_data[ $property ];
						} else {
							unset( $pattern_data[ $property ] );
						}
					}

					// Parse properties of type bool.
					foreach ( array( 'inserter' ) as $property ) {
						if ( ! empty( $pattern_data[ $property ] ) ) {
							$pattern_data[ $property ] = in_array(
								strtolower( $pattern_data[ $property ] ),
								array( 'yes', 'true' ),
								true
							);
						} else {
							unset( $pattern_data[ $property ] );
						}
					}

					// The actual pattern content is the output of the file.
					ob_start();
					include $file;
					$pattern_data['content'] = ob_get_clean();
					if ( ! $pattern_data['content'] ) {
						continue;
					}

					register_block_pattern( $pattern_data['slug'], $pattern_data );
				}
			}
		}
	}

endif;
