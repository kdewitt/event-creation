<?php

/**
 * Filter Manager
 *
 * @package SacITCentral
 */

namespace SacTech_Events\Managers;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Filter Manager class
 */
class Filter_Manager extends Manager {
	/**
	 * Tech categories and keywords
	 *
	 * @var array
	 */
	private $tech_categories = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		// Call parent constructor first
		parent::__construct();

		// Initialize tech categories
		$this->init_tech_categories();
	}

	/**
	 * Initialize hooks
	 *
	 * Implementation of abstract method from parent class
	 */
	protected function init_hooks() {
		// Filter to allow modifying tech categories
		add_filter('sactech_events_tech_categories', array($this, 'filter_tech_categories'), 10, 1);

		// Filter to allow modifying relevance score calculation
		add_filter('sactech_events_relevance_score', array($this, 'filter_relevance_score'), 10, 2);
	}

	/**
	 * Filter tech categories
	 *
	 * @param array $categories Default categories
	 * @return array Modified categories
	 */
	public function filter_tech_categories($categories) {
		return $categories;
	}

	/**
	 * Filter relevance score
	 *
	 * @param int $score Calculated score
	 * @param array $event_data Event data
	 * @return int Modified score
	 */
	public function filter_relevance_score($score, $event_data) {
		return $score;
	}

	/**
	 * Initialize tech categories
	 */
	private function init_tech_categories() {
		// Default tech categories
		$default_categories = array(
			'Web Development' => array('html', 'css', 'javascript', 'php', 'wordpress', 'drupal', 'laravel', 'react', 'angular', 'vue', 'node.js', 'front-end', 'back-end', 'full-stack', 'web'),
			'Mobile Development' => array('android', 'ios', 'swift', 'kotlin', 'react native', 'flutter', 'mobile app', 'mobile development'),
			'DevOps' => array('devops', 'docker', 'kubernetes', 'aws', 'azure', 'cloud', 'ci/cd', 'jenkins', 'terraform', 'ansible', 'infrastructure'),
			'Data Science' => array('data science', 'machine learning', 'ai', 'artificial intelligence', 'big data', 'analytics', 'data mining', 'data visualization', 'statistics'),
			'Security' => array('security', 'cybersecurity', 'infosec', 'hacking', 'penetration testing', 'encryption', 'firewall', 'compliance'),
			'Blockchain' => array('blockchain', 'cryptocurrency', 'bitcoin', 'ethereum', 'smart contracts', 'web3', 'nft'),
			'UI/UX Design' => array('ui', 'ux', 'user interface', 'user experience', 'design', 'wireframe', 'prototype', 'figma', 'sketch'),
			'Project Management' => array('agile', 'scrum', 'kanban', 'project management', 'product management', 'pm', 'pmo'),
			'Database' => array('sql', 'nosql', 'database', 'mongodb', 'postgresql', 'mysql', 'oracle', 'sql server', 'redis'),
			'QA & Testing' => array('qa', 'testing', 'quality assurance', 'test automation', 'selenium', 'cypress', 'jest', 'unit test'),
			'IoT' => array('iot', 'internet of things', 'embedded systems', 'arduino', 'raspberry pi', 'sensors'),
			'AR/VR' => array('ar', 'vr', 'augmented reality', 'virtual reality', 'metaverse', 'unity', 'unreal'),
			'Networking' => array('networking', 'network', 'cisco', 'router', 'switch', 'firewall', 'vpn', 'dns'),
			'Languages & Frameworks' => array('python', 'java', 'c#', '.net', 'ruby', 'go', 'rust', 'scala', 'typescript')
		);

		// Get stored categories or use defaults
		// Use the parent class get_option method
		$this->tech_categories = $this->get_option('sactech_events_tech_categories', $default_categories);

		// Apply filter to allow modifications
		$this->tech_categories = apply_filters('sactech_events_tech_categories', $this->tech_categories);

		$this->log('Tech categories initialized with ' . count($this->tech_categories) . ' categories', 'debug');
	}

	/**
	 * Filter event based on relevance
	 *
	 * @param array $event_data Event data
	 * @return array Result with passed status, score, and reason
	 */
	public function filter_event($event_data) {
		$result = array(
			'passed' => false,
			'score' => 0,
			'reason' => '',
		);

		// Check for required fields
		if (empty($event_data['title'])) {
			$result['reason'] = 'Missing title';
			return $result;
		}

		if (empty($event_data['start_date'])) {
			$result['reason'] = 'Missing start date';
			return $result;
		}

		// Calculate relevance score
		$score = $this->calculate_relevance_score($event_data);
		$result['score'] = $score;

		// Get minimum score from settings using parent class method
		$min_score = $this->get_option('sactech_events_min_relevance_score', 50);

		// Check if event passes minimum score
		if ($score >= $min_score) {
			$result['passed'] = true;
		} else {
			$result['reason'] = sprintf('Low relevance score: %d (minimum: %d)', $score, $min_score);
		}

		// Check for blacklisted keywords
		$blacklist = explode("\n", $this->get_option('sactech_events_blacklist_keywords', ''));
		$blacklist = array_map('trim', $blacklist);
		$blacklist = array_filter($blacklist);

		if (! empty($blacklist)) {
			$content = strtolower($event_data['title'] . ' ' . ($event_data['description'] ?? ''));

			foreach ($blacklist as $keyword) {
				if (! empty($keyword) && stripos($content, $keyword) !== false) {
					$result['passed'] = false;
					$result['reason'] = sprintf('Contains blacklisted keyword: %s', $keyword);
					$this->log(sprintf('Event "%s" rejected due to blacklisted keyword: %s', $event_data['title'], $keyword), 'info');
					break;
				}
			}
		}

		if ($result['passed']) {
			$this->log(sprintf('Event "%s" passed filtering with score: %d', $event_data['title'], $score), 'info');
		} else {
			$this->log(sprintf('Event "%s" failed filtering: %s', $event_data['title'], $result['reason']), 'info');
		}

		return $result;
	}

	/**
	 * Calculate relevance score for an event
	 *
	 * @param array $event_data Event data
	 * @return int Score from 0-100
	 */
	public function calculate_relevance_score($event_data) {
		$score = 0;
		$content = strtolower($event_data['title'] . ' ' . ($event_data['description'] ?? ''));

		// Check for tech keywords in content
		$tech_keywords = $this->get_all_tech_keywords();
		$found_keywords = array();

		foreach ($tech_keywords as $keyword) {
			if (stripos($content, $keyword) !== false) {
				$found_keywords[] = $keyword;
			}
		}

		// Score based on keyword density
		$keyword_count = count($found_keywords);
		if ($keyword_count > 10) {
			$score += 50; // High tech relevance
		} elseif ($keyword_count > 5) {
			$score += 40; // Medium tech relevance
		} elseif ($keyword_count > 2) {
			$score += 30; // Low tech relevance
		} elseif ($keyword_count > 0) {
			$score += 20; // Minimal tech relevance
		}

		// Boost score for known tech event words in title
		$tech_event_terms = array('hackathon', 'meetup', 'conference', 'workshop', 'webinar', 'tech', 'software', 'developer', 'coding', 'programming', 'startup');

		foreach ($tech_event_terms as $term) {
			if (stripos($event_data['title'], $term) !== false) {
				$score += 15;
				break; // Only add boost once
			}
		}

		// Boost score for Sacramento-related terms
		$sacramento_terms = array('sacramento', 'sac', 'folsom', 'roseville', 'rocklin', 'davis', 'elk grove', 'rancho cordova', 'citrus heights', 'natomas', 'west sac', 'downtown');

		foreach ($sacramento_terms as $term) {
			if (stripos($content, $term) !== false) {
				$score += 15;
				break; // Only add boost once
			}
		}

		// Apply filter to allow modifications to the score
		$score = apply_filters('sactech_events_relevance_score', $score, $event_data);

		// Cap score between 0 and 100
		$final_score = max(0, min(100, $score));

		if ($keyword_count > 0) {
			$this->log(sprintf(
				'Event "%s" scored %d with %d tech keywords found',
				$event_data['title'],
				$final_score,
				$keyword_count
			), 'debug');
		}

		return $final_score;
	}

	/**
	 * Get all tech keywords
	 *
	 * @return array All keywords
	 */
	private function get_all_tech_keywords() {
		$all_keywords = array();

		foreach ($this->tech_categories as $category => $keywords) {
			$all_keywords = array_merge($all_keywords, $keywords);
		}

		return array_unique($all_keywords);
	}

	/**
	 * Detect tech categories from content
	 *
	 * @param string $content Content to analyze
	 * @return array Detected categories
	 */
	public function detect_tech_categories($content) {
		$content = strtolower($content);
		$detected_categories = array();

		// Check each category's keywords
		foreach ($this->tech_categories as $category => $keywords) {
			foreach ($keywords as $keyword) {
				if (stripos($content, $keyword) !== false) {
					$detected_categories[] = $category;
					break; // Found a match for this category, move to next
				}
			}
		}

		$unique_categories = array_unique($detected_categories);

		$this->log(sprintf(
			'Detected %d tech categories for content',
			count($unique_categories)
		), 'debug');

		return $unique_categories;
	}

	/**
	 * Update tech categories
	 *
	 * @param array $categories New categories
	 * @return bool Success
	 */
	public function update_tech_categories($categories) {
		if (!is_array($categories)) {
			return false;
		}

		// Use parent class update_option method
		$success = $this->update_option('sactech_events_tech_categories', $categories);

		if ($success) {
			$this->tech_categories = $categories;
			$this->log('Tech categories updated successfully', 'info');
		} else {
			$this->log('Failed to update tech categories', 'error');
		}

		return $success;
	}
}
