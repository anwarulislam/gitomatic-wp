<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
$copy_lines  = array();
foreach ( $entries as $copy_entry ) {
	$copy_lines[] = wp_json_encode( $copy_entry, JSON_UNESCAPED_SLASHES );
}
?>
<div class="wrap dfg_logs_page">
	<h1><?php echo esc_html__( 'Deployment activity', 'deployer-for-git' ); ?></h1>
	<p class="description">
		<?php echo esc_html__( 'Review installations, updates, cache flushes, and failures. Times are shown in the WordPress site timezone.', 'deployer-for-git' ); ?>
	</p>

	<?php if ( isset( $form_submitted ) && $form_submitted ) : ?>
		<?php if ( isset( $clear_log_result ) && $clear_log_result ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Logs cleared.', 'deployer-for-git' ); ?></p></div>
		<?php else : ?>
			<div class="notice notice-error"><p><?php echo esc_html__( 'The logs could not be cleared.', 'deployer-for-git' ); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="dfg_log_summary" aria-label="<?php echo esc_attr__( 'Log summary', 'deployer-for-git' ); ?>">
		<div><strong><?php echo esc_html( number_format_i18n( count( $all_entries ) ) ); ?></strong><span><?php echo esc_html__( 'retained events', 'deployer-for-git' ); ?></span></div>
		<div><strong><?php echo esc_html( number_format_i18n( $error_count ) ); ?></strong><span><?php echo esc_html__( 'errors', 'deployer-for-git' ); ?></span></div>
		<div><strong><?php echo esc_html( size_format( $logger->get_log_size(), 1 ) ); ?></strong><span><?php echo esc_html__( 'storage used', 'deployer-for-git' ); ?></span></div>
	</div>

	<form class="dfg_log_filters" method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( \DeployerForGit\Helper::menu_slug() . '-logs' ); ?>">
		<label class="screen-reader-text" for="dfg-log-search"><?php echo esc_html__( 'Search activity', 'deployer-for-git' ); ?></label>
		<input id="dfg-log-search" type="search" name="log_search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php echo esc_attr__( 'Search package or message', 'deployer-for-git' ); ?>">

		<label class="screen-reader-text" for="dfg-log-level"><?php echo esc_html__( 'Filter by level', 'deployer-for-git' ); ?></label>
		<select id="dfg-log-level" name="log_level">
			<option value=""><?php echo esc_html__( 'All levels', 'deployer-for-git' ); ?></option>
			<option value="info" <?php selected( $filters['level'], 'info' ); ?>><?php echo esc_html__( 'Info', 'deployer-for-git' ); ?></option>
			<option value="warning" <?php selected( $filters['level'], 'warning' ); ?>><?php echo esc_html__( 'Warning', 'deployer-for-git' ); ?></option>
			<option value="error" <?php selected( $filters['level'], 'error' ); ?>><?php echo esc_html__( 'Error', 'deployer-for-git' ); ?></option>
		</select>

		<label class="screen-reader-text" for="dfg-log-trigger"><?php echo esc_html__( 'Filter by source', 'deployer-for-git' ); ?></label>
		<select id="dfg-log-trigger" name="log_trigger">
			<option value=""><?php echo esc_html__( 'All sources', 'deployer-for-git' ); ?></option>
			<option value="wp-admin" <?php selected( $filters['trigger'], 'wp-admin' ); ?>><?php echo esc_html__( 'WP Admin', 'deployer-for-git' ); ?></option>
			<option value="rest" <?php selected( $filters['trigger'], 'rest' ); ?>><?php echo esc_html__( 'REST API', 'deployer-for-git' ); ?></option>
			<option value="wp-cli" <?php selected( $filters['trigger'], 'wp-cli' ); ?>><?php echo esc_html__( 'WP-CLI', 'deployer-for-git' ); ?></option>
		</select>
		<label class="screen-reader-text" for="dfg-log-date"><?php echo esc_html__( 'Filter by date', 'deployer-for-git' ); ?></label>
		<input id="dfg-log-date" type="date" name="log_date" value="<?php echo esc_attr( $filters['date'] ); ?>">
		<button class="button" type="submit"><?php echo esc_html__( 'Filter', 'deployer-for-git' ); ?></button>
		<?php if ( '' !== $filters['search'] || '' !== $filters['level'] || '' !== $filters['trigger'] || '' !== $filters['date'] ) : ?>
			<a class="button-link" href="<?php echo esc_url( menu_page_url( \DeployerForGit\Helper::menu_slug() . '-logs', false ) ); ?>"><?php echo esc_html__( 'Reset', 'deployer-for-git' ); ?></a>
		<?php endif; ?>
	</form>

	<?php if ( empty( $entries ) ) : ?>
		<div class="dfg_log_empty">
			<h2><?php echo esc_html__( 'No activity found', 'deployer-for-git' ); ?></h2>
			<p><?php echo esc_html__( 'Deployment events will appear here. Try resetting the filters if logs already exist.', 'deployer-for-git' ); ?></p>
		</div>
	<?php else : ?>
		<div class="dfg_log_table_wrapper">
			<table class="widefat striped dfg_log_table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Time', 'deployer-for-git' ); ?></th>
						<th><?php echo esc_html__( 'Level', 'deployer-for-git' ); ?></th>
						<th><?php echo esc_html__( 'Package', 'deployer-for-git' ); ?></th>
						<th><?php echo esc_html__( 'Source', 'deployer-for-git' ); ?></th>
						<th><?php echo esc_html__( 'Activity', 'deployer-for-git' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $entries as $entry ) : ?>
						<?php
						$context      = isset( $entry['context'] ) ? $entry['context'] : array();
						$package_slug = isset( $context['package_slug'] ) ? $context['package_slug'] : '';
						$package_type = isset( $context['package_type'] ) ? $context['package_type'] : '';
						$trigger      = isset( $context['trigger'] ) ? $context['trigger'] : '';
						$timestamp    = strtotime( $entry['timestamp'] );
						$display_time = false === $timestamp ? $entry['timestamp'] : get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), $date_format );
						$context_json = empty( $context ) ? '' : wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
						$event_label  = ucwords( str_replace( array( '.', '_' ), ' ', $entry['event'] ) );
						?>
						<tr>
							<td class="dfg_log_time"><time datetime="<?php echo esc_attr( $entry['timestamp'] ); ?>"><?php echo esc_html( $display_time ); ?></time></td>
							<td><span class="dfg_log_level dfg_log_level_<?php echo esc_attr( $entry['level'] ); ?>"><?php echo esc_html( ucfirst( $entry['level'] ) ); ?></span></td>
							<td>
								<?php if ( '' !== $package_slug ) : ?>
									<strong><?php echo esc_html( $package_slug ); ?></strong>
									<?php
									if ( '' !== $package_type ) :
										?>
										<span class="description"><?php echo esc_html( $package_type ); ?></span><?php endif; ?>
								<?php else : ?>
									<span aria-hidden="true">&mdash;</span>
								<?php endif; ?>
							</td>
							<td><?php echo '' === $trigger ? '&mdash;' : esc_html( strtoupper( $trigger ) ); ?></td>
							<td>
								<strong class="dfg_log_event"><?php echo esc_html( $event_label ); ?></strong>
								<div><?php echo esc_html( $entry['message'] ); ?></div>
								<?php if ( '' !== $context_json ) : ?>
									<details><summary><?php echo esc_html__( 'Details', 'deployer-for-git' ); ?></summary><pre><?php echo esc_html( $context_json ); ?></pre></details>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'log_page', '%#%' ),
						'format'    => '',
						'current'   => $current_page,
						'total'     => $total_pages,
						'prev_text' => __( '&laquo; Previous', 'deployer-for-git' ),
						'next_text' => __( 'Next &raquo;', 'deployer-for-git' ),
					)
				)
			);
			?>
		</div></div>
	<?php endif; ?>

	<div class="dfg_log_actions">
		<a class="button button-secondary" href="<?php echo esc_url( $download_url ); ?>"><?php echo esc_html__( 'Download logs', 'deployer-for-git' ); ?></a>
		<button type="button" class="button button-secondary dfg_copy_logs"><?php echo esc_html__( 'Copy visible logs', 'deployer-for-git' ); ?></button>
		<textarea class="screen-reader-text dfg_copy_log_source" readonly><?php echo esc_textarea( implode( "\n", $copy_lines ) ); ?></textarea>

		<form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( 'Permanently clear all retained logs?', 'deployer-for-git' ) ); ?>');">
			<input type="hidden" name="action" value="clear_log_file">
			<?php wp_nonce_field( DFG_SLUG . '_clear_log_file', DFG_SLUG . '_nonce' ); ?>
			<input type="submit" class="button button-secondary dfg_clear_logs" value="<?php echo esc_attr__( 'Clear logs', 'deployer-for-git' ); ?>">
		</form>
	</div>
</div>
