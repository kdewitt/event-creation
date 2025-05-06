<?php

/**
 * Abstract Source
 *
 * @package SacITCentral
 */

namespace SacTech_Events\Sources;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Abstract Source class
 */
abstract class Abstract_Source implements Source_Interface {
	/**
	 * Source data
	 *
	 * @var object
	 */
	protected $source;

	/**
	 * Constructor
	 *
	 * @param object $source Source data
	 */
	public function __construct($source) {
		$this->source = $source;
	}

	/**
	 * Get the source name
	 *
	 * @return string Source name
	 */
	public function get_name() {
		return $this->source->source_name;
	}

	/**
	 * Get the source type
	 *
	 * @return string Source type
	 */
	public function get_type() {
		return $this->source->source_type;
	}

	/**
	 * Format event data
	 *
	 * @param array $raw_event Raw event data
	 * @return array Formatted event data
	 */
	protected function format_event_data($raw_event) {
		// Set default values
		$event_data = array(
			'title' => '',
			'description' => '',
			'start_date' => '',
			'end_date' => '',
			'location' => '',
			'organizer' => '',
			'url' => '',
			'image' => '',
			'external_id' => '',
		);

		// Merge with raw event data
		return array_merge($event_data, $raw_event);
	}

	/**
	 * Make HTTP request
	 *
	 * @param string $url URL to request
	 * @param array  $args Request arguments
	 * @return array|WP_Error Response data or WP_Error
	 */
	protected function make_request($url, $args = array()) {
		$default_args = array(
			'timeout' => 30,
			'user-agent' => 'Sacramento Tech Events Plugin/' . SACTECH_EVENTS_VERSION,
		);

		$args = wp_parse_args($args, $default_args);

		$response = wp_remote_get($url, $args);

		if (is_wp_error($response)) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code($response);

		if ($code !== 200) {
			return new \WP_Error(
				'bad_response',
				sprintf('Received %d response from API', $code)
			);
		}

		return $response;
	}
}
