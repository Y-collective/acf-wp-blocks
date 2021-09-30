<?php

namespace App;
// Check whether WordPress and ACF are available; bail if not.
if ( ! function_exists( 'acf_register_block_type' ) || ! function_exists( 'add_filter' ) || ! function_exists( 'add_action' ) ) {
	return;
}

// Add the default blocks location, 'views/blocks', via filter
add_filter( 'yco-acf-gutenberg-blocks-templates', function () {
	return [ 'templates/blocks' ];
} );

/**
 * Create blocks based on templates found in yco's "views/blocks" directory
 */
add_action( 'acf/init', function () {

	// Global $yco_error so we can throw errors in the typical yco manner
	global $yco_error;

	// Get an array of directories containing blocks
	$directories = apply_filters( 'yco-acf-gutenberg-blocks-templates', [] );

	// Check whether ACF exists before continuing
	foreach ( $directories as $directory ) {
		$dir = \locate_template( $directory );

		// Sanity check whether the directory we're iterating over exists first
		if ( ! file_exists( $dir ) ) {
			return;
		}

		// Iterate over the directories provided and look for templates
		$template_directory = new \DirectoryIterator( $dir );

		foreach ( $template_directory as $template ) {
			if ( ! $template->isDot() && ! $template->isDir() ) {
				// Strip the file extension to get the slug
				$slug = removeExtension( $template->getFilename() );
				// If there is no slug (most likely because the filename does
				// not end with ".php", move on to the next file.
				if ( ! $slug ) {
					continue;
				}

				// Get header info from the found template file(s)
				$file         = "${dir}/${slug}.php";
				$file_path    = file_exists( $file ) ? $file : '';
				$file_headers = get_file_data( $file_path, [
					'title'                  => 'Title',
					'description'            => 'Description',
					'category'               => 'Category',
					'icon'                   => 'Icon',
					'keywords'               => 'Keywords',
					'mode'                   => 'Mode',
					'align'                  => 'Align',
					'post_types'             => 'PostTypes',
					'supports_align'         => 'SupportsAlign',
					'supports_anchor'        => 'SupportsAnchor',
					'supports_mode'          => 'SupportsMode',
					'supports_jsx'           => 'SupportsInnerBlocks',
					'supports_align_text'    => 'SupportsAlignText',
					'supports_align_content' => 'SupportsAlignContent',
					'supports_multiple'      => 'SupportsMultiple',
					'enqueue_style'          => 'EnqueueStyle',
					'enqueue_script'         => 'EnqueueScript',
					'enqueue_assets'         => 'EnqueueAssets',
				] );

				if ( empty( $file_headers['title'] ) ) {
					$yco_error( __( 'This block needs a title: ' . $dir . '/' . $template->getFilename(), 'yco' ), __( 'Block title missing', 'yco' ) );
				}

				if ( empty( $file_headers['category'] ) ) {
					$yco_error( __( 'This block needs a category: ' . $dir . '/' . $template->getFilename(), 'yco' ), __( 'Block category missing', 'yco' ) );
				}

				// Checks if dist contains this asset, then enqueues the dist version.
				if ( ! empty( $file_headers['enqueue_style'] ) ) {
					checkAssetPath( $file_headers['enqueue_style'] );
				}

				if ( ! empty( $file_headers['enqueue_script'] ) ) {
					checkAssetPath( $file_headers['enqueue_script'] );
				}

				// Set up block data for registration
				$data = [
					'name'            => $slug,
					'title'           => $file_headers['title'],
					'description'     => $file_headers['description'],
					'category'        => $file_headers['category'],
					'icon'            => $file_headers['icon'],
					'keywords'        => explode( ' ', $file_headers['keywords'] ),
					'mode'            => $file_headers['mode'],
					'align'           => $file_headers['align'],
					'render_callback' => __NAMESPACE__ . '\\yco_blocks_callback',
					'enqueue_style'   => $file_headers['enqueue_style'],
					'enqueue_script'  => $file_headers['enqueue_script'],
					'enqueue_assets'  => $file_headers['enqueue_assets'],
					'example'         => array(
						'attributes' => array(
							'mode' => 'preview',
						)
					)
				];

				// If the PostTypes header is set in the template, restrict this block to those types
				if ( ! empty( $file_headers['post_types'] ) ) {
					$data['post_types'] = explode( ' ', $file_headers['post_types'] );
				}

				// If the SupportsAlign header is set in the template, restrict this block to those aligns
				if ( ! empty( $file_headers['supports_align'] ) ) {
					$data['supports']['align'] = in_array( $file_headers['supports_align'], array(
						'true',
						'false'
					), true ) ? filter_var( $file_headers['supports_align'], FILTER_VALIDATE_BOOLEAN ) : explode( ' ', $file_headers['supports_align'] );
				}

				// If the SupportsMode header is set in the template, restrict this block mode feature
				if ( ! empty( $file_headers['supports_anchor'] ) ) {
					$data['supports']['anchor'] = $file_headers['supports_anchor'] === 'true' ? true : false;
				}

				// If the SupportsMode header is set in the template, restrict this block mode feature
				if ( ! empty( $file_headers['supports_mode'] ) ) {
					$data['supports']['mode'] = $file_headers['supports_mode'] === 'true' ? true : false;
				}

				// If the SupportsInnerBlocks header is set in the template, restrict this block mode feature
				if ( ! empty( $file_headers['supports_jsx'] ) ) {
					$data['supports']['jsx'] = $file_headers['supports_jsx'] === 'true' ? true : false;
				}

				// If the SupportsAlignText header is set in the template, restrict this block mode feature
				if ( ! empty( $file_headers['supports_align_text'] ) ) {
					$data['supports']['align_text'] = $file_headers['supports_align_text'] === 'true' ? true : false;
				}

				// If the SupportsAlignContent header is set in the template, restrict this block mode feature
				if ( ! empty( $file_headers['supports_align_text'] ) ) {
					$data['supports']['align_content'] = $file_headers['supports_align_content'] === 'true' ? true : false;
				}

				// If the SupportsMultiple header is set in the template, restrict this block multiple feature
				if ( ! empty( $file_headers['supports_multiple'] ) ) {
					$data['supports']['multiple'] = $file_headers['supports_multiple'] === 'true' ? true : false;
				}

				// Register the block with ACF
				\acf_register_block_type( apply_filters( "yco/blocks/$slug/register-data", $data ) );
			}
		}
	}
} );

