<?php

/**
 * Events Manager
 *
 * @package SacITCentral
 */

namespace SacTech_Events\Managers;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Events Manager class
 */
class Events_Manager extends Manager {
	/**
	 * AI Manager instance
	 *
	 * @var AI_Manager
	 */
	private $ai_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Call parent constructor first
		parent::__construct();

		// Initialize AI Manager - this will be accessed via plugin instance from parent
		$this->ai_manager = $this->plugin->ai;
	}

	/**
	 * Initialize hooks
	 */
	protected function init_hooks() {
		// Schedule import
		add_action('sactech_events_import', array($this, 'import_events'));

		// Reschedule import when settings are updated
		add_action('update_option_sactech_events_schedule_frequency', array($this, 'reschedule_import'), 10, 2);

		// Add meta boxes
		add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
	}

	/**
	 * Reschedule import when frequency setting is changed
	 *
	 * @param string $old_value Old frequency value
	 * @param string $new_value New frequency value
	 */
	public function reschedule_import($old_value, $new_value) {
		if ($old_value !== $new_value) {
			// Clear existing schedule
			$timestamp = wp_next_scheduled('sactech_events_import');
			if ($timestamp) {
				wp_unschedule_event($timestamp, 'sactech_events_import');
			}

			// Schedule new import
			wp_schedule_event(time(), $new_value, 'sactech_events_import');

			$this->log(sprintf('Import rescheduled: frequency changed from %s to %s', $old_value, $new_value));
		}
	}

	/**
	 * Import events from all sources
	 */
	public function import_events() {
		// Start logging if enabled
		$logging_enabled = $this->get_option('sactech_events_enable_logging', 1);
		$log_file = false;

		if ($logging_enabled) {
			$log_file = SACTECH_EVENTS_PLUGIN_DIR . 'logs/import-' . date('Y-m-d-H-i-s') . '.log';
			$this->log('Starting import process', 'info');
		}

		try {
			// Get settings
			$max_events = $this->get_option('sactech_events_max_events_per_import', 50);
			$min_relevance_score = $this->get_option('sactech_events_min_relevance_score', 50);
			$default_status = $this->get_option('sactech_events_default_status', 'draft');
			$auto_publish = $this->get_option('sactech_events_auto_publish', 0);
			$blacklist_keywords = array_filter(explode("\n", $this->get_option('sactech_events_blacklist_keywords', '')));

			// Trim whitespace from blacklist keywords
			$blacklist_keywords = array_map('trim', $blacklist_keywords);

			// Get sources manager
			$sources_manager = $this->plugin->sources;

			// Fetch events from all sources
			$events = $sources_manager->fetch_all_events($max_events);

			if ($logging_enabled) {
				$this->log(sprintf('Fetched %d events from all sources', count($events)), 'info');
			}

			// Process events
			$imported_count = 0;
			$skipped_count = 0;

			foreach ($events as $event) {
				// Skip events with blacklisted keywords
				$contains_blacklisted = false;
				foreach ($blacklist_keywords as $keyword) {
					if (empty($keyword)) {
						continue;
					}

					if (
						stripos($event['title'], $keyword) !== false ||
						stripos($event['description'], $keyword) !== false
					) {
						$contains_blacklisted = true;
						break;
					}
				}

				if ($contains_blacklisted) {
					if ($logging_enabled) {
						$this->log(sprintf('Skipped event "%s" due to blacklisted keyword', $event['title']), 'info');
					}
					$skipped_count++;
					continue;
				}

				// Calculate relevance score
				$relevance_score = $this->calculate_relevance_score($event);

				// Skip events with low relevance score
				if ($relevance_score < $min_relevance_score) {
					if ($logging_enabled) {
						$this->log(sprintf(
							'Skipped event "%s" due to low relevance score (%d)',
							$event['title'],
							$relevance_score
						), 'info');
					}
					$skipped_count++;
					continue;
				}

				// Check if event already exists
				if ($this->event_exists($event)) {
					if ($logging_enabled) {
						$this->log(sprintf('Skipped event "%s" as it already exists', $event['title']), 'info');
					}
					$skipped_count++;
					continue;
				}

				// Determine status
				$status = $default_status;
				if ($auto_publish && $relevance_score >= 80) {
					$status = 'publish';
				}

				// Enhance description with AI if enabled
				if ($this->get_option('sactech_events_use_ai_for_descriptions', 1)) {
					$enhanced_description = $this->ai_manager->enhance_description($event);
					if ($enhanced_description) {
						$event['description'] = $enhanced_description;
					}
				}

				// Create event
				$event_id = $this->create_event($event, $status);

				if ($event_id) {
					$imported_count++;

					// Generate SEO meta with AI if enabled
					if ($this->get_option('sactech_events_use_ai_for_seo', 1)) {
						$seo_meta = $this->ai_manager->generate_seo_meta($event);
						if ($seo_meta) {
							// Save SEO meta using suitable SEO plugin hooks
							$this->save_seo_meta($event_id, $seo_meta);
						}
					}

					if ($logging_enabled) {
						$this->log(sprintf(
							'Imported event "%s" with ID %d (Relevance: %d, Status: %s)',
							$event['title'],
							$event_id,
							$relevance_score,
							$status
						), 'info');
					}
				} else {
					$skipped_count++;

					if ($logging_enabled) {
						$this->log(sprintf('Failed to import event "%s"', $event['title']), 'error');
					}
				}
			}

			if ($logging_enabled) {
				$this->log(sprintf(
					'Import process completed. Imported: %d, Skipped: %d',
					$imported_count,
					$skipped_count
				), 'info');
			}
		} catch (\Exception $e) {
			// Use base class error handling
			$this->handle_error($e, true, false);
		}
	}

	/**
	 * Calculate relevance score for an event
	 *
	 * @param array $event Event data
	 * @return int Relevance score (0-100)
	 */
	private function calculate_relevance_score($event) {
		// Start with a base score
		$score = 50;

		// Check for tech-related keywords in title and description
		$tech_keywords = array(
			'tech',
			'technology',
			'developer',
			'programming',
			'code',
			'software',
			'web',
			'mobile',
			'data',
			'cloud',
			'ai',
			'machine learning',
			'artificial intelligence',
			'startup',
			'cyber',
			'security',
			'blockchain',
			'DevOps',
			'UX',
			'UI',
			'design',
			'agile',
			'scrum',
			'javascript',
			'python',
			'java',
			'php',
			'ruby',
			'html',
			'css',
			'react',
			'angular',
			'vue',
			'node',
			'database',
			'api',
			'aws',
			'azure',
			'google cloud',
			'iot',
			'internet of things',
			'hackathon',
			'workshop',
			'meetup',
			'conference',
			'seminar',
			'networking'
		);

		$title = strtolower($event['title']);
		$description = strtolower($event['description']);

		foreach ($tech_keywords as $keyword) {
			if (strpos($title, $keyword) !== false) {
				$score += 5; // Higher weight for keywords in title
			}
			if (strpos($description, $keyword) !== false) {
				$score += 2;
			}
		}

		// Check if it's in Sacramento area
		$sacramento_locations = array(
			'sacramento',
			'sac',
			'davis',
			'folsom',
			'rocklin',
			'roseville',
			'elk grove',
			'rancho cordova',
			'citrus heights',
			'west sacramento',
			'woodland',
			'auburn',
			'placerville',
			'downtown',
			'midtown',
			'natomas'
		);

		$location = strtolower($event['location']);

		foreach ($sacramento_locations as $sac_location) {
			if (strpos($location, $sac_location) !== false) {
				$score += 10;
				break;
			}
		}

		// Cap score between 0 and 100
		return max(0, min(100, $score));
	}

	/**
	 * Check if event already exists
	 *
	 * @param array $event Event data
	 * @return bool True if event exists, false otherwise
	 */
	private function event_exists($event_data) {
		global $wpdb;

		// First, check by event URL if available
		if (! empty($event_data['url'])) {
			$query = $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sactech_event_url' AND meta_value = %s LIMIT 1",
				$event_data['url']
			);

			$result = $wpdb->get_var($query);

			if ($result) {
				return intval($result);
			}
		}

		// If not found by URL, try finding by title and date
		$start_date = ! empty($event_data['start_date']) ? date('Y-m-d', strtotime($event_data['start_date'])) : '';

		if (! empty($event_data['title']) && ! empty($start_date)) {
			// The Events Calendar stores event dates in a custom table
			$events_table = $wpdb->prefix . 'tec_events';

			// Check if table exists
			if ($wpdb->get_var("SHOW TABLES LIKE '{$events_table}'") === $events_table) {
				$query = $wpdb->prepare(
					"SELECT e.post_id FROM {$events_table} e
									JOIN {$wpdb->posts} p ON e.post_id = p.ID
									WHERE p.post_title = %s AND DATE(e.start_date) = %s AND p.post_type = 'tribe_events' LIMIT 1",
					$event_data['title'],
					$start_date
				);

				$result = $wpdb->get_var($query);

				if ($result) {
					return intval($result);
				}
			}
		}

		return null;
	}

	/**
	 * Add meta boxes
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'sactech_events_source_info',
			__('Event Source Information', 'sac-tech-events'),
			array($this, 'render_source_meta_box'),
			'tribe_events',
			'side',
			'default'
		);
	}

	/**
	 * Render source meta box
	 *
	 * @param \WP_Post $post Post object
	 */
	public function render_source_meta_box($post) {
		// Get event source data
		$source_id = get_post_meta($post->ID, '_sactech_event_source_id', true);
		$source_type = get_post_meta($post->ID, '_sactech_event_source_type', true);
		$score = get_post_meta($post->ID, '_sactech_event_relevance_score', true);
		$imported = get_post_meta($post->ID, '_sactech_event_imported', true);
		$url = get_post_meta($post->ID, '_sactech_event_url', true);

		// Get source term
		$source_terms = wp_get_object_terms($post->ID, 'event_source');
		$source_name = !empty($source_terms) ? $source_terms[0]->name : __('Unknown', 'sac-tech-events');

		// Display info
?>
		<p><strong><?php esc_html_e('Source:', 'sac-tech-events'); ?></strong> <?php echo esc_html($source_name); ?></p>
		<?php if ($score) : ?>
			<p><strong><?php esc_html_e('Relevance Score:', 'sac-tech-events'); ?></strong> <?php echo esc_html($score); ?>/100</p>
		<?php endif; ?>
		<?php if ($imported) : ?>
			<p><strong><?php esc_html_e('Imported:', 'sac-tech-events'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($imported))); ?></p>
		<?php endif; ?>
		<?php if ($url) : ?>
			<p><strong><?php esc_html_e('Original URL:', 'sac-tech-events'); ?></strong><br>
				<a href="<?php echo esc_url($url); ?>" target="_blank"><?php esc_html_e('View Original', 'sac-tech-events'); ?></a>
			</p>
		<?php endif; ?>
