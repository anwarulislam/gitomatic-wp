<?php

namespace GitomaticWP\ApiRequests;

use GitomaticWP\DataManager;
use GitomaticWP\Helper;
use GitomaticWP\Logger;
use GitomaticWP\Deployments;

/**
 * PackageUpdate class
 * Used to handle the package installation or update
 */
class PackageUpdate {

	/**
	 * Constructor
	 * Register the REST API route
	 */
	public function __construct() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'gitomatic-wp/v1',
					'/package_update/',
					array(
						array(
							'methods'             => 'GET',
							'callback'            => array( $this, 'update_package_callback' ),
							'permission_callback' => '__return_true',
						),
						array(
							'methods'             => 'POST',
							'callback'            => array( $this, 'update_package_callback' ),
							'permission_callback' => '__return_true',
						),
					)
				);
			}
		);
	}

	/**
	 * Get the package update URL
	 *
	 * @param string $package_slug slug of the package.
	 * @param string $type (theme|plugin).
	 * @return string $url
	 */
	public static function package_update_url( $package_slug = '', $type = 'theme' ) {
		$type = ( 'theme' === $type ) ? 'theme' : 'plugin';
		$url  = add_query_arg(
			array(
				'secret'  => Helper::get_api_secret() === false ? '' : Helper::get_api_secret(),
				'type'    => $type,
				'package' => $package_slug,
			),
			rest_url( 'gitomatic-wp/v1/package_update' )
		);

		return $url;
	}

	/**
	 * Handle the package update request
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return array $response
	 */
	public function update_package_callback( $request ) {
		// Handle the request and return data as JSON.
		$secret       = $request->get_param( 'secret' );
		$package_slug = $request->get_param( 'package' );
		$package_type = $request->get_param( 'type' ) === 'plugin' ? 'plugin' : 'theme';

		do_action( 'gitomatic_wp_before_api_package_update', $package_slug, $package_type );

		if ( ! is_string( $secret ) || '' === $secret || Helper::get_api_secret() !== $secret ) {
			// set status code to 401.
			status_header( 401 );

			return array(
				'success' => false,
				'message' => 'Invalid secret',
			);
		}

		$data_manager = new DataManager();

		if ( 'theme' === $package_type ) {
			$package_details = $data_manager->get_theme( $package_slug );
		} else {
			$package_details = $data_manager->get_plugin( $package_slug );
		}

		if ( false === $package_details ) {
			status_header( 401 );

			return array(
				'success' => false,
				'message' => 'Invalid package',
			);
		}

		// Manual deploys and existing CI calls have no push payload. Provider
		// webhooks carry refs, including tag pushes and branch deletions.
		$payload = $request->get_json_params();
		if ( ! $payload && is_string( $request->get_param( 'payload' ) ) ) {
			$payload = json_decode( $request->get_param( 'payload' ), true );
		}
		if ( ! self::matches_push( $payload, $package_details['branch'], $request ) ) {
			return array( 'success' => true, 'skipped' => true, 'message' => 'No matching branch push.' );
		}

		$logger        = new Logger();
		$started_at    = microtime( true );
		$deployment_id = uniqid( 'gitomatic-wp-', true );
		$log_context   = array(
			'deployment_id' => $deployment_id,
			'package_type'  => $package_type,
			'package_slug'  => $package_details['slug'],
			'instance_id'   => $package_slug,
			'provider'      => $package_details['provider'],
			'branch'        => $package_details['branch'],
			'trigger'       => 'rest',
		);
		$logger->log_event( 'deployment.started', 'info', 'Deployment started.', $log_context );

		$install_result = Deployments::deploy( $package_details, $package_type, true );

		$success = ( is_wp_error( $install_result ) ? false : true );
		$message = '';

		if ( $success ) {
			$log_context['duration_ms'] = (int) round( ( microtime( true ) - $started_at ) * 1000 );
			$logger->log_event( 'deployment.succeeded', 'info', 'Package updated successfully.', $log_context );
			$message = esc_attr__( 'Package updated successfully.', 'gitomatic-wp' );

			$flush_cache_setting_enabled = $data_manager->get_flush_cache_setting();
			if ( $flush_cache_setting_enabled ) {
				$logger->log_event( 'cache_flush.started', 'info', 'Cache flush started.', $log_context );
				try {
					Helper::trigger_cache_flush();
					$logger->log_event( 'cache_flush.succeeded', 'info', 'Cache flushed successfully.', $log_context );
					$message .= ' ' . esc_attr__( 'Cache flushed.', 'gitomatic-wp' );
				} catch ( \Throwable $e ) {
					$cache_context               = $log_context;
					$cache_context['error_code'] = 'cache_flush_failed';
					$logger->log_event( 'cache_flush.failed', 'error', $e->getMessage(), $cache_context );
					$message .= ' ' . esc_attr__( 'Cache flush failed.', 'gitomatic-wp' );
				}
			}
		} elseif ( is_wp_error( $install_result ) ) {
			$error_message              = Helper::format_error_message( $install_result );
			$log_context['duration_ms'] = (int) round( ( microtime( true ) - $started_at ) * 1000 );
			$log_context['error_code']  = $install_result->get_error_code();
			$error_data                 = $install_result->get_error_data();
			if ( ! empty( $error_data ) ) {
				$log_context['error_details'] = $error_data;
			}
			$logger->log_event( 'deployment.failed', 'error', $error_message, $log_context );

			$message = $error_message;
		}

		do_action( 'gitomatic_wp_after_api_package_update', $success, $package_slug, $package_type );

		$response = array(
			'success'      => $success,
			'message'      => $message,
			'package_type' => $package_type,
			'package_slug' => $package_details['slug'],
			'instance_id'  => $package_slug,
			'last_deploy'  => ( $success ? __( 'Succeeded', 'gitomatic-wp' ) : __( 'Failed', 'gitomatic-wp' ) ) . ' · ' . current_time( 'mysql' ),
		);
		if ( is_wp_error( $install_result ) && 'deployment_busy' === $install_result->get_error_code() ) {
			return new \WP_REST_Response( $response, 503, array( 'Retry-After' => '10' ) );
		}
		return $response;
	}

	private static function matches_push( $payload, $branch, $request ) {
		foreach ( array( 'x-github-event', 'x-gitea-event', 'x-gitlab-event', 'x-event-key' ) as $header ) {
			$event = $request->get_header( $header );
			if ( $event && ! in_array( strtolower( $event ), array( 'push', 'push hook', 'repo:push' ), true ) ) {
				return false;
			}
		}
		if ( isset( $payload['ref'] ) ) {
			return 'refs/heads/' . $branch === $payload['ref'] && empty( $payload['deleted'] )
				&& ! preg_match( '/^0+$/', $payload['after'] ?? '' );
		}
		if ( isset( $payload['push']['changes'] ) ) {
			foreach ( $payload['push']['changes'] as $change ) {
				if ( 'branch' === ( $change['new']['type'] ?? '' ) && $branch === ( $change['new']['name'] ?? '' ) ) {
					return true;
				}
			}
			return false;
		}
		return true;
	}

}
