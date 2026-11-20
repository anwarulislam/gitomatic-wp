<?php
use GitomaticWP\Helper;
defined( 'ABSPATH' ) || exit;
$packages = array( 'theme' => $data_manager->get_theme_list(), 'plugin' => $data_manager->get_plugin_list() );
?>
<div class="wrap gitomatic_wp_dashboard">
	<div class="gitomatic_wp_dashboard_header"><h1><?php esc_html_e( 'Deployments', 'gitomatic-wp' ); ?></h1>
		<a href="<?php echo esc_url( Helper::install_plugin_url() ); ?>" class="button button-primary"><?php esc_html_e( 'Add deployment', 'gitomatic-wp' ); ?></a>
	</div>
	<?php if ( ! $packages['plugin'] && ! $packages['theme'] ) : ?>
		<p><?php esc_html_e( 'Add a deployment to connect a plugin or theme to its Git branch.', 'gitomatic-wp' ); ?></p>
	<?php endif; ?>
	<div class="gitomatic_wp_package_boxes">
	<?php foreach ( $packages as $package_type => $list ) : ?>
		<?php foreach ( $list as $package_info ) : ?>
			<?php include __DIR__ . '/partials/_package-card.php'; ?>
		<?php endforeach; ?>
	<?php endforeach; ?>
	</div>
</div>
