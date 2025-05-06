<?php
/**
 * Admin settings
 *
 * @package SacITCentral
 */

namespace SacTech_Events\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings class
 */
class Settings {
    /**
     * Settings tabs
     *
     * @var array
     */
    private $tabs = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->tabs = array(
            'general' => __( 'General', 'sac-tech-events' ),
            'filters' => __( 'Filters', 'sac-tech-events' ),
            'ai' => __( 'AI Settings', 'sac-tech-events' ),
            'advanced' => __( 'Advanced', 'sac-tech-events' ),
        );

        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting( 'sactech_events_general', 'sactech_events_schedule_frequency' );
        register_setting( 'sactech_events_general', 'sactech_events_max_events_per_import' );
        register_setting( 'sactech_events_general', 'sactech_events_default_status' );
        register_setting( 'sactech_events_general', 'sactech_events_auto_publish' );

        // Filter settings
        register_setting( 'sactech_events_filters', 'sactech_events_filter_include' );
        register_setting( 'sactech_events_filters', 'sactech_events_filter_exclude' );
        register_setting( 'sactech_events_filters', 'sactech_events_minimum_score' );
        register_setting( 'sactech_events_filters', 'sactech_events_required_keywords' );

        // AI settings
        register_setting( 'sactech_events_ai', 'sactech_events_ai_provider' );
        register_setting( 'sactech_events_ai', 'sactech_events_ai_api_key' );
        register_setting( 'sactech_events_ai', 'sactech_events_use_ai_for_descriptions' );
        register_setting( 'sactech_events_ai', 'sactech_events_use_ai_for_seo' );
        register_setting( 'sactech_events_ai', 'sactech_events_ai_description_prompt' );
        register_setting( 'sactech_events_ai', 'sactech_events_ai_seo_prompt' );

