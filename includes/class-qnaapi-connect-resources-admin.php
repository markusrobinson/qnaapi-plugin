<?php
/**
 * wp-admin screens for creating polls and quizzes "on the fly" — without
 * ever leaving WordPress — and listing/deleting the ones already on the
 * connected QNAAPI account. Forms are embeddable (see the shortcodes and
 * blocks classes) but are not yet creatable from here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QNAAPI_Connect_Resources_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_qnaapi_connect_create_poll', array( $this, 'handle_create_poll' ) );
		add_action( 'admin_post_qnaapi_connect_create_quiz', array( $this, 'handle_create_quiz' ) );
		add_action( 'admin_post_qnaapi_connect_delete_resource', array( $this, 'handle_delete_resource' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'qnaapi-connect',
			__( 'Polls', 'qnaapi-connect' ),
			__( 'Polls', 'qnaapi-connect' ),
			'manage_options',
			'qnaapi-connect-polls',
			array( $this, 'render_polls_page' )
		);

		add_submenu_page(
			'qnaapi-connect',
			__( 'Quizzes', 'qnaapi-connect' ),
			__( 'Quizzes', 'qnaapi-connect' ),
			'manage_options',
			'qnaapi-connect-quizzes',
			array( $this, 'render_quizzes_page' )
		);
	}

	private function default_site_id() {
		$options = get_option( 'qnaapi_connect_options', array() );

		return isset( $options['site_id'] ) && '' !== $options['site_id'] ? (int) $options['site_id'] : null;
	}

	private function require_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'qnaapi-connect' ) );
		}
	}

	/* ---------------------------------------------------------------
	 * Polls
	 * ------------------------------------------------------------- */

	public function render_polls_page() {
		$this->require_capability();

		$client = qnaapi_connect_client();
		$polls  = $client->has_api_key() ? $client->get( 'polls' ) : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Polls', 'qnaapi-connect' ); ?></h1>
			<?php $this->render_connection_notice( $client ); ?>

			<h2><?php esc_html_e( 'Create a new poll', 'qnaapi-connect' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qnaapi-connect-form">
				<?php wp_nonce_field( 'qnaapi_connect_create_poll' ); ?>
				<input type="hidden" name="action" value="qnaapi_connect_create_poll" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="qnaapi_poll_title"><?php esc_html_e( 'Question', 'qnaapi-connect' ); ?></label></th>
						<td><input type="text" id="qnaapi_poll_title" name="title" class="regular-text" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="qnaapi_poll_description"><?php esc_html_e( 'Description', 'qnaapi-connect' ); ?></label></th>
						<td><textarea id="qnaapi_poll_description" name="description" class="large-text" rows="2"></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'qnaapi-connect' ); ?></th>
						<td>
							<div id="qnaapi-connect-poll-options">
								<p class="qnaapi-connect-repeater-row">
									<input type="text" name="options[]" class="regular-text" placeholder="<?php esc_attr_e( 'Option 1', 'qnaapi-connect' ); ?>" required />
									<button type="button" class="button-link-delete qnaapi-connect-remove-option"><?php esc_html_e( 'Remove', 'qnaapi-connect' ); ?></button>
								</p>
								<p class="qnaapi-connect-repeater-row">
									<input type="text" name="options[]" class="regular-text" placeholder="<?php esc_attr_e( 'Option 2', 'qnaapi-connect' ); ?>" required />
									<button type="button" class="button-link-delete qnaapi-connect-remove-option"><?php esc_html_e( 'Remove', 'qnaapi-connect' ); ?></button>
								</p>
							</div>
							<button type="button" class="button qnaapi-connect-add-option">
								<?php esc_html_e( '+ Add option', 'qnaapi-connect' ); ?>
							</button>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Create poll', 'qnaapi-connect' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Your polls', 'qnaapi-connect' ); ?></h2>
			<?php $this->render_resource_table( $polls, 'poll', 'qnaapi_poll' ); ?>
		</div>
		<?php
	}

	public function handle_create_poll() {
		$this->require_capability();
		check_admin_referer( 'qnaapi_connect_create_poll' );

		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$options     = isset( $_POST['options'] ) && is_array( $_POST['options'] )
			? array_values( array_filter( array_map( 'sanitize_text_field', wp_unslash( $_POST['options'] ) ) ) )
			: array();

		$payload = array(
			'title'   => $title,
			'options' => $options,
		);

		if ( $description ) {
			$payload['description'] = $description;
		}

		if ( $this->default_site_id() ) {
			$payload['site_id'] = $this->default_site_id();
		}

		$result = qnaapi_connect_client()->post( 'polls', $payload );

		$this->redirect_after_create( 'qnaapi-connect-polls', $result );
	}

	/* ---------------------------------------------------------------
	 * Quizzes
	 * ------------------------------------------------------------- */

	public function render_quizzes_page() {
		$this->require_capability();

		$client  = qnaapi_connect_client();
		$quizzes = $client->has_api_key() ? $client->get( 'quizzes' ) : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Quizzes', 'qnaapi-connect' ); ?></h1>
			<?php $this->render_connection_notice( $client ); ?>

			<h2><?php esc_html_e( 'Create a new quiz', 'qnaapi-connect' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qnaapi-connect-form">
				<?php wp_nonce_field( 'qnaapi_connect_create_quiz' ); ?>
				<input type="hidden" name="action" value="qnaapi_connect_create_quiz" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="qnaapi_quiz_title"><?php esc_html_e( 'Title', 'qnaapi-connect' ); ?></label></th>
						<td><input type="text" id="qnaapi_quiz_title" name="title" class="regular-text" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="qnaapi_quiz_description"><?php esc_html_e( 'Description', 'qnaapi-connect' ); ?></label></th>
						<td><textarea id="qnaapi_quiz_description" name="description" class="large-text" rows="2"></textarea></td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Questions', 'qnaapi-connect' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Each question needs at least 2 choices, with at least one marked correct. Select-all-that-apply questions can have more than one correct choice.', 'qnaapi-connect' ); ?>
				</p>

				<div id="qnaapi-connect-quiz-questions">
					<?php echo $this->render_question_template( 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped literals below. ?>
				</div>
				<button type="button" class="button qnaapi-connect-add-question"><?php esc_html_e( '+ Add question', 'qnaapi-connect' ); ?></button>

				<template id="qnaapi-connect-question-template">
					<?php echo $this->render_question_template( '__INDEX__' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</template>

				<?php submit_button( __( 'Create quiz', 'qnaapi-connect' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Your quizzes', 'qnaapi-connect' ); ?></h2>
			<?php $this->render_resource_table( $quizzes, 'quiz', 'qnaapi_quiz' ); ?>
		</div>
		<?php
	}

	private function render_question_template( $index ) {
		ob_start();
		?>
		<fieldset class="qnaapi-connect-question">
			<legend><?php esc_html_e( 'Question', 'qnaapi-connect' ); ?></legend>
			<p>
				<input
					type="text"
					name="questions[<?php echo esc_attr( $index ); ?>][prompt]"
					class="large-text"
					placeholder="<?php esc_attr_e( 'Question prompt', 'qnaapi-connect' ); ?>"
					required
				/>
			</p>
			<div class="qnaapi-connect-choices" data-next-choice-index="2">
				<?php for ( $c = 0; $c < 2; $c++ ) : ?>
					<p class="qnaapi-connect-repeater-row">
						<label>
							<input type="checkbox" name="questions[<?php echo esc_attr( $index ); ?>][choices][<?php echo esc_attr( $c ); ?>][correct]" value="1" />
							<?php esc_html_e( 'Correct', 'qnaapi-connect' ); ?>
						</label>
						<input
							type="text"
							name="questions[<?php echo esc_attr( $index ); ?>][choices][<?php echo esc_attr( $c ); ?>][label]"
							class="regular-text"
							placeholder="<?php echo esc_attr( sprintf( /* translators: %d: choice number */ __( 'Choice %d', 'qnaapi-connect' ), $c + 1 ) ); ?>"
							required
						/>
						<button type="button" class="button-link-delete qnaapi-connect-remove-choice"><?php esc_html_e( 'Remove', 'qnaapi-connect' ); ?></button>
					</p>
				<?php endfor; ?>
			</div>
			<button type="button" class="button button-small qnaapi-connect-add-choice"><?php esc_html_e( '+ Add choice', 'qnaapi-connect' ); ?></button>
			<button type="button" class="button button-small button-link-delete qnaapi-connect-remove-question"><?php esc_html_e( 'Remove question', 'qnaapi-connect' ); ?></button>
		</fieldset>
		<?php
		return ob_get_clean();
	}

	public function handle_create_quiz() {
		$this->require_capability();
		check_admin_referer( 'qnaapi_connect_create_quiz' );

		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$raw_questions = isset( $_POST['questions'] ) && is_array( $_POST['questions'] ) ? wp_unslash( $_POST['questions'] ) : array();

		$questions = array();

		foreach ( $raw_questions as $question ) {
			$prompt       = isset( $question['prompt'] ) ? sanitize_text_field( $question['prompt'] ) : '';
			$raw_choices  = isset( $question['choices'] ) && is_array( $question['choices'] ) ? $question['choices'] : array();

			if ( '' === $prompt || empty( $raw_choices ) ) {
				continue;
			}

			$choices = array();

			foreach ( $raw_choices as $choice ) {
				$label = isset( $choice['label'] ) ? sanitize_text_field( $choice['label'] ) : '';

				if ( '' === $label ) {
					continue;
				}

				$choices[] = array(
					'label'      => $label,
					'is_correct' => ! empty( $choice['correct'] ),
				);
			}

			if ( ! empty( $choices ) ) {
				$questions[] = array(
					'prompt'  => $prompt,
					'choices' => $choices,
				);
			}
		}

		$payload = array(
			'title'     => $title,
			'questions' => $questions,
		);

		if ( $description ) {
			$payload['description'] = $description;
		}

		if ( $this->default_site_id() ) {
			$payload['site_id'] = $this->default_site_id();
		}

		$result = qnaapi_connect_client()->post( 'quizzes', $payload );

		$this->redirect_after_create( 'qnaapi-connect-quizzes', $result );
	}

	/* ---------------------------------------------------------------
	 * Shared: listing, deleting, notices
	 * ------------------------------------------------------------- */

	public function handle_delete_resource() {
		$this->require_capability();
		check_admin_referer( 'qnaapi_connect_delete_resource' );

		$type = isset( $_POST['resource_type'] ) ? sanitize_key( wp_unslash( $_POST['resource_type'] ) ) : '';
		$id   = isset( $_POST['resource_id'] ) ? absint( $_POST['resource_id'] ) : 0;
		$redirect_page = isset( $_POST['redirect_page'] ) ? sanitize_key( wp_unslash( $_POST['redirect_page'] ) ) : 'qnaapi-connect';

		if ( in_array( $type, array( 'poll', 'quiz' ), true ) && $id ) {
			qnaapi_connect_client()->delete( ( 'poll' === $type ? 'polls/' : 'quizzes/' ) . $id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . $redirect_page ) );
		exit;
	}

	private function redirect_after_create( $page, $result ) {
		$url = admin_url( 'admin.php?page=' . $page );

		if ( is_wp_error( $result ) ) {
			$url = add_query_arg( 'qnaapi_connect_error', rawurlencode( $result->get_error_message() ), $url );
		} else {
			$url = add_query_arg( 'qnaapi_connect_created', '1', $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	private function render_connection_notice( $client ) {
		if ( ! $client->has_api_key() ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Add your QNAAPI API key before creating or listing resources.', 'qnaapi-connect' ),
				esc_url( admin_url( 'admin.php?page=qnaapi-connect' ) ),
				esc_html__( 'Go to Settings', 'qnaapi-connect' )
			);
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect flags, no state change.
		if ( isset( $_GET['qnaapi_connect_created'] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Created.', 'qnaapi-connect' ) );
		}

		if ( isset( $_GET['qnaapi_connect_error'] ) ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( sanitize_text_field( wp_unslash( $_GET['qnaapi_connect_error'] ) ) )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	private function render_resource_table( $result, $type, $shortcode_tag ) {
		if ( null === $result ) {
			return;
		}

		if ( is_wp_error( $result ) ) {
			printf( '<p>%s</p>', esc_html( $result->get_error_message() ) );
			return;
		}

		$items = isset( $result['body']['data'] ) ? $result['body']['data'] : array();

		if ( empty( $items ) ) {
			printf( '<p>%s</p>', esc_html__( 'None yet.', 'qnaapi-connect' ) );
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Title', 'qnaapi-connect' ); ?></th>
					<th><?php esc_html_e( 'Identifier', 'qnaapi-connect' ); ?></th>
					<th><?php esc_html_e( 'Shortcode', 'qnaapi-connect' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item['title'] ?? '' ); ?></td>
						<td><code><?php echo esc_html( $item['identifier'] ?? '' ); ?></code></td>
						<td>
							<input
								type="text"
								readonly
								onclick="this.select();"
								class="regular-text code"
								value="[<?php echo esc_attr( $shortcode_tag ); ?> identifier=&quot;<?php echo esc_attr( $item['identifier'] ?? '' ); ?>&quot;]"
							/>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this permanently? This also deletes its responses.', 'qnaapi-connect' ) ); ?>');">
								<?php wp_nonce_field( 'qnaapi_connect_delete_resource' ); ?>
								<input type="hidden" name="action" value="qnaapi_connect_delete_resource" />
								<input type="hidden" name="resource_type" value="<?php echo esc_attr( $type ); ?>" />
								<input type="hidden" name="resource_id" value="<?php echo esc_attr( $item['id'] ?? '' ); ?>" />
								<input type="hidden" name="redirect_page" value="qnaapi-connect-<?php echo esc_attr( $type ); ?>s" />
								<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', 'qnaapi-connect' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
