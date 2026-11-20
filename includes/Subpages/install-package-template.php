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
	<a href="<?php echo esc_url( Helper::dashboard_url() ); ?>">&larr; <?php esc_html_e( 'Deployments', 'gitomatic-wp' ); ?></a>
	<h1><?php echo $source ? esc_html__( 'Add another branch', 'gitomatic-wp' ) : esc_html__( 'Add deployment', 'gitomatic-wp' ); ?></h1>
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
				<p><?php echo esc_html( $source['repo_url'] ); ?></p>
			<?php else : ?>
				<label for="repository_url"><?php esc_html_e( 'Repository URL', 'gitomatic-wp' ); ?></label>
				<input class="regular-text" type="url" id="repository_url" name="repository_url" required placeholder="https://github.com/owner/repository" value="<?php echo esc_attr( wp_unslash( $_POST['repository_url'] ?? '' ) ); ?>">
				<label for="provider_type"><?php esc_html_e( 'Git provider', 'gitomatic-wp' ); ?></label>
				<select id="provider_type" name="provider_type" required><option value="">—</option>
					<?php foreach ( Helper::available_providers() as $key => $name ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $_POST['provider_type'] ?? '', $key ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="package_type"><?php esc_html_e( 'Package type', 'gitomatic-wp' ); ?></label>
				<select name="package_type" id="package_type"><option value="plugin" <?php selected( $type, 'plugin' ); ?>>Plugin</option><option value="theme" <?php selected( $type, 'theme' ); ?>>Theme</option></select>
				<p><label><input type="checkbox" name="is_private_repository" <?php checked( isset( $_POST['is_private_repository'] ) ); ?>> <?php esc_html_e( 'Private repository', 'gitomatic-wp' ); ?></label></p>
				<div id="gitomatic-wp-credentials">
					<label data-auth="bitbucket"><?php esc_html_e( 'Bitbucket email', 'gitomatic-wp' ); ?> <input name="username" autocomplete="off"></label>
					<label data-auth="bitbucket"><?php esc_html_e( 'Bitbucket API token', 'gitomatic-wp' ); ?> <input name="password" type="password" autocomplete="new-password"></label>
					<label data-auth="token"><?php esc_html_e( 'Access token', 'gitomatic-wp' ); ?> <input name="access_token" type="password" autocomplete="new-password"></label>
				</div>
			<?php endif; ?>
			<?php include __DIR__ . '/branch-field.php'; ?>
			<?php if ( $source ) : ?>
				<fieldset><legend><?php esc_html_e( 'Where should this branch deploy?', 'gitomatic-wp' ); ?></legend>
					<label><input type="radio" name="destination_mode" value="shared" <?php checked( 'copy' !== ( $_POST['destination_mode'] ?? '' ) ); ?>> <?php echo esc_html( sprintf( __( 'Use the same installation (%s)', 'gitomatic-wp' ), $source['slug'] ) ); ?></label>
					<p class="description"><?php esc_html_e( 'Each deploy replaces the files in this folder. An active installation stays selected.', 'gitomatic-wp' ); ?></p>
					<label><input type="radio" name="destination_mode" value="copy" <?php checked( 'copy' === ( $_POST['destination_mode'] ?? '' ) ); ?>> <?php esc_html_e( 'Install a separate copy', 'gitomatic-wp' ); ?></label>
				</fieldset>
			<?php else : ?>
				<label><input type="checkbox" name="use_existing" <?php checked( isset( $_POST['use_existing'] ) ); ?>> <?php esc_html_e( 'Deploy into an already installed plugin or theme', 'gitomatic-wp' ); ?></label>
				<div id="gitomatic-wp-existing">
					<?php foreach ( array( 'plugin', 'theme' ) as $kind ) : ?>
						<label data-existing="<?php echo esc_attr( $kind ); ?>"><?php esc_html_e( 'Existing installation', 'gitomatic-wp' ); ?>
							<select name="<?php echo esc_attr( 'existing_' . $kind ); ?>"><option value="">—</option>
							<?php foreach ( \GitomaticWP\Deployments::installed_folders( $kind ) as $installed ) : ?>
								<option value="<?php echo esc_attr( $installed ); ?>" <?php selected( $_POST[ 'existing_' . $kind ] ?? '', $installed ); ?>><?php echo esc_html( $installed ); ?></option>
							<?php endforeach; ?>
							</select>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Deploying replaces the files in the selected installation.', 'gitomatic-wp' ); ?></p>
				</div>
			<?php endif; ?>
			<div id="gitomatic-wp-folder"><label for="folder"><?php esc_html_e( 'Destination folder', 'gitomatic-wp' ); ?></label>
				<input id="folder" name="folder" class="regular-text code" data-base="<?php echo esc_attr( $source['slug'] ?? '' ); ?>" value="<?php echo esc_attr( wp_unslash( $_POST['folder'] ?? '' ) ); ?>">
			</div>
			<p class="description"><output id="gitomatic-wp-path" aria-live="polite"></output></p>
			<?php submit_button( __( 'Deploy branch', 'gitomatic-wp' ) ); ?>
		</form>
	<?php endif; ?>
</div>
