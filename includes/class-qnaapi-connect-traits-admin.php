<?php
/**
 * wp-admin screen for creating and managing QNAAPI trait definitions — the
 * key/label/data_type/merge rules that polls, quizzes, and forms write to a
 * respondent's profile. Mapping a specific option/choice/field (or a whole
 * poll/quiz/form) to a trait is done from the QNAAPI dashboard itself —
 * that part isn't exposed over the API this plugin uses — but the trait
 * definitions themselves can be fully managed from wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QNAAPI_Connect_Traits_Admin {

	/** @var array<string,string> data_type value => label. */
	const DATA_TYPES = array(
		'boolean'     => 'Boolean (true/false)',
		'enum'        => 'Enum (one of a fixed list)',
		'number'      => 'Number',
		'string_list' => 'String list (multiple values)',
	);

	/** @var array<string,string> merge_strategy value => label. */
	const MERGE_STRATEGIES = array(
		'overwrite'     => 'Overwrite — the latest response wins',
		'keep_first'    => 'Keep first — the earliest response wins',
		'keep_highest'  => 'Keep highest — the highest-ranked response wins',
		'append_unique' => 'Append unique — keep every distinct value seen',
	);

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_qnaapi_connect_create_trait', array( $this, 'handle_create_trait' ) );
		add_action( 'admin_post_qnaapi_connect_archive_trait', array( $this, 'handle_archive_trait' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'qnaapi-connect',
			__( 'Traits', 'qnaapi-connect' ),
			__( 'Traits', 'qnaapi-connect' ),
			'manage_options',
			'qnaapi-connect-traits',
			array( $this, 'render_traits_page' )
		);
	}

	private function require_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'qnaapi-connect' ) );
		}
	}

	public function render_traits_page() {
		$this->require_capability();

		$client = qnaapi_connect_client();
		$traits = $client->has_api_key() ? $client->get( 'traits' ) : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Traits', 'qnaapi-connect' ); ?></h1>
			<?php $this->render_connection_notice( $client ); ?>

			<p class="description">
				<?php esc_html_e( 'Traits are the audience attributes your polls, quizzes, and forms can write to a respondent\'s profile — e.g. a playoff poll option mapped to "sports_fan: true". Define the trait here, then map specific options/choices/fields (or a whole poll/quiz/form) to it from your QNAAPI dashboard.', 'qnaapi-connect' ); ?>
			</p>

			<h2><?php esc_html_e( 'Create a new trait', 'qnaapi-connect' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qnaapi-connect-form">
				<?php wp_nonce_field( 'qnaapi_connect_create_trait' ); ?>
				<input type="hidden" name="action" value="qnaapi_connect_create_trait" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="qnaapi_trait_key"><?php esc_html_e( 'Key', 'qnaapi-connect' ); ?></label></th>
						<td>
							<input
								type="text"
								id="qnaapi_trait_key"
								name="key"
								class="regular-text code"
								pattern="[a-z][a-z0-9_]{1,63}"
								required
							/>
							<p class="description">
								<?php esc_html_e( 'Lowercase letters, numbers, and underscores only, starting with a letter (e.g. sports_fan). Cannot be changed later.', 'qnaapi-connect' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qnaapi_trait_label"><?php esc_html_e( 'Label', 'qnaapi-connect' ); ?></label></th>
						<td><input type="text" id="qnaapi_trait_label" name="label" class="regular-text" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="qnaapi_trait_description"><?php esc_html_e( 'Description', 'qnaapi-connect' ); ?></label></th>
						<td><textarea id="qnaapi_trait_description" name="description" class="large-text" rows="2"></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="qnaapi_trait_data_type"><?php esc_html_e( 'Data type', 'qnaapi-connect' ); ?></label></th>
						<td>
							<select id="qnaapi_trait_data_type" name="data_type" required>
								<?php foreach ( self::DATA_TYPES as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Cannot be changed later.', 'qnaapi-connect' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qnaapi_trait_allowed_values"><?php esc_html_e( 'Allowed values', 'qnaapi-connect' ); ?></label></th>
						<td>
							<textarea
								id="qnaapi_trait_allowed_values"
								name="allowed_values"
								class="large-text code"
								rows="4"
								placeholder="<?php esc_attr_e( 'one value per line', 'qnaapi-connect' ); ?>"
							></textarea>
							<p class="description">
								<?php esc_html_e( 'Required for Enum and String list, ignored otherwise. One value per line. For Enum, order matters — list from lowest to highest rank (used by the "keep highest" merge strategy).', 'qnaapi-connect' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qnaapi_trait_merge_strategy"><?php esc_html_e( 'Merge strategy', 'qnaapi-connect' ); ?></label></th>
						<td>
							<select id="qnaapi_trait_merge_strategy" name="merge_strategy">
								<?php foreach ( self::MERGE_STRATEGIES as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'How to resolve this trait when a respondent matches more than one mapping. "Keep highest" needs Enum or Number; "Append unique" needs String list.', 'qnaapi-connect' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qnaapi_trait_ttl_days"><?php esc_html_e( 'Expires after (days)', 'qnaapi-connect' ); ?></label></th>
						<td>
							<input type="number" id="qnaapi_trait_ttl_days" name="ttl_days" min="1" class="small-text" />
							<p class="description">
								<?php esc_html_e( 'Optional. A response older than this many days is ignored when computing a profile\'s current value for this trait. Leave blank to never expire.', 'qnaapi-connect' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Create trait', 'qnaapi-connect' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Your traits', 'qnaapi-connect' ); ?></h2>
			<?php $this->render_traits_table( $traits ); ?>
		</div>
		<?php
	}

	public function handle_create_trait() {
		$this->require_capability();
		check_admin_referer( 'qnaapi_connect_create_trait' );

		$key            = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		$label          = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$description    = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$data_type      = isset( $_POST['data_type'] ) ? sanitize_key( wp_unslash( $_POST['data_type'] ) ) : '';
		$merge_strategy = isset( $_POST['merge_strategy'] ) ? sanitize_key( wp_unslash( $_POST['merge_strategy'] ) ) : '';
		$ttl_days       = isset( $_POST['ttl_days'] ) && '' !== $_POST['ttl_days'] ? absint( $_POST['ttl_days'] ) : null;

		$allowed_values = array();

		if ( ! empty( $_POST['allowed_values'] ) ) {
			$lines          = preg_split( '/\r\n|\r|\n/', wp_unslash( $_POST['allowed_values'] ) );
			$allowed_values = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $lines ) ) ) );
		}

		$payload = array(
			'key'       => $key,
			'label'     => $label,
			'data_type' => $data_type,
		);

		if ( $description ) {
			$payload['description'] = $description;
		}

		if ( ! empty( $allowed_values ) ) {
			$payload['allowed_values'] = $allowed_values;
		}

		if ( $merge_strategy ) {
			$payload['merge_strategy'] = $merge_strategy;
		}

		if ( null !== $ttl_days ) {
			$payload['ttl_days'] = $ttl_days;
		}

		$result = qnaapi_connect_client()->post( 'traits', $payload );

		$this->redirect_after_create( $result );
	}

	/**
	 * QNAAPI never hard-deletes a trait definition — destroy() archives it
	 * (sets archived_at) so existing mappings and computed profile traits
	 * keep working, it just stops showing up for new mappings.
	 */
	public function handle_archive_trait() {
		$this->require_capability();
		check_admin_referer( 'qnaapi_connect_archive_trait' );

		$id = isset( $_POST['trait_id'] ) ? absint( $_POST['trait_id'] ) : 0;

		if ( $id ) {
			qnaapi_connect_client()->delete( 'traits/' . $id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=qnaapi-connect-traits' ) );
		exit;
	}

	private function redirect_after_create( $result ) {
		$url = admin_url( 'admin.php?page=qnaapi-connect-traits' );

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
				esc_html__( 'Add your QNAAPI API key before creating or listing traits.', 'qnaapi-connect' ),
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

	private function render_traits_table( $result ) {
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
					<th><?php esc_html_e( 'Key', 'qnaapi-connect' ); ?></th>
					<th><?php esc_html_e( 'Label', 'qnaapi-connect' ); ?></th>
					<th><?php esc_html_e( 'Data type', 'qnaapi-connect' ); ?></th>
					<th><?php esc_html_e( 'Merge strategy', 'qnaapi-connect' ); ?></th>
					<th><?php esc_html_e( 'Expires after', 'qnaapi-connect' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><code><?php echo esc_html( $item['key'] ?? '' ); ?></code></td>
						<td><?php echo esc_html( $item['label'] ?? '' ); ?></td>
						<td><?php echo esc_html( self::DATA_TYPES[ $item['data_type'] ?? '' ] ?? ( $item['data_type'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( self::MERGE_STRATEGIES[ $item['merge_strategy'] ?? '' ] ?? ( $item['merge_strategy'] ?? '' ) ); ?></td>
						<td>
							<?php
							echo isset( $item['ttl_days'] ) && null !== $item['ttl_days']
								? esc_html(
									sprintf(
										/* translators: %d: number of days */
										_n( '%d day', '%d days', (int) $item['ttl_days'], 'qnaapi-connect' ),
										(int) $item['ttl_days']
									)
								)
								: esc_html__( 'Never', 'qnaapi-connect' );
							?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Archive this trait? It stops appearing for new mappings, but anything already mapped to it keeps working.', 'qnaapi-connect' ) ); ?>');">
								<?php wp_nonce_field( 'qnaapi_connect_archive_trait' ); ?>
								<input type="hidden" name="action" value="qnaapi_connect_archive_trait" />
								<input type="hidden" name="trait_id" value="<?php echo esc_attr( $item['id'] ?? '' ); ?>" />
								<button type="submit" class="button-link-delete"><?php esc_html_e( 'Archive', 'qnaapi-connect' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
