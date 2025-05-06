<?php

/**
 * Admin sources page template
 *
 * @package SacITCentral
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap sactech-events-admin">
	<h1><?php esc_html_e('Event Sources', 'sac-tech-events'); ?></h1>

	<div class="sactech-events-admin__content">
		<div class="sactech-events-admin__section">
			<h2><?php esc_html_e('Manage Event Sources', 'sac-tech-events'); ?></h2>
			<p><?php esc_html_e('Add, edit and manage sources for event data.', 'sac-tech-events'); ?></p>

			<div class="sactech-events-admin__actions">
				<button id="sactech-events-run-import" class="button button-primary">
					<?php esc_html_e('Run Import Now', 'sac-tech-events'); ?>
				</button>
			</div>

			<table class="widefat sactech-events-admin__table">
				<thead>
					<tr>
						<th><?php esc_html_e('Name', 'sac-tech-events'); ?></th>
						<th><?php esc_html_e('Type', 'sac-tech-events'); ?></th>
						<th><?php esc_html_e('URL', 'sac-tech-events'); ?></th>
						<th><?php esc_html_e('Status', 'sac-tech-events'); ?></th>
						<th><?php esc_html_e('Last Check', 'sac-tech-events'); ?></th>
						<th><?php esc_html_e('Actions', 'sac-tech-events'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($sources)) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e('No sources found. Add your first source below.', 'sac-tech-events'); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ($sources as $source) : ?>
							<tr data-source-id="<?php echo esc_attr($source->id); ?>">
								<td><?php echo esc_html($source->source_name); ?></td>
								<td><?php echo esc_html($source_types[$source->source_type]['name'] ?? $source->source_type); ?></td>
								<td>
									<a href="<?php echo esc_url($source->source_url); ?>" target="_blank">
										<?php echo esc_html($source->source_url); ?>
									</a>
								</td>
								<td>
									<span class="sactech-events-status sactech-events-status--<?php echo esc_attr($source->status); ?>">
										<?php echo esc_html(ucfirst($source->status)); ?>
									</span>
								</td>
								<td>
									<?php
									if ($source->last_check) {
										echo esc_html(human_time_diff(strtotime($source->last_check), current_time('timestamp')) . ' ' . __('ago', 'sac-tech-events'));
									} else {
										esc_html_e('Never', 'sac-tech-events');
									}
									?>
								</td>
								<td>
									<button class="button sactech-events-toggle-source">
										<?php
										if ($source->status === 'active') {
											esc_html_e('Deactivate', 'sac-tech-events');
										} else {
											esc_html_e('Activate', 'sac-tech-events');
										}
										?>
									</button>
									<button class="button sactech-events-delete-source">
										<?php esc_html_e('Delete', 'sac-tech-events'); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="sactech-events-admin__section">
			<h2><?php esc_html_e('Add New Source', 'sac-tech-events'); ?></h2>
			<form id="sactech-events-add-source-form" class="sactech-events-admin__form">
				<div class="sactech-events-admin__form-row">
					<label for="source-name"><?php esc_html_e('Source Name', 'sac-tech-events'); ?></label>
					<input type="text" id="source-name" name="source_name" required>
				</div>

				<div class="sactech-events-admin__form-row">
					<label for="source-type"><?php esc_html_e('Source Type', 'sac-tech-events'); ?></label>
					<select id="source-type" name="source_type" required>
						<option value=""><?php esc_html_e('Select source type', 'sac-tech-events'); ?></option>
						<?php foreach ($source_types as $type_id => $type_data) : ?>
							<option value="<?php echo esc_attr($type_id); ?>">
								<?php echo esc_html($type_data['name']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="sactech-events-admin__form-row">
					<label for="source-url"><?php esc_html_e('Source URL', 'sac-tech-events'); ?></label>
					<input type="url" id="source-url" name="source_url" required>
					<p class="description">
						<?php esc_html_e('The URL for the API endpoint, RSS feed, or calendar feed.', 'sac-tech-events'); ?>
					</p>
				</div>

				<div class="sactech-events-admin__form-actions">
					<button type="submit" class="button button-primary">
						<?php esc_html_e('Add Source', 'sac-tech-events'); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>