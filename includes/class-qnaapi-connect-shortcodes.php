<?php
/**
 * [qnaapi_poll], [qnaapi_quiz], and [qnaapi_form] shortcodes.
 *
 * Reading a poll/quiz/form's definition requires an API key (see the docs),
 * so every shortcode fetches server-side with the site's own key and renders
 * plain public HTML for the visitor. Voting/attempting/submitting itself is
 * public, so that part happens client-side (assets/js/frontend.js) by
 * calling QNAAPI directly — no key ever reaches the browser.
 *
 * Quiz choices are a special case: the API's quiz payload includes
 * `is_correct` per choice (the owner's own answer key), so this class
 * strips it before rendering — only whether a question has exactly one
 * correct choice (rendered as radios) vs. several (checkboxes) is kept,
 * since that alone doesn't reveal which choice is correct.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QNAAPI_Connect_Shortcodes {

	const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	public function __construct() {
		add_shortcode( 'qnaapi_poll', array( $this, 'render_poll' ) );
		add_shortcode( 'qnaapi_quiz', array( $this, 'render_quiz' ) );
		add_shortcode( 'qnaapi_form', array( $this, 'render_form' ) );
	}

	/* ---------------------------------------------------------------
	 * Poll
	 * ------------------------------------------------------------- */

	public function render_poll( $atts ) {
		$atts = shortcode_atts( array( 'identifier' => '' ), $atts, 'qnaapi_poll' );

		$poll = $this->fetch( 'poll', $atts['identifier'] );

		if ( is_wp_error( $poll ) ) {
			return $this->render_error( $poll );
		}

		$options = isset( $poll['options'] ) ? $poll['options'] : array();

		ob_start();
		?>
		<div class="qnaapi-widget qnaapi-poll" data-qnaapi-type="poll" data-qnaapi-id="<?php echo esc_attr( $poll['id'] ); ?>">
			<form class="qnaapi-poll-form">
				<p class="qnaapi-widget-title"><?php echo esc_html( $poll['title'] ); ?></p>
				<?php if ( ! empty( $poll['description'] ) ) : ?>
					<p class="qnaapi-widget-description"><?php echo esc_html( $poll['description'] ); ?></p>
				<?php endif; ?>
				<ul class="qnaapi-poll-options">
					<?php foreach ( $options as $option ) : ?>
						<li>
							<label>
								<input
									type="radio"
									name="qnaapi_poll_option"
									value="<?php echo esc_attr( $option['id'] ); ?>"
									data-votes="<?php echo esc_attr( isset( $option['votes_count'] ) ? (int) $option['votes_count'] : 0 ); ?>"
									required
								/>
								<span class="qnaapi-poll-option-label"><?php echo esc_html( $option['label'] ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
				<button type="submit" class="qnaapi-widget-submit"><?php esc_html_e( 'Vote', 'qnaapi-connect' ); ?></button>
			</form>
			<div class="qnaapi-poll-results" hidden></div>
			<p class="qnaapi-widget-message" role="status" aria-live="polite"></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------
	 * Quiz
	 * ------------------------------------------------------------- */

	public function render_quiz( $atts ) {
		$atts = shortcode_atts( array( 'identifier' => '' ), $atts, 'qnaapi_quiz' );

		$quiz = $this->fetch( 'quiz', $atts['identifier'] );

		if ( is_wp_error( $quiz ) ) {
			return $this->render_error( $quiz );
		}

		$questions = isset( $quiz['questions'] ) ? $quiz['questions'] : array();

		ob_start();
		?>
		<div class="qnaapi-widget qnaapi-quiz" data-qnaapi-type="quiz" data-qnaapi-id="<?php echo esc_attr( $quiz['id'] ); ?>">
			<form class="qnaapi-quiz-form">
				<p class="qnaapi-widget-title"><?php echo esc_html( $quiz['title'] ); ?></p>
				<?php if ( ! empty( $quiz['description'] ) ) : ?>
					<p class="qnaapi-widget-description"><?php echo esc_html( $quiz['description'] ); ?></p>
				<?php endif; ?>

				<?php foreach ( $questions as $question ) : ?>
					<?php
					$choices       = isset( $question['choices'] ) ? $question['choices'] : array();
					$correct_count = count(
						array_filter(
							$choices,
							function ( $choice ) {
								return ! empty( $choice['is_correct'] );
							}
						)
					);
					$input_type = 1 === $correct_count ? 'radio' : 'checkbox';
					?>
					<fieldset class="qnaapi-quiz-question" data-question-id="<?php echo esc_attr( $question['id'] ); ?>">
						<legend><?php echo esc_html( $question['prompt'] ); ?></legend>
						<?php foreach ( $choices as $choice ) : ?>
							<label class="qnaapi-quiz-choice">
								<input
									type="<?php echo esc_attr( $input_type ); ?>"
									name="qnaapi_question_<?php echo esc_attr( $question['id'] ); ?>"
									value="<?php echo esc_attr( $choice['id'] ); ?>"
								/>
								<?php echo esc_html( $choice['label'] ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach; ?>

				<button type="submit" class="qnaapi-widget-submit"><?php esc_html_e( 'Submit', 'qnaapi-connect' ); ?></button>
			</form>
			<div class="qnaapi-quiz-result" hidden></div>
			<p class="qnaapi-widget-message" role="status" aria-live="polite"></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------------------------------------------------------------
	 * Form
	 * ------------------------------------------------------------- */

	public function render_form( $atts ) {
		$atts = shortcode_atts( array( 'identifier' => '' ), $atts, 'qnaapi_form' );

		$form = $this->fetch( 'form', $atts['identifier'] );

		if ( is_wp_error( $form ) ) {
			return $this->render_error( $form );
		}

		$fields = isset( $form['fields'] ) ? $form['fields'] : array();

		ob_start();
		?>
		<div class="qnaapi-widget qnaapi-form" data-qnaapi-type="form" data-qnaapi-id="<?php echo esc_attr( $form['id'] ); ?>">
			<form class="qnaapi-form-form">
				<p class="qnaapi-widget-title"><?php echo esc_html( $form['title'] ); ?></p>
				<?php if ( ! empty( $form['description'] ) ) : ?>
					<p class="qnaapi-widget-description"><?php echo esc_html( $form['description'] ); ?></p>
				<?php endif; ?>

				<?php foreach ( $fields as $field ) : ?>
					<p class="qnaapi-form-field" data-field-id="<?php echo esc_attr( $field['id'] ); ?>" data-field-type="<?php echo esc_attr( $field['type'] ); ?>">
						<label>
							<?php echo esc_html( $field['label'] ); ?><?php echo empty( $field['required'] ) ? '' : ' *'; ?>
							<?php echo $this->render_field_input( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside render_field_input(). ?>
						</label>
					</p>
				<?php endforeach; ?>

				<button type="submit" class="qnaapi-widget-submit"><?php esc_html_e( 'Submit', 'qnaapi-connect' ); ?></button>
			</form>
			<p class="qnaapi-widget-message" role="status" aria-live="polite"></p>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_field_input( $field ) {
		$name     = 'qnaapi_field_' . $field['id'];
		$required = empty( $field['required'] ) ? '' : 'required';
		$options  = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();

		switch ( $field['type'] ) {
			case 'textarea':
				return sprintf( '<textarea name="%s" %s></textarea>', esc_attr( $name ), $required );

			case 'number':
				return sprintf( '<input type="number" name="%s" %s />', esc_attr( $name ), $required );

			case 'email':
				return sprintf( '<input type="email" name="%s" %s />', esc_attr( $name ), $required );

			case 'date':
				return sprintf( '<input type="date" name="%s" %s />', esc_attr( $name ), $required );

			case 'rating':
				$out = '<select name="' . esc_attr( $name ) . '" ' . $required . '>';
				$out .= '<option value="">' . esc_html__( 'Select a rating', 'qnaapi-connect' ) . '</option>';
				for ( $i = 1; $i <= 5; $i++ ) {
					$out .= '<option value="' . esc_attr( $i ) . '">' . esc_html( $i ) . '</option>';
				}
				return $out . '</select>';

			case 'single_choice':
				$out = '<select name="' . esc_attr( $name ) . '" ' . $required . '>';
				$out .= '<option value="">' . esc_html__( 'Select one', 'qnaapi-connect' ) . '</option>';
				foreach ( $options as $option ) {
					$out .= '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
				}
				return $out . '</select>';

			case 'multi_choice':
				$out = '<span class="qnaapi-form-multi-choice">';
				foreach ( $options as $option ) {
					$out .= '<label><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $option ) . '" /> ' . esc_html( $option ) . '</label>';
				}
				return $out . '</span>';

			case 'text':
			default:
				return sprintf( '<input type="text" name="%s" %s />', esc_attr( $name ), $required );
		}
	}

	/* ---------------------------------------------------------------
	 * Shared fetch/cache/error helpers
	 * ------------------------------------------------------------- */

	/**
	 * @return array|WP_Error The resource's decoded data array, or a WP_Error.
	 */
	public function fetch( $type, $identifier ) {
		$identifier = sanitize_text_field( $identifier );

		if ( '' === $identifier ) {
			return new WP_Error( 'qnaapi_connect_missing_identifier', __( 'This shortcode/block is missing an identifier.', 'qnaapi-connect' ) );
		}

		$cache_key = 'qnaapi_connect_' . $type . '_' . md5( $identifier );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$path_prefix = array(
			'poll'  => 'polls',
			'quiz'  => 'quizzes',
			'form'  => 'forms',
		);

		$result = qnaapi_connect_client()->get( $path_prefix[ $type ] . '/identifier/' . rawurlencode( $identifier ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = isset( $result['body']['data'] ) ? $result['body']['data'] : null;

		if ( ! $data ) {
			return new WP_Error( 'qnaapi_connect_not_found', __( 'That QNAAPI resource could not be found.', 'qnaapi-connect' ) );
		}

		set_transient( $cache_key, $data, self::CACHE_TTL );

		return $data;
	}

	private function render_error( WP_Error $error ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return sprintf(
			'<p class="qnaapi-widget-error">%s %s</p>',
			esc_html__( 'QNAAPI:', 'qnaapi-connect' ),
			esc_html( $error->get_error_message() )
		);
	}
}
