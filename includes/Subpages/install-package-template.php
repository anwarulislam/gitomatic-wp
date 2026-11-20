<?php
use GitomaticWP\Helper;
use GitomaticWP\DataManager;
defined( 'ABSPATH' ) || exit;
$type = sanitize_key( $_POST['package_type'] ?? $_GET['package_type'] ?? $package_type );
$type = 'theme' === $type ? 'theme' : 'plugin';
$source_id = sanitize_text_field( wp_unslash( $_POST['source_id'] ?? $_GET['source_id'] ?? '' ) );
$source = $source_id ? ( new DataManager() )->get_package( $source_id, $type ) : false;
$branch_value = sanitize_text_field( wp_unslash( $_POST['repository_branch'] ?? '' ) );
?>
<div class="wrap gitomatic-wp-editor">
	<h1 class="wp-heading-inline"><?php echo $source ? esc_html__( 'Add another branch', 'gitomatic-wp' ) : esc_html__( 'Add deployment', 'gitomatic-wp' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( Helper::dashboard_url() ); ?>"><?php esc_html_e( 'View deployments', 'gitomatic-wp' ); ?></a>
	<hr class="wp-header-end">
	<?php if ( true === $install_result ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Branch deployed successfully.', 'gitomatic-wp' ); ?> <a href="<?php echo esc_url( Helper::dashboard_url() ); ?>"><?php esc_html_e( 'View deployments', 'gitomatic-wp' ); ?></a></p></div>
	<?php elseif ( $source_id && ! $source ) : ?>
		<p><?php esc_html_e( 'The original deployment is no longer available.', 'gitomatic-wp' ); ?></p>
	<?php else : ?>
		<?php if ( is_wp_error( $install_result ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $install_result->get_error_message() ); ?></p></div>
		<?php endif; ?>
		<form method="post" class="gitomatic-wp-editor-form">
			<?php wp_nonce_field( GITOMATIC_WP_SLUG . '_install_package_form', GITOMATIC_WP_SLUG . '_nonce' ); ?>
			<input type="hidden" name="gitomatic_wp_install_package_submitted" value="1">
			<?php if ( $source ) : ?>
				<input type="hidden" name="source_id" value="<?php echo esc_attr( $source_id ); ?>">
				<input type="hidden" name="package_type" value="<?php echo esc_attr( $type ); ?>">
			<?php endif; ?>
			<h2><?php esc_html_e( 'Repository', 'gitomatic-wp' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php if ( $source ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Repository URL', 'gitomatic-wp' ); ?></th>
						<td><code><?php echo esc_html( $source['repo_url'] ); ?></code></td>
					</tr>
				<?php else : ?>
					<tr>
						<th scope="row"><label for="repository_url"><?php esc_html_e( 'Repository URL', 'gitomatic-wp' ); ?></label></th>
						<td><input class="regular-text code" type="url" id="repository_url" name="repository_url" required placeholder="https://github.com/owner/repository" value="<?php echo esc_attr( wp_unslash( $_POST['repository_url'] ?? '' ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="provider_type"><?php esc_html_e( 'Git provider', 'gitomatic-wp' ); ?></label></th>
						<td><select id="provider_type" name="provider_type" required>
							<option value=""><?php esc_html_e( 'Select a provider', 'gitomatic-wp' ); ?></option>
							<?php foreach ( Helper::available_providers() as $key => $name ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $_POST['provider_type'] ?? '', $key ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Access', 'gitomatic-wp' ); ?></th>
						<td><label><input type="checkbox" name="is_private_repository" <?php checked( isset( $_POST['is_private_repository'] ) ); ?>> <?php esc_html_e( 'Private repository', 'gitomatic-wp' ); ?></label></td>
					</tr>
					<tr data-auth="bitbucket">
						<th scope="row"><label for="gitomatic-wp-username"><?php esc_html_e( 'Bitbucket email', 'gitomatic-wp' ); ?></label></th>
						<td><input class="regular-text" id="gitomatic-wp-username" type="email" name="username" autocomplete="off"></td>
					</tr>
					<tr data-auth="bitbucket">
						<th scope="row"><label for="gitomatic-wp-password"><?php esc_html_e( 'Bitbucket API token', 'gitomatic-wp' ); ?></label></th>
						<td><input class="regular-text" id="gitomatic-wp-password" name="password" type="password" autocomplete="new-password"></td>
					</tr>
					<tr data-auth="token">
						<th scope="row"><label for="gitomatic-wp-token"><?php esc_html_e( 'Access token', 'gitomatic-wp' ); ?></label></th>
						<td><input class="regular-text" id="gitomatic-wp-token" name="access_token" type="password" autocomplete="new-password"></td>
					</tr>
				<?php endif; ?>
				<?php include __DIR__ . '/branch-field.php'; ?>
			</table>
			<h2><?php esc_html_e( 'Destination', 'gitomatic-wp' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php if ( ! $source ) : ?>
					<tr>
						<th scope="row"><label for="package_type"><?php esc_html_e( 'Package type', 'gitomatic-wp' ); ?></label></th>
						<td><select name="package_type" id="package_type">
							<option value="plugin" <?php selected( $type, 'plugin' ); ?>><?php esc_html_e( 'Plugin', 'gitomatic-wp' ); ?></option>
							<option value="theme" <?php selected( $type, 'theme' ); ?>><?php esc_html_e( 'Theme', 'gitomatic-wp' ); ?></option>
						</select></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Installation', 'gitomatic-wp' ); ?></th>
					<td>
						<?php if ( $source ) : ?>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Installation', 'gitomatic-wp' ); ?></legend>
								<label><input type="radio" name="destination_mode" value="shared" <?php checked( 'copy' !== ( $_POST['destination_mode'] ?? '' ) ); ?>> <?php echo esc_html( sprintf( __( 'Use the same installation (%s)', 'gitomatic-wp' ), $source['slug'] ) ); ?></label><br>
								<label><input type="radio" name="destination_mode" value="copy" <?php checked( 'copy' === ( $_POST['destination_mode'] ?? '' ) ); ?>> <?php esc_html_e( 'Install a separate copy', 'gitomatic-wp' ); ?></label>
							</fieldset>
						<?php else : ?>
							<label><input type="checkbox" name="use_existing" <?php checked( isset( $_POST['use_existing'] ) ); ?>> <?php esc_html_e( 'Use an existing installation', 'gitomatic-wp' ); ?></label>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( ! $source ) : ?>
					<?php foreach ( array( 'plugin', 'theme' ) as $kind ) : ?>
						<tr data-existing="<?php echo esc_attr( $kind ); ?>">
							<th scope="row"><label for="<?php echo esc_attr( 'existing_' . $kind ); ?>"><?php echo 'plugin' === $kind ? esc_html__( 'Installed plugin', 'gitomatic-wp' ) : esc_html__( 'Installed theme', 'gitomatic-wp' ); ?></label></th>
							<td><select name="<?php echo esc_attr( 'existing_' . $kind ); ?>" id="<?php echo esc_attr( 'existing_' . $kind ); ?>">
								<option value="">—</option>
								<?php foreach ( \GitomaticWP\Deployments::installed_folders( $kind ) as $installed ) : ?>
									<option value="<?php echo esc_attr( $installed ); ?>" <?php selected( $_POST[ 'existing_' . $kind ] ?? '', $installed ); ?>><?php echo esc_html( $installed ); ?></option>
								<?php endforeach; ?>
							</select></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				<tr id="gitomatic-wp-folder">
					<th scope="row"><label for="folder"><?php esc_html_e( 'Destination folder', 'gitomatic-wp' ); ?></label></th>
					<td><input id="folder" name="folder" class="regular-text code" data-base="<?php echo esc_attr( $source['slug'] ?? '' ); ?>" value="<?php echo esc_attr( wp_unslash( $_POST['folder'] ?? '' ) ); ?>"></td>
				</tr>
				<tr id="gitomatic-wp-path-row" hidden>
					<th scope="row"><?php esc_html_e( 'Deployment path', 'gitomatic-wp' ); ?></th>
					<td>
						<code><output id="gitomatic-wp-path" aria-live="polite"></output></code>
						<p class="description" id="gitomatic-wp-replace-note" hidden><?php esc_html_e( 'Deploying replaces the files in this installation.', 'gitomatic-wp' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Deploy branch', 'gitomatic-wp' ) ); ?>
		</form>
	<?php endif; ?>
</div>
