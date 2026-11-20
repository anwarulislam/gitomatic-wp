<?php

namespace GitomaticWP;

/**
 * File-based structured activity logger.
 */
class Logger {

	const DEFAULT_MAX_FILE_SIZE  = 2097152;
	const DEFAULT_RETAINED_FILES = 5;

	/**
	 * Directory containing the retained log files.
	 *
	 * @var string
	 */
	private $log_directory;

	/**
	 * Active opaque log file path.
	 *
	 * @var string
	 */
	private $log_file_path;

	/** Constructor. */
	public function __construct() {
		$upload_dir          = wp_upload_dir();
		$this->log_directory = trailingslashit( $upload_dir['basedir'] ) . GITOMATIC_WP_SLUG . '-logs';

		$this->create_log_directory();
		$this->create_protection_files();
		$this->log_file_path = trailingslashit( $this->log_directory ) . $this->get_log_filename();
		$this->migrate_legacy_log_file();
	}

	/**
	 * Log a backwards-compatible informational message.
	 *
	 * @param string $message Message to log.
	 * @return bool Whether the message was written.
	 */
	public function log( $message ) {
		return $this->log_event( 'general', 'info', $message );
	}

	/**
	 * Write a structured event.
	 *
	 * @param string $event   Stable event name.
	 * @param string $level   info, warning, or error.
	 * @param string $message Human-readable summary.
	 * @param array  $context Additional non-secret event context.
	 * @return bool Whether the event was written.
	 */
	public function log_event( $event, $level, $message, $context = array() ) {
		$allowed_levels = array( 'info', 'warning', 'error' );
		$level          = in_array( $level, $allowed_levels, true ) ? $level : 'info';
		$event          = preg_replace( '/[^a-z0-9._-]/', '', strtolower( (string) $event ) );
		$event          = '' === $event ? 'general' : $event;
		$message        = $this->sanitize_log_string( $message );

		$record = array(
			'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'level'     => $level,
			'event'     => $event,
			'message'   => $message,
			'context'   => $this->sanitize_context( $context ),
		);

		$encoded = wp_json_encode( $record, JSON_UNESCAPED_SLASHES );
		if ( false === $encoded ) {
			return false;
		}

		$result      = $this->append_line( $encoded . PHP_EOL );
		$legacy_line = '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $message . PHP_EOL;

		/**
		 * Fires after a log write is attempted.
		 *
		 * The first argument remains the legacy formatted line for compatibility.
		 *
		 * @param string $legacy_line Legacy formatted log line.
		 * @param array  $record      Structured event record.
		 * @param bool   $result      Whether the write succeeded.
		 */
		do_action( 'gitomatic_wp_after_log', $legacy_line, $record, $result );

		return $result;
	}

	/**
	 * Get all retained log entries, newest first.
	 *
	 * @return array Log entries.
	 */
	public function get_log_entries() {
		$entries = array();

		foreach ( $this->get_log_file_paths( true ) as $path ) {
			if ( ! file_exists( $path ) ) {
				continue;
			}

			// Retained files have a strict total size limit, so this read is bounded.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file
			$lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
			if ( false === $lines ) {
				continue;
			}

			foreach ( $lines as $line ) {
				$entries[] = $this->parse_log_line( $line );
			}
		}

		return array_reverse( $entries );
	}

