<?php

namespace DeployerForGit\Subpages;

use DeployerForGit\DataManager;
use DeployerForGit\Logger;
use DeployerForGit\Helper;
/**
 * Class InstallPackage
 *
 * @package Wp_Git_Deployer
 */
class InstallPackage {
    /**
     * Init package menu hook.
     */
    public function __construct() {
        add_action( ( is_multisite() ? 'network_admin_menu' : 'admin_menu' ), array($this, 'init_menu') );
    }

    /**
     * Initializes the menu.
     */
    public function init_menu() {
    }

    /**
     * Validates the package installation form.
     */
    private function validate_install_package_form() {
        if ( !isset( $_POST[DFG_SLUG . '_install_package_submitted'] ) ) {
            return;
        }
        // Vefiry form nonce.
        $nonce = ( isset( $_POST[DFG_SLUG . '_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST[DFG_SLUG . '_nonce'] ) ) : '' );
        if ( !wp_verify_nonce( $nonce, DFG_SLUG . '_install_package_form' ) ) {
            return new \WP_Error('invalid', __( 'Invalid nonce', 'deployer-for-git' ));
        }
        $provider_type = ( isset( $_POST['provider_type'] ) ? sanitize_text_field( wp_unslash( $_POST['provider_type'] ) ) : '' );
        // Check if provider type is valid.
        if ( !in_array( $provider_type, array_keys( Helper::available_providers() ), true ) ) {
            return new \WP_Error('invalid', __( 'Invalid provider type', 'deployer-for-git' ));
        }
        return true;
    }

    /**
     * Handles the package installation form.
     *
     * @return WP_Error|bool Returns WP_Error object for failure or true for success.
     */
    public function handle_package_install_form() {
        // Validate form.
        $validation_result = $this->validate_install_package_form();
        if ( $validation_result !== true ) {
            return $validation_result;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        // Sanitize form data.
        $package_type = ( isset( $_POST['package_type'] ) ? sanitize_text_field( wp_unslash( $_POST['package_type'] ) ) : '' );
        $package_type = ( $package_type === 'plugin' ? 'plugin' : 'theme' );
        $provider_type = ( isset( $_POST['provider_type'] ) ? sanitize_text_field( wp_unslash( $_POST['provider_type'] ) ) : '' );
        $branch = ( isset( $_POST['repository_branch'] ) && !empty( $_POST['repository_branch'] ) ? sanitize_text_field( wp_unslash( $_POST['repository_branch'] ) ) : 'master' );
        $repository_url = ( isset( $_POST['repository_url'] ) ? esc_url_raw( wp_unslash( $_POST['repository_url'] ) ) : '' );
        $is_private_repository = isset( $_POST['is_private_repository'] );
        $username = ( isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '' );
        $password = ( isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '' );
        $access_token = ( isset( $_POST['access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : '' );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        // Validate private repository credentials.
        if ( $is_private_repository ) {
            if ( $provider_type === 'bitbucket' && ( empty( $username ) || empty( $password ) ) ) {
                return new \WP_Error('invalid', __( 'Bitbucket private repositories require a username and app password.', 'deployer-for-git' ));
            }
            if ( in_array( $provider_type, array( 'github', 'gitlab', 'gitea' ), true ) && empty( $access_token ) ) {
                return new \WP_Error('invalid', __( 'Private repositories for this provider require an access token.', 'deployer-for-git' ));
            }
        }
        $package_install_options = array(
            'branch' => $branch,
        );
        if ( $is_private_repository ) {
            $package_install_options = array(
                'branch'                => $branch,
                'is_private_repository' => true,
                'username'              => $username,
                'password'              => $password,
                'access_token'          => $access_token,
            );
        }
        $provider_class_name = Helper::get_provider_class( $provider_type );
        $logger = new Logger();
        $started_at = microtime( true );
        $log_context = array(
            'deployment_id' => uniqid( 'dfg-', true ),
            'package_type'  => $package_type,
            'provider'      => $provider_type,
            'branch'        => $branch,
            'trigger'       => 'wp-admin',
        );
        $logger->log_event(
            'deployment.started',
            'info',
            'Installation started.',
            $log_context
        );
        try {
            $provider = new $provider_class_name($repository_url);
            $package_slug = $provider->get_package_slug();
            $log_context['package_slug'] = $package_slug;
            $package_zip_url = $provider->get_zip_repo_url( $branch );
            // If the provider is GitHub, we need to do some additional checks.
            if ( $provider instanceof \DeployerForGit\Providers\GithubProvider ) {
                $github_token = ( isset( $package_install_options['access_token'] ) ? $package_install_options['access_token'] : '' );
                $package_zip_url = $provider->get_zip_repo_url( $branch, $github_token );
            }
            $install_result = self::install_package_from_zip_url(
                $package_zip_url,
                $package_slug,
                $package_type,
                $provider_type,
                $package_install_options
            );
            // TODO: maybe move this piece to a method?
            if ( $install_result === true ) {
                $data_manager = new DataManager();
                $package_data = array(
                    'slug'                  => $package_slug,
                    'repo_url'              => $repository_url,
                    'branch'                => $branch,
                    'provider'              => $provider_type,
                    'is_private_repository' => $is_private_repository,
                    'options'               => array(
                        'username'     => $username,
                        'password'     => $password,
                        'access_token' => $access_token,
                    ),
                );
                $data_manager->store_package_details( $package_data, $package_type );
                $log_context['duration_ms'] = (int) round( (microtime( true ) - $started_at) * 1000 );
                $logger->log_event(
                    'deployment.succeeded',
                    'info',
                    'Package installed successfully.',
                    $log_context
                );
            } else {
                $error_message              = ( is_wp_error( $install_result ) ? Helper::format_error_message( $install_result ) : __( 'Some error occurred', 'deployer-for-git' ) );
                $log_context['duration_ms'] = (int) round( (microtime( true ) - $started_at) * 1000 );
                if ( is_wp_error( $install_result ) ) {
                    $log_context['error_code'] = $install_result->get_error_code();
                    $error_data                = $install_result->get_error_data();
                    if ( ! empty( $error_data ) ) {
                        $log_context['error_details'] = $error_data;
                    }
                }
                $logger->log_event(
                    'deployment.failed',
                    'error',
                    $error_message,
                    $log_context
                );
            }
        } catch ( \Exception $e ) {
            // Invalid repository URL.
            $install_result = new \WP_Error('invalid', $e->getMessage());
            $log_context['duration_ms'] = (int) round( (microtime( true ) - $started_at) * 1000 );
            $logger->log_event(
                'deployment.failed',
                'error',
                $e->getMessage(),
                $log_context
            );
        }
        $success = $install_result === true;
        do_action( 'dfg_after_package_install', $success );
        return $install_result;
    }

    /**
     * Initializes the page.
     */
    public function init_page() {
    }

    /**
     * Checks if the request is a WP JSON request.
     *
     * @param array $options Additional options for the installation.
     */
    private static function is_wp_json_request( $options = array() ) {
        return array_key_exists( 'wp_json_request', $options ) && $options['wp_json_request'] === true;
    }

    /**
     * Checks if the current user can proceed with the installation.
     *
     * @param string $package_type Type of the package (theme|plugin).
     */
    private static function current_user_can_proceed( $package_type ) {
        $type_in_plural = "{$package_type}s";
        return current_user_can( "install_{$type_in_plural}" );
    }

    /**
     * Validate that an extracted archive contains the requested WordPress package type.
     *
     * @param string|\WP_Error $source        Extracted package source path.
     * @param string           $remote_source Remote package source path.
     * @param \WP_Upgrader     $upgrader      WordPress upgrader instance.
     * @param array            $hook_extra    Extra upgrader arguments.
     * @return string|\WP_Error Source path when valid, otherwise a helpful error.
     */
    public static function validate_package_source(
        $source,
        $remote_source,
        $upgrader,
        $hook_extra
    ) {
        global $wp_filesystem;
        if ( is_wp_error( $source ) ) {
            return $source;
        }
        $type = ( isset( $hook_extra['type'] ) && 'theme' === $hook_extra['type'] ? 'theme' : 'plugin' );
        $working_directory = str_replace( $wp_filesystem->wp_content_dir(), trailingslashit( WP_CONTENT_DIR ), $source );
        if ( !is_dir( $working_directory ) ) {
            // Match WordPress core's confidence check for non-direct filesystems.
            return $source;
        }
        if ( 'theme' === $type ) {
            $style_file = trailingslashit( $working_directory ) . 'style.css';
            $theme_data = ( file_exists( $style_file ) ? get_file_data( $style_file, array(
                'Name' => 'Theme Name',
            ) ) : array() );
            if ( empty( $theme_data['Name'] ) ) {
                return new \WP_Error(
                    'package_type_mismatch',
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    __( 'This repository does not contain a valid WordPress theme. If it contains a plugin, install it from the Install Plugin page instead.', 'deployer-for-git' )
                );
            }
            return $source;
        }
        $plugin_files = glob( trailingslashit( $working_directory ) . '*.php' );
        foreach ( (array) $plugin_files as $plugin_file ) {
            $plugin_data = get_plugin_data( $plugin_file, false, false );
            if ( !empty( $plugin_data['Name'] ) ) {
                return $source;
            }
        }
        return new \WP_Error(
            'package_type_mismatch',
            // phpcs:ignore Generic.Files.LineLength.TooLong
            __( 'This repository does not contain a valid WordPress plugin. If it contains a theme, install it from the Install Theme page instead.', 'deployer-for-git' )
        );
    }

    /**
     * Add package validation filters for the selected upgrader.
     *
     * @param \WP_Upgrader $package_upgrader WordPress package upgrader.
     * @return void
     */
    private static function add_package_validation_filters( $package_upgrader ) {
        add_filter(
            'upgrader_source_selection',
            array(__CLASS__, 'validate_package_source'),
            9,
            4
        );
        add_filter( 'upgrader_source_selection', array($package_upgrader, 'check_package') );
    }

    /**
     * Remove package validation filters for the selected upgrader.
     *
     * @param \WP_Upgrader $package_upgrader WordPress package upgrader.
     * @return void
     */
    private static function remove_package_validation_filters( $package_upgrader ) {
        remove_filter( 'upgrader_source_selection', array(__CLASS__, 'validate_package_source'), 9 );
        remove_filter( 'upgrader_source_selection', array($package_upgrader, 'check_package') );
    }

    /**
     * Installs a package from a zip file URL.
     *
     * @param string $package_zip_url The URL of the package zip file.
     * @param string $package_slug The slug of the package.
     * @param string $type Type of the package (theme|plugin).
     * @param string $provider_type Type of the provider (bitbucket|github|gitlab|gitea).
     * @param array  $options Additional options for the installation.
     *
     * @return WP_Error|bool Returns WP_Error object for failure or true for success.
     */
    public static function install_package_from_zip_url(
        $package_zip_url,
        $package_slug,
        $type,
        $provider_type,
        $options = array()
    ) {
        if ( empty( $provider_type ) ) {
            return new \WP_Error('invalid', __( 'Provider does not exist.', 'deployer-for-git' ));
        }
        // Check if provider type is valid.
        if ( !in_array( $provider_type, array_keys( Helper::available_providers() ), true ) ) {
            return new \WP_Error('invalid', __( 'Invalid provider type', 'deployer-for-git' ));
        }
        $type = ( $type === 'theme' ? 'theme' : 'plugin' );
        // Check for empty URL and slug.
        if ( empty( $package_zip_url ) || empty( $package_slug ) ) {
            return new \WP_Error('invalid', __( 'Package zip URL and package slug must exist', 'deployer-for-git' ));
        }
        $is_wp_json_request = self::is_wp_json_request( $options );
        // Check user capabilities.
        if ( !self::current_user_can_proceed( $type ) && !$is_wp_json_request ) {
            return new \WP_Error('invalid', __( 'User should have enough permissions', 'deployer-for-git' ));
        }
        // Initialize WordPress filesystem.
        if ( !function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        // load file which loads most of the classes needed for package installation.
        if ( !function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        // Set up package installation parameters.
        if ( $type === 'theme' ) {
            $package_destination_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $package_slug;
            $skin = new \DeployerForGit\ThemeInstallerSkin();
            // Create a new instance of the Theme_Upgrader class.
            $package_upgrader = new \Theme_Upgrader($skin);
        } else {
            $package_destination_dir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $package_slug;
            $skin = new \DeployerForGit\PluginInstallerSkin();
            // Create a new instance of the Plugin_Upgrader class.
            $package_upgrader = new \Plugin_Upgrader($skin);
        }
        // Set upgrader arguments.
        $package_upgrader->generic_strings();
        /*
         * The low-level run() method does not enable the package checks that
         * Plugin_Upgrader::install() and Theme_Upgrader::install() normally use.
         * Add the matching check explicitly because this installer needs a custom
         * destination directory.
         */
        self::add_package_validation_filters( $package_upgrader );
        /*
         * For private repositories, attach authentication headers to the exact
         * package zip URL request only (download_url() fires http_request_args).
         * Provider redirect targets (e.g. codeload, signed CDN URLs) must not
         * receive credentials.
         */
        $auth_filter = null;
        if ( ! empty( $options['is_private_repository'] ) ) {
            $provider_class_name = Helper::get_provider_class( $provider_type );
            $headers = $provider_class_name::get_download_headers( $options );
            if ( ! empty( $headers ) ) {
                $auth_filter = function ( $args, $url ) use ( $package_zip_url, $headers ) {
                    if ( $url === $package_zip_url ) {
                        $args['headers'] = array_merge( (array) $args['headers'], $headers );
                    }
                    return $args;
                };
                add_filter( 'http_request_args', $auth_filter, 10, 2 );
            }
        }
        /*
         * Capture the HTTP response during package download so we can report
         * precise reasons (HTTP 401, 404, rate limits, cURL errors) if it fails.
         */
        $last_http_response   = null;
        $http_response_filter = function ( $response, $args, $url ) use ( &$last_http_response ) {
            if ( is_wp_error( $response ) ) {
                $last_http_response = array(
                    'url'           => $url,
                    'error_message' => $response->get_error_message(),
                    'error_code'    => $response->get_error_code(),
                );
            } else {
                $code    = (int) wp_remote_retrieve_response_code( $response );
                $message = wp_remote_retrieve_response_message( $response );
                $body    = '';
                if ( ! empty( $args['filename'] ) && file_exists( $args['filename'] ) ) {
                    $file_content = @file_get_contents( $args['filename'], false, null, 0, 2048 );
                    if ( false !== $file_content ) {
                        $body = $file_content;
                    }
                } else {
                    $body = wp_remote_retrieve_body( $response );
                }
                $last_http_response = array(
                    'url'     => $url,
                    'code'    => $code,
                    'message' => $message,
                    'body'    => $body,
                );
            }
            return $response;
        };
        add_filter( 'http_response', $http_response_filter, 10, 3 );

        // Run the package installation.
        $result = $package_upgrader->run( array(
            'package'           => $package_zip_url,
            'destination'       => $package_destination_dir,
            'clear_destination' => true,
            'clear_working'     => true,
            'hook_extra'        => array(
                'type'   => $type,
                'action' => 'install',
            ),
        ) );
        remove_filter( 'http_response', $http_response_filter, 10 );
        if ( null !== $auth_filter ) {
            remove_filter( 'http_request_args', $auth_filter, 10, 2 );
        }
        self::remove_package_validation_filters( $package_upgrader );
        $package_upgrader->maintenance_mode( false );
        if ( $result === false ) {
            if ( isset( $package_upgrader->skin ) && method_exists( $package_upgrader->skin, 'get_errors' ) ) {
                $skin_errors = $package_upgrader->skin->get_errors();
                if ( is_wp_error( $skin_errors ) && $skin_errors->has_errors() ) {
                    return $skin_errors;
                }
            }
            return new \WP_Error('invalid', __( 'Some error occurred during package installation.', 'deployer-for-git' ));
        } elseif ( is_wp_error( $result ) ) {
            return self::enhance_install_error( $result, $last_http_response, $provider_type, $options );
        }
        return true;
    }

    /**
     * Enhance an installation WP_Error with rich HTTP / provider diagnostics.
     *
     * @param \WP_Error $error              Original WP_Error from upgrader.
     * @param array     $last_http_response Captured HTTP response details.
     * @param string    $provider_type      Provider slug (github, gitlab, etc.).
     * @param array     $options            Installation options (branch, etc.).
     * @return \WP_Error
     */
    private static function enhance_install_error( $error, $last_http_response, $provider_type, $options ) {
        if ( 'download_failed' !== $error->get_error_code() ) {
            return $error;
        }

        $error_data   = $error->get_error_data();
        $http_status  = null;
        $api_message  = '';
        $hint         = '';
        $branch_name  = isset( $options['branch'] ) ? $options['branch'] : '';

        if ( ! empty( $last_http_response ) ) {
            if ( ! empty( $last_http_response['error_message'] ) ) {
                // cURL or network transport error.
                $api_message = $last_http_response['error_message'];
                $hint        = $api_message;
            } elseif ( ! empty( $last_http_response['code'] ) ) {
                $http_status = (int) $last_http_response['code'];
                $raw_body    = isset( $last_http_response['body'] ) ? $last_http_response['body'] : '';

                if ( ! empty( $raw_body ) ) {
                    $decoded = json_decode( $raw_body, true );
                    if ( is_array( $decoded ) && ! empty( $decoded['message'] ) ) {
                        $api_message = (string) $decoded['message'];
                    } elseif ( is_array( $decoded ) && ! empty( $decoded['error'] ) ) {
                        $api_message = is_string( $decoded['error'] ) ? $decoded['error'] : ( isset( $decoded['error']['message'] ) ? (string) $decoded['error']['message'] : '' );
                    }
                }

                if ( empty( $api_message ) && ! empty( $last_http_response['message'] ) ) {
                    $api_message = $last_http_response['message'];
                }

                if ( 404 === $http_status ) {
                    $branch_hint = '' !== $branch_name ? sprintf( ' (branch: "%s")', $branch_name ) : '';
                    $hint        = sprintf(
                        __( 'HTTP 404: Not Found%s. Verify that the repository URL and branch exist, and that your access token has permission to access this repository.', 'deployer-for-git' ),
                        $branch_hint
                    );
                    if ( empty( $options['is_private_repository'] ) ) {
                        $hint .= ' ' . __( 'If this repository is private, enable "Is Private Repository" and provide a token.', 'deployer-for-git' );
                    }
                } elseif ( 401 === $http_status ) {
                    $hint = __( 'HTTP 401: Unauthorized. The access token or credentials are invalid, expired, or missing.', 'deployer-for-git' );
                } elseif ( 403 === $http_status ) {
                    $hint = __( 'HTTP 403: Forbidden. Token lacks required permissions/scopes, organization requires SSO authorization, or API rate limit was exceeded.', 'deployer-for-git' );
                } elseif ( 429 === $http_status ) {
                    $hint = __( 'HTTP 429: Too Many Requests. Git provider API rate limit exceeded.', 'deployer-for-git' );
                } else {
                    $hint = sprintf( __( 'HTTP %d response from provider.', 'deployer-for-git' ), $http_status );
                }
            }
        }

        if ( empty( $hint ) && is_string( $error_data ) && '' !== $error_data ) {
            $hint = $error_data;
        }

        $combined_message = __( 'Download failed.', 'deployer-for-git' );
        if ( ! empty( $hint ) ) {
            $combined_message = rtrim( $combined_message, '.' ) . ': ' . $hint;
            if ( ! empty( $api_message ) && false === strpos( $hint, $api_message ) ) {
                $combined_message .= ' (' . $api_message . ')';
            }
        }

        return new \WP_Error(
            'download_failed',
            $combined_message,
            array(
                'http_status' => $http_status,
                'api_message' => $api_message,
                'raw_error'   => $error_data,
                'provider'    => $provider_type,
            )
        );
    }

}
