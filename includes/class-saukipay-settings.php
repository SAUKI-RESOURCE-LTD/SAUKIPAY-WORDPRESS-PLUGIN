<?php
/**
 * Admin settings.
 *
 * @package SaukiPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles global Sauki Pay settings.
 */
class SaukiPay_Settings {
	const OPTION_NAME = 'saukipay_settings';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'         => 'yes',
			'test_mode'       => 'yes',
			'test_public_key' => '',
			'test_secret_key' => '',
			'live_public_key' => '',
			'live_secret_key' => '',
			'api_base_url'          => 'https://www.server.saukipay.net/api/v1',
			'button_text'           => 'Pay with Sauki Pay',
			'form_success_page_id'  => 0,
			'form_failure_page_id'  => 0,
		);
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function all() {
		$saved = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $key, $default = '' ) {
		$settings = $this->all();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Whether global plugin processing is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === $this->get( 'enabled', 'yes' );
	}

	/**
	 * Whether test mode is active.
	 *
	 * @return bool
	 */
	public function is_test_mode() {
		return 'yes' === $this->get( 'test_mode', 'yes' );
	}

	/**
	 * Current public key.
	 *
	 * @return string
	 */
	public function public_key() {
		return $this->is_test_mode() ? trim( (string) $this->get( 'test_public_key' ) ) : trim( (string) $this->get( 'live_public_key' ) );
	}

	/**
	 * Current secret key.
	 *
	 * @return string
	 */
	public function secret_key() {
		return $this->is_test_mode() ? trim( (string) $this->get( 'test_secret_key' ) ) : trim( (string) $this->get( 'live_secret_key' ) );
	}

	/**
	 * Base API URL.
	 *
	 * @return string
	 */
	public function api_base_url() {
		return untrailingslashit( esc_url_raw( $this->get( 'api_base_url', 'https://www.server.saukipay.net/api/v1' ) ) );
	}

	/**
	 * Add admin menu item.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Sauki Pay', 'saukipay' ),
			__( 'Sauki Pay', 'saukipay' ),
			'manage_options',
			'saukipay-settings',
			array( $this, 'render_page' ),
			'dashicons-money-alt',
			56
		);
	}

	/**
	 * Register option and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'saukipay_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$current  = $this->all();

		return array(
			'enabled'         => empty( $input['enabled'] ) ? 'no' : 'yes',
			'test_mode'       => empty( $input['test_mode'] ) ? 'no' : 'yes',
			'test_public_key' => isset( $input['test_public_key'] ) ? sanitize_text_field( wp_unslash( $input['test_public_key'] ) ) : '',
			'test_secret_key' => ! empty( $input['test_secret_key'] ) ? sanitize_text_field( wp_unslash( $input['test_secret_key'] ) ) : $current['test_secret_key'],
			'live_public_key' => isset( $input['live_public_key'] ) ? sanitize_text_field( wp_unslash( $input['live_public_key'] ) ) : '',
			'live_secret_key' => ! empty( $input['live_secret_key'] ) ? sanitize_text_field( wp_unslash( $input['live_secret_key'] ) ) : $current['live_secret_key'],
			'api_base_url'          => ! empty( $input['api_base_url'] ) ? esc_url_raw( wp_unslash( $input['api_base_url'] ) ) : $defaults['api_base_url'],
			'button_text'           => ! empty( $input['button_text'] ) ? sanitize_text_field( wp_unslash( $input['button_text'] ) ) : $defaults['button_text'],
			'form_success_page_id'  => isset( $input['form_success_page_id'] ) ? absint( $input['form_success_page_id'] ) : 0,
			'form_failure_page_id'  => isset( $input['form_failure_page_id'] ) ? absint( $input['form_failure_page_id'] ) : 0,
		);
	}

	/**
	 * Callback URL.
	 *
	 * @return string
	 */
	public static function callback_url() {
		return add_query_arg( 'saukipay-listener', 'callback', home_url( '/' ) );
	}

	/**
	 * WooCommerce callback URL.
	 *
	 * @return string
	 */
	public static function woocommerce_callback_url() {
		if ( function_exists( 'WC' ) && WC() ) {
			return WC()->api_request_url( 'saukipay_callback' );
		}

		return home_url( '/wc-api/saukipay_callback/' );
	}

	/**
	 * Webhook URL.
	 *
	 * @return string
	 */
	public static function webhook_url() {
		return add_query_arg( 'saukipay-listener', 'webhook', home_url( '/' ) );
	}

	/**
	 * Standalone payment form result URL.
	 *
	 * @param string $status Payment result status.
	 * @param string $message Result message.
	 * @param string $fallback_url Fallback URL when no page is configured.
	 * @return string
	 */
	public function form_result_url( $status, $message, $fallback_url = '' ) {
		$page_key = 'success' === sanitize_key( $status ) ? 'form_success_page_id' : 'form_failure_page_id';
		$page_id  = absint( $this->get( $page_key, 0 ) );
		$url      = $page_id ? get_permalink( $page_id ) : '';

		if ( ! $url && '' !== $fallback_url ) {
			$url = $fallback_url;
		}

		if ( ! $url ) {
			$url = home_url( '/' );
		}

		return add_query_arg(
			array(
				'saukipay_result'  => sanitize_key( $status ),
				'saukipay_message' => $message,
			),
			$url
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sauki Pay Settings', 'saukipay' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'saukipay_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable plugin', 'saukipay' ); ?></th>
							<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" value="yes" <?php checked( $settings['enabled'], 'yes' ); ?>> <?php esc_html_e( 'Enable Sauki Pay payments', 'saukipay' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Test mode', 'saukipay' ); ?></th>
							<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[test_mode]" value="yes" <?php checked( $settings['test_mode'], 'yes' ); ?>> <?php esc_html_e( 'Use test keys', 'saukipay' ); ?></label></td>
						</tr>
						<?php
						$this->render_text_field( 'test_public_key', __( 'Test public key', 'saukipay' ), $settings['test_public_key'] );
						$this->render_password_field( 'test_secret_key', __( 'Test secret key', 'saukipay' ), $settings['test_secret_key'] );
						$this->render_text_field( 'live_public_key', __( 'Live public key', 'saukipay' ), $settings['live_public_key'] );
						$this->render_password_field( 'live_secret_key', __( 'Live secret key', 'saukipay' ), $settings['live_secret_key'] );
						$this->render_text_field( 'api_base_url', __( 'API base URL', 'saukipay' ), $settings['api_base_url'] );
						$this->render_text_field( 'button_text', __( 'Button text', 'saukipay' ), $settings['button_text'] );
						$this->render_page_field( 'form_success_page_id', __( 'Success redirect page', 'saukipay' ), $settings['form_success_page_id'], __( 'Optional. Standalone payment form and GiveWP customers are sent here after a verified successful payment.', 'saukipay' ) );
						$this->render_page_field( 'form_failure_page_id', __( 'Failure redirect page', 'saukipay' ), $settings['form_failure_page_id'], __( 'Optional. Standalone payment form and GiveWP customers are sent here when payment fails or cannot be verified.', 'saukipay' ) );
						?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Plugin callback URL', 'saukipay' ); ?></th>
							<td><code><?php echo esc_html( self::callback_url() ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'WooCommerce callback URL', 'saukipay' ); ?></th>
							<td><code><?php echo esc_html( self::woocommerce_callback_url() ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Webhook URL', 'saukipay' ); ?></th>
							<td><code><?php echo esc_html( self::webhook_url() ); ?></code></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'WooCommerce gateway', 'saukipay' ); ?></th>
							<td>
								<?php if ( class_exists( 'WooCommerce' ) ) : ?>
									<p>
										<strong><?php esc_html_e( 'Available', 'saukipay' ); ?></strong>
										<?php esc_html_e( 'Sauki Pay can be enabled from WooCommerce payment settings.', 'saukipay' ); ?>
									</p>
									<p>
										<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>">
											<?php esc_html_e( 'Open WooCommerce Payments', 'saukipay' ); ?>
										</a>
									</p>
								<?php else : ?>
									<p>
										<strong><?php esc_html_e( 'Not active', 'saukipay' ); ?></strong>
										<?php esc_html_e( 'Install and activate WooCommerce to use Sauki Pay at WooCommerce checkout.', 'saukipay' ); ?>
									</p>
									<p class="description"><?php esc_html_e( 'The shortcode payment form works without WooCommerce.', 'saukipay' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'GiveWP gateway', 'saukipay' ); ?></th>
							<td>
								<?php if ( class_exists( 'Give' ) || defined( 'GIVE_VERSION' ) || function_exists( 'give' ) ) : ?>
									<p>
										<strong><?php esc_html_e( 'Available', 'saukipay' ); ?></strong>
										<?php esc_html_e( 'Sauki Pay can be enabled from GiveWP payment gateway settings.', 'saukipay' ); ?>
									</p>
									<p>
										<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways' ) ); ?>">
											<?php esc_html_e( 'Open GiveWP Gateways', 'saukipay' ); ?>
										</a>
									</p>
								<?php else : ?>
									<p>
										<strong><?php esc_html_e( 'Not active', 'saukipay' ); ?></strong>
										<?php esc_html_e( 'Install and activate GiveWP to accept donations with Sauki Pay.', 'saukipay' ); ?>
									</p>
									<p class="description"><?php esc_html_e( 'The shortcode payment form works without GiveWP.', 'saukipay' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
			<p class="description saukipay-admin-credit">
				<?php esc_html_e( 'Sauki Pay WordPress Plugin developed by Ayomikun Oloyede, Co-founder / Lead backend Engineer.', 'saukipay' ); ?>
				<a href="https://github.com/ayo83" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GitHub', 'saukipay' ); ?></a>
				<span aria-hidden="true"> | </span>
				<a href="https://www.linkedin.com/in/ayo-oloyede-078907164/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'LinkedIn', 'saukipay' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render a text input row.
	 *
	 * @param string $key Field key.
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return void
	 */
	private function render_text_field( $key, $label, $value ) {
		?>
		<tr>
			<th scope="row"><label for="saukipay_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input type="text" class="regular-text" id="saukipay_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>"></td>
		</tr>
		<?php
	}

	/**
	 * Render a page select row.
	 *
	 * @param string $key Field key.
	 * @param string $label Field label.
	 * @param int    $value Selected page ID.
	 * @param string $description Field description.
	 * @return void
	 */
	private function render_page_field( $key, $label, $value, $description ) {
		?>
		<tr>
			<th scope="row"><label for="saukipay_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => self::OPTION_NAME . '[' . $key . ']',
						'id'                => 'saukipay_' . $key,
						'selected'          => absint( $value ),
						'show_option_none'  => __( 'Use default page', 'saukipay' ),
						'option_none_value' => '0',
					)
				);
				?>
				<p class="description"><?php echo esc_html( $description ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render a password input row.
	 *
	 * @param string $key Field key.
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return void
	 */
	private function render_password_field( $key, $label, $value ) {
		?>
		<tr>
			<th scope="row"><label for="saukipay_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="password" autocomplete="new-password" class="regular-text" id="saukipay_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="" placeholder="<?php echo esc_attr( '' !== $value ? __( 'Configured', 'saukipay' ) : '' ); ?>">
				<p class="description"><?php esc_html_e( 'Leave blank to keep the existing secret key.', 'saukipay' ); ?></p>
			</td>
		</tr>
		<?php
	}
}
