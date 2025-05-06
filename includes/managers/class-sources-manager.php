<?php

/**
 * Sources Manager
 *
 * @package SacITCentral
 */

namespace SacTech_Events\Managers;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Sources Manager class
 */
class Sources_Manager extends Manager {
	/**
	 * Available source types
	 *
	 * @var array
	 */
	private $source_types = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		// Call parent constructor first
		parent::__construct();

		// Register default source types
		$this->register_default_sources();
	}

	/**
	 * Initialize hooks
	 */
	protected function init_hooks() {
		// Allow external sources to be registered
		add_action('init', array($this, 'register_external_sources'), 20);

		// Admin AJAX handlers for source management
		add_action('admin_post_sactech_events_add_source', array($this, 'handle_add_source'));
		add_action('admin_post_sactech_events_edit_source', array($this, 'handle_edit_source'));
		add_action('admin_post_sactech_events_delete_source', array($this, 'handle_delete_source'));

		// Register custom post types and taxonomies
		add_action('init', array($this, 'register_taxonomies'), 10);
	}

	/**
	 * Register custom taxonomies
	 */
	public function register_taxonomies() {
		// Register event source taxonomy
		register_taxonomy(
			'event_source',
			'tribe_events',
			array(
				'label' => __('Event Sources', 'sac-tech-events'),
				'hierarchical' => false,
				'show_ui' => true,
				'show_admin_column' => true,
				'query_var' => true,
				'rewrite' => array('slug' => 'event-source'),
			)
		);
	}

	/**
	 * Register default source types
	 */
	private function register_default_sources() {
		$this->register_source_type('meetup', __('Meetup.com', 'sac-tech-events'), 'Meetup_Source');
		$this->register_source_type('eventbrite', __('Eventbrite', 'sac-tech-events'), 'Eventbrite_Source');
		$this->register_source_type('rss', __('RSS Feed', 'sac-tech-events'), 'RSS_Source');
		$this->register_source_type('ical', __('iCalendar', 'sac-tech-events'), 'ICal_Source');
		$this->register_source_type('json', __('JSON API', 'sac-tech-events'), 'JSON_Source');

		$this->log('Registered default source types: ' . implode(', ', array_keys($this->source_types)), 'debug');
	}

	/**
	 * Register external sources
	 */
	public function register_external_sources() {
		/**
		 * Filter to allow plugins to register additional source types
		 *
		 * @param array $source_types Array of source types
		 */
		$original_count = count($this->source_types);
		$this->source_types = apply_filters('sactech_events_source_types', $this->source_types);
		$new_count = count($this->source_types);

		if ($new_count > $original_count) {
			$this->log(sprintf('External source registration added %d source types', $new_count - $original_count), 'info');
		}
	}

	/**
	 * Register a source type
	 *
	 * @param string $id Source type ID
	 * @param string $name Source type name
	 * @param string $class Source handler class name
	 */
	public function register_source_type($id, $name, $class) {
		$this->source_types[$id] = array(
			'name' => $name,
			'class' => $class,
		);

		$this->log(sprintf('Registered source type: %s (%s)', $name, $id), 'debug');
	}

	/**
	 * Get all registered source types
	 *
	 * @return array Source types
	 */
	public function get_source_types() {
		return $this->source_types;
	}

	/**
	 * Get all active sources
	 *
	 * @return array Active sources
	 */
	public function get_active_sources() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sactech_events_sources';

		$sources = $wpdb->get_results(
			"SELECT * FROM $table_name WHERE status = 'active' ORDER BY source_name ASC"
		);

		$this->log(sprintf('Retrieved %d active sources', count($sources)), 'debug');
		return $sources;
	}

	/**
	 * Create a source instance
	 *
	 * @param object $source Source data from database
	 * @return Source_Interface|null Source instance or null on failure
	 */
	public function create_source_instance($source) {
		// Check if source type is registered
		if (! isset($this->source_types[$source->source_type])) {
			$this->log(sprintf('Failed to create source instance: unknown source type "%s"', $source->source_type), 'error');
			return null;
		}

		// Get class name
		$class_name = $this->source_types[$source->source_type]['class'];

		// Add namespace
		$full_class_name = 'SacTech_Events\\Sources\\' . $class_name;

		// Check if class exists
		if (! class_exists($full_class_name)) {
			$this->log(sprintf('Failed to create source instance: class "%s" not found', $full_class_name), 'error');
			return null;
		}

		// Create instance
		try {
			$instance = new $full_class_name($source);
			$this->log(sprintf('Created source instance for "%s" (%s)', $source->source_name, $source->source_type), 'debug');
			return $instance;
		} catch (\Exception $e) {
			$this->handle_error($e, true, false);
			return null;
		}
	}

	/**
	 * Fetch events from all active sources
	 *
	 * @param int $limit Maximum number of events to fetch
	 * @return array Array of events from all sources
	 */
	public function fetch_all_events($limit = 50) {
		$all_events = array();

		// Get all active sources
		$sources = $this->get_active_sources();

		if (empty($sources)) {
			$this->log('No active sources found to fetch events from', 'info');
			return $all_events;
		}

		// Calculate events per source
		$events_per_source = max(5, intval($limit / max(1, count($sources))));
		$this->log(sprintf('Fetching up to %d events per source', $events_per_source), 'debug');

		// Loop through sources
		foreach ($sources as $source) {
			try {
				$this->log(sprintf('Fetching events from source: %s (%s)', $source->source_name, $source->source_type), 'info');

				// Create source instance
				$source_instance = $this->create_source_instance($source);

				if (! $source_instance) {
					continue;
				}

				// Fetch events from source
				$events = $source_instance->fetch_events($events_per_source);
				$this->log(sprintf('Fetched %d events from %s', count($events), $source->source_name), 'info');

				// Add source info to each event
				foreach ($events as &$event) {
					$event['source'] = $source;
				}

				// Add to all events
				$all_events = array_merge($all_events, $events);

				// Update last check time
				$this->update_source_last_check($source->id);
			} catch (\Exception $e) {
				$this->handle_error($e, true, false);
			}
		}

		// Limit total events
		if (count($all_events) > $limit) {
			$all_events = array_slice($all_events, 0, $limit);
			$this->log(sprintf('Limited events to %d as requested', $limit), 'debug');
		}

		$this->log(sprintf('Fetched a total of %d events from all sources', count($all_events)), 'info');
		return $all_events;
	}

	/**
	 * Update source last check time
	 *
	 * @param int $source_id Source ID
	 */
	private function update_source_last_check($source_id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sactech_events_sources';

		$result = $wpdb->update(
			$table_name,
			array('last_check' => current_time('mysql')),
			array('id' => $source_id),
			array('%s'),
			array('%d')
		);

		if ($result !== false) {
			$this->log(sprintf('Updated last check time for source ID %d', $source_id), 'debug');
		} else {
			$this->log(sprintf('Failed to update last check time for source ID %d', $source_id), 'error');
		}
	}

	/**
	 * Get a source by ID
	 *
	 * @param int $id Source ID
	 * @return object|null Source object or null
	 */
	public function get_source($id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sactech_events_sources';

		$source = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table_name WHERE id = %d",
			$id
		));

		if ($source) {
			$this->log(sprintf('Retrieved source: %s (ID: %d)', $source->source_name, $id), 'debug');
		} else {
			$this->log(sprintf('Source not found with ID: %d', $id), 'warning');
		}

		return $source;
	}

	/**
	 * Add a new source
	 *
	 * @param array $source_data Source data
	 * @return int|false Source ID or false on failure
	 */
	public function add_source($source_data) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sactech_events_sources';

		$data = array(
			'source_type' => $source_data['source_type'],
			'source_url' => $source_data['source_url'],
			'source_name' => $source_data['source_name'],
			'last_check' => current_time('mysql'),
			'status' => 'active',
		);

		$format = array('%s', '%s', '%s', '%s', '%s');

		$result = $wpdb->insert($table_name, $data, $format);

		if ($result) {
			$source_id = $wpdb->insert_id;
			$this->log(sprintf(
				'Added new source: %s (ID: %d, Type: %s)',
				$source_data['source_name'],
				$source_id,
				$source_data['source_type']
			), 'info');
			return $source_id;
		} else {
			$this->log(sprintf('Failed to add source: %s', $source_data['source_name']), 'error');
			return false;
		}
	}

	/**
	 * Update a source
	 *
	 * @param int $id Source ID
	 * @param array $source_data Source data
	 * @return bool Success
	 */
	public function update_source($id, $source_data) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sactech_events_sources';

		$data = array(
			'source_type' => $source_data['source_type'],
			'source_url' => $source_data['source_url'],
			'source_name' => $source_data['source_name'],
		);

		$format = array('%s', '%s', '%s');

		$result = $wpdb->update(
			$table_name,
			$data,
			array('id' => $id),
			$format,
			array('%d')
		);

		if ($result !== false) {
			$this->log(sprintf('Updated source ID %d: %s', $id, $source_data['source_name']), 'info');
			return true;
		} else {
			$this->log(sprintf('Failed to update source ID %d', $id), 'error');
			return false;
		}
	}

	/**
	 * Delete a source
	 *
	 * @param int $id Source ID
	 * @return bool Success
	 */
	public function delete_source($id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sactech_events_sources';

		// Get source name for logging
		$source = $this->get_source($id);
		$source_name = $source ? $source->source_name : 'Unknown';

		$result = $wpdb->update(
			$table_name,
			array('status' => 'deleted'),
			array('id' => $id),
			array('%s'),
			array('%d')
		);

		if ($result !== false) {
			$this->log(sprintf('Deleted source ID %d: %s', $id, $source_name), 'info');
			return true;
		} else {
			$this->log(sprintf('Failed to delete source ID %d', $id), 'error');
			return false;
		}
	}

	/**
	 * Handle add source form submission
	 */
	public function handle_add_source() {
		// Check nonce
		check_admin_referer('sactech_events_add_source', 'sactech_events_nonce');

		// Check permissions
		if (! current_user_can('manage_options')) {
			wp_die(__('You do not have permission to add sources.', 'sac-tech-events'));
		}

		// Get source data
		$source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : '';
		$source_url = isset($_POST['source_url']) ? esc_url_raw($_POST['source_url']) : '';
		$source_name = isset($_POST['source_name']) ? sanitize_text_field($_POST['source_name']) : '';

		// Validate data
		if (empty($source_type) || empty($source_url) || empty($source_name)) {
			// Redirect back with error
			wp_redirect(add_query_arg('error', 'missing_fields', admin_url('admin.php?page=sactech-events-sources')));
			exit;
		}

		// Check if source type is valid
		if (! array_key_exists($source_type, $this->get_source_types())) {
			wp_redirect(add_query_arg('error', 'invalid_type', admin_url('admin.php?page=sactech-events-sources')));
			exit;
		}

		// Add source
		$source_id = $this->add_source(array(
			'source_type' => $source_type,
			'source_url' => $source_url,
			'source_name' => $source_name,
		));

		if (! $source_id) {
			wp_redirect(add_query_arg('error', 'db_error', admin_url('admin.php?page=sactech-events-sources')));
			exit;
		}

		// Redirect to sources page with success message
		wp_redirect(add_query_arg('message', 'source_added', admin_url('admin.php?page=sactech-events-sources')));
		exit;
	}

	/**
	 * Handle edit source form submission
	 */
	public function handle_edit_source() {
		// Check nonce
		check_admin_referer('sactech_events_edit_source', 'sactech_events_nonce');

		// Check permissions
		if (! current_user_can('manage_options')) {
			wp_die(__('You do not have permission to edit sources.', 'sac-tech-events'));
		}

		// Get source data
		$source_id = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;
		$source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : '';
		$source_url = isset($_POST['source_url']) ? esc_url_raw($_POST['source_url']) : '';
		$source_name = isset($_POST['source_name']) ? sanitize_text_field($_POST['source_name']) : '';

		// Validate data
		if (! $source_id || empty($source_type) || empty($source_url) || empty($source_name)) {
			// Redirect back with error
			wp_redirect(add_query_arg(
				array(
					'error' => 'missing_fields',
					'action' => 'edit',
					'id' => $source_id
				),
				admin_url('admin.php?page=sactech-events-sources')
			));
			exit;
		}

		// Check if source type is valid
		if (! array_key_exists($source_type, $this->get_source_types())) {
			wp_redirect(add_query_arg(
				array(
					'error' => 'invalid_type',
					'action' => 'edit',
					'id' => $source_id
				),
				admin_url('admin.php?page=sactech-events-sources')
			));
			exit;
		}

		// Update source
		$updated = $this->update_source($source_id, array(
			'source_type' => $source_type,
			'source_url' => $source_url,
			'source_name' => $source_name,
		));

		if (! $updated) {
			wp_redirect(add_query_arg(
				array(
					'error' => 'db_error',
					'action' => 'edit',
					'id' => $source_id
				),
				admin_url('admin.php?page=sactech-events-sources')
			));
			exit;
		}

		// Redirect to sources page with success message
		wp_redirect(add_query_arg('message', 'source_updated', admin_url('admin.php?page=sactech-events-sources')));
		exit;
	}

	/**
	 * Handle delete source form submission
	 */
	public function handle_delete_source() {
		// Check nonce
		check_admin_referer('sactech_events_delete_source', 'sactech_events_nonce');

		// Check permissions
		if (! current_user_can('manage_options')) {
			wp_die(__('You do not have permission to delete sources.', 'sac-tech-events'));
		}

		// Get source ID
		$source_id = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;

		if (! $source_id) {
			wp_redirect(add_query_arg('error', 'missing_id', admin_url('admin.php?page=sactech-events-sources')));
			exit;
		}

		// Delete source
		$deleted = $this->delete_source($source_id);

		if (! $deleted) {
			wp_redirect(add_query_arg('error', 'db_error', admin_url('admin.php?page=sactech-events-sources')));
			exit;
		}

		// Redirect to sources page with success message
		wp_redirect(add_query_arg('message', 'source_deleted', admin_url('admin.php?page=sactech-events-sources')));
		exit;
	}

	/**
	 * Get all sources
	 *
	 * @return array All sources
	 */
	public function get_all_sources() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sactech_events_sources';

		$sources = $wpdb->get_results("SELECT * FROM $table_name WHERE status != 'deleted' ORDER BY source_name ASC");

		$this->log(sprintf('Retrieved %d total sources', count($sources)), 'debug');
		return $sources;
	}

	/**
	 * Test a source connection
	 *
	 * @param string $source_type Source type
	 * @param string $source_url Source URL
	 * @return array Test result with count and sample
	 */
	public function test_source($source_type, $source_url) {
		$source = $this->create_source_instance((object)[
			'source_type' => $source_type,
			'source_url' => $source_url,
			'source_name' => 'Test Source'
		]);

		if (! $source) {
			throw new \Exception(__('Invalid source type.', 'sac-tech-events'));
		}

		try {
			$this->log(sprintf('Testing source connection: %s', $source_url), 'info');

			// Fetch events
			$events = $source->fetch_events(5); // Just get a few for testing

			// Get a sample event
			$sample = ! empty($events) ? $events[0] : null;

			$this->log(sprintf('Source test successful: found %d events', count($events)), 'info');

			return array(
				'count' => count($events),
				'sample' => $sample,
			);
		} catch (\Exception $e) {
			$this->log(sprintf('Source test failed: %s', $e->getMessage()), 'error');
			throw new \Exception($e->getMessage());
		}
	}

	/**
	 * Get events from a specific source
	 *
	 * @param int $source_id Source ID
	 * @param int $limit Maximum number of events
	 * @return array Events from the source
	 */
	public function get_events_from_source($source_id, $limit = 10) {
		$source = $this->get_source($source_id);

		if (!$source) {
			$this->log(sprintf('Cannot get events: source ID %d not found', $source_id), 'error');
			return [];
		}

		try {
			$source_instance = $this->create_source_instance($source);

			if (!$source_instance) {
				return [];
			}

			$events = $source_instance->fetch_events($limit);
			$this->log(sprintf('Fetched %d events from source ID %d', count($events), $source_id), 'info');

			// Add source info to each event
			foreach ($events as &$event) {
				$event['source'] = $source;
			}

			// Update last check time
			$this->update_source_last_check($source_id);

			return $events;
		} catch (\Exception $e) {
			$this->handle_error($e, true, false);
			return [];
		}
	}
}
