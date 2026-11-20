<?php
namespace GitomaticWP;

use GitomaticWP\Subpages\DashboardPage\Dashboard;
use GitomaticWP\Subpages\InstallThemePage\InstallTheme;
use GitomaticWP\Subpages\InstallPluginPage\InstallPlugin;
use GitomaticWP\Subpages\LogsPage\Logs;
use GitomaticWP\Subpages\MiscellaneousPage\Miscellaneous;
use GitomaticWP\Subpages\EditPackagePage\EditPackage;

/**
 * Class Admin
 *
 * @package GitomaticWP
 */
class Admin {
	/**
	 * Admin constructor.
	 */
	public function __construct() {
		$this->init_dashboard_page();
		$this->init_install_theme_page();
		$this->init_install_plugin_page();
		$this->init_logs_page();
		$this->init_miscellaneous_page();
		$this->init_edit_package_page();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( is_multisite() ? 'network_admin_notices' : 'admin_notices', array( $this, 'display_alert_notification_box' ) );
		add_action( 'wp_ajax_gitomatic_wp_branches', array( Branches::class, 'ajax' ) );
		add_action( 'wp_ajax_gitomatic_wp_unlink_package', array( $this, 'handle_unlink_package' ) );

		// Add plugin action links.
		add_filter( 'plugin_action_links', array( $this, 'add_plugin_action_links' ), 10, 2 );
	}

	/**
	 * Initialize dashboard page
	 */
	private function init_dashboard_page() {
		new Dashboard();
	}

	/**
	 * Initialize install theme page
	 */
	public function init_install_theme_page() {
		new InstallTheme();
	}

	/**
	 * Initialize install plugin page
	 */
	private function init_install_plugin_page() {
		new InstallPlugin();
	}

	/**
	 * Initialize miscellaneous page
	 */
	private function init_miscellaneous_page() {
		new Miscellaneous();
	}

	/**
	 * Initialize logs page
	 */
	private function init_logs_page() {
		new Logs();
	}

	/**
	 * Initialize edit package page
	 */
	private function init_edit_package_page() {
		new EditPackage();
	}

	/**
	 * Display alert notification box
	 */
	public function display_alert_notification_box() {
		$data_manager               = new DataManager();
		$alert_notification_setting = $data_manager->get_alert_notification_setting();
		$capability                 = is_multisite() ? 'manage_network_options' : 'manage_options';

		if ( $alert_notification_setting && current_user_can( $capability ) ) {
			$dashboard_url = Helper::dashboard_url();
			$message       = self::get_alert_notification_message( $dashboard_url );

			echo '<div class="notice notice-warning gitomatic_wp_deployment_notice"><p>';
			echo wp_kses_post( $message );
			echo '</p></div>';
		}
	}

