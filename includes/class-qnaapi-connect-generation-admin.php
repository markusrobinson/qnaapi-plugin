<?php
/**
 * wp-admin screen for generating a poll/quiz draft from an article
 * (POST /generate), then reviewing, publishing, or rejecting the draft it
 * produces. There's no "list my jobs" or "list my drafts" endpoint — only
 * GET /generate/{job} and GET /drafts/{id} — so this class remembers job
 * ids itself (the qnaapi_connect_tracked_jobs option, capped at the most
 * recent 10) and looks each one up live on every page load.
 *
 * Unlike the Polls/Quizzes/Forms screens, there's no inline editing here —
 * a draft can be published or rejected as-is. Editing a draft's questions
 * before publishing is done from the QNAAPI dashboard itself.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QNAAPI_Connect_Generation_Admin {

	const TRACKED_JOBS_OPTION = 'qnaapi_connect_tracked_jobs';

	const MAX_TRACKED_JOBS = 10;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_qnaapi_connect_generate', array( $this, 'handle_generate' ) );
		add_action( 'admin_post_qnaapi_connect_publish_draft', array( $this, 'handle_publish_draft' ) );
		add_action( 'admin_post_qnaapi_connect_reject_draft', array( $this, 'handle_reject_draft' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'qnaapi-connect',
			__( 'AI Drafts', 'qnaapi-connect' ),
			__( 'AI Drafts', 'qnaapi-connect' ),
			'manage_options',
			'qnaapi-connect-ai-drafts',
			array( $this, 'render_page' )
		);
	}

	private function require_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'qnaapi-connect' ) );
		}
	}

	public function render_page() {
		$this->require_capability();

		$client = qnaapi_connect_client();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Drafts', 'qnaapi-connect' ); ?></h1>
			<?php $this->render_connection_notice( $client ); ?>

			<p class="description">
				<?php esc_html_e( 'Generate a poll or quiz draft from an article, then review and publish it (or reject it) below. A draft is never public and never collects responses until you publish it.', 'qnaapi-connect' ); ?>
			</p>

			<h2><?php esc_html_e( 'Generate from an article', 'qnaapi-connect' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qnaapi-connect-form">
				<?php wp_nonce_field( 'qnaapi_connect_generate' ); ?>
				<input type="hidden" name="action" value="qnaapi_connect_generate" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="qnaapi_gen_article_url"><?php esc_html_e( 'Article URL', 'qnaapi-connect' ); ?></label></th>
						<td>
							<input type="url" id="qnaapi_gen_article_url" name="article_url" class="regular-text" placeholder="https://example.com/news/article" />
							<p class="description"><?php esc_html_e( 'Provide this or the article text below — not both.', 'qnaapi-connect' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qnaapi_gen_article_text"><?php esc_html_e( 'Article text', 'qnaapi-connect' ); ?></label></th>
						<td><textarea id="qnaapi_gen_article_text" name="article_text" class="large-text" rows="5"></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="qnaapi_gen_type"><?php esc_html_e( 'Type', 'qnaapi-connect' ); ?></label></th>
						<td>
							<select id="qnaapi_gen_type" name="type">
								<option value="poll" selected="selected"><?php esc_html_e( 'Poll', 'qnaapi-connect' ); ?></option>
								<option value="quiz"><?php esc_html_e( 'Quiz', 'qnaapi-connect' ); ?></option>
							</select>
						</td>
					</tr>
					<tr id="qnaapi-gen-question-count-row">
						<th scope="row"><label for="qnaapi_gen_question_count"><?php esc_html_e( 'Questions', 'qnaapi-connect' ); ?></label></th>
						<td>
							<input type="number" id="qnaapi_gen_question_count" name="question_count" value="5" min="1" class="small-text" />
							<p class="description"><?php esc_html_e( 'A poll is always exactly 1 question, regardless of this value.', 'qnaapi-connect' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qnaapi_gen_tone"><?php esc_html_e( 'Tone', 'qnaapi-connect' ); ?></label></th>
						<td><input type="text" id="qnaapi_gen_tone" name="tone" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. playful, serious (optional)', 'qnaapi-connect' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-publish', 'qnaapi-connect' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="auto_publish" value="1" />
								<?php esc_html_e( 'Publish automatically if the draft isn\'t flagged', 'qnaapi-connect' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Requires the Pro plan or above on your QNAAPI account — ignored otherwise, with a notice shown below.', 'qnaapi-connect' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sensitive topics', 'qnaapi-connect' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="check_sensitive_topics" value="1" checked="checked" />
								<?php esc_html_e( 'Flag and skip generation for sensitive subject matter', 'qnaapi-connect' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'On by default. Uncheck to generate regardless of subject matter — the job will never come back flagged for topic (an ungrounded question or low confidence can still flag it).', 'qnaapi-connect' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Generate', 'qnaapi-connect' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Your generation jobs', 'qnaapi-connect' ); ?></h2>
			<?php $this->render_jobs_table( $client ); ?>
		</div>
		<?php
	}

	public function handle_generate() {
		$this->require_capability();
		check_admin_referer( 'qnaapi_connect_generate' );

		$article_url     = isset( $_POST['article_url'] ) ? esc_url_raw( wp_unslash( $_POST['article_url'] ) ) : '';
		$article_text    = isset( $_POST['article_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['article_text'] ) ) : '';
		$type            = isset( $_POST['type'] ) && 'poll' === $_POST['type'] ? 'poll' : 'quiz';
		$question_count  = 'poll' === $type ? 1 : ( isset( $_POST['question_count'] ) ? max( 1, absint( $_POST['question_count'] ) ) : 5 );
		$tone            = isset( $_POST['tone'] ) ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : '';

		$payload = array(
			'type'           => $type,
			'question_count' => $question_count,
		);

		if ( $article_url ) {
			$payload['article_url'] = $article_url;
		} else {
			$payload['article_text'] = $article_text;
		}

		if ( $tone ) {
			$payload['tone'] = $tone;
		}

		if ( ! empty( $_POST['auto_publish'] ) ) {
			$payload['auto_publish'] = true;
		}

		if ( empty( $_POST['check_sensitive_topics'] ) ) {
			$payload['check_sensitive_topics'] = false;
		}

		$result = qnaapi_connect_client()->post( 'generate', $payload );
		$url    = admin_url( 'admin.php?page=qnaapi-connect-ai-drafts' );

		if ( is_wp_error( $result ) ) {
			$url = add_query_arg( 'qnaapi_connect_error', rawurlencode( $result->get_error_message() ), $url );
		} else {
			$job_id = $result['body']['data']['job_id'] ?? null;

			if ( $job_id ) {
				$this->track_job( (int) $job_id );
			}

			$url = add_query_arg( 'qnaapi_connect_created', '1', $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	public function handle_publish_draft() {
		$this->require_capability();
		check_admin_referer( 'qnaapi_connect_publish_draft' );

		$this->post_draft_action( 'publish', 'qnaapi_connect_published' );
	}

	public function handle_reject_draft() {
		$this->require_capability();
		check_admin_referer( 'qnaapi_connect_reject_draft' );

		$this->post_draft_action( 'reject', 'qnaapi_connect_rejected' );
	}

	private function post_draft_action( $action, $success_flag ) {
		$draft_id = isset( $_POST['draft_id'] ) ? absint( $_POST['draft_id'] ) : 0;
		$url      = admin_url( 'admin.php?page=qnaapi-connect-ai-drafts' );

		if ( $draft_id ) {
			$result = qnaapi_connect_client()->post( 'drafts/' . $draft_id . '/' . $action );

			$url = is_wp_error( $result )
				? add_query_arg( 'qnaapi_connect_error', rawurlencode( $result->get_error_message() ), $url )
				: add_query_arg( $success_flag, '1', $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Remember a job id so render_jobs_table() can look it up later —
	 * there's no "list my jobs" endpoint, only GET /generate/{job}.
	 */
	private function track_job( $job_id ) {
		$jobs   = get_option( self::TRACKED_JOBS_OPTION, array() );
		$jobs   = array_values( array_diff( $jobs, array( $job_id ) ) );
		$jobs[] = $job_id;
		$jobs   = array_slice( $jobs, -self::MAX_TRACKED_JOBS );

		update_option( self::TRACKED_JOBS_OPTION, $jobs, false );
	}

	private function render_connection_notice( $client ) {
		if ( ! $client->has_api_key() ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Add your QNAAPI API key before generating or publishing drafts.', 'qnaapi-connect' ),
				esc_url( admin_url( 'admin.php?page=qnaapi-connect' ) ),
				esc_html__( 'Go to Settings', 'qnaapi-connect' )
			);
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect flags, no state change.
		if ( isset( $_GET['qnaapi_connect_created'] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Generation queued — check its status below (refresh if it still says Pending/Processing).', 'qnaapi-connect' ) );
		}

		if ( isset( $_GET['qnaapi_connect_published'] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Draft published.', 'qnaapi-connect' ) );
		}

		if ( isset( $_GET['qnaapi_connect_rejected'] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Draft rejected.', 'qnaapi-connect' ) );
		}

		if ( isset( $_GET['qnaapi_connect_error'] ) ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( sanitize_text_field( wp_unslash( $_GET['qnaapi_connect_error'] ) ) )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	private function render_jobs_table( $client ) {
		if ( ! $client->has_api_key() ) {
			return;
		}

		$job_ids = array_reverse( get_option( self::TRACKED_JOBS_OPTION, array() ) );

		if ( empty( $job_ids ) ) {
			printf( '<p>%s</p>', esc_html__( 'No generation jobs yet.', 'qnaapi-connect' ) );
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'qnaapi-connect' ); ?></th>
					<th><?php esc_html_e( 'Status', 'qnaapi-connect' ); ?></th>
					<th><?php esc_html_e( 'Details', 'qnaapi-connect' ); ?></th>
					<th><?php esc_html_e( 'Created', 'qnaapi-connect' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $job_ids as $job_id ) : ?>
					<?php $this->render_job_rows( $client, $job_id ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_job_rows( $client, $job_id ) {
		$result = $client->get( 'generate/' . $job_id );

		if ( is_wp_error( $result ) ) {
			printf(
				'<tr><td colspan="5">%s</td></tr>',
				esc_html(
					sprintf(
						/* translators: 1: job id, 2: error message */
						__( 'Job #%1$d: %2$s', 'qnaapi-connect' ),
						$job_id,
						$result->get_error_message()
					)
				)
			);
			return;
		}

		$job    = $result['body']['data'] ?? array();
		$draft  = null;

		if ( ! empty( $job['draft_id'] ) ) {
			$draft_result = $client->get( 'drafts/' . $job['draft_id'] );
			$draft        = is_wp_error( $draft_result ) ? null : ( $draft_result['body']['data'] ?? null );
		}
		?>
		<tr>
			<td><?php echo esc_html( ucfirst( $job['type'] ?? '' ) ); ?></td>
			<td><?php echo esc_html( $this->status_label( $job['status'] ?? '' ) ); ?></td>
			<td>
				<?php if ( 'failed' === ( $job['status'] ?? '' ) && ! empty( $job['error_message'] ) ) : ?>
					<?php echo esc_html( $job['error_message'] ); ?>
				<?php elseif ( 'flagged' === ( $job['status'] ?? '' ) && ! empty( $job['flagged_reason'] ) ) : ?>
					<?php echo esc_html( $job['flagged_reason'] ); ?>
				<?php endif; ?>
				<?php foreach ( ( $job['notices'] ?? array() ) as $notice ) : ?>
					<div class="description"><?php echo esc_html( $notice ); ?></div>
				<?php endforeach; ?>
			</td>
			<td><?php echo esc_html( $this->format_date( $job['created_at'] ?? null ) ); ?></td>
			<td>
				<?php if ( $draft && 'pending_review' === $draft['status'] ) : ?>
					<?php $this->render_draft_action_buttons( (int) $job['draft_id'] ); ?>
				<?php elseif ( $draft ) : ?>
					<span class="description"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $draft['status'] ) ) ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( $draft ) : ?>
			<tr>
				<td colspan="5" style="background:#f6f7f7;"><?php $this->render_draft_preview( $draft ); ?></td>
			</tr>
		<?php endif; ?>
		<?php
	}

	private function render_draft_action_buttons( $draft_id ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
			<?php wp_nonce_field( 'qnaapi_connect_publish_draft' ); ?>
			<input type="hidden" name="action" value="qnaapi_connect_publish_draft" />
			<input type="hidden" name="draft_id" value="<?php echo esc_attr( $draft_id ); ?>" />
			<button type="submit" class="button button-primary button-small"><?php esc_html_e( 'Publish', 'qnaapi-connect' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_js( __( 'Reject this draft? It will never be published.', 'qnaapi-connect' ) ); ?>');">
			<?php wp_nonce_field( 'qnaapi_connect_reject_draft' ); ?>
			<input type="hidden" name="action" value="qnaapi_connect_reject_draft" />
			<input type="hidden" name="draft_id" value="<?php echo esc_attr( $draft_id ); ?>" />
			<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Reject', 'qnaapi-connect' ); ?></button>
		</form>
		<?php
	}

	/**
	 * @param array<string, mixed> $draft
	 */
	private function render_draft_preview( array $draft ) {
		?>
		<div style="padding:10px 4px;">
			<strong><?php echo esc_html( $draft['title'] ?? '' ); ?></strong>
			<?php if ( ! empty( $draft['description'] ) ) : ?>
				<p><?php echo esc_html( $draft['description'] ); ?></p>
			<?php endif; ?>
			<p class="description">
				<?php
				printf(
					/* translators: %d: confidence percentage */
					esc_html__( '%d%% confidence', 'qnaapi-connect' ),
					round( ( $draft['confidence'] ?? 0 ) * 100 )
				);
				?>
				<?php if ( ! empty( $draft['flagged_reason'] ) ) : ?>
					&middot; <span style="color:#b32d2e;"><?php echo esc_html( $draft['flagged_reason'] ); ?></span>
				<?php endif; ?>
			</p>
			<?php foreach ( ( $draft['questions'] ?? array() ) as $question ) : ?>
				<p style="margin-bottom:4px;"><strong><?php echo esc_html( $question['prompt'] ?? '' ); ?></strong></p>
				<ul style="margin-top:0;">
					<?php foreach ( ( $question['options'] ?? array() ) as $option ) : ?>
						<li>
							<?php echo esc_html( $option['label'] ?? '' ); ?>
							<?php if ( ! empty( $option['is_correct'] ) ) : ?>
								<span style="color:#2271b1;"> (<?php esc_html_e( 'correct', 'qnaapi-connect' ); ?>)</span>
							<?php endif; ?>
							<?php if ( ! empty( $option['suggested_trait_key'] ) ) : ?>
								<code style="margin-left:6px;"><?php echo esc_html( $option['suggested_trait_key'] . ': ' . $option['suggested_trait_value'] ); ?></code>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function status_label( $status ) {
		$labels = array(
			'pending'           => __( 'Pending', 'qnaapi-connect' ),
			'processing'        => __( 'Processing', 'qnaapi-connect' ),
			'completed'         => __( 'Completed', 'qnaapi-connect' ),
			'flagged'           => __( 'Flagged', 'qnaapi-connect' ),
			'failed'            => __( 'Failed', 'qnaapi-connect' ),
			'credits_exhausted' => __( 'Credits exhausted', 'qnaapi-connect' ),
		);

		return $labels[ $status ] ?? $status;
	}

	private function format_date( $value ) {
		if ( ! $value ) {
			return '—';
		}

		$timestamp = strtotime( $value );

		return $timestamp ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : $value;
	}
}
