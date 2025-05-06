<?php

/**
 * Website Source
 *
 * @package SacITCentral
 */

namespace SacTech_Events\Sources;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Website Source class
 *
 * Scrapes events from a generic website
 */
class Website implements Source_Interface {
	/**
	 * Source URL
	 *
	 * @var string
	 */
	private $source_url = '';

	/**
	 * Set source URL
	 *
	 * @param string $url Source URL
	 */
	public function set_source_url($url) {
		$this->source_url = $url;
	}

	/**
	 * Get source name
	 *
	 * @return string
	 */
	public function get_name() {
		return __('Website', 'sac-tech-events');
	}

	/**
	 * Get source description
	 *
	 * @return string
	 */
	public function get_description() {
		return __('Scrape events from a generic website.', 'sac-tech-events');
	}

	/**
	 * Get configuration fields
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array(
			'url' => array(
				'label' => __('Website URL', 'sac-tech-events'),
				'type' => 'url',
				'required' => true,
			),
		);
	}

	/**
	 * Fetch events from the website
	 *
	 * @return array Array of event data
	 */
	public function fetch_events() {
		if (empty($this->source_url)) {
			throw new \Exception(__('Source URL is required.', 'sac-tech-events'));
		}

		// Initialize events array
		$events = array();

		// Get the website content
		$response = wp_remote_get($this->source_url, array(
			'timeout' => get_option('sactech_events_request_timeout', 30),
			'sslverify' => ! get_option('sactech_events_disable_ssl_verify', 0),
			'user-agent' => 'Mozilla/5.0 (compatible; Sacramento Tech Events/1.0; +https://sacitcentral.com)'
		));

		// Check for errors
		if (is_wp_error($response)) {
			throw new \Exception($response->get_error_message());
		}

		// Check response code
		$response_code = wp_remote_retrieve_response_code($response);
		if ($response_code !== 200) {
			throw new \Exception(sprintf(__('Error fetching website content. Response code: %s', 'sac-tech-events'), $response_code));
		}

		// Get the response body
		$body = wp_remote_retrieve_body($response);

		// Create a DOMDocument
		$doc = new \DOMDocument();

		// Suppress warnings for malformed HTML
		@$doc->loadHTML($body);

		// Create a DOMXPath to query the document
		$xpath = new \DOMXPath($doc);

		// Extract potential event elements using common event patterns
		$event_nodes = $this->find_event_elements($xpath);

		// Process each potential event
		foreach ($event_nodes as $event_node) {
			// Extract event data
			$event_data = $this->extract_event_data($event_node, $xpath, $doc);

			if ($event_data) {
				$events[] = $event_data;
			}
		}

		return $events;
	}

	/**
	 * Find potential event elements
	 *
	 * @param \DOMXPath $xpath XPath object
	 * @return array Array of DOMElement nodes
	 */
	private function find_event_elements($xpath) {
		$event_nodes = array();

		// Common class/id patterns for event containers
		$queries = array(
			"//div[contains(@class, 'event')]",
			"//div[contains(@class, 'calendar-item')]",
			"//article[contains(@class, 'event')]",
			"//div[contains(@id, 'event')]",
			"//li[contains(@class, 'event')]",
			"//div[contains(@class, 'tribe-events')]",
			"//div[contains(@class, 'schedule')]",
			"//div[contains(@class, 'meetup')]",
			"//div[contains(@itemtype, 'Event')]",
			"//div[contains(@class, 'session')]",
			"//div[contains(@class, 'workshop')]",
			"//div[contains(@class, 'conference')]",
			"//div[contains(@class, 'webinar')]",
		);

		// Try each query
		foreach ($queries as $query) {
			$nodes = $xpath->query($query);

			if ($nodes && $nodes->length > 0) {
				foreach ($nodes as $node) {
					$event_nodes[] = $node;
				}
			}
		}

		// If no event containers found, look for structured data
		if (empty($event_nodes)) {
			$structured_data = $xpath->query("//script[@type='application/ld+json']");

			if ($structured_data && $structured_data->length > 0) {
				foreach ($structured_data as $script) {
					$json = $script->nodeValue;
					$data = json_decode($json, true);

					// Check if JSON-LD contains event data
					if ($data && isset($data['@type']) && ($data['@type'] === 'Event' || $data['@type'] === 'events')) {
						// Create a dummy node to represent this structured data
						$dummy_node = new \stdClass();
						$dummy_node->structured_data = $data;
						$event_nodes[] = $dummy_node;
					}
				}
			}
		}

		return $event_nodes;
	}

