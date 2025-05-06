<?php

/**
 * Abstract Manager Base Class
 *
 * @package SacITCentral
 */

namespace SacTech_Events\Managers;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Abstract Manager base class
 *
 * Provides common functionality for all manager classes.
 */
abstract class Manager {
	/**
	 * Plugin instance
	 *
	 * @var \SacTech_Events\Plugin
	 */
	protected $plugin;

	/**
	 * Logger instance
	 *
	 * @var \SacTech_Events\Utilities\Logger
	 */
	protected $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Get plugin instance
		$this->plugin = \SacTech_Events\Plugin::instance();

		// Initialize logger
		$this->setup_logger();

		// Load dependencies
		$this->load_dependencies();

		// Initialize hooks
		$this->init_hooks();
	}

	/**
	 * Setup logger
	 */
	protected function setup_logger() {
		// Use logger if available, otherwise log to error_log
		if (isset($this->plugin) && isset($this->plugin->logger)) {
			$this->logger = $this->plugin->logger;
		}
	}

	/**
	 * Load dependencies
	 *
	 * Override in child classes to load specific dependencies.
	 */
	protected function load_dependencies() {
		// To be implemented by child classes if needed
	}

	/**
	 * Initialize hooks
	 *
	 * Must be implemented by child classes to set up WordPress hooks.
	 */
	abstract protected function init_hooks();

	/**
	 * Log message
	 *
	 * @param string $message Message to log
	 * @param string $type Log type (info, error, debug)
	 */
	protected function log($message, $type = 'info') {
		// Skip if logging is disabled
		if (! get_option('sactech_events_enable_logging', 1)) {
			return;
		}

		// Use logger if available
		if (isset($this->logger)) {
			$this->logger->log($message, $type, get_class($this));
			return;
		}

		// Fallback to basic logging
		$log_file = SACTECH_EVENTS_PLUGIN_DIR . 'logs/plugin.log';

		// Create logs directory if it doesn't exist
		if (! file_exists(dirname($log_file))) {
			mkdir(dirname($log_file), 0755, true);
		}

		// Format message
		$formatted_message = sprintf(
			'[%s] [%s] [%s] %s' . PHP_EOL,
			current_time('mysql'),
			strtoupper($type),
			get_class($this),
			$message
		);

		// Append to log file
		file_put_contents($log_file, $formatted_message, FILE_APPEND);
	}

	/**
	 * Handle errors
	 *
	 * @param \Exception $e Exception object
	 * @param bool $log Whether to log the error
	 * @param bool $display Whether to display the error to admin
	 */
	protected function handle_error($e, $log = true, $display = false) {
		if ($log) {
			$this->log($e->getMessage(), 'error');
		}

		if ($display && is_admin() && current_user_can('manage_options')) {
			add_action('admin_notices', function () use ($e) {
?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html(sprintf('Sacramento Tech Events Error: %s', $e->getMessage())); ?></p>
				</div>
<?php
			});
		}
	}

	/**
	 * Get option with default
	 *
	 * @param string $key Option key
	 * @param mixed $default Default value
	 * @return mixed Option value
	 */
	protected function get_option($key, $default = false) {
		return get_option($key, $default);
	}

	/**
	 * Update option
	 *
	 * @param string $key Option key
	 * @param mixed $value Option value
	 * @return bool Success
	 */
	protected function update_option($key, $value) {
		return update_option($key, $value);
	}

	/**
	 * Check if user has capability
	 *
	 * @param string $capability Capability to check
	 * @return bool User has capability
	 */
	protected function current_user_can($capability = 'manage_options') {
		return current_user_can($capability);
	}

	/**
	 * Check nonce
	 *
	 * @param string $nonce Nonce value
	 * @param string $action Nonce action
	 * @return bool Nonce is valid
	 */
	protected function verify_nonce($nonce, $action) {
		return wp_verify_nonce($nonce, $action);
	}

	/**
	 * Sanitize and validate input
	 *
	 * @param mixed $input Input to sanitize
	 * @param string $type Sanitization type
	 * @return mixed Sanitized input
	 */
	protected function sanitize_input($input, $type = 'text') {
		switch ($type) {
			case 'email':
				return sanitize_email($input);
			case 'url':
				return esc_url_raw($input);
			case 'int':
				return intval($input);
			case 'float':
				return floatval($input);
			case 'textarea':
				return sanitize_textarea_field($input);
			case 'key':
				return sanitize_key($input);
			case 'bool':
				return (bool) $input;
			case 'array':
				return is_array($input) ? array_map('sanitize_text_field', $input) : array();
			case 'text':
			default:
				return sanitize_text_field($input);
		}
	}
}