/**
 * Callback to register blocks
 */
function yco_blocks_callback( $block, $content = '', $is_preview = false, $post_id = 0 ) {
	// Set up the slug to be useful
	$slug  = str_replace( 'acf/', '', $block['name'] );
	$block = array_merge( [ 'className' => '' ], $block );

	// Set up the block data
	$block['post_id']    = $post_id;
	$block['is_preview'] = $is_preview;
	$block['content']    = $content;
	$block['slug']       = $slug;
	$block['anchor']     = isset( $block['anchor'] ) ? $block['anchor'] : '';
	// Send classes as array to filter for easy manipulation.
	$block['classes'] = [
		$slug,
		$block['className'],
		$block['is_preview'] ? 'is-preview' : null,
		'align' . $block['align']
	];

	// Filter the block data.
	$block = apply_filters( "yco/blocks/$slug/data", $block );

	// Join up the classes.
	$block['classes'] = implode( ' ', array_filter( $block['classes'] ) );

	// Get the template directories.
	$directories = apply_filters( 'yco-acf-gutenberg-blocks-templates', [] );

	foreach ( $directories as $directory ) {
		$template = "$directory/$slug";
		echo template( "$template", [ 'block' => $block ] );
	}
}

/**
 * Checks asset path for specified asset.
 *
 * @param string &$path
 *
 * @return void
 */
function checkAssetPath( &$path ) {
	if ( preg_match( "/^(styles|scripts)/", $path ) ) {
		$path = asset_path( $path );
	}
}

/**
 * Function to strip the `.php` from a filename
 */
function removeExtension( $filename ) {
	// Filename must end with ".php". Parenthetical captures the slug.
	$pattern = '/(.*)\.php$/';
	$matches = [];
	// If the filename matches the pattern, return the slug.
	if ( preg_match( $pattern, $filename, $matches ) ) {
		return $matches[1];
	}

	// Return FALSE if the filename doesn't match the pattern.
	return false;
}
