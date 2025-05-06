<?php

/**
 * Admin functionality
 *
 * @package SacITCentral
 */

namespace SacTech_Events\Admin;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Admin class
 */
class Admin {
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required admin files
	 */
	private function includes() {
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/admin/class-settings.php';
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/admin/class-events-list.php';
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/admin/class-sources-list.php';
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/admin/class-dashboard.php';
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Admin menu
		add_action('admin_menu', array($this, 'register_admin_menu'));

		// Admin scripts and styles
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

		// Meta boxes for event feedback
		add_action('add_meta_boxes', array($this, 'register_meta_boxes'));

		// AJAX handlers
		add_action('wp_ajax_sactech_events_run_manual_import', array($this, 'handle_manual_import'));
		add_action('wp_ajax_sactech_events_update_event_status', array($this, 'handle_event_status_update'));
		add_action('wp_ajax_sactech_events_test_source', array($this, 'handle_test_source'));
	}

	/**
	 * Register admin menu
	 */
	public function register_admin_menu() {
		add_menu_page(
			__('Sacramento Tech Events', 'sac-tech-events'),
			__('Sac Tech Events', 'sac-tech-events'),
			'manage_options',
			'sactech-events',
			array($this, 'render_dashboard_page'),
			'dashicons-calendar-alt',
			30
		);

		add_submenu_page(
			'sactech-events',
			__('Dashboard', 'sac-tech-events'),
			__('Dashboard', 'sac-tech-events'),
			'manage_options',
			'sactech-events',
			array($this, 'render_dashboard_page')
		);

		add_submenu_page(
			'sactech-events',
			__('Event Sources', 'sac-tech-events'),
			__('Event Sources', 'sac-tech-events'),
			'manage_options',
			'sactech-events-sources',
			array($this, 'render_sources_page')
		);

		add_submenu_page(
			'sactech-events',
			__('Filter Settings', 'sac-tech-events'),
			__('Filter Settings', 'sac-tech-events'),
			'manage_options',
			'sactech-events-filters',
			array($this, 'render_filters_page')
		);

		add_submenu_page(
			'sactech-events',
			__('AI Settings', 'sac-tech-events'),
			__('AI Settings', 'sac-tech-events'),
			'manage_options',
			'sactech-events-ai',
			array($this, 'render_ai_page')
		);

		add_submenu_page(
			'sactech-events',
			__('Settings', 'sac-tech-events'),
			__('Settings', 'sac-tech-events'),
			'manage_options',
			'sactech-events-settings',
			array($this, 'render_settings_page')
		);
	}

	/**
	 * Render dashboard page
	 */
	public function render_dashboard_page() {
		$dashboard = new Dashboard();
		$dashboard->render();
	}

	/**
	 * Render sources page
	 */
	public function render_sources_page() {
		$sources_list = new Sources_List();
		$sources_list->render();
	}

	/**
	 * Render filters page
	 */
	public function render_filters_page() {
		$settings = new Settings();
		$settings->render_filters_page();
	}

	/**
	 * Render AI settings page
	 */
	public function render_ai_page() {
		$settings = new Settings();
		$settings->render_ai_page();
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		$settings = new Settings();
		$settings->render();
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page
	 */
	public function enqueue_admin_assets($hook) {
		// Only load on plugin pages
		if (strpos($hook, 'sactech-events') === false) {
			return;
		}

		// CSS
		wp_enqueue_style(
			'sactech-events-admin',
			SACTECH_EVENTS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SACTECH_EVENTS_VERSION
		);

		// JS
		wp_enqueue_script(
			'sactech-events-admin',
			SACTECH_EVENTS_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery'),
			SACTECH_EVENTS_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'sactech-events-admin',
			'SacTechEventsAdmin',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('sactech-events-admin-nonce'),
				'strings' => array(
					'confirmDelete' => __('Are you sure you want to delete this source?', 'sac-tech-events'),
					'confirmRun' => __('Are you sure you want to run the import now?', 'sac-tech-events'),
					'importing' => __('Importing events...', 'sac-tech-events'),
					'importSuccess' => __('Import completed successfully!', 'sac-tech-events'),
					'importError' => __('Error during import. Please check logs.', 'sac-tech-events'),
				)
			)
		);
	}

	/**
	 * Register meta boxes
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'sactech-events-feedback',
			__('Sacramento Tech Events Feedback', 'sac-tech-events'),
			array($this, 'render_feedback_meta_box'),
			'tribe_events',
			'side',
			'default'
		);
	}

	/**
	 * Render feedback meta box
	 *
	 * @param WP_Post $post Current post object
	 */
	public function render_feedback_meta_box($post) {
		// Check if this is an auto-generated event
		$is_auto_generated = get_post_meta($post->ID, '_sactech_events_auto_generated', true);

		if (! $is_auto_generated) {
			echo '<p>' . esc_html__('This event was not auto-generated by Sacramento Tech Events.', 'sac-tech-events') . '</p>';
			return;
		}

		$source_id = get_post_meta($post->ID, '_sactech_events_source_id', true);
		$source_name = get_post_meta($post->ID, '_sactech_events_source_name', true);
		$source_url = get_post_meta($post->ID, '_sactech_events_source_url', true);
		$relevance_score = get_post_meta($post->ID, '_sactech_events_relevance_score', true);
		$ai_enhanced = get_post_meta($post->ID, '_sactech_events_ai_enhanced', true);

		// Get feedback if any
		global $wpdb;
		$feedback_table = $wpdb->prefix . 'sactech_events_feedback';
		$feedback = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $feedback_table WHERE event_id = %d ORDER BY timestamp DESC LIMIT 1",
			$post->ID
		));

		// Output meta box content