	/**
	 * Return retained logs as a downloadable text payload.
	 *
	 * @return string Log content.
	 */
	public function get_download_content() {
		$content = '';

		foreach ( $this->get_log_file_paths( true ) as $path ) {
			if ( ! file_exists( $path ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$file_content = file_get_contents( $path );
			if ( false !== $file_content ) {
				$content .= $file_content;
			}
		}

		return $content;
	}

	/**
	 * Display log content for backwards compatibility.
	 *
	 * @return string Log content.
	 */
	public function display_log_content() {
		$content = $this->get_download_content();

		return '' === $content ? __( 'No log entries found.', 'gitomatic-wp' ) : $content;
	}

	/**
	 * Get total bytes occupied by retained logs.
	 *
	 * @return int Size in bytes.
	 */
	public function get_log_size() {
		$size = 0;

		foreach ( $this->get_log_file_paths( true ) as $path ) {
			if ( file_exists( $path ) ) {
				$file_size = filesize( $path );
				$size     += false === $file_size ? 0 : $file_size;
			}
		}

		return $size;
	}

	/**
	 * Get the active log path. Intended for diagnostics and tests.
	 *
	 * @return string Log file path.
	 */
	public function get_log_file_path() {
		return $this->log_file_path;
	}

	/**
	 * Clear current and rotated log files.
	 *
	 * @return bool Whether the logs were cleared.
	 */
	public function clear_log_file() {
		$lock = $this->acquire_lock();
		if ( false === $lock ) {
			return false;
		}

		$result = true;
		foreach ( $this->get_log_file_paths( false ) as $path ) {
			if ( $path === $this->log_file_path ) {
				// Direct access is required for an atomic truncate while holding the logger lock.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$result = false !== file_put_contents( $path, '' ) && $result;
			} elseif ( file_exists( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				$result = unlink( $path ) && $result;
			}
		}

		$this->release_lock( $lock );

		return $result;
	}

	/**
	 * Append one line while holding the logger lock.
	 *
	 * @param string $line Encoded log line.
	 * @return bool Whether the line was written.
	 */
	private function append_line( $line ) {
		$lock = $this->acquire_lock();
		if ( false === $lock ) {
			return false;
		}

		$this->maybe_rotate( strlen( $line ) );

		// Direct access is required for atomic append semantics while holding the logger lock.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $this->log_file_path, $line, FILE_APPEND );
		$this->release_lock( $lock );

		return false !== $result;
	}

	/**
	 * Rotate the active log when the next write exceeds the configured limit.
	 *
	 * @param int $incoming_size Bytes about to be written.
	 * @return void
	 */
	private function maybe_rotate( $incoming_size ) {
		$current_size = file_exists( $this->log_file_path ) ? filesize( $this->log_file_path ) : 0;
		$current_size = false === $current_size ? 0 : $current_size;
		$max_size     = max( 1024, (int) apply_filters( 'gitomatic_wp_log_max_file_size', self::DEFAULT_MAX_FILE_SIZE ) );

		if ( 0 === $current_size || $current_size + $incoming_size <= $max_size ) {
			return;
		}

		$retained = $this->get_retained_file_count();
		$oldest   = $this->log_file_path . '.' . $retained;
		if ( file_exists( $oldest ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $oldest );
		}

		for ( $index = $retained - 1; $index >= 1; $index-- ) {
			$source = $this->log_file_path . '.' . $index;
			if ( file_exists( $source ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				rename( $source, $this->log_file_path . '.' . ( $index + 1 ) );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		rename( $this->log_file_path, $this->log_file_path . '.1' );
	}

	/**
	 * Acquire the logger lock.
	 *
	 * @return resource|false Lock handle.
	 */
	private function acquire_lock() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( trailingslashit( $this->log_directory ) . '.lock', 'c' );
		if ( false === $handle || ! flock( $handle, LOCK_EX ) ) {
			if ( is_resource( $handle ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				fclose( $handle );
			}
			return false;
		}

		return $handle;
	}

	/**
	 * Release the logger lock.
	 *
	 * @param resource $handle Lock handle.
	 * @return void
	 */
	private function release_lock( $handle ) {
		flock( $handle, LOCK_UN );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );
	}

	/**
	 * Parse a structured or legacy log line.
	 *
	 * @param string $line Log line.
	 * @return array Normalized entry.
	 */
	private function parse_log_line( $line ) {
		$entry = json_decode( $line, true );
		if ( is_array( $entry ) && isset( $entry['timestamp'], $entry['message'] ) ) {
			$entry['level']   = isset( $entry['level'] ) ? $entry['level'] : 'info';
			$entry['event']   = isset( $entry['event'] ) ? $entry['event'] : 'general';
			$entry['context'] = isset( $entry['context'] ) && is_array( $entry['context'] ) ? $entry['context'] : array();
			return $entry;
		}

		$timestamp = '';
		$message   = $line;
		if ( preg_match( '/^\[([^\]]+)\]\s*(.*)$/', $line, $matches ) ) {
			$timestamp = $matches[1] . ' UTC';
			$message   = $matches[2];
		}

		return array(
			'timestamp' => $timestamp,
			'level'     => 0 === stripos( $message, 'error' ) ? 'error' : 'info',
			'event'     => 'legacy',
			'message'   => $this->sanitize_log_string( $message ),
			'context'   => array(),
		);
	}

	/**
	 * Sanitize and redact an event context recursively.
	 *
	 * @param mixed  $value Context value.
	 * @param string $key   Parent key.
	 * @return mixed Safe context value.
	 */
	private function sanitize_context( $value, $key = '' ) {
		if ( preg_match( '/token|password|secret|authorization|credential|api[_-]?key/i', $key ) ) {
			return '[REDACTED]';
		}

		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $child_key => $child_value ) {
				$safe_key               = is_string( $child_key ) ? sanitize_key( $child_key ) : $child_key;
				$sanitized[ $safe_key ] = $this->sanitize_context( $child_value, (string) $child_key );
			}
			return $sanitized;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return $this->sanitize_log_string( (string) $value );
	}

	/**
	 * Normalize a string and redact common inline credentials.
	 *
	 * @param string $value Untrusted log value.
	 * @return string Safe single-line value.
	 */
	private function sanitize_log_string( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = preg_replace( '/[\r\n\t]+/', ' ', $value );
		$value = preg_replace( '/\b(Bearer|Basic|token)\s+[A-Za-z0-9+\/_=.\-]+/i', '$1 [REDACTED]', $value );
		$value = preg_replace( '/\b(?:github_pat_|gh[pousr]_|glpat-)[A-Za-z0-9_\-]+/i', '[REDACTED]', $value );
		$value = preg_replace( '#(https?://)[^/\s:@]+:[^/\s@]+@#i', '$1[REDACTED]@', $value );
		$value = preg_replace( '/([?&](?:access_token|private_token|token|password|secret|api[_-]?key)=)[^&\s]+/i', '$1[REDACTED]', $value );
		if ( strlen( $value ) > 8192 ) {
			$value = substr( $value, 0, 8192 ) . '...';
		}

		return trim( $value );
	}

	/**
	 * Get ordered retained log paths.
	 *
	 * @param bool $oldest_first Whether to return oldest first.
	 * @return array Paths.
	 */
	private function get_log_file_paths( $oldest_first ) {
		$paths    = array();
		$retained = $this->get_retained_file_count();

		if ( $oldest_first ) {
			for ( $index = $retained; $index >= 1; $index-- ) {
				$paths[] = $this->log_file_path . '.' . $index;
			}
			$paths[] = $this->log_file_path;
			return $paths;
		}

		$paths[] = $this->log_file_path;
		for ( $index = 1; $index <= $retained; $index++ ) {
			$paths[] = $this->log_file_path . '.' . $index;
		}

		return $paths;
	}

	/**
	 * Get the number of rotated files to retain.
	 *
	 * @return int Retained rotated file count.
	 */
	private function get_retained_file_count() {
		return max( 1, min( 20, (int) apply_filters( 'gitomatic_wp_log_retained_files', self::DEFAULT_RETAINED_FILES ) ) );
	}

	/**
	 * Get or create the opaque log filename.
	 *
	 * @return string Opaque filename.
	 */
	private function get_log_filename() {
		$option_name = GITOMATIC_WP_SLUG . '_log_filename';
		$filename    = get_option( $option_name, '' );

		if ( ! is_string( $filename ) || ! preg_match( '/^plugin-[a-zA-Z0-9]+\.log$/', $filename ) ) {
			$filename = 'plugin-' . wp_generate_password( 32, false, false ) . '.log';
			update_option( $option_name, $filename, false );
		}

		return $filename;
	}

	/**
	 * Move a legacy predictable log file to the opaque path.
	 *
	 * @return void
	 */
	private function migrate_legacy_log_file() {
		$legacy_path = trailingslashit( $this->log_directory ) . 'plugin.log';
		if ( ! file_exists( $legacy_path ) || file_exists( $this->log_file_path ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		rename( $legacy_path, $this->log_file_path );
	}

	/**
	 * Create server protection and directory-index files.
	 *
	 * @return void
	 */
	private function create_protection_files() {
		$files = array(
			'.htaccess'  => "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
		);

		foreach ( $files as $filename => $content ) {
			$path = trailingslashit( $this->log_directory ) . $filename;
			if ( ! file_exists( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $path, $content );
			}
		}
	}

	/**
	 * Create the log directory.
	 *
	 * @return void
	 */
	private function create_log_directory() {
		if ( ! is_dir( $this->log_directory ) ) {
			wp_mkdir_p( $this->log_directory );
		}
	}
}
