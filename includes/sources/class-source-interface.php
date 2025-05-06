<?php

/**
 * Source Interface
 *
 * @package SacITCentral
 */

namespace SacTech_Events\Sources;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Source Interface
 */
interface Source_Interface {
	/**
	 * Set source URL
	 *
	 * @param string $url Source URL
	 */
	public function set_source_url($url);

	/**
	 * Fetch events from the source
	 *
	 * @return array Array of event data
	 */
	public function fetch_events();

	/**
	 * Get source name
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Get source description
	 *
	 * @return string
	 */
	public function get_description();

	/**
	 * Get configuration fields
	 *
	 * @return array
	 */
	public function get_config_fields();
}