	/**
	 * Get the alert notification message.
	 *
	 * @param string $dashboard_url Dashboard URL.
	 * @return string
	 */
	public static function get_alert_notification_message( $dashboard_url ) {
		// translators: %s = dashboard URL.
		$message = __( '<strong>This site is deployed from Git.</strong> Do not edit connected plugin or theme files directly. Your changes may be overwritten by the next deployment. <a href="%s">View connected packages &rarr;</a>', 'gitomatic-wp' );
		$message = sprintf( $message, esc_url( $dashboard_url ) );

		return apply_filters( 'gitomatic_wp_alert_notification_message', $message );
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts( $hook = '' ) {
		if ( false === strpos( $hook, Helper::menu_slug() ) ) {
			return;
		}
		$plugin_data = get_plugin_data( GITOMATIC_WP_FILE );
		$version     = $plugin_data['Version'];

		wp_enqueue_style( 'gitomatic-wp-styles', GITOMATIC_WP_URL . 'assets/css/gitomatic-wp-main.css', array(), $version );

		wp_enqueue_script( 'gitomatic-wp-script', GITOMATIC_WP_URL . 'assets/js/gitomatic-wp-main.js', array( 'jquery' ), $version, true );

		wp_localize_script(
			'gitomatic-wp-script',
			'gitomatic_wp',
			array(
				'copy_url_label'         => __( 'Copy URL', 'gitomatic-wp' ),
				'copied_url_label'       => __( 'Copied!', 'gitomatic-wp' ),
				'copy_failed_label'      => __( 'Could not copy the URL.', 'gitomatic-wp' ),
				'show_webhook_label'     => __( 'Show webhook URL', 'gitomatic-wp' ),
				'hide_webhook_label'     => __( 'Hide webhook URL', 'gitomatic-wp' ),
				'deploying_now_label'    => __( 'Deploying now...', 'gitomatic-wp' ),
				'error_label'            => __( 'Something went wrong', 'gitomatic-wp' ),
				'deploy_completed_label' => __( 'Deployed!', 'gitomatic-wp' ),
				'unlinking_label'        => __( 'Unlinking package...', 'gitomatic-wp' ),
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'select_branch' => __( 'Select a branch', 'gitomatic-wp' ),
				'enter_branch' => __( 'Enter branch manually…', 'gitomatic-wp' ),
				'roots' => array( 'theme' => get_theme_root(), 'plugin' => WP_PLUGIN_DIR ),
				'load_branches' => __( 'Load branches', 'gitomatic-wp' ),
				'loading_branches' => __( 'Loading branches…', 'gitomatic-wp' ),
				'manual_branch' => __( 'Choose an available branch or type its name.', 'gitomatic-wp' ),
				'more_branches' => __( 'Load more branches', 'gitomatic-wp' ),
				'retry' => __( 'Retry', 'gitomatic-wp' ),
				'branch_error' => __( 'Branch lookup failed. Retry or enter a branch manually.', 'gitomatic-wp' ),
				'branch_nonce'           => wp_create_nonce( 'gitomatic_wp_branches' ),
				'unlink_nonce'           => wp_create_nonce( GITOMATIC_WP_SLUG . '_unlink_nonce' ),
				'unlink_confirm_label'   => __( 'Disconnect this deployment? Its files stay installed and other branches keep their connections.', 'gitomatic-wp' ),
			)
		);
	}

	/**
	 * Handle AJAX request to unlink a package from Gitomatic WP.
	 */
	public function handle_unlink_package() {
		check_ajax_referer( GITOMATIC_WP_SLUG . '_unlink_nonce', 'nonce' );

		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'gitomatic-wp' ) ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		if ( empty( $slug ) || ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'gitomatic-wp' ) ) );
		}

		$data_manager = new DataManager();
		$result = Deployments::locked( function () use ( $data_manager, $slug, $type ) {
			return $data_manager->remove_package_details( $slug, $type );
		} );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		if ( $result ) {
			$logger = new Logger();
			$logger->log_event(
				'package.unlinked',
				'info',
				__( 'Package unlinked from Gitomatic WP.', 'gitomatic-wp' ),
				array(
					'package_type' => $type,
					'package_slug' => $slug,
					'trigger'      => 'wp-admin',
				)
			);
			wp_send_json_success( array( 'message' => __( 'Package unlinked successfully.', 'gitomatic-wp' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to unlink package.', 'gitomatic-wp' ) ) );
		}
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array  $actions An array of plugin action links.
	 * @param string $plugin_file Path to the plugin file.
	 *
	 * @return array
	 */
	public function add_plugin_action_links( $actions, $plugin_file ) {

		$data_manager = new DataManager();
		$plugin_list  = $data_manager->get_plugin_list();

		foreach ( $plugin_list as $plugin_data_item ) {
			$target_dir = $plugin_data_item['slug'];

			// Check if this plugin belongs to that directory.
			if ( strpos( $plugin_file, $target_dir . '/' ) === 0 ) {
					$actions['gitomatic_wp_package_update_link'] = sprintf(
						'<a href="%s">%s</a> <span class="dashicons dashicons-update-alt"></span>',
						esc_url( Helper::dashboard_url() ),
						__( 'Update with Git', 'gitomatic-wp' )
					);
			}
		}

		return $actions;
	}
}
