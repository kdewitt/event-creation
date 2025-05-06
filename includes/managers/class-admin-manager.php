<?php

/**
 * Admin Manager
 *
 * @package SacITCentral
 */

namespace SacTech_Events\Managers;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Admin Manager class
 */
class Admin_Manager extends Manager {
	/**
	 * Constructor
	 */
	public function __construct() {
		// Call parent constructor which will call init_hooks()
		parent::__construct();
	}

	/**
	 * Initialize hooks
	 */
	protected function init_hooks() {
		// Add admin menu
		add_action('admin_menu', array($this, 'add_admin_menu'));

		// Register settings
		add_action('admin_init', array($this, 'register_settings'));

		// Add action links
		add_filter('plugin_action_links_event-creation/event-creation.php', array($this, 'add_action_links'));

		// AJAX handlers for source management
		add_action('wp_ajax_sactech_events_add_source', array($this, 'ajax_add_source'));
		add_action('wp_ajax_sactech_events_delete_source', array($this, 'ajax_delete_source'));
		add_action('wp_ajax_sactech_events_toggle_source', array($this, 'ajax_toggle_source'));
		add_action('wp_ajax_sactech_events_run_import', array($this, 'ajax_run_import'));

		// Add admin notices
		add_action('admin_notices', array($this, 'admin_notices'));
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=tribe_events',
			__('Event Sources', 'sac-tech-events'),
			__('Event Sources', 'sac-tech-events'),
			'manage_options',
			'sactech-events-sources',
			array($this, 'render_sources_page')
		);

		add_submenu_page(
			'edit.php?post_type=tribe_events',
			__('Event Creation Settings', 'sac-tech-events'),
			__('Event Creation Settings', 'sac-tech-events'),
			'manage_options',
			'sactech-events-settings',
			array($this, 'render_settings_page')
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		// Import settings
		register_setting('sactech_events_import', 'sactech_events_schedule_frequency');
		register_setting('sactech_events_import', 'sactech_events_max_events_per_import', 'intval');
		register_setting('sactech_events_import', 'sactech_events_min_relevance_score', 'intval');
		register_setting('sactech_events_import', 'sactech_events_default_status');
		register_setting('sactech_events_import', 'sactech_events_auto_publish', 'intval');
		register_setting('sactech_events_import', 'sactech_events_enable_logging', 'intval');

		// Filter settings
		register_setting('sactech_events_filter', 'sactech_events_blacklist_keywords');
		register_setting('sactech_events_filter', 'sactech_events_tech_categories');

		// AI settings
		register_setting('sactech_events_ai', 'sactech_events_openai_api_key');
		register_setting('sactech_events_ai', 'sactech_events_use_ai_for_descriptions', 'intval');
		register_setting('sactech_events_ai', 'sactech_events_use_ai_for_seo', 'intval');
	}

	/**
	 * Add action links
	 *
	 * @param array $links Existing action links
	 * @return array Modified action links
	 */
	public function add_action_links($links) {
		$settings_link = '<a href="' . admin_url('edit.php?post_type=tribe_events&page=sactech-events-settings') . '">' . __('Settings', 'sac-tech-events') . '</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	/**
	 * Render sources page
	 */
	public function render_sources_page() {
		// Enqueue admin scripts and styles
		wp_enqueue_script('sactech-events-admin', SACTECH_EVENTS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SACTECH_EVENTS_VERSION, true);
		wp_enqueue_style('sactech-events-admin', SACTECH_EVENTS_PLUGIN_URL . 'assets/css/admin.css', array(), SACTECH_EVENTS_VERSION);

		// Localize script with ajax url
		wp_localize_script('sactech-events-admin', 'sactechEvents', array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('sactech_events_nonce'),
			'confirmDelete' => __('Are you sure you want to delete this source?', 'sac-tech-events'),
		));

		// Get all sources
		global $wpdb;
		$table_name = $wpdb->prefix . 'sactech_events_sources';
		$sources = $wpdb->get_results("SELECT * FROM $table_name ORDER BY source_name ASC");

		// Get source types
		$source_manager = sactech_events()->sources;
		$source_types = $source_manager->get_source_types();

		// Render the page
		include(SACTECH_EVENTS_PLUGIN_DIR . 'templates/admin/sources-page.php');
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		// Enqueue admin scripts and styles
		wp_enqueue_script('sactech-events-admin', SACTECH_EVENTS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SACTECH_EVENTS_VERSION, true);
		wp_enqueue_style('sactech-events-admin', SACTECH_EVENTS_PLUGIN_URL . 'assets/css/admin.css', array(), SACTECH_EVENTS_VERSION);

		// Render the page
		include(SACTECH_EVENTS_PLUGIN_DIR . 'templates/admin/settings-page.php');
	}