<?php
	}

	/**
	 * Save SEO meta using available SEO plugins
	 *
	 * @param int $event_id Event ID
	 * @param array $seo_meta SEO meta data
	 * @return bool Success
	 */
	private function save_seo_meta($event_id, $seo_meta) {
		if (!$event_id || !is_array($seo_meta) || empty($seo_meta['title']) || empty($seo_meta['description'])) {
			return false;
		}

		$success = false;

		// Yoast SEO
		if (defined('WPSEO_VERSION')) {
			update_post_meta($event_id, '_yoast_wpseo_title', $seo_meta['title']);
			update_post_meta($event_id, '_yoast_wpseo_metadesc', $seo_meta['description']);
			$success = true;
		}

		// Rank Math
		if (class_exists('RankMath')) {
			update_post_meta($event_id, 'rank_math_title', $seo_meta['title']);
			update_post_meta($event_id, 'rank_math_description', $seo_meta['description']);
			$success = true;
		}

		// All in One SEO
		if (class_exists('AIOSEO\Plugin\Common\Main')) {
			update_post_meta($event_id, '_aioseo_title', $seo_meta['title']);
			update_post_meta($event_id, '_aioseo_description', $seo_meta['description']);
			$success = true;
		}

		if ($success) {
			$this->log(sprintf('SEO meta saved for event ID %d', $event_id), 'info');
		} else {
			$this->log('No compatible SEO plugin found for saving SEO meta', 'warning');
		}

		return $success;
	}

	/**
	 * Run scheduled import
	 */
	public function run_scheduled_import() {
		$this->log('Starting scheduled import', 'info');
		$this->import_events();
	}

	/**
	 * Update existing event
	 *
	 * @param int   $event_id Event ID
	 * @param array $event_data Event data
	 * @param int   $score Relevance score
	 * @return bool Success
	 */
	private function update_event($event_id, $event_data, $score) {
		// Skip if The Events Calendar is not active
		if (!class_exists('Tribe__Events__Main')) {
			$this->log('The Events Calendar plugin is not active', 'error');
			return false;
		}

		// Get existing event data
		$event = get_post($event_id);

		if (!$event || $event->post_type !== \Tribe__Events__Main::POSTTYPE) {
			return false;
		}

		// Check if event needs updating
		$needs_update = false;

		// Compare titles
		if ($event->post_title !== $event_data['title']) {
			$needs_update = true;
		}

		// Compare descriptions
		if (empty($event_data['description'])) {
			// Skip description update if new description is empty
			$description = $event->post_content;
		} else {
			// Check if description has changed significantly
			$similarity = similar_text($event->post_content, $event_data['description'], $percent);

			if ($percent < 90) { // If less than 90% similar
				$description = $event_data['description'];
				$needs_update = true;
			} else {
				$description = $event->post_content;
			}
		}

		// Skip updating if nothing changed
		if (!$needs_update) {
			// Update last import timestamp
			update_post_meta($event_id, '_sactech_event_imported', current_time('mysql'));
			return true;
		}

		// Enhance description with AI if enabled
		if ($this->get_option('sactech_events_use_ai_for_descriptions', 1) && $needs_update) {
			try {
				$enhanced_description = $this->ai_manager->enhance_description($description, $event_data['title']);

				if ($enhanced_description) {
					$description = $enhanced_description;
				}
			} catch (\Exception $e) {
				$this->handle_error($e, true, false);
			}
		}

		// Update event post
		$event_args = array(
			'ID'           => $event_id,
			'post_title'   => $event_data['title'],
			'post_content' => $description,
		);

		// Update the event
		$updated = wp_update_post($event_args);

		if (!$updated || is_wp_error($updated)) {
			if (is_wp_error($updated)) {
				$this->log(sprintf('Error updating event: %s', $updated->get_error_message()), 'error');
			}
			return false;
		}

		// Update metadata
		update_post_meta($event_id, '_sactech_event_relevance_score', $score);
		update_post_meta($event_id, '_sactech_event_imported', current_time('mysql'));

		// Clean the cache for this post
		clean_post_cache($event_id);

		$this->log(sprintf('Event updated: %s (ID: %d)', $event_data['title'], $event_id), 'info');

		// Fire action for event updated
		do_action('sactech_events_event_updated', $event_id, $event_data);

		return true;
	}

	/**
	 * Create a new event (implementation of the full method)
	 *
	 * @param array  $event_data Event data
	 * @param string $status Post status
	 * @return int|false Event ID or false on failure
	 */
	private function create_event($event_data, $status) {
		// Skip if The Events Calendar is not active
		if (!class_exists('Tribe__Events__Main')) {
			$this->log('The Events Calendar plugin is not active', 'error');
			return false;
		}

		// Enhanced description with AI if enabled
		$description = $event_data['description'];

		if ($this->get_option('sactech_events_use_ai_for_descriptions', 1) && !empty($description)) {
			try {
				$enhanced_description = $this->ai_manager->enhance_description($description, $event_data['title']);

				if ($enhanced_description) {
					$description = $enhanced_description;
				}
			} catch (\Exception $e) {
				$this->handle_error($e, true, false);
			}
		}

		// Prepare date data
		$start_date = !empty($event_data['start_date']) ? date('Y-m-d H:i:s', strtotime($event_data['start_date'])) : '';
		$end_date = !empty($event_data['end_date']) ? date('Y-m-d H:i:s', strtotime($event_data['end_date'])) : '';

		// If no end date, set it to start date + 2 hours
		if (empty($end_date) && !empty($start_date)) {
			$end_date = date('Y-m-d H:i:s', strtotime($start_date . ' +2 hours'));
		}

		// Prepare location data
		$venue_id = 0;

		if (!empty($event_data['location'])) {
			// Try to find existing venue
			$venue_id = $this->find_or_create_venue($event_data['location']);
		}

		// Prepare organizer data
		$organizer_id = 0;

		if (!empty($event_data['organizer'])) {
			// Try to find existing organizer
			$organizer_id = $this->find_or_create_organizer($event_data['organizer']);
		}

		// Create event post
		$event_args = array(
			'post_title'   => $event_data['title'],
			'post_content' => $description,
			'post_status'  => $status,
			'post_type'    => \Tribe__Events__Main::POSTTYPE,
		);

		// Insert the event
		$event_id = wp_insert_post($event_args);

		if (!$event_id || is_wp_error($event_id)) {
			if (is_wp_error($event_id)) {
				$this->log(sprintf('Error creating event: %s', $event_id->get_error_message()), 'error');
			}
			return false;
		}

		// Set event meta data
		update_post_meta($event_id, '_EventStartDate', $start_date);
		update_post_meta($event_id, '_EventEndDate', $end_date);

		if ($venue_id) {
			update_post_meta($event_id, '_EventVenueID', $venue_id);
		}

		if ($organizer_id) {
			update_post_meta($event_id, '_EventOrganizerID', $organizer_id);
		}

		// Set cost to free if no cost provided
		update_post_meta($event_id, '_EventCost', 0);
		update_post_meta($event_id, '_EventCurrencySymbol', '$');
		update_post_meta($event_id, '_EventCurrencyPosition', 'prefix');

		// Set event URL
		if (!empty($event_data['url'])) {
			update_post_meta($event_id, '_EventURL', esc_url_raw($event_data['url']));
			update_post_meta($event_id, '_sactech_event_url', esc_url_raw($event_data['url']));
		}

		// Set featured image if available
		if (!empty($event_data['image'])) {
			$this->set_featured_image($event_id, $event_data['image']);
		}

		// Set source and import metadata
		update_post_meta($event_id, '_sactech_event_source_id', $event_data['source_id'] ?? 0);
		update_post_meta($event_id, '_sactech_event_source_type', $event_data['source_type'] ?? '');
		update_post_meta($event_id, '_sactech_event_relevance_score', $event_data['score'] ?? 0);
		update_post_meta($event_id, '_sactech_event_imported', current_time('mysql'));

		// Set event categories if provided
		if (!empty($event_data['categories'])) {
			wp_set_object_terms($event_id, $event_data['categories'], 'tribe_events_cat');
		}

		// Set event source
		if (!empty($event_data['source_name'])) {
			wp_set_object_terms($event_id, $event_data['source_name'], 'event_source');
		}

		// Generate SEO meta using AI if enabled
		if ($this->get_option('sactech_events_use_ai_for_seo', 1)) {
			try {
				$seo_meta = $this->ai_manager->generate_seo_meta($event_data['title'], $description);

				if ($seo_meta && isset($seo_meta['title']) && isset($seo_meta['description'])) {
					$this->save_seo_meta($event_id, $seo_meta);
				}
			} catch (\Exception $e) {
				$this->handle_error($e, true, false);
			}
		}

		// Clean the cache for this post
		clean_post_cache($event_id);

		$this->log(sprintf('Event created: %s (ID: %d)', $event_data['title'], $event_id), 'info');

		// Fire action for event created
		do_action('sactech_events_event_created', $event_id, $event_data);

		return $event_id;
	}

	/**
	 * Find or create venue
	 *
	 * @param string $location Location string
	 * @return int Venue ID
	 */
	private function find_or_create_venue($location) {
		global $wpdb;

		// Clean location string
		$location = trim($location);

		// Try to find existing venue
		$venue_query = $wpdb->prepare(
			"SELECT ID FROM $wpdb->posts
									WHERE post_type = %s
									AND post_title = %s
									AND post_status = 'publish'",
			\Tribe__Events__Main::VENUE_POST_TYPE,
			$location
		);

		$venue_id = $wpdb->get_var($venue_query);

		if ($venue_id) {
			return $venue_id;
		}

		// Create new venue
		$venue_data = array(
			'post_title'  => $location,
			'post_status' => 'publish',
			'post_type'   => \Tribe__Events__Main::VENUE_POST_TYPE,
		);

		$venue_id = wp_insert_post($venue_data);

		if (! $venue_id || is_wp_error($venue_id)) {
			return 0;
		}

		// Set venue metadata
		update_post_meta($venue_id, '_VenueAddress', $location);

		return $venue_id;
	}

	/**
	 * Find or create organizer
	 *
	 * @param string $organizer Organizer name
	 * @return int Organizer ID
	 */
	private function find_or_create_organizer($organizer) {
		global $wpdb;

		// Clean organizer string
		$organizer = trim($organizer);

		// Try to find existing organizer
		$organizer_query = $wpdb->prepare(
			"SELECT ID FROM $wpdb->posts
									WHERE post_type = %s
									AND post_title = %s
									AND post_status = 'publish'",
			\Tribe__Events__Main::ORGANIZER_POST_TYPE,
			$organizer
		);

		$organizer_id = $wpdb->get_var($organizer_query);

		if ($organizer_id) {
			return $organizer_id;
		}

		// Create new organizer
		$organizer_data = array(
			'post_title'  => $organizer,
			'post_status' => 'publish',
			'post_type'   => \Tribe__Events__Main::ORGANIZER_POST_TYPE,
		);

		$organizer_id = wp_insert_post($organizer_data);

		if (! $organizer_id || is_wp_error($organizer_id)) {
			return 0;
		}

		return $organizer_id;
	}

	/**
	 * Set featured image from URL
	 *
	 * @param int    $post_id Post ID
	 * @param string $image_url Image URL
	 */
	private function set_featured_image($post_id, $image_url) {
		// Skip if already has featured image
		if (has_post_thumbnail($post_id)) {
			return;
		}

		// Bail if invalid URL
		if (empty($image_url) || ! filter_var($image_url, FILTER_VALIDATE_URL)) {
			return;
		}

		// Get the file name and extension
		$file_name = basename($image_url);

		// Include required files for media handling
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/media.php');

		// Download the image
		$tmp = download_url($image_url);

		if (is_wp_error($tmp)) {
			return;
		}

		$file_array = array(
			'name'     => $file_name,
			'tmp_name' => $tmp,
		);

		// Upload the image and attach it to the post
		$attachment_id = media_handle_sideload($file_array, $post_id);

		// Clean up temp file
		if (file_exists($tmp)) {
			@unlink($tmp);
		}

		if (is_wp_error($attachment_id)) {
			return;
		}

		// Set as featured image
		set_post_thumbnail($post_id, $attachment_id);
	}

	/**
	 * Save event source metadata
	 *
	 * @param int   $post_id Post ID
	 * @param array $data Post data
	 */
	public function save_event_source_metadata($post_id, $data) {
		// Check if this is a manual event creation
		$is_imported = get_post_meta($post_id, '_sactech_event_imported', true);

		// Skip if this is an imported event
		if ($is_imported) {
			return;
		}

		// Set manual creation flag
		update_post_meta($post_id, '_sactech_event_manual_creation', '1');

		// Set 'Manual' as the event source
		wp_set_object_terms($post_id, 'Manual', 'event_source');
	}

	/**
	 * Log message
	 *
	 * @param string $message Message to log
	 * @param string $type Log type (info, error)
	 */
	private function log($message, $type = 'info') {
		// Skip if logging is disabled
		if (! get_option('sactech_events_enable_logging', 1)) {
			return;
		}

		$log_file = SACTECH_EVENTS_PLUGIN_DIR . 'logs/import.log';

		// Create logs directory if it doesn't exist
		if (! file_exists(dirname($log_file))) {
			mkdir(dirname($log_file), 0755, true);
		}

		// Format message
		$formatted_message = sprintf(
			'[%s] [%s] %s' . PHP_EOL,
			current_time('mysql'),
			strtoupper($type),
			$message
		);

		// Append to log file
		file_put_contents($log_file, $formatted_message, FILE_APPEND);
	}
}
