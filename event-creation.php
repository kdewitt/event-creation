<?php

/**
 * Plugin Name: Sacramento Tech Event Creation
 * Plugin URI: https://sacitcentral.com/plugins/event-creation
 * Description: Automatically fetches and creates tech events from various sources for the Sacramento IT community
 * Version: 1.0.0
 * Author: Sacramento IT Central
 * Author URI: https://sacitcentral.com
 * Text Domain: sac-tech-events
 *
 * @package SacITCentral
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

// Define plugin constants
define('SACTECH_EVENTS_VERSION', '1.0.0');
define('SACTECH_EVENTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SACTECH_EVENTS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
	// Check if the class should be autoloaded by this plugin
	if (strpos($class, 'SacTech_Events') !== 0) {
		return;
	}

	// Convert class name to file path
	$class_path = str_replace('SacTech_Events\\', '', $class);
	$class_path = str_replace('\\', DIRECTORY_SEPARATOR, $class_path);

	// Check for manager classes first
	if (strpos($class, 'SacTech_Events\\Manager') !== false) {
		$file_path = SACTECH_EVENTS_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . 'managers' . DIRECTORY_SEPARATOR . $class_path . '.php';
	} else {
		$file_path = SACTECH_EVENTS_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $class_path . '.php';
	}

	// Load the file if it exists
	if (file_exists($file_path)) {
		require_once $file_path;
	}
});

/**
 * Main plugin class
 */
class SacTech_Events {
	/**
	 * Sources manager
	 *
	 * @var SacTech_Events\Sources_Manager
	 */
	public $sources;

	/**
	 * Events manager
	 *
	 * @var SacTech_Events\Events_Manager
	 */
	public $events;

	/**
	 * Admin manager
	 *
	 * @var SacTech_Events\Admin_Manager
	 */
	public $admin;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize plugin
		add_action('plugins_loaded', array($this, 'init'));

		// Register activation hook
		register_activation_hook(__FILE__, array($this, 'activate'));

		// Register deactivation hook
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Check if The Events Calendar is active
		if (! class_exists('Tribe__Events__Main')) {
			add_action('admin_notices', array($this, 'missing_events_calendar_notice'));
			return;
		}

		// Load text domain
		load_plugin_textdomain('sac-tech-events', false, dirname(plugin_basename(__FILE__)) . '/languages');

		// Initialize components
		$this->sources = new SacTech_Events\Sources_Manager();
		$this->events = new SacTech_Events\Events_Manager();
		$this->admin = new SacTech_Events\Admin_Manager();
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create database tables
		$this->create_tables();

		// Set default options
		$this->set_default_options();

		// Create necessary directories
		if (! file_exists(SACTECH_EVENTS_PLUGIN_DIR . 'logs')) {
			mkdir(SACTECH_EVENTS_PLUGIN_DIR . 'logs', 0755, true);
		}

		// Schedule import
		if (! wp_next_scheduled('sactech_events_import')) {
			wp_schedule_event(time(), 'daily', 'sactech_events_import');
		}

		// Clear rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Unschedule import
		$timestamp = wp_next_scheduled('sactech_events_import');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'sactech_events_import');
		}

		// Clear rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Create database tables
	 */
	private function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Sources table
		$table_name = $wpdb->prefix . 'sactech_events_sources';

		$sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            source_name varchar(100) NOT NULL,
            source_type varchar(50) NOT NULL,
            source_url varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            last_check datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * Set default options
	 */
	private function set_default_options() {
		// Import settings
		add_option('sactech_events_schedule_frequency', 'daily');
		add_option('sactech_events_max_events_per_import', 50);
		add_option('sactech_events_min_relevance_score', 50);
		add_option('sactech_events_default_status', 'draft');
		add_option('sactech_events_auto_publish', 0);
		add_option('sactech_events_enable_logging', 1);

		// AI settings
		add_option('sactech_events_use_ai_for_descriptions', 1);
		add_option('sactech_events_use_ai_for_seo', 1);

		// Filter settings
		add_option('sactech_events_blacklist_keywords', '');
	}

	/**
	 * Display notice if The Events Calendar is not active
	 */
	public function missing_events_calendar_notice() {
?>
		<div class="notice notice-error">
			<p><?php esc_html_e('Sacramento Tech Event Creation requires The Events Calendar plugin to be installed and activated.', 'sac-tech-events'); ?></p>
		</div>
<?php
	}
}

/**
 * Returns the main instance of SacTech_Events
 *
 * @return SacTech_Events
 */
function sactech_events() {
	static $instance = null;

	if ($instance === null) {
		$instance = new SacTech_Events();
	}

	return $instance;
}

// Initialize the plugin
sactech_events();