	/**
	 * Extract event data from a node
	 *
	 * @param mixed     $node Event node or object
	 * @param \DOMXPath $xpath XPath object
	 * @param \DOMDocument $doc Document
	 * @return array|false Event data or false if no valid event
	 */
	private function extract_event_data($node, $xpath, $doc) {
		// Check if we're dealing with structured data
		if (isset($node->structured_data)) {
			return $this->extract_structured_event_data($node->structured_data);
		}

		// Initialize event data
		$event = array(
			'title' => '',
			'description' => '',
			'start_date' => '',
			'end_date' => '',
			'location' => '',
			'url' => '',
			'image' => '',
			'organizer' => '',
			'categories' => array(),
		);

		// Extract title
		$title_element = $xpath->query(".//h1|.//h2|.//h3|.//h4|.//div[contains(@class, 'title')]|.//span[contains(@class, 'title')]", $node)->item(0);
		if ($title_element) {
			$event['title'] = trim($title_element->textContent);
		}

		// Extract description
		$desc_element = $xpath->query(".//div[contains(@class, 'desc')]|.//div[contains(@class, 'description')]|.//p", $node)->item(0);
		if ($desc_element) {
			$event['description'] = trim($desc_element->textContent);
		}

		// Extract date
		$date_element = $xpath->query(".//time|.//div[contains(@class, 'date')]|.//span[contains(@class, 'date')]", $node)->item(0);
		if ($date_element) {
			$date_text = trim($date_element->textContent);
			$parsed_date = $this->parse_date($date_text);

			if ($parsed_date) {
				$event['start_date'] = $parsed_date;
			}
		}

		// Extract location
		$location_element = $xpath->query(".//div[contains(@class, 'location')]|.//span[contains(@class, 'location')]|.//address", $node)->item(0);
		if ($location_element) {
			$event['location'] = trim($location_element->textContent);
		}

		// Extract URL
		$url_element = $xpath->query(".//a[contains(@class, 'more')]|.//a[contains(@class, 'link')]|.//a[contains(@class, 'url')]", $node)->item(0);
		if ($url_element && $url_element->hasAttribute('href')) {
			$href = $url_element->getAttribute('href');

			// Make relative URLs absolute
			if (strpos($href, 'http') !== 0) {
				$base_url = parse_url($this->source_url, PHP_URL_SCHEME) . '://' . parse_url($this->source_url, PHP_URL_HOST);
				$href = $base_url . '/' . ltrim($href, '/');
			}

			$event['url'] = $href;
		}

		// Extract image
		$image_element = $xpath->query(".//img", $node)->item(0);
		if ($image_element && $image_element->hasAttribute('src')) {
			$src = $image_element->getAttribute('src');

			// Make relative URLs absolute
			if (strpos($src, 'http') !== 0) {
				$base_url = parse_url($this->source_url, PHP_URL_SCHEME) . '://' . parse_url($this->source_url, PHP_URL_HOST);
				$src = $base_url . '/' . ltrim($src, '/');
			}

			$event['image'] = $src;
		}

		// Skip if no title or no date
		if (empty($event['title']) || empty($event['start_date'])) {
			return false;
		}

		// Add source info
		$event['source_type'] = 'website';
		$event['source_url'] = $this->source_url;

		return $event;
	}

	/**
	 * Extract event data from structured data
	 *
	 * @param array $data Structured data
	 * @return array Event data
	 */
	private function extract_structured_event_data($data) {
		$event = array(
			'title' => '',
			'description' => '',
			'start_date' => '',
			'end_date' => '',
			'location' => '',
			'url' => '',
			'image' => '',
			'organizer' => '',
			'categories' => array(),
		);

		// Extract event data from JSON-LD
		if (isset($data['name'])) {
			$event['title'] = $data['name'];
		}

		if (isset($data['description'])) {
			$event['description'] = $data['description'];
		}

		if (isset($data['startDate'])) {
			$event['start_date'] = $data['startDate'];
		}

		if (isset($data['endDate'])) {
			$event['end_date'] = $data['endDate'];
		}

		if (isset($data['location'])) {
			if (is_array($data['location']) && isset($data['location']['name'])) {
				$event['location'] = $data['location']['name'];

				if (isset($data['location']['address']) && is_array($data['location']['address'])) {
					$event['location'] .= ', ' . implode(', ', array_values($data['location']['address']));
				}
			} else {
				$event['location'] = $data['location'];
			}
		}

		if (isset($data['url'])) {
			$event['url'] = $data['url'];
		}

		if (isset($data['image'])) {
			if (is_array($data['image'])) {
				$event['image'] = $data['image'][0];
			} else {
				$event['image'] = $data['image'];
			}
		}

		if (isset($data['organizer']) && is_array($data['organizer']) && isset($data['organizer']['name'])) {
			$event['organizer'] = $data['organizer']['name'];
		}

		// Add source info
		$event['source_type'] = 'website';
		$event['source_url'] = $this->source_url;

		return $event;
	}

	/**
	 * Parse a date string
	 *
	 * @param string $date_string Date string
	 * @return string|false Formatted date or false on failure
	 */
	private function parse_date($date_string) {
		// Try to parse the date using strtotime
		$timestamp = strtotime($date_string);

		if ($timestamp) {
			return date('Y-m-d H:i:s', $timestamp);
		}

		// If that fails, try to extract date parts using regex
		$patterns = array(
			// MM/DD/YYYY
			'/(\d{1,2})\/(\d{1,2})\/(\d{4})/' => function ($matches) {
				return sprintf('%04d-%02d-%02d 00:00:00', $matches[3], $matches[1], $matches[2]);
			},
			// DD/MM/YYYY
			'/(\d{1,2})\.(\d{1,2})\.(\d{4})/' => function ($matches) {
				return sprintf('%04d-%02d-%02d 00:00:00', $matches[3], $matches[2], $matches[1]);
			},
			// YYYY-MM-DD
			'/(\d{4})-(\d{1,2})-(\d{1,2})/' => function ($matches) {
				return sprintf('%04d-%02d-%02d 00:00:00', $matches[1], $matches[2], $matches[3]);
			},
			// Month DD, YYYY
			'/([A-Za-z]+) (\d{1,2}),? (\d{4})/' => function ($matches) {
				$month = date_parse($matches[1]);
				return sprintf('%04d-%02d-%02d 00:00:00', $matches[3], $month['month'], $matches[2]);
			},
		);

		foreach ($patterns as $pattern => $callback) {
			if (preg_match($pattern, $date_string, $matches)) {
				return $callback($matches);
			}
		}

		return false;
	}
}
