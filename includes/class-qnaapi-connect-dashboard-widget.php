<?php
/**
 * A wp-admin "At a Glance"-style widget showing live poll/quiz/form counts,
 * total votes cast, traits defined, and respondent profiles, so a site
 * owner doesn't have to leave WordPress to check in on their QNAAPI
 * resources.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QNAAPI_Connect_Dashboard_Widget {

	const CACHE_KEY = 'qnaapi_connect_dashboard_summary';
	const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
	}

	public function register_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'qnaapi_connect_dashboard_widget',
			__( 'QNAAPI', 'qnaapi-connect' ),
			array( $this, 'render_widget' )
		);
	}

	public function render_widget() {
		$client = qnaapi_connect_client();

		if ( ! $client->has_api_key() ) {
			printf(
				'<p>%s <a href="%s">%s</a></p>',
				esc_html__( 'Add your QNAAPI API key to see live stats here.', 'qnaapi-connect' ),
				esc_url( admin_url( 'admin.php?page=qnaapi-connect' ) ),
				esc_html__( 'Go to Settings', 'qnaapi-connect' )
			);
			return;
		}

		$summary = $this->get_summary( $client );

		if ( is_wp_error( $summary ) ) {
			printf( '<p>%s</p>', esc_html( $summary->get_error_message() ) );
			return;
		}
		?>
		<ul class="qnaapi-connect-dashboard-stats">
			<li><strong><?php echo esc_html( $summary['polls'] ); ?></strong> <?php esc_html_e( 'polls', 'qnaapi-connect' ); ?></li>
			<li><strong><?php echo esc_html( $summary['quizzes'] ); ?></strong> <?php esc_html_e( 'quizzes', 'qnaapi-connect' ); ?></li>
			<li><strong><?php echo esc_html( $summary['forms'] ); ?></strong> <?php esc_html_e( 'forms', 'qnaapi-connect' ); ?></li>
			<li><strong><?php echo esc_html( $summary['votes'] ); ?></strong> <?php esc_html_e( 'total votes cast', 'qnaapi-connect' ); ?></li>
			<li><strong><?php echo esc_html( $summary['traits'] ); ?></strong> <?php esc_html_e( 'traits defined', 'qnaapi-connect' ); ?></li>
			<li><strong><?php echo esc_html( $summary['profiles'] ); ?></strong> <?php esc_html_e( 'respondent profiles', 'qnaapi-connect' ); ?></li>
		</ul>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=qnaapi-connect-polls' ) ); ?>"><?php esc_html_e( 'Manage polls', 'qnaapi-connect' ); ?></a>
			&middot;
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=qnaapi-connect-quizzes' ) ); ?>"><?php esc_html_e( 'Manage quizzes', 'qnaapi-connect' ); ?></a>
			&middot;
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=qnaapi-connect-traits' ) ); ?>"><?php esc_html_e( 'Manage traits', 'qnaapi-connect' ); ?></a>
			&middot;
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=qnaapi-connect-profiles' ) ); ?>"><?php esc_html_e( 'View profiles', 'qnaapi-connect' ); ?></a>
		</p>
		<?php
	}

	private function get_summary( QNAAPI_Connect_Client $client ) {
		$cached = get_transient( self::CACHE_KEY );

		if ( false !== $cached ) {
			return $cached;
		}

		$polls    = $client->get( 'polls' );
		$quizzes  = $client->get( 'quizzes' );
		$forms    = $client->get( 'forms' );
		$traits   = $client->get( 'traits' );
		$profiles = $client->get( 'profiles', array( 'per_page' => 1 ) );

		foreach ( array( $polls, $quizzes, $forms, $traits, $profiles ) as $result ) {
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$poll_items = isset( $polls['body']['data'] ) ? $polls['body']['data'] : array();

		$votes = 0;

		foreach ( $poll_items as $poll ) {
			foreach ( ( $poll['options'] ?? array() ) as $option ) {
				$votes += isset( $option['votes_count'] ) ? (int) $option['votes_count'] : 0;
			}
		}

		$summary = array(
			'polls'    => count( $poll_items ),
			'quizzes'  => count( $quizzes['body']['data'] ?? array() ),
			'forms'    => count( $forms['body']['data'] ?? array() ),
			'votes'    => $votes,
			'traits'   => count( $traits['body']['data'] ?? array() ),
			'profiles' => (int) ( $profiles['body']['meta']['total'] ?? 0 ),
		);

		set_transient( self::CACHE_KEY, $summary, self::CACHE_TTL );

		return $summary;
	}
}
