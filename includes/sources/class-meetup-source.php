<?php

/**
 * Meetup Source
 *
 * @package SacITCentral
 */

namespace SacTech_Events\Sources;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Meetup Source class
 */
class Meetup_Source extends Abstract_Source {
	/**
	 * Fetch events from Meetup
	 *
	 * @param int $limit Maximum number of events to fetch
	 * @return array Array of events
	 */
	public function fetch_events($limit = 10) {
		$events = array();

		try {
			// Construct the API URL
			$url = add_query_arg(array(
				'page' => $limit,
				'fields' => 'description,featured_photo,group_key_photo,plain_text_description',
				'status' => 'upcoming',
			), $this->source->source_url);

			// Make the request
			$response = $this->make_request($url);

			if (is_wp_error($response)) {
				throw new \Exception($response->get_error_message());
			}

			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);

			if (! is_array($data)) {
				throw new \Exception('Invalid response from Meetup API');
			}

			// Process events
			foreach ($data as $event) {
				// Skip if missing required fields
				if (empty($event['name']) || empty($event['time'])) {
					continue;
				}

				// Format start and end dates
				$start_date = date('Y-m-d H:i:s', intval($event['time'] / 1000));

				$end_date = $start_date;
				if (! empty($event['duration'])) {
					$end_date = date('Y-m-d H:i:s', intval(($event['time'] + $event['duration']) / 1000));
				}

				// Get venue
				$location = '';
				if (! empty($event['venue']) && ! empty($event['venue']['name'])) {
					$location = $event['venue']['name'];

					if (! empty($event['venue']['address_1'])) {
						$location .= ', ' . $event['venue']['address_1'];
					}

					if (! empty($event['venue']['city'])) {
						$location .= ', ' . $event['venue']['city'];
					}
				}

				// Get image
				$image = '';
				if (! empty($event['featured_photo']) && ! empty($event['featured_photo']['photo_link'])) {
					$image = $event['featured_photo']['photo_link'];
				} elseif (! empty($event['group']) && ! empty($event['group']['key_photo']) && ! empty($event['group']['key_photo']['photo_link'])) {
					$image = $event['group']['key_photo']['photo_link'];
				}

				// Format event data
				$formatted_event = $this->format_event_data(array(
					'title' => $event['name'],
					'description' => $event['description'] ?? '',
					'start_date' => $start_date,
					'end_date' => $end_date,
					'location' => $location,
					'organizer' => $event['group']['name'] ?? '',
					'url' => $event['link'] ?? '',
					'image' => $image,
					'external_id' => $event['id'],
				));

				$events[] = $formatted_event;

				// Stop if we've reached the limit
				if (count($events) >= $limit) {
					break;
				}
			}

			return $events;
		} catch (\Exception $e) {
			// Log error
			error_log(sprintf(
				'Sacramento Tech Events: Error fetching events from Meetup: %s',
				$e->getMessage()
			));

			return $events;
		}
	}
}
