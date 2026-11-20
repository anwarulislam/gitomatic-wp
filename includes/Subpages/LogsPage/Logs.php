<?php
namespace GitomaticWP\Subpages\LogsPage;

use GitomaticWP\Logger;

/**
 * Logs page
 */
class Logs {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'init_menu' ) );
		add_action( 'admin_post_gitomatic_wp_download_log', array( $this, 'handle_download' ) );
	}

	/**
	 * Initialize menu
	 *
	 * @return void
	 */
	public function init_menu() {
		$menu_slug  = \GitomaticWP\Helper::menu_slug();
		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';

		add_submenu_page(
			$menu_slug,
			esc_attr__( 'Logs', 'gitomatic-wp' ),
			esc_attr__( 'Logs', 'gitomatic-wp' ),
			$capability,
			"{$menu_slug}-logs",
			array( $this, 'init_page' )
		);
	}

	/**
	 * Initialize page
	 *
	 * @return void
	 */
	public function init_page() {
		$this->handle_clear_log_form();

		$logger      = new Logger();
		$all_entries = $logger->get_log_entries();
		$filters     = $this->get_filters();
		$entries     = array_values(
			array_filter(
				$all_entries,
				function ( $entry ) use ( $filters ) {
					$context = isset( $entry['context'] ) && is_array( $entry['context'] ) ? $entry['context'] : array();
					$trigger = isset( $context['trigger'] ) ? $context['trigger'] : '';
					$time    = isset( $entry['timestamp'] ) ? strtotime( $entry['timestamp'] ) : false;

					if ( '' !== $filters['level'] && $filters['level'] !== $entry['level'] ) {
						return false;
					}
					if ( '' !== $filters['trigger'] && $filters['trigger'] !== $trigger ) {
						return false;
					}
					if ( '' !== $filters['date'] && false !== $time ) {
						$local_date = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $time ), 'Y-m-d' );
						if ( $filters['date'] !== $local_date ) {
							return false;
						}
					}
					if ( '' !== $filters['search'] ) {
						$haystack = strtolower( wp_json_encode( $entry ) );
						if ( false === strpos( $haystack, strtolower( $filters['search'] ) ) ) {
							return false;
						}
					}

					return true;
				}
			)
		);

		$per_page      = 50;
		$total_entries = count( $entries );
		$total_pages   = max( 1, (int) ceil( $total_entries / $per_page ) );
		$current_page  = min( $filters['log_page'], $total_pages );
		$entries       = array_slice( $entries, ( $current_page - 1 ) * $per_page, $per_page );
		$error_count   = count(
			array_filter(
				$all_entries,
				function ( $entry ) {
					return 'error' === $entry['level'];
				}
			)
		);
		$download_url  = wp_nonce_url(
			admin_url( 'admin-post.php?action=gitomatic_wp_download_log' ),
			GITOMATIC_WP_SLUG . '_download_log'
		);

		include_once __DIR__ . '/template.php';
	}

	/**
	 * Handle clear log form
	 *
	 * @return void
	 */
	public function handle_clear_log_form() {
		$form_submitted = false;

		if ( ! isset( $_POST['action'] ) || 'clear_log_file' !== $_POST['action'] ) {
			return;
		}

		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			return;
		}

		if ( isset( $_POST[ GITOMATIC_WP_SLUG . '_nonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ GITOMATIC_WP_SLUG . '_nonce' ] ) ), GITOMATIC_WP_SLUG . '_clear_log_file' ) ) {
			$logger           = new Logger();
			$clear_log_result = $logger->clear_log_file();
			$form_submitted   = true;
		}
	}

	/**
	 * Download retained logs through an authenticated admin action.
	 *
	 * @return void
	 */
	public function handle_download() {
		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			wp_die(
				esc_html__( 'You are not allowed to download these logs.', 'gitomatic-wp' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( GITOMATIC_WP_SLUG . '_download_log' );

		$logger = new Logger();
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="gitomatic-wp-logs-' . gmdate( 'Y-m-d' ) . '.log"' );
		// Log entries are sanitized before they are written and this response is a download.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $logger->get_download_content();
		exit;
	}

	/**
	 * Read and sanitize log-page filters.
	 *
	 * @return array Filters.
	 */
	private function get_filters() {
		// Viewing and filtering logs does not mutate data.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search   = isset( $_GET['log_search'] ) ? sanitize_text_field( wp_unslash( $_GET['log_search'] ) ) : '';
		$level    = isset( $_GET['log_level'] ) ? sanitize_key( wp_unslash( $_GET['log_level'] ) ) : '';
		$trigger  = isset( $_GET['log_trigger'] ) ? sanitize_key( wp_unslash( $_GET['log_trigger'] ) ) : '';
		$date     = isset( $_GET['log_date'] ) ? sanitize_text_field( wp_unslash( $_GET['log_date'] ) ) : '';
		$log_page = isset( $_GET['log_page'] ) ? absint( $_GET['log_page'] ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return array(
			'search'   => $search,
			'level'    => in_array( $level, array( 'info', 'warning', 'error' ), true ) ? $level : '',
			'trigger'  => in_array( $trigger, array( 'wp-admin', 'rest', 'wp-cli' ), true ) ? $trigger : '',
			'date'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '',
			'log_page' => max( 1, $log_page ),
		);
	}
}
