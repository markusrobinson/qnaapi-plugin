<?php
/**
 * Fires only on Delete (not on deactivate) — removes the plugin's saved
 * option and any cached transients. Never touches the QNAAPI account
 * itself; polls/quizzes/forms already created stay on qnaapi.com.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'qnaapi_connect_options' );
delete_option( 'qnaapi_connect_tracked_jobs' );
delete_transient( 'qnaapi_connect_dashboard_summary' );

global $wpdb;

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_qnaapi_connect_' ) . '%'
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_timeout_qnaapi_connect_' ) . '%'
	)
);
