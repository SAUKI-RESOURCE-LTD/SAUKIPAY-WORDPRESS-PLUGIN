<?php
/**
 * Shortcode payment form.
 *
 * @package SaukiPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and processes [saukipay_payment_form].
 */
class SaukiPay_Payment_Form {
	const BUILDER_OPTION_NAME = 'saukipay_form_builder';

	/**
	 * Settings service.
	 *
	 * @var SaukiPay_Settings
	 */
	private $settings;

	/**
	 * API client.
	 *
	 * @var SaukiPay_API
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param SaukiPay_Settings $settings Settings service.
	 * @param SaukiPay_API      $api API client.
	 */
	public function __construct( SaukiPay_Settings $settings, SaukiPay_API $api ) {
		$this->settings = $settings;
		$this->api      = $api;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_shortcode( 'saukipay_payment_form', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_saukipay_save_form_builder', array( $this, 'save_form_builder' ) );
		add_action( 'admin_post_saukipay_form_pay', array( $this, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_saukipay_form_pay', array( $this, 'handle_submit' ) );
	}

	/**
	 * Register frontend assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( 'saukipay-form', SAUKIPAY_URL . 'assets/css/saukipay-form.css', array(), SAUKIPAY_VERSION );
		wp_register_script( 'saukipay-form', SAUKIPAY_URL . 'assets/js/saukipay-form.js', array(), SAUKIPAY_VERSION, true );
	}

	/**
	 * Load form styles on the admin form builder preview.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'sauki-pay_page_saukipay-payment-form' !== $hook ) {
			return;
		}

		$this->register_assets();
		wp_enqueue_style( 'saukipay-form' );
	}

	/**
	 * Render shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$form_settings = $this->get_form_builder_settings();
		$atts = shortcode_atts(
			array(
				'amount'       => $form_settings['amount'],
				'currency'     => $form_settings['currency'],
				'title'        => $form_settings['title'],
				'button_text'  => $form_settings['button_text'],
				'fixed_amount' => $form_settings['fixed_amount'],
			),
			$atts,
			'saukipay_payment_form'
		);

		wp_enqueue_style( 'saukipay-form' );
		wp_enqueue_script( 'saukipay-form' );

		$fixed_amount = filter_var( $atts['fixed_amount'], FILTER_VALIDATE_BOOLEAN );
		$amount       = '' !== $atts['amount'] ? (float) $atts['amount'] : 0;
		$currency     = sanitize_text_field( strtoupper( $atts['currency'] ) );

		ob_start();
		$this->render_result_notice();
		?>
		<div class="saukipay-form-shell">
			<div class="saukipay-form-brand">
				<div class="saukipay-form-brand-main">
					<span class="saukipay-form-icon"><img src="<?php echo esc_url( SAUKIPAY_URL . 'assets/images/saukipay-icon.png' ); ?>" alt=""></span>
					<span class="saukipay-form-wordmark"><span>Sauki</span><strong>PAY</strong></span>
				</div>
				<span class="saukipay-form-secure"><?php esc_html_e( 'Secure checkout', 'saukipay' ); ?></span>
			</div>
			<form class="saukipay-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<div class="saukipay-form-heading">
					<h3><?php echo esc_html( $atts['title'] ); ?></h3>
					<?php if ( $amount > 0 ) : ?>
						<strong><?php echo esc_html( $currency . ' ' . number_format_i18n( $amount, 2 ) ); ?></strong>
					<?php endif; ?>
				</div>
				<input type="hidden" name="action" value="saukipay_form_pay">
				<input type="hidden" name="currency" value="<?php echo esc_attr( $currency ); ?>">
				<input type="hidden" name="fixed_amount" value="<?php echo esc_attr( $fixed_amount ? 'yes' : 'no' ); ?>">
				<?php wp_nonce_field( 'saukipay_form_pay', 'saukipay_nonce' ); ?>

				<label>
					<span><?php esc_html_e( 'Full name', 'saukipay' ); ?></span>
					<input type="text" name="full_name" required maxlength="120" autocomplete="name" placeholder="<?php esc_attr_e( 'Ada Lovelace', 'saukipay' ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Email address', 'saukipay' ); ?></span>
					<input type="email" name="email" required maxlength="120" autocomplete="email" placeholder="<?php esc_attr_e( 'customer@example.com', 'saukipay' ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Phone number', 'saukipay' ); ?></span>
					<input type="tel" name="phone" required maxlength="30" autocomplete="tel" placeholder="<?php esc_attr_e( '08012345678', 'saukipay' ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Amount', 'saukipay' ); ?></span>
					<input type="number" name="amount" min="1" step="0.01" required value="<?php echo esc_attr( $amount > 0 ? $amount : '' ); ?>" <?php disabled( $fixed_amount ); ?>>
					<?php if ( $fixed_amount ) : ?>
						<input type="hidden" name="amount" value="<?php echo esc_attr( $amount ); ?>">
					<?php endif; ?>
				</label>
				<button type="submit">
					<span><?php echo esc_html( $atts['button_text'] ); ?></span>
					<span aria-hidden="true">→</span>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add form builder submenu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'saukipay-settings',
			__( 'Payment Form', 'saukipay' ),
			__( 'Payment Form', 'saukipay' ),
			'manage_options',
			'saukipay-payment-form',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Default form builder settings.
	 *
	 * @return array
	 */
	public function get_form_builder_defaults() {
		return array(
			'title'        => 'Pay with Sauki Pay',
			'amount'       => '',
			'currency'     => 'NGN',
			'button_text'  => $this->settings->get( 'button_text', 'Pay with Sauki Pay' ),
			'fixed_amount' => 'no',
		);
	}

	/**
	 * Saved form builder settings.
	 *
	 * @return array
	 */
	public function get_form_builder_settings() {
		$saved = get_option( self::BUILDER_OPTION_NAME, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $this->get_form_builder_defaults() );
	}

	/**
	 * Save form builder settings.
	 *
	 * @return void
	 */
	public function save_form_builder() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Sauki Pay forms.', 'saukipay' ), 403 );
		}

		check_admin_referer( 'saukipay_save_form_builder' );

		$title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$amount       = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : 0;
		$currency     = isset( $_POST['currency'] ) ? sanitize_text_field( strtoupper( wp_unslash( $_POST['currency'] ) ) ) : 'NGN';
		$button_text  = isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '';
		$fixed_amount = isset( $_POST['fixed_amount'] ) ? 'yes' : 'no';

		update_option(
			self::BUILDER_OPTION_NAME,
			array(
				'title'        => '' !== $title ? $title : 'Pay with Sauki Pay',
				'amount'       => $amount > 0 ? (string) $amount : '',
				'currency'     => '' !== $currency ? $currency : 'NGN',
				'button_text'  => '' !== $button_text ? $button_text : 'Pay with Sauki Pay',
				'fixed_amount' => $fixed_amount,
			),
			false
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'saukipay-payment-form',
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render form builder page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$form      = $this->get_form_builder_settings();
		$shortcode = $this->build_shortcode( $form );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sauki Pay Payment Form', 'saukipay' ); ?></h1>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Payment form settings saved.', 'saukipay' ); ?></p></div>
			<?php endif; ?>
			<div style="display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:24px;align-items:start;max-width:1180px;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;">
					<input type="hidden" name="action" value="saukipay_save_form_builder">
					<?php wp_nonce_field( 'saukipay_save_form_builder' ); ?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="saukipay_form_title"><?php esc_html_e( 'Form title', 'saukipay' ); ?></label></th>
								<td><input class="regular-text" id="saukipay_form_title" type="text" name="title" value="<?php echo esc_attr( $form['title'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="saukipay_form_amount"><?php esc_html_e( 'Amount', 'saukipay' ); ?></label></th>
								<td><input class="regular-text" id="saukipay_form_amount" type="number" min="0" step="0.01" name="amount" value="<?php echo esc_attr( $form['amount'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="saukipay_form_currency"><?php esc_html_e( 'Currency', 'saukipay' ); ?></label></th>
								<td><input class="regular-text" id="saukipay_form_currency" type="text" maxlength="3" name="currency" value="<?php echo esc_attr( $form['currency'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="saukipay_form_button"><?php esc_html_e( 'Button text', 'saukipay' ); ?></label></th>
								<td><input class="regular-text" id="saukipay_form_button" type="text" name="button_text" value="<?php echo esc_attr( $form['button_text'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Fixed amount', 'saukipay' ); ?></th>
								<td><label><input type="checkbox" name="fixed_amount" value="yes" <?php checked( $form['fixed_amount'], 'yes' ); ?>> <?php esc_html_e( 'Customers cannot edit the amount', 'saukipay' ); ?></label></td>
							</tr>
						</tbody>
					</table>
					<?php submit_button( __( 'Save Form', 'saukipay' ) ); ?>
				</form>
				<div style="display:grid;gap:18px;">
					<div style="background:#102f3a;color:#fff;border-radius:8px;padding:20px;">
						<h2 style="color:#fff;margin-top:0;"><?php esc_html_e( 'Generated Shortcode', 'saukipay' ); ?></h2>
						<p><?php esc_html_e( 'Copy this shortcode into any page or post.', 'saukipay' ); ?></p>
						<textarea readonly rows="5" style="width:100%;font-family:monospace;border-radius:6px;padding:10px;"><?php echo esc_textarea( $shortcode ); ?></textarea>
					</div>
					<div style="background:#f4f8f9;border:1px solid #dce7ea;border-radius:8px;padding:18px;">
						<h2 style="margin-top:0;"><?php esc_html_e( 'Form Preview', 'saukipay' ); ?></h2>
						<?php $this->render_admin_preview( $form ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render static admin preview.
	 *
	 * @param array $form Form settings.
	 * @return void
	 */
	private function render_admin_preview( array $form ) {
		$amount   = '' !== $form['amount'] ? (float) $form['amount'] : 0;
		$currency = sanitize_text_field( strtoupper( $form['currency'] ) );
		?>
		<div class="saukipay-form-shell saukipay-form-preview">
			<div class="saukipay-form-brand">
				<div class="saukipay-form-brand-main">
					<span class="saukipay-form-icon"><img src="<?php echo esc_url( SAUKIPAY_URL . 'assets/images/saukipay-icon.png' ); ?>" alt=""></span>
					<span class="saukipay-form-wordmark"><span>Sauki</span><strong>PAY</strong></span>
				</div>
				<span class="saukipay-form-secure"><?php esc_html_e( 'Secure checkout', 'saukipay' ); ?></span>
			</div>
			<div class="saukipay-form">
				<div class="saukipay-form-heading">
					<h3><?php echo esc_html( $form['title'] ); ?></h3>
					<?php if ( $amount > 0 ) : ?>
						<strong><?php echo esc_html( $currency . ' ' . number_format_i18n( $amount, 2 ) ); ?></strong>
					<?php endif; ?>
				</div>
				<label><span><?php esc_html_e( 'Full name', 'saukipay' ); ?></span><input type="text" disabled value="Ada Lovelace"></label>
				<label><span><?php esc_html_e( 'Email address', 'saukipay' ); ?></span><input type="email" disabled value="customer@example.com"></label>
				<label><span><?php esc_html_e( 'Phone number', 'saukipay' ); ?></span><input type="tel" disabled value="08012345678"></label>
				<label><span><?php esc_html_e( 'Amount', 'saukipay' ); ?></span><input type="number" disabled value="<?php echo esc_attr( $amount > 0 ? $amount : '5000' ); ?>"></label>
				<button type="button" disabled><span><?php echo esc_html( $form['button_text'] ); ?></span><span aria-hidden="true">→</span></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Build shortcode from form settings.
	 *
	 * @param array $form Form settings.
	 * @return string
	 */
	private function build_shortcode( array $form ) {
		$parts = array(
			'title="' . esc_attr( $form['title'] ) . '"',
			'currency="' . esc_attr( $form['currency'] ) . '"',
			'button_text="' . esc_attr( $form['button_text'] ) . '"',
			'fixed_amount="' . esc_attr( $form['fixed_amount'] ) . '"',
		);

		if ( '' !== $form['amount'] ) {
			$parts[] = 'amount="' . esc_attr( $form['amount'] ) . '"';
		}

		return '[saukipay_payment_form ' . implode( ' ', $parts ) . ']';
	}

	/**
	 * Process form submit.
	 *
	 * @return void
	 */
	public function handle_submit() {
		if ( ! isset( $_POST['saukipay_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saukipay_nonce'] ) ), 'saukipay_form_pay' ) ) {
			wp_die( esc_html__( 'Invalid payment request.', 'saukipay' ), 403 );
		}

		if ( ! $this->settings->is_enabled() ) {
			wp_die( esc_html__( 'Sauki Pay is currently disabled.', 'saukipay' ), 403 );
		}

		$full_name = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone     = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$currency  = isset( $_POST['currency'] ) ? sanitize_text_field( strtoupper( wp_unslash( $_POST['currency'] ) ) ) : 'NGN';
		$amount    = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : 0;

		if ( '' === $full_name || ! is_email( $email ) || '' === $phone || $amount <= 0 ) {
			wp_die( esc_html__( 'Please provide valid payment details.', 'saukipay' ), 400 );
		}

		$reference = $this->create_reference();
		$payload   = array(
			'reference'    => $reference,
			'amount'       => $amount,
			'currency'     => $currency,
			'callback_url' => SaukiPay_Settings::callback_url(),
			'customer'     => array(
				'payerName'   => $full_name,
				'email'       => $email,
				'phoneNumber' => $phone,
			),
			'metadata'     => array(
				'source'   => 'shortcode',
				'site_url' => home_url( '/' ),
			),
		);

		$response = $this->api->initialize_payment( $payload );

		if ( is_wp_error( $response ) || empty( $response['data']['checkout'] ) ) {
			wp_die( esc_html__( 'Unable to initialize Sauki Pay payment. Please try again.', 'saukipay' ), 500 );
		}

		update_option(
			'saukipay_form_txn_' . sanitize_key( $reference ),
			array(
				'reference'   => $reference,
				'amount'      => $amount,
				'currency'    => $currency,
				'customer'    => array(
					'name'  => $full_name,
					'email' => $email,
					'phone' => $phone,
				),
				'status'      => 'initialized',
				'checkout'    => esc_url_raw( $response['data']['checkout'] ),
				'created_at'  => time(),
				'access_code' => isset( $response['data']['accessCode'] ) ? sanitize_text_field( $response['data']['accessCode'] ) : '',
			),
			false
		);

		wp_safe_redirect( esc_url_raw( $response['data']['checkout'] ) );
		exit;
	}

	/**
	 * Create unique payment reference.
	 *
	 * @return string
	 */
	private function create_reference() {
		return 'SPF-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 8, false, false );
	}

	/**
	 * Render result notice from callback.
	 *
	 * @return void
	 */
	private function render_result_notice() {
		if ( empty( $_GET['saukipay_result'] ) ) {
			return;
		}

		$status  = sanitize_key( wp_unslash( $_GET['saukipay_result'] ) );
		$message = isset( $_GET['saukipay_message'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['saukipay_message'] ) ) ) : '';
		$class   = 'success' === $status ? 'saukipay-result saukipay-result-success' : 'saukipay-result saukipay-result-failed';

		printf( '<div class="%1$s">%2$s</div>', esc_attr( $class ), esc_html( $message ) );
	}
}
