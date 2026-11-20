<?php defined( 'ABSPATH' ) || exit; ?>
<tr>
	<th scope="row"><label for="repository_branch"><?php esc_html_e( 'Branch', 'gitomatic-wp' ); ?></label></th>
	<td>
		<input class="regular-text code" id="repository_branch" name="repository_branch" required aria-describedby="gitomatic-wp-branch-status" autocomplete="off" value="<?php echo esc_attr( $branch_value ); ?>">
		<select id="gitomatic-wp-branch-select" aria-describedby="gitomatic-wp-branch-status" aria-label="<?php esc_attr_e( 'Available branches', 'gitomatic-wp' ); ?>" hidden></select>
		<button type="button" class="button" id="gitomatic-wp-load-branches"><?php esc_html_e( 'Load branches', 'gitomatic-wp' ); ?></button>
		<p class="description" id="gitomatic-wp-branch-status" role="status"><?php esc_html_e( 'Select a branch or type its name.', 'gitomatic-wp' ); ?></p>
	</td>
</tr>
