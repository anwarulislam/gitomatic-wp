<?php

namespace GitomaticWP\CLI;

use GitomaticWP\DataManager;
use GitomaticWP\Helper;
use GitomaticWP\Logger;
use GitomaticWP\Deployments;
use GitomaticWP\Branches;

/**
 * Manage Gitomatic WP packages from the command line.
 *
 * ## EXAMPLES
 *
 *     # List all tracked packages
 *     wp gitomatic-wp list
 *
 *     # Update a registered plugin
 *     wp gitomatic-wp update my-plugin --type=plugin
 *
 *     # Install a new theme from GitHub
 *     wp gitomatic-wp install https://github.com/user/my-theme --provider=github --type=theme
 *
 * @package GitomaticWP\CLI
 */
class GitomaticCommand {

	/**
	 * List all packages managed by Gitomatic WP.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Filter results by package type.
	 * ---
	 * options:
	 *   - plugin
	 *   - theme
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp gitomatic-wp list
	 *     wp gitomatic-wp list --type=plugin
	 *     wp gitomatic-wp list --format=json
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_packages( $args, $assoc_args ) {
		$type   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'type', 'all' );
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$data_manager = new DataManager();
		$items        = array();

		if ( 'all' === $type || 'plugin' === $type ) {
			foreach ( $data_manager->get_plugin_list() as $pkg ) {
				$items[] = $this->format_package_row( $pkg, 'plugin' );
			}
		}

		if ( 'all' === $type || 'theme' === $type ) {
			foreach ( $data_manager->get_theme_list() as $pkg ) {
				$items[] = $this->format_package_row( $pkg, 'theme' );
			}
		}

		if ( empty( $items ) ) {
			\WP_CLI::line( __( 'No packages found.', 'gitomatic-wp' ) );
			return;
		}

		$fields = array( 'instance', 'slug', 'type', 'provider', 'branch', 'private', 'repo_url' );
		\WP_CLI\Utils\format_items( $format, $items, $fields );
	}

	/**
	 * Update (redeploy) a registered package from its git repository.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The deployment instance from wp gitomatic-wp list, or the original package slug.
	 *
	 * [--type=<type>]
	 * : Package type.
	 * ---
	 * default: plugin
	 * options:
	 *   - plugin
	 *   - theme
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp gitomatic-wp update my-plugin
	 *     wp gitomatic-wp update my-theme --type=theme
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function update( $args, $assoc_args ) {
		$slug = isset( $args[0] ) ? $args[0] : '';

		if ( empty( $slug ) ) {
			\WP_CLI::error( __( 'Please provide a package slug.', 'gitomatic-wp' ) );
		}

		$type = \WP_CLI\Utils\get_flag_value( $assoc_args, 'type', 'plugin' );
		$type = ( 'theme' === $type ) ? 'theme' : 'plugin';

		$data_manager = new DataManager();

		if ( 'theme' === $type ) {
			$package_details = $data_manager->get_theme( $slug );
		} else {
			$package_details = $data_manager->get_plugin( $slug );
		}

		if ( false === $package_details ) {
			\WP_CLI::error(
				sprintf(
					/* translators: 1: package slug, 2: package type */
					__( 'Package "%1$s" (%2$s) not found. Is it registered with Gitomatic WP?', 'gitomatic-wp' ),
					$slug,
					$type
				)
			);
		}

		\WP_CLI::line(
			sprintf(
				/* translators: 1: package type, 2: package slug, 3: repository URL */
				__( 'Updating %1$s "%2$s" from %3$s...', 'gitomatic-wp' ),
				$type,
				$slug,
				$package_details['repo_url']
			)
		);

		$logger      = new Logger();
		$started_at  = microtime( true );
		$log_context = array(
			'deployment_id' => uniqid( 'gitomatic-wp-', true ),
			'package_type'  => $type,
			'package_slug'  => $slug,
			'provider'      => $package_details['provider'],
			'branch'        => $package_details['branch'],
			'trigger'       => 'wp-cli',
		);
		$logger->log_event( 'deployment.started', 'info', 'Update started.', $log_context );
		$result = Deployments::deploy( $package_details, $type, true );

		if ( is_wp_error( $result ) ) {
			$error_message              = Helper::format_error_message( $result );
			$log_context['duration_ms'] = (int) round( ( microtime( true ) - $started_at ) * 1000 );
			$log_context['error_code']  = $result->get_error_code();
			$error_data                 = $result->get_error_data();
			if ( ! empty( $error_data ) ) {
				$log_context['error_details'] = $error_data;
			}
			$logger->log_event( 'deployment.failed', 'error', $error_message, $log_context );
			\WP_CLI::error( $error_message );
		}

		$log_context['duration_ms'] = (int) round( ( microtime( true ) - $started_at ) * 1000 );
		$logger->log_event( 'deployment.succeeded', 'info', 'Package updated successfully.', $log_context );

		\WP_CLI::success(
			sprintf(
				/* translators: %s: package slug */
				__( '"%s" updated successfully.', 'gitomatic-wp' ),
				$slug
			)
		);
	}

	/**
	 * Install a package from a git repository and register it with Gitomatic WP.
	 *
	 * ## OPTIONS
	 *
	 * <repo-url>
	 * : The full repository URL.
	 *
	 * --provider=<provider>
	 * : The git provider hosting the repository.
	 * ---
	 * options:
	 *   - github
	 *   - gitlab
	 *   - bitbucket
	 *   - gitea
	 * ---
	 *
	 * --type=<type>
	 * : The package type to install.
	 * ---
	 * options:
	 *   - plugin
	 *   - theme
	 * ---
	 *
	 * [--branch=<branch>]
	 * : The branch to deploy. Defaults to the repository default branch.
	 *
	 * [--folder=<folder>]
	 * : Install a separate copy in this folder.
	 *
	 * [--share-with=<instance>]
	 * : Deploy this branch into an existing deployment folder.
	 *
	 * [--private]
	 * : Treat the repository as private.
	 *
	 * [--token=<token>]
	 * : Access token for private GitHub, GitLab, or Gitea repositories.
	 *
	 * [--username=<username>]
	 * : Username for private Bitbucket repositories.
	 *
	 * [--password=<password>]
	 * : App password for private Bitbucket repositories.
	 *
	 * ## EXAMPLES
	 *
	 *     wp gitomatic-wp install https://github.com/user/my-plugin --provider=github --type=plugin
	 *     wp gitomatic-wp install https://github.com/user/my-theme --provider=github --type=theme --branch=main
	 *     wp gitomatic-wp install https://github.com/user/private-plugin --provider=github --type=plugin --private --token=ghp_xxx
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function install( $args, $assoc_args ) {
		$repo_url   = isset( $args[0] ) ? $args[0] : '';
		$provider   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'provider', '' );
		$type       = \WP_CLI\Utils\get_flag_value( $assoc_args, 'type', 'plugin' );
		$branch     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'branch', '' );
		$is_private = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'private', false );
		$token      = \WP_CLI\Utils\get_flag_value( $assoc_args, 'token', '' );
		$username   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'username', '' );
		$password   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'password', '' );

		$type = ( 'theme' === $type ) ? 'theme' : 'plugin';

		if ( empty( $repo_url ) ) {
			\WP_CLI::error( __( 'Please provide a repository URL.', 'gitomatic-wp' ) );
		}

		$valid_providers = array_keys( Helper::available_providers() );
		if ( ! in_array( $provider, $valid_providers, true ) ) {
			\WP_CLI::error(
				sprintf(
					/* translators: 1: invalid provider, 2: comma-separated list of valid providers */
					__( 'Invalid provider "%1$s". Valid options: %2$s', 'gitomatic-wp' ),
					$provider,
					implode( ', ', $valid_providers )
				)
			);
		}

		if ( $is_private ) {
			if ( 'bitbucket' === $provider && ( empty( $username ) || empty( $password ) ) ) {
				\WP_CLI::error( __( 'Bitbucket private repositories require a username and app password.', 'gitomatic-wp' ) );
			}
			if ( in_array( $provider, array( 'github', 'gitlab', 'gitea' ), true ) && empty( $token ) ) {
				\WP_CLI::error( __( 'Private repositories for this provider require an access token.', 'gitomatic-wp' ) );
			}
		}

		$provider_class = Helper::get_provider_class( $provider );

		try {
			$provider_instance = new $provider_class( $repo_url );
			$package_slug      = $provider_instance->get_package_slug();
			if ( '' === $branch ) {
				$auth = $is_private ? array( 'access_token' => $token, 'username' => $username, 'password' => $password ) : array();
				$lookup = Branches::lookup( $provider, $repo_url, $auth );
				if ( is_wp_error( $lookup ) || empty( $lookup['default'] ) ) {
					\WP_CLI::error( __( 'Could not determine the default branch. Supply --branch explicitly.', 'gitomatic-wp' ) );
					return;
				}
				$branch = $lookup['default'];
			}
		} catch ( \Exception $e ) {
			\WP_CLI::error( $e->getMessage() );
			return; // Unreachable in real WP-CLI (error() exits), but needed for testability.
		}

		\WP_CLI::line(
			sprintf(
				/* translators: 1: package type, 2: package slug, 3: repository URL, 4: branch */
				__( 'Installing %1$s "%2$s" from %3$s (branch: %4$s)...', 'gitomatic-wp' ),
				$type,
				$package_slug,
				$repo_url,
				$branch
			)
		);

		$logger      = new Logger();
		$started_at  = microtime( true );
		$log_context = array(
			'deployment_id' => uniqid( 'gitomatic-wp-', true ),
			'package_type'  => $type,
			'package_slug'  => $package_slug,
			'provider'      => $provider,
			'branch'        => $branch,
			'trigger'       => 'wp-cli',
		);
		$logger->log_event( 'deployment.started', 'info', 'Installation started.', $log_context );

		$package_data = array(
			'slug'                  => $package_slug,
			'repo_url'              => $repo_url,
			'branch'                => $branch,
			'provider'              => $provider,
			'is_private_repository' => $is_private,
			'options'               => array(
				'username'     => $username,
				'password'     => $password,
				'access_token' => $token,
			),
		);
		$source_id = $assoc_args['share-with'] ?? '';
		if ( $source_id ) {
			$source = ( new DataManager() )->get_package( $source_id, $type );
			if ( ! $source || isset( $assoc_args['folder'] ) ) {
				\WP_CLI::error( 'Choose a valid --share-with instance or a separate --folder.' );
				return;
			}
			$package_data['slug'] = $source['slug'];
		} else {
			$package_data['slug'] = $assoc_args['folder'] ?? $package_slug;
		}
		$log_context['package_slug'] = $package_data['slug'];
		$result = Deployments::create( $package_data, $type, $source_id ? 'shared' : 'copy', $source_id, true );

		if ( is_wp_error( $result ) ) {
			$error_message              = Helper::format_error_message( $result );
			$log_context['duration_ms'] = (int) round( ( microtime( true ) - $started_at ) * 1000 );
			$log_context['error_code']  = $result->get_error_code();
			$error_data                 = $result->get_error_data();
			if ( ! empty( $error_data ) ) {
				$log_context['error_details'] = $error_data;
			}
			$logger->log_event( 'deployment.failed', 'error', $error_message, $log_context );
			\WP_CLI::error( $error_message );
			return; // Unreachable in real WP-CLI, but needed for testability.
		}

		$log_context['instance_id'] = $result['instance_id'];
		$log_context['duration_ms'] = (int) round( ( microtime( true ) - $started_at ) * 1000 );
		$logger->log_event( 'deployment.succeeded', 'info', 'Package installed successfully.', $log_context );

		\WP_CLI::success(
			sprintf(
				/* translators: %s: package slug */
				__( '"%s" installed and registered successfully.', 'gitomatic-wp' ),
				$result['instance_id']
			)
		);
	}

	/**
	 * Format a stored package array as a display row.
	 *
	 * @param array  $pkg  Raw package data from DataManager.
	 * @param string $type Package type: plugin or theme.
	 * @return array
	 */
	private function format_package_row( $pkg, $type ) {
		return array(
			'instance' => DataManager::instance_id( $pkg ),
			'slug'     => isset( $pkg['slug'] ) ? $pkg['slug'] : '',
			'type'     => $type,
			'provider' => isset( $pkg['provider'] ) ? $pkg['provider'] : '',
			'branch'   => isset( $pkg['branch'] ) ? $pkg['branch'] : '',
			'private'  => ( isset( $pkg['is_private_repository'] ) && $pkg['is_private_repository'] ) ? 'yes' : 'no',
			'repo_url' => isset( $pkg['repo_url'] ) ? $pkg['repo_url'] : '',
		);
	}

}
