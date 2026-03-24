<?php
/*
 * Plugin Name: Bedrock File Proxy
 * Plugin URI: https://blog.utc.edu
 * Description: Get only the files you need from your production environment. Don't run this in production!
 * Version: 0.1.6
 * Author: University of Tennessee at Chattanooga
 * Author URI: https://www.utc.edu
 */

/**
 * The majority of this plugin is an upgraded and modernized version of the Stage
 * File Proxy plugin published, and deprecated, by Alley Interactive. Further work
 * was done by Charles Leverington, Taoti Creative: https://taoti.com.
 * Adapted for Bedrock WordPress Multisite by PHPStorm AI and UTChatttanooga.
 */

namespace BFP;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Get the minimum version of PHP required by this plugin.
 *
 * @since 0.0.1
 *
 * @return string Minimum version required.
 */
function minimum_php_requirement(): string {
	return '8.0.0';
}

/**
 * Whether PHP installation meets the minimum requirements.
 *
 * @since 0.0.1
 *
 * @return bool True if meets minimum requirements, false otherwise.
 */
function site_meets_php_requirements(): bool {
	return version_compare( phpversion(), minimum_php_requirement(), '>=' );
}

/**
 * Whether the current site is in a non-production environment.
 *
 * @since 0.0.1
 *
 * @return bool True when not in production.
 */
function site_in_development(): bool {
	return 'production' !== wp_get_environment_type();
}

/**
 * Render an admin notice when the PHP version is too old.
 *
 * @since 0.0.1
 */
function render_php_version_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
				/* translators: %s: Minimum required PHP version */
					__( 'Bedrock File Proxy requires PHP version %s or later. Please upgrade PHP or disable the plugin.', 'bedrock-file-proxy' ),
					esc_html( minimum_php_requirement() )
				)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Render an admin notice when the plugin is active in production.
 *
 * @since 0.0.1
 */
function render_production_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php esc_html_e( 'URGENT: Bedrock File Proxy should not be active on Production Environments.', 'bedrock-file-proxy' ); ?>
		</p>
	</div>
	<?php
}

// Ensure PHP version is met first.
if ( ! site_meets_php_requirements() ) {
	add_action( 'admin_notices', __NAMESPACE__ . '\\render_php_version_notice' );
	return;
}

/**
 * Plugin version.
 *
 * Configured to allow for easy version checking and prevent 'update' notices
 * from published version of the plugin this version forked from.
 */
if ( ! defined( 'BEDROCK_FILE_PROXY_VERSION' ) ) {
	define( 'BEDROCK_FILE_PROXY_VERSION', '0.1.4' );
}

if ( ! defined( 'BEDROCK_FILE_PROXY_DIR' ) ) {
	define( 'BEDROCK_FILE_PROXY_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'BEDROCK_FILE_PROXY_URL' ) ) {
	define( 'BEDROCK_FILE_PROXY_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'BEDROCK_FILE_PROXY_FILE' ) ) {
	define( 'BEDROCK_FILE_PROXY_FILE', __FILE__ );
}

require_once BEDROCK_FILE_PROXY_DIR . 'includes/admin.php';

if ( site_in_development() ) {
	require_once BEDROCK_FILE_PROXY_DIR . 'includes/image-processing.php';
} else {
	add_action( 'admin_notices', __NAMESPACE__ . '\\render_production_notice' );
}
