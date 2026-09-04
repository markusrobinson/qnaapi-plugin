<?php
/**
 * Registers the three Gutenberg blocks (poll/quiz/form) as dynamic blocks
 * that share their render output with the shortcodes class — a block is
 * really just [qnaapi_poll identifier="..."] with an editor UI on top.
 *
 * The editor scripts are plain JS (no build step): they're registered
 * manually with an explicit dependency list rather than relying on
 * block.json's file-based auto-registration, since that only infers
 * dependencies from a *.asset.php file a JS bundler would normally emit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QNAAPI_Connect_Blocks {

	/** @var array<string,string> Block slug => resource type. */
	const BLOCKS = array(
		'poll' => 'poll',
		'quiz' => 'quiz',
		'form' => 'form',
	);

	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$shortcodes = new QNAAPI_Connect_Shortcodes();

		foreach ( self::BLOCKS as $slug => $type ) {
			$handle = 'qnaapi-connect-' . $slug . '-editor';

			wp_register_script(
				$handle,
				QNAAPI_CONNECT_URL . 'blocks/' . $slug . '/index.js',
				array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
				QNAAPI_CONNECT_VERSION,
				true
			);

			register_block_type(
				QNAAPI_CONNECT_DIR . 'blocks/' . $slug,
				array(
					'editor_script'   => $handle,
					'render_callback' => function ( $attributes ) use ( $shortcodes, $type ) {
						$identifier = isset( $attributes['identifier'] ) ? $attributes['identifier'] : '';

						switch ( $type ) {
							case 'poll':
								return $shortcodes->render_poll( array( 'identifier' => $identifier ) );
							case 'quiz':
								return $shortcodes->render_quiz( array( 'identifier' => $identifier ) );
							case 'form':
								return $shortcodes->render_form( array( 'identifier' => $identifier ) );
							default:
								return '';
						}
					},
				)
			);
		}
	}
}
