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
	 * Fetch events from the source
	 *
	 * @param int $limit Maximum number of events to fetch
	 * @return array Array of events
	 */
	public function fetch_events($limit = 10);

	/**
	 * Get the source name
	 *
	 * @return string Source name
	 */
	public function get_name();

	/**
	 * Get the source type
	 *
	 * @return string Source type
	 */
	public function get_type();
}
