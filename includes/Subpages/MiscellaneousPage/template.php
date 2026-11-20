<?php
use GitomaticWP\Admin;
use GitomaticWP\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1>
		<?php echo esc_attr__( 'Miscellaneous', 'gitomatic-wp' ); ?>
	</h1>

	<?php
	if ( isset( $regenerate_secret_key_result ) && $regenerate_secret_key_result !== null ) {
		if ( $regenerate_secret_key_result === false ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_attr__( 'Error while regenerating secret key.', 'gitomatic-wp' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_attr__( 'Secret key has been successfully regenerated.', 'gitomatic-wp' ) . '</p></div>';
		}
	}
	?>

	<?php
	if ( isset( $flush_cache_result ) && $flush_cache_result !== null ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_attr__( 'Cache setting has been updated.', 'gitomatic-wp' ) . '</p></div>';
	}
	?>

	<?php
	if ( isset( $alert_notification_result ) && $alert_notification_result !== null ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_attr__( 'Alert notification setting has been updated.', 'gitomatic-wp' ) . '</p></div>';
	}
	?>

	<div class="gitomatic_wp_form_box">
		<h3><span class="dashicons dashicons-admin-network"></span> <?php echo esc_attr__( 'Secret Key management', 'gitomatic-wp' ); ?></h3>

		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					<?php echo esc_attr__( 'Current secret key:', 'gitomatic-wp' ); ?>
				</th>
				<td>
					<span><?php echo esc_attr( Helper::get_api_secret() ); ?></span>
				</td>
			</tr>
		</table>

		<form method="post" action="" onsubmit="return confirm( '<?php echo esc_attr__( 'Are you sure?', 'gitomatic-wp' ); ?>' );">
			<input type="hidden" name="action" value="regenerate_secret_key">
			<?php wp_nonce_field( GITOMATIC_WP_SLUG . '_regenerate_secret_key', GITOMATIC_WP_SLUG . '_nonce' ); ?>

			<input type="submit" class="button button-primary" value="<?php echo esc_attr__( 'Regenerate Secret Key', 'gitomatic-wp' ); ?>">
		</form>
	</div>

	<div class="gitomatic_wp_form_box">
		<h3><span class="dashicons dashicons-database"></span> <?php echo esc_attr__( 'Flush cache', 'gitomatic-wp' ); ?></h3>

		<p class="description">
			<?php echo esc_attr__( 'Activate this option if you want the plugin to clear the cache every time the package update link is triggered. We support next list of plugins:', 'gitomatic-wp' ); ?>
		<ul>
			<li>
				WP Rocket |
				<?php if ( Helper::wp_rocket_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'gitomatic-wp' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'gitomatic-wp' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>
				WP-Optimize |
				<?php if ( Helper::wp_optimize_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'gitomatic-wp' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'gitomatic-wp' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>W3 Total Cache |
				<?php if ( Helper::w3tc_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'gitomatic-wp' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'gitomatic-wp' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>
				LiteSpeed Cache |
				<?php if ( Helper::litespeed_cache_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'gitomatic-wp' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'gitomatic-wp' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>
				WP Super Cache |
				<?php if ( Helper::wp_super_cache_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'gitomatic-wp' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'gitomatic-wp' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>
				WP Fastest Cache |
				<?php if ( Helper::wp_fastest_cache_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'gitomatic-wp' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'gitomatic-wp' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>
				Autoptimize |
				<?php if ( Helper::autoptimize_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'gitomatic-wp' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'gitomatic-wp' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>
				Breeze |
				<?php if ( Helper::breeze_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'gitomatic-wp' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'gitomatic-wp' ); ?>
					</span>
				<?php endif; ?>
			</li>
		</ul>
		</p>

		<form method="post" action="">
			<input type="hidden" name="action" value="flush_cache">
			<?php wp_nonce_field( GITOMATIC_WP_SLUG . '_flush_cache', GITOMATIC_WP_SLUG . '_nonce' ); ?>

			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<?php echo esc_attr__( 'Enable', 'gitomatic-wp' ); ?>
					</th>
					<td>
						<input type="checkbox" name="flush_cache_setting" <?php echo $data_manager->get_flush_cache_setting() === true ? 'checked' : ''; ?> value="1">
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" class="button-primary" value="<?php echo esc_attr__( 'Save Caching Settings', 'gitomatic-wp' ); ?>" />
			</p>
		</form>
	</div>

	<div class="gitomatic_wp_form_box gitomatic_wp_alert_settings">
		<h3><span class="dashicons dashicons-warning"></span> <?php echo esc_html__( 'Admin deployment warning', 'gitomatic-wp' ); ?></h3>

		<p class="description">
			<?php echo esc_html__( 'Remind administrators that connected plugin and theme code should be changed through Git.', 'gitomatic-wp' ); ?>
		</p>

		<form class="gitomatic_wp_alert_settings_form" method="post" action="">
			<input type="hidden" name="action" value="alert_notification">
			<?php wp_nonce_field( GITOMATIC_WP_SLUG . '_alert_notification', GITOMATIC_WP_SLUG . '_nonce' ); ?>

			<label class="gitomatic_wp_checkbox_setting" for="gitomatic-wp-alert-notification-setting">
				<input
					id="gitomatic-wp-alert-notification-setting"
					type="checkbox"
					name="alert_notification_setting"
					value="1"
					<?php checked( $data_manager->get_alert_notification_setting() ); ?>
				>
				<span>
					<strong><?php echo esc_html__( 'Show this warning throughout WordPress admin', 'gitomatic-wp' ); ?></strong>
					<span class="description"><?php echo esc_html__( 'The warning is shown only to administrators who can manage this site.', 'gitomatic-wp' ); ?></span>
				</span>
			</label>

			<div class="gitomatic_wp_notice_preview">
				<span class="gitomatic_wp_notice_preview_label"><?php echo esc_html__( 'Preview', 'gitomatic-wp' ); ?></span>
				<div class="notice notice-warning gitomatic_wp_deployment_notice inline">
					<p><?php echo wp_kses_post( Admin::get_alert_notification_message( Helper::dashboard_url() ) ); ?></p>
				</div>
			</div>

			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php echo esc_attr__( 'Save changes', 'gitomatic-wp' ); ?>" />
			</p>
		</form>
	</div>

</div>
