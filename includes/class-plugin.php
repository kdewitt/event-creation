<?php

/**
 * Main plugin class
 *
 * @package SacITCentral
 */

namespace SacTech_Events;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Main plugin class
 */
class Plugin {
	/**
	 * Plugin instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Admin class instance
	 *
	 * @var Admin\Admin
	 */
	public $admin;

	/**
	 * Source Manager instance
	 *
	 * @var Sources\Manager
	 */
	public $sources;

	/**
	 * Events Manager instance
	 *
	 * @var Events\Manager
	 */
	public $events;

	/**
	 * AI class instance
	 *
	 * @var AI\Manager
	 */
	public $ai;

	/**
	 * Feedback class instance
	 *
	 * @var Feedback\Manager
	 */
	public $feedback;

	/**
	 * Filter class instance
	 *
	 * @var Filter\Manager
	 */
	public $filter;

	/**
	 * Class constructor
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_components();
		$this->setup_hooks();
	}

	/**
	 * Get plugin instance
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load required dependencies
	 */
	private function load_dependencies() {
		// Load core files
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/admin/class-admin.php';
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/admin/class-settings.php';

		// Load manager classes
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/managers/class-sources-manager.php';
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/managers/class-events-manager.php';
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/managers/class-ai-manager.php';
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/managers/class-filter-manager.php';

		// Load source interface
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/sources/interface-source.php';

		// Load source classes
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/sources/class-abstract-source.php';
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/sources/class-meetup-source.php';
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/sources/class-source-interface.php';
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/sources/class-website.php';
		require_once SACTECH_EVENTS_PLUGIN_DIR . 'includes/utilities/class-logger.php';
	}

	/**
	 * Initialize plugin components
	 */
	private function init_components() {
		// Create component instances
		$this->admin = new Admin\Admin();
		$this->sources = new Sources\Manager();
		$this->events = new Events\Manager();
		$this->ai = new AI\Manager();
		$this->feedback = new Feedback\Manager();
		$this->filter = new Filter\Manager();
	}

	/**
	 * Set up WordPress hooks
	 */
	private function setup_hooks() {
		// Register activation/deactivation hooks
		register_activation_hook(SACTECH_EVENTS_PLUGIN_FILE, array($this, 'activate'));
		register_deactivation_hook(SACTECH_EVENTS_PLUGIN_FILE, array($this, 'deactivate'));

		// Add actions
		add_action('init', array($this, 'load_textdomain'));

		// Setup cron jobs
		add_action('sactech_events_scheduled_import', array($this->events, 'run_scheduled_import'));
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Setup database tables if needed
		$this->create_database_tables();

		// Schedule events
		if (! wp_next_scheduled('sactech_events_scheduled_import')) {
			wp_schedule_event(time(), 'daily', 'sactech_events_scheduled_import');
		}

		// Add default options
		$this->add_default_options();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clear scheduled events
		wp_clear_scheduled_hook('sactech_events_scheduled_import');

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Create required database tables
	 */
	private function create_database_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Table for feedback data
		$table_name = $wpdb->prefix . 'sactech_events_feedback';
		$sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) NOT NULL,
            source_id varchar(255) NOT NULL,
            action varchar(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            notes text NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

		// Table for event sources tracking
		$source_table = $wpdb->prefix . 'sactech_events_sources';
		$source_sql = "CREATE TABLE $source_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            source_type varchar(50) NOT NULL,
            source_url varchar(255) NOT NULL,
            source_name varchar(100) NOT NULL,
            last_check datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            PRIMARY KEY  (id),
            KEY source_type (source_type),
            KEY status (status)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
		dbDelta($source_sql);
	}

	/**
	 * Add default plugin options
	 */
	private function add_default_options() {
		// Default filter criteria
		$default_include_terms = array(
			'programming',
			'developer',
			'software',
			'tech',
			'technology',
			'code',
			'coding',
			'cloud',
			'data science',
			'machine learning',
			'artificial intelligence',
			'ai',
			'devops',
			'cybersecurity',
			'security',
			'web development',
			'mobile development',
			'database',
			'sql',
			'nosql',
			'javascript',
			'python',
			'java',
			'php',
			'c#',
			'.net',
			'react',
			'angular',
			'vue',
			'node',
			'aws',
			'azure',
			'google cloud',
			'kubernetes',
			'docker',
			'blockchain',
			'iot',
			'networking',
			'sysadmin',
			'linux',
			'windows server'
		);

		$default_exclude_terms = array(
			'webinar',
			'free trial',
			'kids',
			'children',
			'elementary',
			'middle school',
			'high school',
			'sale',
			'discount',
			'promotion'
		);

		$default_sources = array(
			array(
				'name' => 'Sacramento JS',
				'type' => 'website',
				'url' => 'https://www.meetup.com/Sacramento-JavaScript-Meetup/',
			),
			array(
				'name' => 'Sac.NET',
				'type' => 'website',
				'url' => 'https://www.meetup.com/sac-net/',
			),
			array(
				'name' => 'SacPy',
				'type' => 'website',
				'url' => 'https://www.meetup.com/sacpython/',
			),
			array(
				'name' => 'UC Davis Tech Events',
				'type' => 'website',
				'url' => 'https://cs.ucdavis.edu/events',
			),
			array(
				'name' => 'Sacramento State Tech Events',
				'type' => 'website',
				'url' => 'https://www.csus.edu/college/engineering-computer-science/student-success/news-events.html',
			)
		);

		// Only add default options if they don't already exist
		if (! get_option('sactech_events_filter_include')) {
			update_option('sactech_events_filter_include', $default_include_terms);
		}

		if (! get_option('sactech_events_filter_exclude')) {
			update_option('sactech_events_filter_exclude', $default_exclude_terms);
		}

		if (! get_option('sactech_events_minimum_score')) {
			update_option('sactech_events_minimum_score', 50);
		}

		if (! get_option('sactech_events_sources')) {
			update_option('sactech_events_sources', $default_sources);
		}

		// AI settings
		if (! get_option('sactech_events_ai_provider')) {
			update_option('sactech_events_ai_provider', 'openai');
		}

		if (! get_option('sactech_events_ai_api_key')) {
			update_option('sactech_events_ai_api_key', '');
		}
	}

	/**
	 * Load plugin text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'sac-tech-events',
			false,
			dirname(plugin_basename(SACTECH_EVENTS_PLUGIN_FILE)) . '/languages/'
		);
	}
}
