<?php
/**
 * Settings → QNAAPI Connect: where a site owner pastes their API key and
 * (optionally) picks the QNAAPI "site" this WordPress install maps to.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QNAAPI_Connect_Settings {

	const OPTION_KEY = 'qnaapi_connect_options';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'QNAAPI Connect', 'qnaapi-connect' ),
			__( 'QNAAPI', 'qnaapi-connect' ),
			'manage_options',
			'qnaapi-connect',
			array( $this, 'render_settings_page' ),
			'dashicons-forms',
			30
		);

		add_submenu_page(
			'qnaapi-connect',
			__( 'Settings', 'qnaapi-connect' ),
			__( 'Settings', 'qnaapi-connect' ),
			'manage_options',
			'qnaapi-connect',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'qnaapi_connect_settings',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => array(
					'api_key'            => '',
					'base_url'           => QNAAPI_CONNECT_DEFAULT_BASE_URL,
					'site_id'            => '',
					'turnstile_site_key' => '',
				),
			)
		);
	}

	public function sanitize_options( $input ) {
		$existing = get_option( self::OPTION_KEY, array() );

		return array(
			'api_key'            => isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : ( $existing['api_key'] ?? '' ),
			'base_url'           => isset( $input['base_url'] ) && '' !== trim( $input['base_url'] )
				? esc_url_raw( untrailingslashit( trim( $input['base_url'] ) ) )
				: QNAAPI_CONNECT_DEFAULT_BASE_URL,
			'site_id'            => isset( $input['site_id'] ) ? sanitize_text_field( $input['site_id'] ) : '',
			'turnstile_site_key' => isset( $input['turnstile_site_key'] ) ? sanitize_text_field( $input['turnstile_site_key'] ) : '',
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options            = get_option( self::OPTION_KEY, array() );
		$api_key            = $options['api_key'] ?? '';
		$base_url           = $options['base_url'] ?? QNAAPI_CONNECT_DEFAULT_BASE_URL;
		$site_id            = $options['site_id'] ?? '';
		$turnstile_site_key = $options['turnstile_site_key'] ?? '';
		$connection    = $api_key ? qnaapi_connect_client()->get( 'me' ) : null;
		$is_connected  = $connection && ! is_wp_error( $connection );
		$account_email = $is_connected && isset( $connection['body']['data']['email'] ) ? $connection['body']['data']['email'] : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'QNAAPI Connect Settings', 'qnaapi-connect' ); ?></h1>

			<?php if ( $api_key ) : ?>
				<?php if ( $is_connected ) : ?>
					<div class="notice notice-success">
						<p>
							<?php
							printf(
								/* translators: %s: connected account email address */
								esc_html__( 'Connected to QNAAPI as %s.', 'qnaapi-connect' ),
								'<strong>' . esc_html( $account_email ) . '</strong>'
							);
							?>
						</p>
					</div>
				<?php else : ?>
					<div class="notice notice-error">
						<p>
							<?php
							printf(
								/* translators: %s: error message returned by the API */
								esc_html__( 'Could not connect to QNAAPI: %s', 'qnaapi-connect' ),
								esc_html( is_wp_error( $connection ) ? $connection->get_error_message() : __( 'unknown error', 'qnaapi-connect' ) )
							);
							?>
						</p>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'qnaapi_connect_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="qnaapi_connect_api_key"><?php esc_html_e( 'API Key', 'qnaapi-connect' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="qnaapi_connect_api_key"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]"
								value="<?php echo esc_attr( $api_key ); ?>"
								class="regular-text"
								autocomplete="off"
							/>
							<p class="description">
								<?php
								printf(
									/* translators: %s: link to the QNAAPI docs page on API keys */
									wp_kses_post( __( 'Create or copy a key from your QNAAPI dashboard\'s API health &amp; keys page. See <a href="%s" target="_blank" rel="noreferrer">API Keys</a>.', 'qnaapi-connect' ) ),
									'https://qnaapi.com/docs/api-keys'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="qnaapi_connect_site_id"><?php esc_html_e( 'QNAAPI Site ID', 'qnaapi-connect' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="qnaapi_connect_site_id"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[site_id]"
								value="<?php echo esc_attr( $site_id ); ?>"
								class="regular-text"
							/>
							<p class="description">
								<?php esc_html_e( 'Optional. If your QNAAPI account groups resources by site, new polls/quizzes/forms created from this plugin are tagged with this site ID. Leave blank if you don\'t use sites, or if your key is already site-scoped.', 'qnaapi-connect' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="qnaapi_connect_turnstile_site_key"><?php esc_html_e( 'Turnstile Site Key', 'qnaapi-connect' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="qnaapi_connect_turnstile_site_key"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[turnstile_site_key]"
								value="<?php echo esc_attr( $turnstile_site_key ); ?>"
								class="regular-text"
							/>
							<p class="description">
								<?php esc_html_e( 'Optional — only needed if you\'ve enabled captcha protection on a poll/quiz/form from your QNAAPI dashboard. This is the public site key from your Cloudflare Turnstile widget (never the secret key, which stays on QNAAPI\'s own servers).', 'qnaapi-connect' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="qnaapi_connect_base_url"><?php esc_html_e( 'API Base URL', 'qnaapi-connect' ); ?></label>
						</th>
						<td>
							<input
								type="url"
								id="qnaapi_connect_base_url"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[base_url]"
								value="<?php echo esc_attr( $base_url ); ?>"
								class="regular-text"
								placeholder="<?php echo esc_attr( QNAAPI_CONNECT_DEFAULT_BASE_URL ); ?>"
							/>
							<p class="description"><?php esc_html_e( 'Only change this if you\'re pointed at a self-hosted or staging QNAAPI instance.', 'qnaapi-connect' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
