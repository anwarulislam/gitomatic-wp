<?php
use GitomaticWP\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php
if ( isset( $edit_error ) && is_wp_error( $edit_error ) ) :
	?>
	<div class="wrap">
		<h1><?php echo esc_attr__( 'Edit deployment', 'gitomatic-wp' ); ?></h1>
		<div class="error">
			<p><?php echo esc_attr( $edit_error->get_error_message() ); ?></p>
		</div>
		<p>
			<a href="<?php echo esc_url( Helper::dashboard_url() ); ?>" class="button">
				<?php echo esc_attr__( '&larr; Back to Dashboard', 'gitomatic-wp' ); ?>
			</a>
		</p>
	</div>
	<?php
	return;
endif;
?>

<?php if ( isset( $edit_result ) ) : ?>
	<?php if ( $edit_result === true ) : ?>
		<div class="updated">
			<p><?php echo esc_attr__( 'Deployment settings saved.', 'gitomatic-wp' ); ?></p>
		</div>
	<?php elseif ( is_wp_error( $edit_result ) ) : ?>
		<div class="error">
			<p><?php echo esc_attr( $edit_result->get_error_message() ); ?></p>
		</div>
	<?php endif; ?>
<?php endif; ?>

<div class="wrap">
	<h1><?php echo esc_attr__( 'Edit deployment', 'gitomatic-wp' ); ?></h1>

	<p>
		<a href="<?php echo esc_url( Helper::dashboard_url() ); ?>">
			<?php echo esc_attr__( '&larr; Back to Dashboard', 'gitomatic-wp' ); ?>
		</a>
	</p>

	<table class="form-table">
		<tr valign="top">
			<th scope="row"><?php echo esc_attr__( 'Destination folder', 'gitomatic-wp' ); ?></th>
			<td><code><?php echo esc_attr( $package_data['slug'] ); ?></code></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php echo esc_attr__( 'Type', 'gitomatic-wp' ); ?></th>
			<td><code><?php echo esc_attr( $package_type ); ?></code></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php echo esc_attr__( 'Provider', 'gitomatic-wp' ); ?></th>
			<td><code><?php echo esc_attr( Helper::available_providers()[ $package_data['provider'] ] ); ?></code></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php echo esc_attr__( 'Repository URL', 'gitomatic-wp' ); ?></th>
			<td><code><?php echo esc_attr( $package_data['repo_url'] ); ?></code></td>
		</tr>
	</table>

	<hr>

	<?php
	$existing_options      = isset( $package_data['options'] ) ? $package_data['options'] : array();
	$existing_username     = isset( $existing_options['username'] ) ? $existing_options['username'] : '';
	$provider              = $package_data['provider'];
	?>

	<form method="post" action="">
		<input type="hidden" name="<?php echo esc_attr( GITOMATIC_WP_SLUG . '_edit_package_submitted' ); ?>" value="1">
		<?php wp_nonce_field( GITOMATIC_WP_SLUG . '_edit_package_form', GITOMATIC_WP_SLUG . '_nonce' ); ?>

		<table class="form-table">
			<tr><td colspan="2">
				<input type="hidden" name="source_id" value="<?php echo esc_attr( $package_slug ); ?>">
				<input type="hidden" name="package_type" value="<?php echo esc_attr( $package_type ); ?>">
				<?php $branch_value = $package_data['branch']; include dirname( __DIR__ ) . '/branch-field.php'; ?>
				<p class="description"><?php esc_html_e( 'This changes the branch used on the next deployment to this folder.', 'gitomatic-wp' ); ?></p>
			</td></tr>

			<?php if ( $package_data['is_private_repository'] ) : ?>
				<?php if ( $provider === 'bitbucket' ) : ?>
					<tr valign="top">
						<th scope="row"><?php echo esc_attr__( 'Email address', 'gitomatic-wp' ); ?></th>
						<td><input type="text" name="username" value="<?php echo esc_attr( $existing_username ); ?>"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php echo esc_attr__( 'API Token', 'gitomatic-wp' ); ?></th>
						<td><input type="password" name="password" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Leave blank to keep saved token', 'gitomatic-wp' ); ?>"></td>
					</tr>
				<?php else : ?>
					<input type="hidden" name="username" value="">
					<input type="hidden" name="password" value="">
				<?php endif; ?>

				<?php if ( in_array( $provider, array( 'github', 'gitlab', 'gitea' ), true ) ) : ?>
					<tr valign="top">
						<th scope="row"><?php echo esc_attr__( 'Access Token', 'gitomatic-wp' ); ?></th>
						<td>
							<input class="regular-text code" type="password" name="access_token" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Leave blank to keep saved token', 'gitomatic-wp' ); ?>">
							<?php if ( $provider === 'gitea' ) : ?>
								<p class="description">
									<?php echo esc_attr__( 'You can generate Gitea access token here:', 'gitomatic-wp' ); ?>
									<a href="https://gitea.com/user/settings/applications" target="_blank" rel="nofollow"><?php echo esc_attr__( 'Link', 'gitomatic-wp' ); ?></a>
								</p>
							<?php elseif ( $provider === 'github' ) : ?>
								<p class="description">
									<?php echo esc_attr__( 'You can generate Github access token here:', 'gitomatic-wp' ); ?>
									<a href="https://github.com/settings/tokens" target="_blank" rel="nofollow"><?php echo esc_attr__( 'Link', 'gitomatic-wp' ); ?></a>
								</p>
							<?php elseif ( $provider === 'gitlab' ) : ?>
								<p class="description">
									<?php echo esc_attr__( 'You can generate GitLab access token here ("api" scope required):', 'gitomatic-wp' ); ?>
									<a href="https://gitlab.com/-/user_settings/personal_access_tokens" target="_blank" rel="nofollow"><?php echo esc_attr__( 'Link', 'gitomatic-wp' ); ?></a>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				<?php else : ?>
					<input type="hidden" name="access_token" value="">
				<?php endif; ?>
			<?php endif; ?>
		</table>
		<div class="submit">
			<input
				type="submit"
				class="button-primary"
				value="<?php echo esc_attr__( 'Save changes', 'gitomatic-wp' ); ?>"
			/>
		</div>
	</form>
</div>