        // Advanced settings
        register_setting( 'sactech_events_advanced', 'sactech_events_disable_ssl_verify' );
        register_setting( 'sactech_events_advanced', 'sactech_events_request_timeout' );
        register_setting( 'sactech_events_advanced', 'sactech_events_debug_mode' );
        register_setting( 'sactech_events_advanced', 'sactech_events_log_retention_days' );
    }

    /**
     * Render settings page
     */
    public function render() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap sactech-events-settings">
            <h1><?php esc_html_e( 'Sacramento Tech Events Settings', 'sac-tech-events' ); ?></h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ( $this->tabs as $tab_id => $tab_name ) : ?>
                    <a href="?page=sactech-events-settings&tab=<?php echo esc_attr( $tab_id ); ?>" class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab_name ); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="options.php">
                <?php
                // Output settings fields based on active tab
                if ( $active_tab === 'general' ) {
                    settings_fields( 'sactech_events_general' );
                    $this->render_general_settings();
                } elseif ( $active_tab === 'filters' ) {
                    settings_fields( 'sactech_events_filters' );
                    $this->render_filters_settings();
                } elseif ( $active_tab === 'ai' ) {
                    settings_fields( 'sactech_events_ai' );
                    $this->render_ai_settings();
                } elseif ( $active_tab === 'advanced' ) {
                    settings_fields( 'sactech_events_advanced' );
                    $this->render_advanced_settings();
                }

                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render general settings
     */
    private function render_general_settings() {
        $frequency = get_option( 'sactech_events_schedule_frequency', 'daily' );
        $max_events = get_option( 'sactech_events_max_events_per_import', 50 );
        $default_status = get_option( 'sactech_events_default_status', 'draft' );
        $auto_publish = get_option( 'sactech_events_auto_publish', 0 );

        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Import Schedule', 'sac-tech-events' ); ?></th>
                <td>
                    <select name="sactech_events_schedule_frequency">
                        <option value="hourly" <?php selected( $frequency, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'sac-tech-events' ); ?></option>
                        <option value="twicedaily" <?php selected( $frequency, 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'sac-tech-events' ); ?></option>
                        <option value="daily" <?php selected( $frequency, 'daily' ); ?>><?php esc_html_e( 'Daily', 'sac-tech-events' ); ?></option>
                        <option value="weekly" <?php selected( $frequency, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'sac-tech-events' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'How often should the plugin import new events.', 'sac-tech-events' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Maximum Events Per Import', 'sac-tech-events' ); ?></th>
                <td>
                    <input type="number" name="sactech_events_max_events_per_import" value="<?php echo esc_attr( $max_events ); ?>" min="1" max="500" />
                    <p class="description"><?php esc_html_e( 'Maximum number of events to process per import run.', 'sac-tech-events' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Default Event Status', 'sac-tech-events' ); ?></th>
                <td>
                    <select name="sactech_events_default_status">
                        <option value="draft" <?php selected( $default_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'sac-tech-events' ); ?></option>
                        <option value="pending" <?php selected( $default_status, 'pending' ); ?>><?php esc_html_e( 'Pending Review', 'sac-tech-events' ); ?></option>
                        <option value="publish" <?php selected( $default_status, 'publish' ); ?>><?php esc_html_e( 'Published', 'sac-tech-events' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Default status for imported events.', 'sac-tech-events' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Auto-Publish High Scoring Events', 'sac-tech-events' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sactech_events_auto_publish" value="1" <?php checked( $auto_publish, 1 ); ?> />
                        <?php esc_html_e( 'Automatically publish events with a relevance score of 80 or higher', 'sac-tech-events' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Events with high relevance will be published immediately without review.', 'sac-tech-events' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render filters settings
     */
    public function render_filters_settings() {
        $include_terms = get_option( 'sactech_events_filter_include', array() );
        $exclude_terms = get_option( 'sactech_events_filter_exclude', array() );
        $minimum_score = get_option( 'sactech_events_minimum_score', 50 );
        $required_keywords = get_option( 'sactech_events_required_keywords', array() );

        // Convert arrays to strings for textarea
        $include_terms_string = is_array( $include_terms ) ? implode( "\n", $include_terms ) : $include_terms;
        $exclude_terms_string = is_array( $exclude_terms ) ? implode( "\n", $exclude_terms ) : $exclude_terms;
        $required_keywords_string = is_array( $required_keywords ) ? implode( "\n", $required_keywords ) : $required_keywords;

        ?>
        <div class="sactech-events-filters-settings">
            <p>
                <?php esc_html_e( 'Configure the keywords and terms used to filter and score events. These settings determine which events are imported and presented to you.', 'sac-tech-events' ); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Include Terms', 'sac-tech-events' ); ?></th>
                    <td>
                        <textarea name="sactech_events_filter_include" rows="10" cols="50" class="large-text"><?php echo esc_textarea( $include_terms_string ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Enter one keyword/phrase per line. Events containing these terms will receive positive scoring.', 'sac-tech-events' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Exclude Terms', 'sac-tech-events' ); ?></th>
                    <td>
                        <textarea name="sactech_events_filter_exclude" rows="10" cols="50" class="large-text"><?php echo esc_textarea( $exclude_terms_string ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Enter one keyword/phrase per line. Events containing these terms will receive negative scoring.', 'sac-tech-events' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Required Keywords', 'sac-tech-events' ); ?></th>
                    <td>
                        <textarea name="sactech_events_required_keywords" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $required_keywords_string ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Enter one keyword/phrase per line. Events must contain at least one of these terms to be imported.', 'sac-tech-events' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Minimum Relevance Score', 'sac-tech-events' ); ?></th>
                    <td>
                        <input type="range" name="sactech_events_minimum_score" min="0" max="100" value="<?php echo esc_attr( $minimum_score ); ?>" class="sactech-events-range" />
                        <span class="sactech-events-range-value"><?php echo esc_html( $minimum_score ); ?></span>
                        <p class="description"><?php esc_html_e( 'Events with a relevance score below this threshold will be ignored.', 'sac-tech-events' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
				    /**
     * Render AI settings
     */
    public function render_ai_settings() {
			$provider = get_option( 'sactech_events_ai_provider', 'openai' );
			$api_key = get_option( 'sactech_events_ai_api_key', '' );
			$use_for_descriptions = get_option( 'sactech_events_use_ai_for_descriptions', 1 );
			$use_for_seo = get_option( 'sactech_events_use_ai_for_seo', 1 );
			$description_prompt = get_option( 'sactech_events_ai_description_prompt', 'Enhance the following tech event description with more technical context and relevance for IT professionals in Sacramento. Make it sound professional but engaging:' );
			$seo_prompt = get_option( 'sactech_events_ai_seo_prompt', 'Create an SEO-optimized title and meta description for a Sacramento tech event targeting IT professionals. The event is about:' );

			?>
			<table class="form-table">
					<tr>
							<th scope="row"><?php esc_html_e( 'AI Provider', 'sac-tech-events' ); ?></th>
							<td>
									<select name="sactech_events_ai_provider" id="sactech_events_ai_provider">
											<option value="openai" <?php selected( $provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI (GPT)', 'sac-tech-events' ); ?></option>
											<option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>><?php esc_html_e( 'Anthropic (Claude)', 'sac-tech-events' ); ?></option>
											<option value="disabled" <?php selected( $provider, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'sac-tech-events' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Select the AI provider to use for enhancing event descriptions and generating SEO content.', 'sac-tech-events' ); ?></p>
							</td>
					</tr>
					<tr>
							<th scope="row"><?php esc_html_e( 'API Key', 'sac-tech-events' ); ?></th>
							<td>
									<input type="password" name="sactech_events_ai_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off" />
									<p class="description"><?php esc_html_e( 'Enter your API key for the selected AI provider.', 'sac-tech-events' ); ?></p>
							</td>
					</tr>
					<tr>
							<th scope="row"><?php esc_html_e( 'AI Features', 'sac-tech-events' ); ?></th>
							<td>
									<label>
											<input type="checkbox" name="sactech_events_use_ai_for_descriptions" value="1" <?php checked( $use_for_descriptions, 1 ); ?> />
											<?php esc_html_e( 'Enhance event descriptions', 'sac-tech-events' ); ?>
									</label>
									<br>
									<label>
											<input type="checkbox" name="sactech_events_use_ai_for_seo" value="1" <?php checked( $use_for_seo, 1 ); ?> />
											<?php esc_html_e( 'Generate SEO metadata', 'sac-tech-events' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Select which features to use AI for.', 'sac-tech-events' ); ?></p>
							</td>
					</tr>
					<tr>
							<th scope="row"><?php esc_html_e( 'Description Enhancement Prompt', 'sac-tech-events' ); ?></th>
							<td>
									<textarea name="sactech_events_ai_description_prompt" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $description_prompt ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Customize the prompt used for enhancing event descriptions.', 'sac-tech-events' ); ?></p>
							</td>
					</tr>
					<tr>
							<th scope="row"><?php esc_html_e( 'SEO Generation Prompt', 'sac-tech-events' ); ?></th>
							<td>
									<textarea name="sactech_events_ai_seo_prompt" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $seo_prompt ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Customize the prompt used for generating SEO metadata.', 'sac-tech-events' ); ?></p>
							</td>
					</tr>
					<tr>
							<th scope="row"><?php esc_html_e( 'Test AI Connection', 'sac-tech-events' ); ?></th>
							<td>
									<button type="button" class="button" id="sactech_events_test_ai"><?php esc_html_e( 'Test Connection', 'sac-tech-events' ); ?></button>
									<span class="spinner" id="sactech_events_ai_spinner"></span>
									<span id="sactech_events_ai_test_result"></span>
									<p class="description"><?php esc_html_e( 'Test your AI provider connection with current settings.', 'sac-tech-events' ); ?></p>
							</td>
					</tr>
			</table>
			<?php
	}

	/**
	 * Render advanced settings
	 */
	private function render_advanced_settings() {
			$disable_ssl_verify = get_option( 'sactech_events_disable_ssl_verify', 0 );
			$request_timeout = get_option( 'sactech_events_request_timeout', 30 );
			$debug_mode = get_option( 'sactech_events_debug_mode', 0 );
			$log_retention = get_option( 'sactech_events_log_retention_days', 30 );

			?>
			<table class="form-table">
					<tr>
							<th scope="row"><?php esc_html_e( 'Disable SSL Verification', 'sac-tech-events' ); ?></th>
							<td>
									<label>
											<input type="checkbox" name="sactech_events_disable_ssl_verify" value="1" <?php checked( $disable_ssl_verify, 1 ); ?> />
											<?php esc_html_e( 'Disable SSL certificate verification', 'sac-tech-events' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Warning: Only enable this if you are having issues connecting to sources. This is a security risk.', 'sac-tech-events' ); ?></p>
							</td>
					</tr>
					<tr>
							<th scope="row"><?php esc_html_e( 'Request Timeout', 'sac-tech-events' ); ?></th>
							<td>
									<input type="number" name="sactech_events_request_timeout" value="<?php echo esc_attr( $request_timeout ); ?>" min="5" max="120" />
									<p class="description"><?php esc_html_e( 'Timeout in seconds for HTTP requests to event sources.', 'sac-tech-events' ); ?></p>
							</td>
					</tr>
					<tr>
							<th scope="row"><?php esc_html_e( 'Debug Mode', 'sac-tech-events' ); ?></th>
							<td>
									<label>
											<input type="checkbox" name="sactech_events_debug_mode" value="1" <?php checked( $debug_mode, 1 ); ?> />
											<?php esc_html_e( 'Enable debug logging', 'sac-tech-events' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Logs detailed information during event imports for troubleshooting.', 'sac-tech-events' ); ?></p>
							</td>
					</tr>
					<tr>
							<th scope="row"><?php esc_html_e( 'Log Retention', 'sac-tech-events' ); ?></th>
							<td>
									<input type="number" name="sactech_events_log_retention_days" value="<?php echo esc_attr( $log_retention ); ?>" min="1" max="365" />
									<p class="description"><?php esc_html_e( 'Number of days to keep logs before automatic deletion.', 'sac-tech-events' ); ?></p>
							</td>
					</tr>
			</table>
			<?php
	}
}