	/**
	 * AJAX handler for adding a source
	 */
	public function ajax_add_source() {
		// Check nonce using the base class method
		if (! $this->verify_nonce($_POST['nonce'], 'sactech_events_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed', 'sac-tech-events')));
		}

		// Check permissions using the base class method
		if (! $this->current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to do this', 'sac-tech-events')));
		}

		// Check required fields
		if (empty($_POST['source_name']) || empty($_POST['source_type']) || empty($_POST['source_url'])) {
			wp_send_json_error(array('message' => __('All fields are required', 'sac-tech-events')));
		}

		// Sanitize input using the base class method
		$source_name = $this->sanitize_input($_POST['source_name']);
		$source_type = $this->sanitize_input($_POST['source_type']);
		$source_url = $this->sanitize_input($_POST['source_url'], 'url');

		// Validate source type
		$source_manager = $this->plugin->sources;
		$valid_types = array_keys($source_manager->get_source_types());

		if (! in_array($source_type, $valid_types)) {
			wp_send_json_error(array('message' => __('Invalid source type', 'sac-tech-events')));
		}

		// Validate URL
		if (! filter_var($source_url, FILTER_VALIDATE_URL)) {
			wp_send_json_error(array('message' => __('Invalid URL', 'sac-tech-events')));
		}

		// Add source to database
		global $wpdb;
		$table_name = $wpdb->prefix . 'sactech_events_sources';

		$result = $wpdb->insert(
			$table_name,
			array(
				'source_name' => $source_name,
				'source_type' => $source_type,
				'source_url' => $source_url,
				'status' => 'active',
				'created_at' => current_time('mysql'),
			),
			array('%s', '%s', '%s', '%s', '%s')
		);

		if ($result === false) {
			// Log error using base class method
			$this->log('Failed to add source: ' . $wpdb->last_error, 'error');
			wp_send_json_error(array('message' => __('Failed to add source', 'sac-tech-events')));
		}

		// Get the new source ID
		$source_id = $wpdb->insert_id;

		// Log success using base class method
		$this->log(sprintf('Source added: %s (ID: %d)', $source_name, $source_id), 'info');

		// Return success
		wp_send_json_success(array(
			'message' => __('Source added successfully', 'sac-tech-events'),
			'source' => array(
				'id' => $source_id,
				'source_name' => $source_name,
				'source_type' => $source_type,
				'source_url' => $source_url,
				'status' => 'active',
				'last_check' => __('Never', 'sac-tech-events'),
				'created_at' => current_time('mysql'),
			),
		));
	}

	/**
	 * AJAX handler for deleting a source
	 */
	public function ajax_delete_source() {
		// Check nonce
		if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'sactech_events_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed', 'sac-tech-events')));
		}

		// Check permissions
		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to do this', 'sac-tech-events')));
		}

		// Check source ID
		if (empty($_POST['source_id'])) {
			wp_send_json_error(array('message' => __('Source ID is required', 'sac-tech-events')));
		}

		// Sanitize input
		$source_id = intval($_POST['source_id']);

		// Delete source from database
		global $wpdb;
		$table_name = $wpdb->prefix . 'sactech_events_sources';

		$result = $wpdb->delete(
			$table_name,
			array('id' => $source_id),
			array('%d')
		);

		if ($result === false) {
			wp_send_json_error(array('message' => __('Failed to delete source', 'sac-tech-events')));
		}

		// Return success
		wp_send_json_success(array(
			'message' => __('Source deleted successfully', 'sac-tech-events'),
		));
	}

	/**
	 * AJAX handler for toggling a source status
	 */
	public function ajax_toggle_source() {
		// Check nonce
		if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'sactech_events_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed', 'sac-tech-events')));
		}

		// Check permissions
		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to do this', 'sac-tech-events')));
		}

		// Check source ID
		if (empty($_POST['source_id'])) {
			wp_send_json_error(array('message' => __('Source ID is required', 'sac-tech-events')));
		}

		// Sanitize input
		$source_id = intval($_POST['source_id']);

		// Get current status
		global $wpdb;
		$table_name = $wpdb->prefix . 'sactech_events_sources';

		$current_status = $wpdb->get_var($wpdb->prepare(
			"SELECT status FROM $table_name WHERE id = %d",
			$source_id
		));

		if ($current_status === null) {
			wp_send_json_error(array('message' => __('Source not found', 'sac-tech-events')));
		}

		// Toggle status
		$new_status = ($current_status === 'active') ? 'inactive' : 'active';

		// Update status in database
		$result = $wpdb->update(
			$table_name,
			array('status' => $new_status),
			array('id' => $source_id),
			array('%s'),
			array('%d')
		);

		if ($result === false) {
			wp_send_json_error(array('message' => __('Failed to update source status', 'sac-tech-events')));
		}

		// Return success
		wp_send_json_success(array(
			'message' => sprintf(
				__('Source status updated to %s', 'sac-tech-events'),
				$new_status === 'active' ? __('active', 'sac-tech-events') : __('inactive', 'sac-tech-events')
			),
			'new_status' => $new_status,
		));
	}

	/**
	 * AJAX handler for running import manually
	 */
	public function ajax_run_import() {
		// Check nonce
		if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'sactech_events_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed', 'sac-tech-events')));
		}

		// Check permissions
		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to do this', 'sac-tech-events')));
		}

		// Run import
		try {
			// Get events manager
			$events_manager = sactech_events()->events;

			// Start import in the background
			wp_schedule_single_event(time(), 'sactech_events_import');

			// Log using base class method
			$this->log('Manual import initiated by admin', 'info');

			// Return success
			wp_send_json_success(array(
				'message' => __('Import process started in the background', 'sac-tech-events'),
			));
		} catch (\Exception $e) {
			// Handle error using base class method
			$this->handle_error($e, true, false);
			wp_send_json_error(array('message' => $e->getMessage()));
		}
	}

	/**
	 * Display admin notices
	 */
	public function admin_notices() {
		// Check if log directory is writable
		$log_dir = SACTECH_EVENTS_PLUGIN_DIR . 'logs';
		if (! is_writable($log_dir) && get_option('sactech_events_enable_logging', 1)) {
?>
			<div class="notice notice-warning">
				<p><?php esc_html_e('Sacramento Tech Event Creation: Log directory is not writable. Some features may not work correctly.', 'sac-tech-events'); ?></p>
			</div>
			<?php
		}

		// Check if AI settings are configured
		if (get_option('sactech_events_use_ai_for_descriptions', 1) || get_option('sactech_events_use_ai_for_seo', 1)) {
			$api_key = get_option('sactech_events_openai_api_key', '');
			if (empty($api_key)) {
			?>
				<div class="notice notice-warning">
					<p><?php esc_html_e('Sacramento Tech Event Creation: AI features are enabled but API key is not set. Please configure the API key in the settings.', 'sac-tech-events'); ?></p>
				</div>
<?php
			}
		}
	}
}
