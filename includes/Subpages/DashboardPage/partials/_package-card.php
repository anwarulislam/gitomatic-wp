<?php
use GitomaticWP\ApiRequests\PackageUpdate;
use GitomaticWP\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$id = \GitomaticWP\DataManager::instance_id( $package_info );
$last = get_option( 'gitomatic_wp_result_' . md5( $package_type . '/' . $id ) );
$live = get_option( 'gitomatic_wp_live_' . md5( $package_type . '/' . $package_info['slug'] ) );
$endpoint_url      = PackageUpdate::package_update_url( $id, $package_type );
$provider_logo_url = plugin_dir_url( GITOMATIC_WP_FILE ) . 'assets/img/' . $package_info['provider'] . '-logo.svg';
$webhook_id        = 'gitomatic-wp-webhook-' . $package_type . '-' . sanitize_html_class( $id );
$status_id         = 'gitomatic-wp-status-' . $package_type . '-' . sanitize_html_class( $id );
$package_label     = ( 'theme' === $package_type ) ? __( 'theme', 'gitomatic-wp' ) : __( 'plugin', 'gitomatic-wp' );
$deploy_label      = __( 'Deploy now', 'gitomatic-wp' );
$edit_aria_label   = sprintf(
	/* translators: 1: Package type, 2: Package slug. */
	__( 'Edit %1$s %2$s', 'gitomatic-wp' ),
	$package_label,
	$package_info['slug']
);
$unlink_aria_label = sprintf(
	/* translators: 1: Package type, 2: Package slug. */
	__( 'Disconnect %1$s %2$s from Gitomatic WP', 'gitomatic-wp' ),
	$package_label,
	$package_info['slug']
);
?>

<article class="gitomatic_wp_package_box" data-folder="<?php echo esc_attr( $package_type . '/' . $package_info['slug'] ); ?>">
	<div class="gitomatic_wp_package_card">
		<header class="gitomatic_wp_package_card_header">
			<img class="gitomatic_wp_provider_logo" src="<?php echo esc_url( $provider_logo_url ); ?>" alt="" aria-hidden="true">
			<div class="gitomatic_wp_package_identity">
				<h3><?php echo esc_html( $package_info['slug'] ); ?></h3>
				<div class="gitomatic_wp_package_badges">
					<span class="gitomatic_wp_badge"><?php echo esc_html( ucfirst( $package_type ) ); ?></span>
					<span <?php echo $live === $package_info['branch'] ? '' : 'hidden'; ?> class="gitomatic_wp_badge gitomatic_wp_badge_success"><?php esc_html_e( 'Currently deployed', 'gitomatic-wp' ); ?></span>
				</div>
			</div>
		</header>

		<dl class="gitomatic_wp_package_metadata">
			<div class="gitomatic-wp-last-deploy" <?php echo $last ? '' : 'hidden'; ?>><dt><?php esc_html_e( 'Last deploy', 'gitomatic-wp' ); ?></dt>
				<dd><?php if ( $last ) { echo esc_html( ( $last['success'] ? __( 'Succeeded', 'gitomatic-wp' ) : __( 'Failed', 'gitomatic-wp' ) ) . ' · ' . get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $last['time'] ) ) ); } ?></dd></div>
			<div>
				<dt><?php echo esc_html__( 'Branch', 'gitomatic-wp' ); ?></dt>
				<dd><code class="gitomatic_wp_package_box_branch"><?php echo esc_html( $package_info['branch'] ); ?></code></dd>
			</div>
			<div>
				<dt><?php echo esc_html__( 'Repository', 'gitomatic-wp' ); ?></dt>
				<dd>
					<a class="gitomatic_wp_repository_link" href="<?php echo esc_url( $package_info['repo_url'] ); ?>" target="_blank" rel="noopener noreferrer">
						<span><?php echo esc_html( $package_info['repo_url'] ); ?></span>
						<span class="dashicons dashicons-external" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php echo esc_html__( '(opens in a new tab)', 'gitomatic-wp' ); ?></span>
					</a>
				</dd>
			</div>
		</dl>

		<div class="gitomatic_wp_webhook">
			<button type="button" class="gitomatic_wp_webhook_toggle" data-show-ptd-btn aria-expanded="false" aria-controls="<?php echo esc_attr( $webhook_id ); ?>">
				<span class="dashicons dashicons-rest-api" aria-hidden="true"></span>
				<span class="text"><?php echo esc_html__( 'Show webhook URL', 'gitomatic-wp' ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2 gitomatic_wp_webhook_chevron" aria-hidden="true"></span>
			</button>
			<div id="<?php echo esc_attr( $webhook_id ); ?>" class="gitomatic_wp_package_box_action" hidden>
				<label class="screen-reader-text" for="<?php echo esc_attr( $webhook_id ); ?>-url">
					<?php echo esc_html__( 'Push-to-deploy webhook URL', 'gitomatic-wp' ); ?>
				</label>
				<input id="<?php echo esc_attr( $webhook_id ); ?>-url" type="url" readonly value="<?php echo esc_url( $endpoint_url ); ?>">
				<button type="button" data-copy-url-btn="<?php echo esc_url( $endpoint_url ); ?>" class="button button-small">
					<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
					<span class="text"><?php echo esc_html__( 'Copy URL', 'gitomatic-wp' ); ?></span>
				</button>
			</div>
		</div>

		<p id="<?php echo esc_attr( $status_id ); ?>" class="gitomatic_wp_deploy_status" role="status" aria-live="polite"></p>

		<footer class="gitomatic_wp_package_box_buttons">
			<div class="gitomatic_wp_package_primary_actions">
				<button
					type="button"
					data-package-type="<?php echo esc_attr( $package_type ); ?>"
					data-trigger-ptd-btn="<?php echo esc_url( $endpoint_url ); ?>"
					aria-describedby="<?php echo esc_attr( $status_id ); ?>"
					class="button button-primary update-package-btn"
				>
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<span class="text"><?php echo esc_html( $deploy_label ); ?></span>
				</button>
				<a
					href="<?php echo esc_url( Helper::edit_package_url( $id, $package_type ) ); ?>"
					aria-label="<?php echo esc_attr( $edit_aria_label ); ?>"
					class="button edit-package-btn"
				>
					<span class="dashicons dashicons-edit" aria-hidden="true"></span>
					<?php echo esc_html__( 'Edit', 'gitomatic-wp' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( add_query_arg( array( 'source_id' => $id, 'package_type' => $package_type ), Helper::install_plugin_url() ) ); ?>"><?php esc_html_e( 'Add another branch', 'gitomatic-wp' ); ?></a>
			</div>

			<button
				type="button"
				data-unlink-btn
				data-unlink-slug="<?php echo esc_attr( $id ); ?>"
				data-unlink-type="<?php echo esc_attr( $package_type ); ?>"
				aria-label="<?php echo esc_attr( $unlink_aria_label ); ?>"
				class="button gitomatic_wp_unlink_button"
			>
				<span class="dashicons dashicons-editor-unlink" aria-hidden="true"></span>
				<?php echo esc_html__( 'Disconnect', 'gitomatic-wp' ); ?>
			</button>
		</footer>
	</div>
</article>
