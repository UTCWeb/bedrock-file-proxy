<?php
/**
/**
 * Bedrock File Proxy: Admin Settings
 *
 * @package bedrock-file-proxy
 */

namespace BFP;

if ( ! class_exists( 'BFP_Admin' ) ) {

	/**
	 * The BFP Admin class
	 */
	class BFP_Admin {
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
			add_action( 'after_setup_theme', array( $this, 'bfp_admin' ) );
		}

		/**
		 * Cloning is forbidden.
		 *
		 * @since 0.0.1
		 */
		public function __clone() {
			wp_die( "Please don't __clone BFP_Admin" );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 *
		 * @since 0.0.1
		 */
		public function __wakeup() {
			wp_die( "Please don't __wakeup BFP_Admin" );
		}

		/**
		 * Main BFP_Admin instance.
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
		 * BFP: Setup.
		 *
		 * @since 0.0.1
		 */
		public function setup(): void {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_post_bfp_settings', array( $this, 'save_settings' ) );
		}

		/**
		 * BFP: Admin menu.
		 *
		 * @since 0.0.1
		 */
		public function admin_menu(): void {
			add_options_page(
				__( 'Bedrock File Proxy', 'bedrock-file-proxy' ),
				__( 'Bedrock File Proxy', 'bedrock-file-proxy' ),
				'manage_options',
				'bedrock-file-proxy',
				array(
					$this,
					'settings_page',
				)
			);
		}

		/**
		 * BFP: Settings page.
		 *
		 * @since 0.0.1
		 */
		public function settings_page(): void {
			$url = get_option( 'bfp_url' );
			if ( ! $url ) {
				$url = get_option( 'sfp_url', '' );
			}

			$mode = get_option( 'bfp_mode' );
			if ( ! $mode ) {
				$mode = get_option( 'sfp_mode', 'download' );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die(
					esc_html__( 'You do not have sufficient permissions to access this page.', 'bedrock-file-proxy' )
				);
			}

			if ( ! site_in_development() ) {
				wp_die(
					esc_html__( 'URGENT: This plugin is not meant to be run in production environments.', 'bedrock-file-proxy' )
				);
			}
			?>
			<div class="wrap">
				<h2><?php esc_html_e( 'Bedrock File Proxy', 'bedrock-file-proxy' ); ?></h2>

				<?php if ( isset( $_GET['error'] ) ) : ?>
					<div class="error updated"><p><?php esc_html_e( 'There was an error updating the settings', 'bedrock-file-proxy' ); ?></p></div>
				<?php endif; ?>

				<?php if ( isset( $_GET['success'] ) ) : ?>
					<div class="updated success"><p><?php esc_html_e( 'Settings updated!', 'bedrock-file-proxy' ); ?></p></div>
				<?php endif; ?>

				<div id="sfp-settings">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="bfp_settings" />
						<?php wp_nonce_field( 'bfp_settings', 'bfp_settings_nonce' ); ?>
						<table class="form-table">
							<tbody>
								<tr>
									<th scope="row"><label for="sfp_mode"><?php esc_html_e( 'Mode', 'bedrock-file-proxy' ); ?></label></th>
									<td>
										<select name="bfp[mode]" id="sfp_mode">
											<option value="download"<?php selected( 'download', $mode ); ?>><?php esc_html_e( 'Download', 'bedrock-file-proxy' ); ?></option>
											<option value="header"<?php selected( 'header', $mode ); ?>><?php esc_html_e( 'Redirect', 'bedrock-file-proxy' ); ?></option>
										</select>
									</td>
								</tr>

								<tr>
									<th scope="row"><label for="sfp_url"><?php esc_html_e( 'URL', 'bedrock-file-proxy' ); ?></label></th>
									<td>
										<input type="text" name="bfp[url]" id="sfp_url" value="<?php echo esc_url( $url ); ?>" style="width:100%;max-width:500px" />
										<p class="description"><?php esc_html_e( 'This should point to the remote uploads root, including multisite site paths when applicable.', 'bedrock-file-proxy' ); ?></p>
									</td>
								</tr>
							</tbody>
						</table>

						<?php submit_button( __( 'Save Settings', 'bedrock-file-proxy' ) ); ?>
					</form>
				</div>

			</div>
			<?php
		}

		/**
		 * BFP: Save settings.
		 *
		 * @since 0.0.1
		 */
		public function save_settings(): void {
			if ( ! isset( $_POST['bfp_settings_nonce'] ) || ! wp_verify_nonce( $_POST['bfp_settings_nonce'], 'bfp_settings' ) ) {
				wp_die( esc_html__( 'You are not authorized to perform that action', 'bedrock-file-proxy' ) );
			}

			if ( isset( $_POST['bfp']['url'], $_POST['bfp']['mode'] ) ) {
				$url  = sanitize_url( wp_unslash( $_POST['bfp']['url'] ) );
				$mode = wp_unslash( $_POST['bfp']['mode'] );

				update_option( 'bfp_url', $url );
				update_option( 'bfp_mode', 'header' === $mode ? 'header' : 'download' );

				wp_redirect( admin_url( 'options-general.php?page=bedrock-file-proxy&success=1' ) );
			} else {
				wp_redirect( admin_url( 'options-general.php?page=bedrock-file-proxy&error=1' ) );
			}

			exit;
		}

		/**
		 * BFP: Trigger the instance of the BFP_Admin class.
		 *
		 * @since 0.0.1
		 *
		 * @return self
		 */
		public function bfp_admin(): self {
			return self::instance();
		}
	}

}

new BFP_Admin();
