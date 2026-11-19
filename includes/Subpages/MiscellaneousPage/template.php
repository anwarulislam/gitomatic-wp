<?php
use DeployerForGit\Admin;
use DeployerForGit\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1>
		<?php echo esc_attr__( 'Miscellaneous', 'deployer-for-git' ); ?>
	</h1>

	<?php
	if ( isset( $regenerate_secret_key_result ) && $regenerate_secret_key_result !== null ) {
		if ( $regenerate_secret_key_result === false ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_attr__( 'Error while regenerating secret key.', 'deployer-for-git' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_attr__( 'Secret key has been successfully regenerated.', 'deployer-for-git' ) . '</p></div>';
		}
	}
	?>

	<?php
	if ( isset( $flush_cache_result ) && $flush_cache_result !== null ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_attr__( 'Cache setting has been updated.', 'deployer-for-git' ) . '</p></div>';
	}
	?>

	<?php
	if ( isset( $alert_notification_result ) && $alert_notification_result !== null ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_attr__( 'Alert notification setting has been updated.', 'deployer-for-git' ) . '</p></div>';
	}
	?>

	<div class="dfg_form_box">
		<h3><span class="dashicons dashicons-admin-network"></span> <?php echo esc_attr__( 'Secret Key management', 'deployer-for-git' ); ?></h3>

		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					<?php echo esc_attr__( 'Current secret key:', 'deployer-for-git' ); ?>
				</th>
				<td>
					<span><?php echo esc_attr( Helper::get_api_secret() ); ?></span>
				</td>
			</tr>
		</table>

		<form method="post" action="" onsubmit="return confirm( '<?php echo esc_attr__( 'Are you sure?', 'deployer-for-git' ); ?>' );">
			<input type="hidden" name="action" value="regenerate_secret_key">
			<?php wp_nonce_field( DFG_SLUG . '_regenerate_secret_key', DFG_SLUG . '_nonce' ); ?>

			<input type="submit" class="button button-primary" value="<?php echo esc_attr__( 'Regenerate Secret Key', 'deployer-for-git' ); ?>">
		</form>
	</div>

	<div class="dfg_form_box">
		<h3><span class="dashicons dashicons-database"></span> <?php echo esc_attr__( 'Flush cache', 'deployer-for-git' ); ?></h3>

		<p class="description">
			<?php echo esc_attr__( 'Activate this option if you want the plugin to clear the cache every time the package update link is triggered. We support next list of plugins:', 'deployer-for-git' ); ?>
		<ul>
			<li>
				WP Rocket |
				<?php if ( Helper::wp_rocket_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'deployer-for-git' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'deployer-for-git' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>
				WP-Optimize |
				<?php if ( Helper::wp_optimize_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'deployer-for-git' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'deployer-for-git' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>W3 Total Cache |
				<?php if ( Helper::w3tc_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'deployer-for-git' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'deployer-for-git' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>
				LiteSpeed Cache |
				<?php if ( Helper::litespeed_cache_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'deployer-for-git' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'deployer-for-git' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>
				WP Super Cache |
				<?php if ( Helper::wp_super_cache_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'deployer-for-git' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'deployer-for-git' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>
				WP Fastest Cache |
				<?php if ( Helper::wp_fastest_cache_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'deployer-for-git' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'deployer-for-git' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>
				Autoptimize |
				<?php if ( Helper::autoptimize_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'deployer-for-git' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'deployer-for-git' ); ?>
					</span>
				<?php endif; ?>
			</li>
			<li>
				Breeze |
				<?php if ( Helper::breeze_activated() ) : ?>
					<span class="wp-ui-text-highlight">
						<?php echo esc_attr__( 'Active plugin found', 'deployer-for-git' ); ?>
					</span>
				<?php else : ?>
					<span class="wp-ui-text-notification">
						<?php echo esc_attr__( 'No plugin detected', 'deployer-for-git' ); ?>
					</span>
				<?php endif; ?>
			</li>
		</ul>
		</p>

		<form method="post" action="">
			<input type="hidden" name="action" value="flush_cache">
			<?php wp_nonce_field( DFG_SLUG . '_flush_cache', DFG_SLUG . '_nonce' ); ?>

			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<?php echo esc_attr__( 'Enable', 'deployer-for-git' ); ?>
					</th>
					<td>
						<input type="checkbox" name="flush_cache_setting" <?php echo $data_manager->get_flush_cache_setting() === true ? 'checked' : ''; ?> value="1">
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" class="button-primary" value="<?php echo esc_attr__( 'Save Caching Settings', 'deployer-for-git' ); ?>" />
			</p>
		</form>
	</div>

	<div class="dfg_form_box dfg_alert_settings">
		<h3><span class="dashicons dashicons-warning"></span> <?php echo esc_html__( 'Admin deployment warning', 'deployer-for-git' ); ?></h3>

		<p class="description">
			<?php echo esc_html__( 'Remind administrators that connected plugin and theme code should be changed through Git.', 'deployer-for-git' ); ?>
		</p>

		<form class="dfg_alert_settings_form" method="post" action="">
			<input type="hidden" name="action" value="alert_notification">
			<?php wp_nonce_field( DFG_SLUG . '_alert_notification', DFG_SLUG . '_nonce' ); ?>

			<label class="dfg_checkbox_setting" for="dfg-alert-notification-setting">
				<input
					id="dfg-alert-notification-setting"
					type="checkbox"
					name="alert_notification_setting"
					value="1"
					<?php checked( $data_manager->get_alert_notification_setting() ); ?>
				>
				<span>
					<strong><?php echo esc_html__( 'Show this warning throughout WordPress admin', 'deployer-for-git' ); ?></strong>
					<span class="description"><?php echo esc_html__( 'The warning is shown only to administrators who can manage this site.', 'deployer-for-git' ); ?></span>
				</span>
			</label>

			<div class="dfg_notice_preview">
				<span class="dfg_notice_preview_label"><?php echo esc_html__( 'Preview', 'deployer-for-git' ); ?></span>
				<div class="notice notice-warning dfg_deployment_notice inline">
					<p><?php echo wp_kses_post( Admin::get_alert_notification_message( Helper::dashboard_url() ) ); ?></p>
				</div>
			</div>

			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php echo esc_attr__( 'Save changes', 'deployer-for-git' ); ?>" />
			</p>
		</form>
	</div>

</div>
