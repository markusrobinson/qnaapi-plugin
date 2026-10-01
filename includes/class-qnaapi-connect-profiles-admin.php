<?php
/**
 * Read-only wp-admin screen for browsing QNAAPI respondent profiles and the
 * audience traits computed for each one. Profiles aren't created or edited
 * from WordPress — they're derived entirely from votes, quiz attempts, and
 * form submissions on qnaapi.com — so this page only ever reads from the
 * API, with no admin-post handlers of its own.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QNAAPI_Connect_Profiles_Admin {

	const PER_PAGE = 20;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'qnaapi-connect',
			__( 'Profiles', 'qnaapi-connect' ),
			__( 'Profiles', 'qnaapi-connect' ),
			'manage_options',
			'qnaapi-connect-profiles',
			array( $this, 'render_profiles_page' )
		);
	}

	public function render_profiles_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'qnaapi-connect' ) );
		}

		$client = qnaapi_connect_client();

		if ( ! $client->has_api_key() ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Profiles', 'qnaapi-connect' ); ?></h1>
				<div class="notice notice-warning">
					<p>
						<?php esc_html_e( 'Add your QNAAPI API key before viewing profiles.', 'qnaapi-connect' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=qnaapi-connect' ) ); ?>"><?php esc_html_e( 'Go to Settings', 'qnaapi-connect' ); ?></a>
					</p>
				</div>
			</div>
			<?php
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only lookup, no state change.
		$identifier = isset( $_GET['identifier'] ) ? sanitize_text_field( wp_unslash( $_GET['identifier'] ) ) : '';
		// phpcs:enable

		if ( '' !== $identifier ) {
			$this->render_profile_detail( $client, $identifier );
			return;
		}

		$this->render_profile_list( $client );
	}

	/* ---------------------------------------------------------------
	 * List
	 * ------------------------------------------------------------- */

	private function render_profile_list( $client ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only pagination, no state change.
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$result = $client->get(
			'profiles',
			array(
				'per_page' => self::PER_PAGE,
				'page'     => $page,
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Profiles', 'qnaapi-connect' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Everyone who has voted on a poll, taken a quiz, or submitted a form, with the audience traits computed for them.', 'qnaapi-connect' ); ?>
			</p>

			<?php if ( is_wp_error( $result ) ) : ?>
				<p><?php echo esc_html( $result->get_error_message() ); ?></p>
				<?php return; ?>
			<?php endif; ?>

			<?php
			$profiles = isset( $result['body']['data'] ) ? $result['body']['data'] : array();
			$meta     = isset( $result['body']['meta'] ) ? $result['body']['meta'] : array();
			?>

			<?php if ( empty( $profiles ) ) : ?>
				<p><?php esc_html_e( 'No respondent activity yet.', 'qnaapi-connect' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Identifier', 'qnaapi-connect' ); ?></th>
							<th><?php esc_html_e( 'Traits', 'qnaapi-connect' ); ?></th>
							<th><?php esc_html_e( 'Activity', 'qnaapi-connect' ); ?></th>
							<th><?php esc_html_e( 'Last active', 'qnaapi-connect' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $profiles as $profile ) : ?>
							<tr>
								<td><code><?php echo esc_html( $profile['identifier'] ?? '' ); ?></code></td>
								<td><?php echo esc_html( $this->format_traits_summary( $profile['traits'] ?? array() ) ); ?></td>
								<td><?php echo esc_html( count( $profile['activity'] ?? array() ) ); ?></td>
								<td><?php echo esc_html( $this->format_date( $profile['last_active_at'] ?? null ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( 'identifier', rawurlencode( $profile['identifier'] ?? '' ), admin_url( 'admin.php?page=qnaapi-connect-profiles' ) ) ); ?>">
										<?php esc_html_e( 'View', 'qnaapi-connect' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php $this->render_pagination( $meta ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------
	 * Detail
	 * ------------------------------------------------------------- */

	private function render_profile_detail( $client, $identifier ) {
		$result = $client->get( 'profiles/' . rawurlencode( $identifier ) );
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Profile:', 'qnaapi-connect' ); ?>
				<code><?php echo esc_html( $identifier ); ?></code>
			</h1>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=qnaapi-connect-profiles' ) ); ?>">
					&larr; <?php esc_html_e( 'Back to all profiles', 'qnaapi-connect' ); ?>
				</a>
			</p>

			<?php if ( is_wp_error( $result ) ) : ?>
				<p><?php echo esc_html( $result->get_error_message() ); ?></p>
				<?php return; ?>
			<?php endif; ?>

			<?php $profile = isset( $result['body']['data'] ) ? $result['body']['data'] : array(); ?>

			<h2><?php esc_html_e( 'Traits', 'qnaapi-connect' ); ?></h2>
			<?php $traits = $profile['traits'] ?? array(); ?>
			<?php if ( empty( $traits ) ) : ?>
				<p><?php esc_html_e( 'No traits computed for this profile yet.', 'qnaapi-connect' ); ?></p>
			<?php else : ?>
				<table class="widefat striped qnaapi-connect-profile-traits">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Trait', 'qnaapi-connect' ); ?></th>
							<th><?php esc_html_e( 'Value', 'qnaapi-connect' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $traits as $key => $trait ) : ?>
							<tr>
								<td><?php echo esc_html( $trait['label'] ?? $key ); ?></td>
								<td><?php echo esc_html( $this->format_trait_value( $trait['value'] ?? null ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Activity', 'qnaapi-connect' ); ?></h2>
			<?php $activity = $profile['activity'] ?? array(); ?>
			<?php if ( empty( $activity ) ) : ?>
				<p><?php esc_html_e( 'No activity recorded.', 'qnaapi-connect' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $activity as $event ) : ?>
						<li>
							<strong><?php echo esc_html( $this->format_date( $event['created_at'] ?? null ) ); ?></strong>
							&mdash;
							<?php echo esc_html( $this->format_activity_event( $event ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	private function format_activity_event( $event ) {
		$type  = $event['type'] ?? '';
		$title = $event['resource_title'] ?? '';

		switch ( $type ) {
			case 'vote':
				return sprintf(
					/* translators: 1: poll title, 2: the option voted for */
					__( 'Voted "%2$s" on %1$s', 'qnaapi-connect' ),
					$title,
					$event['value'] ?? ''
				);

			case 'quiz_attempt':
				return sprintf(
					/* translators: 1: quiz title, 2: score, 3: total possible score */
					__( 'Scored %2$d/%3$d on %1$s', 'qnaapi-connect' ),
					$title,
					$event['score'] ?? 0,
					$event['total'] ?? 0
				);

			case 'form_submission':
				return sprintf(
					/* translators: %s: form title */
					__( 'Submitted %s', 'qnaapi-connect' ),
					$title
				);

			default:
				return $title;
		}
	}

	/* ---------------------------------------------------------------
	 * Formatting helpers
	 * ------------------------------------------------------------- */

	private function format_traits_summary( $traits ) {
		if ( empty( $traits ) ) {
			return '—';
		}

		$parts = array();

		foreach ( $traits as $key => $trait ) {
			$parts[] = ( $trait['label'] ?? $key ) . ': ' . $this->format_trait_value( $trait['value'] ?? null );
		}

		return implode( ', ', $parts );
	}

	private function format_trait_value( $value ) {
		if ( is_array( $value ) ) {
			return implode( ', ', $value );
		}

		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'qnaapi-connect' ) : __( 'No', 'qnaapi-connect' );
		}

		return (string) $value;
	}

	private function format_date( $value ) {
		if ( ! $value ) {
			return '—';
		}

		$timestamp = strtotime( $value );

		return $timestamp ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : $value;
	}

	private function render_pagination( $meta ) {
		$current = isset( $meta['current_page'] ) ? (int) $meta['current_page'] : 1;
		$last    = isset( $meta['last_page'] ) ? (int) $meta['last_page'] : 1;

		if ( $last <= 1 ) {
			return;
		}

		echo '<div class="qnaapi-connect-pagination">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $current,
					'total'   => $last,
				)
			)
		);
		echo '</div>';
	}
}
