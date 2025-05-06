<?php

/**
 * AI Manager
 *
 * @package SacITCentral
 */

namespace SacTech_Events\Managers;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * AI Manager class
 *
 * Handles AI operations for event descriptions and SEO
 */
class AI_Manager extends Manager {
	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Call parent constructor
		parent::__construct();

		// Initialize API key
		$this->api_key = $this->get_option('sactech_events_openai_api_key', '');
	}

	/**
	 * Initialize hooks
	 *
	 * Implementation of abstract method from parent class.
	 */
	protected function init_hooks() {
		// Filter to override API key
		add_filter('sactech_events_openai_api_key', array($this, 'filter_api_key'));

		// No other hooks needed for this manager at this time
	}

	/**
	 * Filter API key
	 *
	 * @param string $api_key Current API key
	 * @return string Filtered API key
	 */
	public function filter_api_key($api_key) {
		return $api_key;
	}

	/**
	 * Check if AI services are available
	 *
	 * @return bool
	 */
	public function is_available() {
		return ! empty($this->api_key);
	}

	/**
	 * Enhance event description with AI
	 *
	 * @param string $description Original description
	 * @param string $title Event title
	 * @return string|false Enhanced description or false on failure
	 */
	public function enhance_description($description, $title) {
		if (! $this->is_available()) {
			$this->log('AI enhancement failed: API key not available', 'warning');
			return false;
		}

		try {
			$prompt = sprintf(
				"Enhance the following tech event description for the event titled '%s'. Improve clarity, add structure with better paragraphs, and make it more engaging for a tech audience in Sacramento. Keep the technical accuracy but make it more readable:\n\n%s",
				$title,
				$description
			);

			$response = $this->make_openai_request($prompt);

			if (isset($response['choices'][0]['message']['content'])) {
				$this->log('Description enhanced successfully for event: ' . $title, 'info');
				return trim($response['choices'][0]['message']['content']);
			}

			$this->log('Failed to parse AI response for description enhancement', 'error');
			return false;
		} catch (\Exception $e) {
			// Use base class error handling
			$this->handle_error($e, true, false);
			return false;
		}
	}

	/**
	 * Generate SEO meta for an event
	 *
	 * @param string $title Event title
	 * @param string $description Event description
	 * @return array|false SEO meta or false on failure
	 */
	public function generate_seo_meta($title, $description) {
		if (! $this->is_available()) {
			$this->log('SEO generation failed: API key not available', 'warning');
			return false;
		}

		try {
			$truncated_description = substr($description, 0, 1000) . (strlen($description) > 1000 ? '...' : '');

			$prompt = sprintf(
				"Create SEO metadata for a tech event in Sacramento with this title: '%s' and description: '%s'. Generate an SEO-friendly title and meta description. Return only the title and description in this format: Title: [SEO title]\nDescription: [SEO description]. The title should be under 60 characters and the description under 155 characters.",
				$title,
				$truncated_description
			);

			$response = $this->make_openai_request($prompt);

			if (isset($response['choices'][0]['message']['content'])) {
				$content = $response['choices'][0]['message']['content'];

				// Parse the response
				$seo_title = '';
				$seo_description = '';

				if (preg_match('/Title: (.+)/', $content, $title_matches)) {
					$seo_title = trim($title_matches[1]);
				}

				if (preg_match('/Description: (.+)/s', $content, $desc_matches)) {
					$seo_description = trim($desc_matches[1]);
				}

				if ($seo_title && $seo_description) {
					$this->log('SEO meta generated successfully for event: ' . $title, 'info');
					return array(
						'title' => $seo_title,
						'description' => $seo_description,
					);
				}
			}

			$this->log('Failed to parse AI response for SEO generation', 'error');
			return false;
		} catch (\Exception $e) {
			// Use base class error handling
			$this->handle_error($e, true, false);
			return false;
		}
	}

	/**
	 * Make request to OpenAI API
	 *
	 * @param string $prompt The prompt to send
	 * @return array Response data
	 * @throws \Exception If API request fails
	 */
	protected function make_openai_request($prompt) {
		$url = 'https://api.openai.com/v1/chat/completions';

		$args = array(
			'method'  => 'POST',
			'timeout' => 45,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => json_encode(array(
				'model'       => 'gpt-3.5-turbo',
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => 'You are a helpful assistant specializing in creating high-quality content for tech events in Sacramento.',
					),
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
				'temperature' => 0.7,
				'max_tokens'  => 500,
			)),
		);

		$this->log('Making OpenAI API request', 'debug');
		$response = wp_remote_post($url, $args);

		if (is_wp_error($response)) {
			throw new \Exception($response->get_error_message());
		}

		$response_code = wp_remote_retrieve_response_code($response);
		if ($response_code !== 200) {
			throw new \Exception('API returned error: ' . $response_code);
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (! $data) {
			throw new \Exception('Failed to decode API response');
		}

		return $data;
	}
}