?>
		<div class="sactech-events-meta-box">
			<p>
				<strong><?php esc_html_e('Source:', 'sac-tech-events'); ?></strong>
				<?php if ($source_url) : ?>
					<a href="<?php echo esc_url($source_url); ?>" target="_blank"><?php echo esc_html($source_name); ?></a>
				<?php else : ?>
					<?php echo esc_html($source_name); ?>
				<?php endif; ?>
			</p>

			<p>
				<strong><?php esc_html_e('Relevance Score:', 'sac-tech-events'); ?></strong>
				<?php echo esc_html($relevance_score . '/100'); ?>
			</p>

			<p>
				<strong><?php esc_html_e('AI Enhanced:', 'sac-tech-events'); ?></strong>
				<?php echo $ai_enhanced ? esc_html__('Yes', 'sac-tech-events') : esc_html__('No', 'sac-tech-events'); ?>
			</p>

			<?php if ($feedback) : ?>
				<p>
					<strong><?php esc_html_e('Feedback:', 'sac-tech-events'); ?></strong>
					<?php echo esc_html(ucfirst($feedback->action)); ?>
				</p>

				<?php if ($feedback->notes) : ?>
					<p>
						<strong><?php esc_html_e('Notes:', 'sac-tech-events'); ?></strong>
						<?php echo esc_html($feedback->notes); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<div class="sactech-events-feedback-actions">
				<input type="hidden" name="sactech_events_nonce" value="<?php echo esc_attr(wp_create_nonce('sactech_events_feedback')); ?>">
				<button type="button" class="button sactech-events-approve" data-event-id="<?php echo esc_attr($post->ID); ?>" data-source-id="<?php echo esc_attr($source_id); ?>">
					<?php esc_html_e('Approve', 'sac-tech-events'); ?>
				</button>
				<button type="button" class="button sactech-events-reject" data-event-id="<?php echo esc_attr($post->ID); ?>" data-source-id="<?php echo esc_attr($source_id); ?>">
					<?php esc_html_e('Reject', 'sac-tech-events'); ?>
				</button>
			</div>

			<div class="sactech-events-feedback-notes">
				<textarea name="sactech_events_feedback_notes" placeholder="<?php esc_attr_e('Optional notes for feedback', 'sac-tech-events'); ?>"></textarea>
			</div>
		</div>
<?php
	}

	/**
	 * Handle manual import AJAX request
	 */
	public function handle_manual_import() {
		// Check nonce
		check_ajax_referer('sactech-events-admin-nonce', 'nonce');

		// Check permissions
		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to do this.', 'sac-tech-events')));
		}

		// Get source ID if provided
		$source_id = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;

		try {
			// Run import
			$result = sactech_events()->events->run_import($source_id);

			wp_send_json_success(array(
				'message' => __('Import completed successfully.', 'sac-tech-events'),
				'count' => $result['count'],
				'filtered' => $result['filtered'],
				'created' => $result['created'],
			));
		} catch (\Exception $e) {
			wp_send_json_error(array('message' => $e->getMessage()));
		}
	}

	/**
	 * Handle event status update AJAX request
	 */
	public function handle_event_status_update() {
		// Check nonce
		check_ajax_referer('sactech_events_feedback', 'nonce');

		// Check permissions
		if (! current_user_can('publish_tribe_events')) {
			wp_send_json_error(array('message' => __('You do not have permission to do this.', 'sac-tech-events')));
		}

		// Get variables
		$event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
		$source_id = isset($_POST['source_id']) ? sanitize_text_field($_POST['source_id']) : '';
		$action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
		$notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';

		if (! $event_id || ! $source_id || ! $action) {
			wp_send_json_error(array('message' => __('Missing required parameters.', 'sac-tech-events')));
		}

		try {
			// Add feedback
			$feedback_id = sactech_events()->feedback->add_feedback(
				$event_id,
				$source_id,
				$action,
				get_current_user_id(),
				$notes
			);

			// Update event status if needed
			if ($action === 'approve') {
				wp_update_post(array(
					'ID' => $event_id,
					'post_status' => 'publish',
				));
			} elseif ($action === 'reject') {
				wp_update_post(array(
					'ID' => $event_id,
					'post_status' => 'trash',
				));
			}

			wp_send_json_success(array(
				'message' => __('Feedback recorded successfully.', 'sac-tech-events'),
				'feedback_id' => $feedback_id,
			));
		} catch (\Exception $e) {
			wp_send_json_error(array('message' => $e->getMessage()));
		}
	}

	/**
	 * Handle test source AJAX request
	 */
	public function handle_test_source() {
		// Check nonce
		check_ajax_referer('sactech-events-admin-nonce', 'nonce');

		// Check permissions
		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to do this.', 'sac-tech-events')));
		}

		// Get source data
		$source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : '';
		$source_url = isset($_POST['source_url']) ? esc_url_raw($_POST['source_url']) : '';

		if (! $source_type || ! $source_url) {
			wp_send_json_error(array('message' => __('Missing required parameters.', 'sac-tech-events')));
		}

		try {
			// Test the source
			$result = sactech_events()->sources->test_source($source_type, $source_url);

			wp_send_json_success(array(
				'message' => __('Source tested successfully.', 'sac-tech-events'),
				'events_found' => $result['count'],
				'sample' => $result['sample'],
			));
		} catch (\Exception $e) {
			wp_send_json_error(array('message' => $e->getMessage()));
		}
	}
}
