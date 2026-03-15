<?php
/**
 * Stage File Proxy: Admin Settings
 *
 * @package stage-file-proxy
 */

namespace SFP;

if ( ! class_exists( 'SFP_Admin' ) ) {

	/**
	 * The SFP Admin class
	 */
	class SFP_Admin {
		/**
		 * The single instance of the class.
		 *
		 * @since 0.0.1
		 *
		 * @var self|null
		 */
		private static ?self $instance = null;

		/**
		 * Constructor.
		 *
		 * @since 0.0.1
		 */
		public function __construct() {
			add_action( 'after_setup_theme', array( $this, 'sfp_admin' ) );
		}

		/**
		 * Cloning is forbidden.
		 *
		 * @since 0.0.1
		 */
		public function __clone() {
			wp_die( "Please don't __clone SFP_Admin" );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 *
		 * @since 0.0.1
		 */
		public function __wakeup() {
			wp_die( "Please don't __wakeup SFP_Admin" );
		}

		/**
		 * Main SFP_Admin instance.
		 *
		 * @since 0.0.1
		 *
		 * @return self
		 */
		public static function instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
				self::$instance->setup();
			}

			return self::$instance;
		}

		/**
		 * SFP: Setup.
		 *
		 * @since 0.0.1
		 */
		public function setup(): void {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_post_sfp_settings', array( $this, 'save_settings' ) );
		}

		/**
		 * SFP: Admin menu.
		 *
		 * @since 0.0.1
		 */
		public function admin_menu(): void {
			add_options_page(
				__( 'Stage File Proxy', 'stage-file-proxy' ),
				__( 'Stage File Proxy', 'stage-file-proxy' ),
				'manage_options',
				'stage-file-proxy',
				array(
					$this,
					'settings_page',
				)
			);
		}

		/**
		 * SFP: Settings page.
		 *
		 * @since 0.0.1
		 */
		public function settings_page(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die(
					esc_html__( 'You do not have sufficient permissions to access this page.', 'stage-file-proxy' )
				);
			}

			if ( ! site_in_development() ) {
				wp_die(
					esc_html__( 'URGENT: This plugin is not meant to be run in production environments.', 'stage-file-proxy' )
				);
			}
			?>
			<div class="wrap">
				<h2><?php esc_html_e( 'Stage File Proxy', 'stage-file-proxy' ); ?></h2>

				<?php if ( isset( $_GET['error'] ) ) : ?>
					<div class="error updated"><p><?php esc_html_e( 'There was an error updating the settings', 'stage-file-proxy' ); ?></p></div>
				<?php endif; ?>

				<?php if ( isset( $_GET['success'] ) ) : ?>
					<div class="updated success"><p><?php esc_html_e( 'Settings updated!', 'stage-file-proxy' ); ?></p></div>
				<?php endif; ?>

				<div id="sfp-settings">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="sfp_settings" />
						<?php wp_nonce_field( 'sfp_settings', 'sfp_settings_nonce' ); ?>
						<table class="form-table">
							<tbody>
								<tr>
									<th scope="row"><label for="sfp_mode"><?php esc_html_e( 'Mode', 'stage-file-proxy' ); ?></label></th>
									<td>
										<select name="sfp[mode]" id="sfp_mode">
											<option value="download"<?php selected( 'download', get_option( 'sfp_mode' ) ); ?>><?php esc_html_e( 'Download', 'stage-file-proxy' ); ?></option>
											<option value="header"<?php selected( 'header', get_option( 'sfp_mode' ) ); ?>><?php esc_html_e( 'Redirect', 'stage-file-proxy' ); ?></option>
										</select>
									</td>
								</tr>

								<tr>
									<th scope="row"><label for="sfp_url"><?php esc_html_e( 'URL', 'stage-file-proxy' ); ?></label></th>
									<td>
										<input type="text" name="sfp[url]" id="sfp_url" value="<?php echo esc_url( get_option( 'sfp_url', '' ) ); ?>" style="width:100%;max-width:500px" />
										<p class="description"><?php esc_html_e( "This should point to the site's uploads directory", 'stage-file-proxy' ); ?></p>
									</td>
								</tr>
							</tbody>
						</table>

						<?php submit_button( __( 'Save Settings', 'stage-file-proxy' ) ); ?>
					</form>
				</div>

			</div>
			<?php
		}

		/**
		 * SFP: Save settings.
		 *
		 * @since 0.0.1
		 */
		public function save_settings(): void {
			if ( ! isset( $_POST['sfp_settings_nonce'] ) || ! wp_verify_nonce( $_POST['sfp_settings_nonce'], 'sfp_settings' ) ) {
				wp_die( esc_html__( 'You are not authorized to perform that action', 'stage-file-proxy' ) );
			}

			if ( isset( $_POST['sfp']['url'], $_POST['sfp']['mode'] ) ) {
				$url  = sanitize_url( wp_unslash( $_POST['sfp']['url'] ) );
				$mode = wp_unslash( $_POST['sfp']['mode'] );

				update_option( 'sfp_url', $url );
				update_option( 'sfp_mode', 'header' === $mode ? 'header' : 'download' );
				wp_redirect( admin_url( 'options-general.php?page=stage-file-proxy&success=1' ) );
			} else {
				wp_redirect( admin_url( 'options-general.php?page=stage-file-proxy&error=1' ) );
			}

			exit;
		}

		/**
		 * SFP: Trigger the instance of the SFP_Admin class.
		 *
		 * @since 0.0.1
		 *
		 * @return self
		 */
		public function sfp_admin(): self {
			return self::instance();
		}
	}

}

new SFP_Admin();
