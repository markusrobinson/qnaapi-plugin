<?php
/**
 * Enqueues the plugin's CSS/JS: frontend assets only on pages that actually
 * contain a QNAAPI shortcode or block, and admin assets only on the
 * plugin's own wp-admin screens.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QNAAPI_Connect_Assets {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_admin_assets' ) );
	}

	public function maybe_enqueue_frontend_assets() {
		if ( ! $this->current_page_has_a_widget() ) {
			return;
		}

		wp_enqueue_style(
			'qnaapi-connect-frontend',
			QNAAPI_CONNECT_URL . 'assets/css/frontend.css',
			array(),
			QNAAPI_CONNECT_VERSION
		);

		wp_enqueue_script(
			'qnaapi-connect-frontend',
			QNAAPI_CONNECT_URL . 'assets/js/frontend.js',
			array(),
			QNAAPI_CONNECT_VERSION,
			true
		);

		$options = get_option( 'qnaapi_connect_options', array() );

		wp_localize_script(
			'qnaapi-connect-frontend',
			'qnaapiConnect',
			array(
				'apiBase' => isset( $options['base_url'] ) && $options['base_url'] ? $options['base_url'] : QNAAPI_CONNECT_DEFAULT_BASE_URL,
				'i18n'    => array(
					'voting'          => __( 'Submitting…', 'qnaapi-connect' ),
					'voted'           => __( 'Thanks for voting!', 'qnaapi-connect' ),
					'alreadyVoted'    => __( 'You\'ve already voted on this poll.', 'qnaapi-connect' ),
					'quizSubmitting'  => __( 'Scoring…', 'qnaapi-connect' ),
					'quizAlreadyDone' => __( 'You\'ve already taken this quiz.', 'qnaapi-connect' ),
					'formSubmitting'  => __( 'Sending…', 'qnaapi-connect' ),
					'formSubmitted'   => __( 'Thanks — your response was recorded.', 'qnaapi-connect' ),
					'genericError'    => __( 'Something went wrong. Please try again.', 'qnaapi-connect' ),
				),
			)
		);
	}

	private function current_page_has_a_widget() {
		if ( is_admin() ) {
			return false;
		}

		global $post;

		$shortcodes = array( 'qnaapi_poll', 'qnaapi_quiz', 'qnaapi_form' );
		$blocks     = array( 'qnaapi-connect/poll', 'qnaapi-connect/quiz', 'qnaapi-connect/form' );

		if ( $post instanceof WP_Post ) {
			foreach ( $shortcodes as $shortcode ) {
				if ( has_shortcode( $post->post_content, $shortcode ) ) {
					return true;
				}
			}

			foreach ( $blocks as $block ) {
				if ( has_block( $block, $post ) ) {
					return true;
				}
			}
		}

		/**
		 * Widgets, template parts, and other non-post content can also carry
		 * a QNAAPI shortcode/block outside of $post. Themes/plugins that
		 * know they need the frontend assets on a given request can force
		 * enqueueing with this filter.
		 */
		return (bool) apply_filters( 'qnaapi_connect_force_enqueue_frontend_assets', false );
	}

	public function maybe_enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'qnaapi-connect' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'qnaapi-connect-admin',
			QNAAPI_CONNECT_URL . 'assets/css/admin.css',
			array(),
			QNAAPI_CONNECT_VERSION
		);

		wp_enqueue_script(
			'qnaapi-connect-admin',
			QNAAPI_CONNECT_URL . 'assets/js/admin.js',
			array(),
			QNAAPI_CONNECT_VERSION,
			true
		);
	}
}
