<?php

/**
 * Admin settings page template
 *
 * @package SacITCentral
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap sactech-events-admin">
	<h1><?php esc_html_e('Event Creation Settings', 'sac-tech-events'); ?></h1>

	<div class="sactech-events-admin__content">
		<form method="post" action="options.php" class="sactech-events-admin__settings-form">
			<div class="nav-tab-wrapper">
				<a href="#import-settings" class="nav-tab nav-tab-active"><?php esc_html_e('Import Settings', 'sac-tech-events'); ?></a>
				<a href="#filter-settings" class="nav-tab"><?php esc_html_e('Filter Settings', 'sac-tech-events'); ?></a>
				<a href="#ai-settings" class="nav-tab"><?php esc_html_e('AI Settings', 'sac-tech-events'); ?></a>
			</div>

			<div id="import-settings" class="sactech-events-admin__tab-content active">
				<?php settings_fields('sactech_events_import'); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="sactech_events_schedule_frequency">
								<?php esc_html_e('Import Frequency', 'sac-tech-events'); ?>
							</label>
						</th>
						<td>
							<select name="sactech_events_schedule_frequency" id="sactech_events_schedule_frequency">
								<option value="hourly" <?php selected(get_option('sactech_events_schedule_frequency'), 'hourly'); ?>>
									<?php esc_html_e('Hourly', 'sac-tech-events'); ?>
								</option>
								<option value="twicedaily" <?php selected(get_option('sactech_events_schedule_frequency'), 'twicedaily'); ?>>
									<?php esc_html_e('Twice Daily', 'sac-tech-events'); ?>
								</option>
								<option value="daily" <?php selected(get_option('sactech_events_schedule_frequency'), 'daily'); ?>>
									<?php esc_html_e('Daily', 'sac-tech-events'); ?>
								</option>
								<option value="weekly" <?php selected(get_option('sactech_events_schedule_frequency'), 'weekly'); ?>>
									<?php esc_html_e('Weekly', 'sac-tech-events'); ?>
								</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="sactech_events_max_events_per_import">
								<?php esc_html_e('Max Events Per Import', 'sac-tech-events'); ?>
							</label>
						</th>
						<td>
							<input type="number" name="sactech_events_max_events_per_import" id="sactech_events_max_events_per_import"
								value="<?php echo esc_attr(get_option('sactech_events_max_events_per_import', 50)); ?>" min="1" max="200">
							<p class="description">
								<?php esc_html_e('Maximum number of events to import in a single run.', 'sac-tech-events'); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="sactech_events_default_status">
								<?php esc_html_e('Default Event Status', 'sac-tech-events'); ?>
							</label>
						</th>
						<td>
							<select name="sactech_events_default_status" id="sactech_events_default_status">
								<option value="draft" <?php selected(get_option('sactech_events_default_status'), 'draft'); ?>>
									<?php esc_html_e('Draft', 'sac-tech-events'); ?>
								</option>
								<option value="publish" <?php selected(get_option('sactech_events_default_status'), 'publish'); ?>>
									<?php esc_html_e('Published', 'sac-tech-events'); ?>
								</option>
								<option value="pending" <?php selected(get_option('sactech_events_default_status'), 'pending'); ?>>
									<?php esc_html_e('Pending Review', 'sac-tech-events'); ?>
								</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e('Auto-Publish High Relevance', 'sac-tech-events'); ?>
						</th>
						<td>
							<label for="sactech_events_auto_publish">
								<input type="checkbox" name="sactech_events_auto_publish" id="sactech_events_auto_publish"
									value="1" <?php checked(get_option('sactech_events_auto_publish'), 1); ?>>
								<?php esc_html_e('Automatically publish events with high relevance scores', 'sac-tech-events'); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e('Enable Logging', 'sac-tech-events'); ?>
						</th>
						<td>
							<label for="sactech_events_enable_logging">
								<input type="checkbox" name="sactech_events_enable_logging" id="sactech_events_enable_logging"
									value="1" <?php checked(get_option('sactech_events_enable_logging'), 1); ?>>
								<?php esc_html_e('Log import process for debugging', 'sac-tech-events'); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<div id="filter-settings" class="sactech-events-admin__tab-content">
				<?php settings_fields('sactech_events_filter'); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="sactech_events_min_relevance_score">
								<?php esc_html_e('Minimum Relevance Score', 'sac-tech-events'); ?>
							</label>
						</th>
						<td>
							<input type="range" name="sactech_events_min_relevance_score" id="sactech_events_min_relevance_score"
								value="<?php echo esc_attr(get_option('sactech_events_min_relevance_score', 50)); ?>"
								min="0" max="100" step="5">
							<span class="sactech-events-range-value">
								<?php echo esc_html(get_option('sactech_events_min_relevance_score', 50)); ?>
							</span>
							<p class="description">
								<?php esc_html_e('Minimum relevance score for events to be imported (0-100).', 'sac-tech-events'); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="sactech_events_blacklist_keywords">
								<?php esc_html_e('Blacklisted Keywords', 'sac-tech-events'); ?>
							</label>
						</th>
						<td>
							<textarea name="sactech_events_blacklist_keywords" id="sactech_events_blacklist_keywords"
								rows="6" class="large-text"><?php echo esc_textarea(get_option('sactech_events_blacklist_keywords', '')); ?></textarea>
							<p class="description">
								<?php esc_html_e('Enter keywords to exclude events, one per line. Events containing these keywords will not be imported.', 'sac-tech-events'); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<div id="ai-settings" class="sactech-events-admin__tab-content">
				<?php settings_fields('sactech_events_ai'); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="sactech_events_openai_api_key">
								<?php esc_html_e('OpenAI API Key', 'sac-tech-events'); ?>
							</label>
						</th>
						<td>
							<input type="password" name="sactech_events_openai_api_key" id="sactech_events_openai_api_key"
								value="<?php echo esc_attr(get_option('sactech_events_openai_api_key', '')); ?>"
								class="regular-text">
							<p class="description">
								<?php esc_html_e('Enter your OpenAI API key for AI-powered features.', 'sac-tech-events'); ?>
								<a href="https://platform.openai.com/api-keys" target="_blank">
									<?php esc_html_e('Get API key', 'sac-tech-events'); ?>
								</a>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e('AI-Powered Descriptions', 'sac-tech-events'); ?>
						</th>
						<td>
							<label for="sactech_events_use_ai_for_descriptions">
								<input type="checkbox" name="sactech_events_use_ai_for_descriptions" id="sactech_events_use_ai_for_descriptions"
									value="1" <?php checked(get_option('sactech_events_use_ai_for_descriptions'), 1); ?>>
								<?php esc_html_e('Use AI to enhance event descriptions', 'sac-tech-events'); ?>
							</label>
							<p class="description">
								<?php esc_html_e('AI will improve event descriptions to make them more engaging and informative.', 'sac-tech-events'); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e('AI-Powered SEO', 'sac-tech-events'); ?>
						</th>
						<td>
							<label for="sactech_events_use_ai_for_seo">
								<input type="checkbox" name="sactech_events_use_ai_for_seo" id="sactech_events_use_ai_for_seo"
									value="1" <?php checked(get_option('sactech_events_use_ai_for_seo'), 1); ?>>
								<?php esc_html_e('Use AI to generate SEO meta titles and descriptions', 'sac-tech-events'); ?>
							</label>
							<p class="description">
								<?php esc_html_e('AI will create optimized SEO titles and descriptions for imported events.', 'sac-tech-events'); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
	</div>
</div>

<script>
	jQuery(document).ready(function($) {
		// Tab functionality
		$('.nav-tab').on('click', function(e) {
			e.preventDefault();

			// Update tabs
			$('.nav-tab').removeClass('nav-tab-active');
			$(this).addClass('nav-tab-active');

			// Update content
			$('.sactech-events-admin__tab-content').removeClass('active');
			$($(this).attr('href')).addClass('active');
		});

		// Range input value display
		$('#sactech_events_min_relevance_score').on('input', function() {
			$(this).next('.sactech-events-range-value').text($(this).val());
		});
	});
</script>