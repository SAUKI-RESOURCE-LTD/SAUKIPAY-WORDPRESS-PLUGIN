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
				'description'  => $form_settings['description'],
				'button_text'  => $form_settings['button_text'],
				'footer_text'  => $form_settings['footer_text'],
				'fixed_amount' => $form_settings['fixed_amount'],
				'preset_amounts' => $form_settings['preset_amounts'],
				'allow_custom_amount' => $form_settings['allow_custom_amount'],
				'width'        => $form_settings['width'],
			),
			$atts,
			'saukipay_payment_form'
		);

		wp_enqueue_style( 'saukipay-form' );
		wp_enqueue_script( 'saukipay-form' );

		$fixed_amount = filter_var( $atts['fixed_amount'], FILTER_VALIDATE_BOOLEAN );
		$amount       = '' !== $atts['amount'] ? (float) $atts['amount'] : 0;
		$currency     = sanitize_text_field( strtoupper( $atts['currency'] ) );
		$width        = $this->sanitize_width( $atts['width'] );
		$preset_amounts = $this->sanitize_preset_amounts( $atts['preset_amounts'] );
		$allow_custom_amount = filter_var( $atts['allow_custom_amount'], FILTER_VALIDATE_BOOLEAN );
		$default_amount = $amount > 0 ? $amount : $this->first_preset_amount( $preset_amounts );

		ob_start();
		$this->render_result_notice();
		?>
		<div class="saukipay-form-shell saukipay-form-width-<?php echo esc_attr( $width ); ?>">
			<div class="saukipay-form-brand">
				<div class="saukipay-form-brand-main">
					<span class="saukipay-form-icon"><img src="<?php echo esc_url( SAUKIPAY_URL . 'assets/images/saukipay-icon.png' ); ?>" alt=""></span>
					<span class="saukipay-form-wordmark"><span>Sauki</span><strong>PAY</strong></span>
				</div>
				<span class="saukipay-form-secure"><?php esc_html_e( 'Secure checkout', 'saukipay' ); ?></span>
			</div>
			<form class="saukipay-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<div class="saukipay-form-heading">
					<div>
						<h3><?php echo esc_html( $atts['title'] ); ?></h3>
						<?php if ( '' !== trim( (string) $atts['description'] ) ) : ?>
							<p><?php echo esc_html( $atts['description'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php if ( $default_amount > 0 ) : ?>
					<strong><?php echo esc_html( $currency . ' ' . number_format_i18n( $default_amount, 2 ) ); ?></strong>
				<?php endif; ?>
				</div>
				<input type="hidden" name="action" value="saukipay_form_pay">
				<input type="hidden" name="currency" value="<?php echo esc_attr( $currency ); ?>">
				<input type="hidden" name="fixed_amount" value="<?php echo esc_attr( $fixed_amount ? 'yes' : 'no' ); ?>">
				<input type="hidden" name="return_url" value="<?php echo esc_url( $this->current_url() ); ?>">
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
				<?php $this->render_amount_control( $currency, $amount, $preset_amounts, $allow_custom_amount, $fixed_amount ); ?>
				<button type="submit">
					<span><?php echo esc_html( $atts['button_text'] ); ?></span>
					<span aria-hidden="true">→</span>
				</button>
				<?php if ( '' !== trim( (string) $atts['footer_text'] ) ) : ?>
					<p class="saukipay-form-footer"><?php echo esc_html( $atts['footer_text'] ); ?></p>
				<?php endif; ?>
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
			'description'  => '',
			'amount'       => '',
			'currency'     => 'NGN',
			'button_text'  => $this->settings->get( 'button_text', 'Pay with Sauki Pay' ),
			'footer_text'  => 'You will be redirected to Sauki Pay secure checkout.',
			'fixed_amount' => 'no',
			'preset_amounts' => '1000,10000,50000,100000,250000,500000',
			'allow_custom_amount' => 'yes',
			'width'        => 'wide',
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
		$description  = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$amount       = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : 0;
		$currency     = isset( $_POST['currency'] ) ? $this->sanitize_currency( wp_unslash( $_POST['currency'] ) ) : 'NGN';
		$button_text  = isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '';
		$footer_text  = isset( $_POST['footer_text'] ) ? sanitize_text_field( wp_unslash( $_POST['footer_text'] ) ) : '';
		$fixed_amount = isset( $_POST['fixed_amount'] ) ? 'yes' : 'no';
		$preset_amounts = isset( $_POST['preset_amounts'] ) ? $this->preset_amounts_to_string( $this->sanitize_preset_amounts( wp_unslash( $_POST['preset_amounts'] ) ) ) : '';
		$allow_custom_amount = isset( $_POST['allow_custom_amount'] ) ? 'yes' : 'no';
		$width        = isset( $_POST['width'] ) ? $this->sanitize_width( wp_unslash( $_POST['width'] ) ) : 'wide';

		update_option(
			self::BUILDER_OPTION_NAME,
			array(
				'title'        => '' !== $title ? $title : 'Pay with Sauki Pay',
				'description'  => $description,
				'amount'       => $amount > 0 ? (string) $amount : '',
				'currency'     => '' !== $currency ? $currency : 'NGN',
				'button_text'  => '' !== $button_text ? $button_text : 'Pay with Sauki Pay',
				'footer_text'  => $footer_text,
				'fixed_amount' => $fixed_amount,
				'preset_amounts' => $preset_amounts,
				'allow_custom_amount' => $allow_custom_amount,
				'width'        => $width,
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
			<div class="saukipay-builder-layout">
				<form class="saukipay-builder-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="saukipay_save_form_builder">
					<?php wp_nonce_field( 'saukipay_save_form_builder' ); ?>
					<h2><?php esc_html_e( 'Form Content', 'saukipay' ); ?></h2>
					<div class="saukipay-builder-grid">
						<label>
							<span><?php esc_html_e( 'Form title', 'saukipay' ); ?></span>
							<input id="saukipay_form_title" type="text" name="title" value="<?php echo esc_attr( $form['title'] ); ?>">
						</label>
						<label>
							<span><?php esc_html_e( 'Description', 'saukipay' ); ?></span>
							<input id="saukipay_form_description" type="text" name="description" value="<?php echo esc_attr( $form['description'] ); ?>" placeholder="<?php esc_attr_e( 'Support our work with a secure donation.', 'saukipay' ); ?>">
						</label>
						<label>
							<span><?php esc_html_e( 'Button text', 'saukipay' ); ?></span>
							<input id="saukipay_form_button" type="text" name="button_text" value="<?php echo esc_attr( $form['button_text'] ); ?>">
						</label>
						<label>
							<span><?php esc_html_e( 'Footer note', 'saukipay' ); ?></span>
							<input id="saukipay_form_footer" type="text" name="footer_text" value="<?php echo esc_attr( $form['footer_text'] ); ?>">
						</label>
					</div>
					<h2><?php esc_html_e( 'Payment Settings', 'saukipay' ); ?></h2>
					<div class="saukipay-builder-grid">
						<label>
							<span><?php esc_html_e( 'Amount', 'saukipay' ); ?></span>
							<input id="saukipay_form_amount" type="number" min="0" step="0.01" name="amount" value="<?php echo esc_attr( $form['amount'] ); ?>" placeholder="5000">
						</label>
						<label>
							<span><?php esc_html_e( 'Preset amounts', 'saukipay' ); ?></span>
							<input id="saukipay_form_presets" type="text" name="preset_amounts" value="<?php echo esc_attr( $form['preset_amounts'] ); ?>" placeholder="1000,10000,50000">
							<small><?php esc_html_e( 'Separate each amount with a comma. These show as quick-select buttons.', 'saukipay' ); ?></small>
						</label>
						<label>
							<span><?php esc_html_e( 'Currency', 'saukipay' ); ?></span>
							<select id="saukipay_form_currency" name="currency">
								<?php foreach ( $this->supported_currencies() as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $form['currency'], $code ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label>
							<span><?php esc_html_e( 'Form width', 'saukipay' ); ?></span>
							<select id="saukipay_form_width" name="width">
								<option value="compact" <?php selected( $form['width'], 'compact' ); ?>><?php esc_html_e( 'Compact', 'saukipay' ); ?></option>
								<option value="wide" <?php selected( $form['width'], 'wide' ); ?>><?php esc_html_e( 'Wide', 'saukipay' ); ?></option>
								<option value="full" <?php selected( $form['width'], 'full' ); ?>><?php esc_html_e( 'Full width', 'saukipay' ); ?></option>
							</select>
						</label>
						<label class="saukipay-builder-check">
							<input type="checkbox" name="fixed_amount" value="yes" <?php checked( $form['fixed_amount'], 'yes' ); ?>>
							<span><?php esc_html_e( 'Customers cannot edit the amount', 'saukipay' ); ?></span>
						</label>
						<label class="saukipay-builder-check">
							<input type="checkbox" name="allow_custom_amount" value="yes" <?php checked( $form['allow_custom_amount'], 'yes' ); ?>>
							<span><?php esc_html_e( 'Allow customers to enter a custom amount', 'saukipay' ); ?></span>
						</label>
					</div>
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
		$preset_amounts = $this->sanitize_preset_amounts( $form['preset_amounts'] );
		$allow_custom_amount = filter_var( $form['allow_custom_amount'], FILTER_VALIDATE_BOOLEAN );
		?>
		<div class="saukipay-form-shell saukipay-form-preview saukipay-form-width-<?php echo esc_attr( $this->sanitize_width( $form['width'] ) ); ?>">
			<div class="saukipay-form-brand">
				<div class="saukipay-form-brand-main">
					<span class="saukipay-form-icon"><img src="<?php echo esc_url( SAUKIPAY_URL . 'assets/images/saukipay-icon.png' ); ?>" alt=""></span>
					<span class="saukipay-form-wordmark"><span>Sauki</span><strong>PAY</strong></span>
				</div>
				<span class="saukipay-form-secure"><?php esc_html_e( 'Secure checkout', 'saukipay' ); ?></span>
			</div>
			<div class="saukipay-form">
				<div class="saukipay-form-heading">
					<div>
						<h3><?php echo esc_html( $form['title'] ); ?></h3>
						<?php if ( '' !== trim( (string) $form['description'] ) ) : ?>
							<p><?php echo esc_html( $form['description'] ); ?></p>
						<?php endif; ?>
					</div>
					<?php if ( $amount > 0 || ! empty( $preset_amounts ) ) : ?>
						<strong><?php echo esc_html( $currency . ' ' . number_format_i18n( $amount > 0 ? $amount : $this->first_preset_amount( $preset_amounts ), 2 ) ); ?></strong>
					<?php endif; ?>
				</div>
				<label><span><?php esc_html_e( 'Full name', 'saukipay' ); ?></span><input type="text" disabled value="Ada Lovelace"></label>
				<label><span><?php esc_html_e( 'Email address', 'saukipay' ); ?></span><input type="email" disabled value="customer@example.com"></label>
				<label><span><?php esc_html_e( 'Phone number', 'saukipay' ); ?></span><input type="tel" disabled value="08012345678"></label>
				<?php $this->render_amount_control( $currency, $amount, $preset_amounts, $allow_custom_amount, 'yes' === $form['fixed_amount'], true ); ?>
				<button type="button" disabled><span><?php echo esc_html( $form['button_text'] ); ?></span><span aria-hidden="true">→</span></button>
				<?php if ( '' !== trim( (string) $form['footer_text'] ) ) : ?>
					<p class="saukipay-form-footer"><?php echo esc_html( $form['footer_text'] ); ?></p>
				<?php endif; ?>
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
			'preset_amounts="' . esc_attr( $form['preset_amounts'] ) . '"',
			'allow_custom_amount="' . esc_attr( $form['allow_custom_amount'] ) . '"',
			'width="' . esc_attr( $form['width'] ) . '"',
		);

		if ( '' !== $form['description'] ) {
			$parts[] = 'description="' . esc_attr( $form['description'] ) . '"';
		}

		if ( '' !== $form['footer_text'] ) {
			$parts[] = 'footer_text="' . esc_attr( $form['footer_text'] ) . '"';
		}

		if ( '' !== $form['amount'] ) {
			$parts[] = 'amount="' . esc_attr( $form['amount'] ) . '"';
		}

		return '[saukipay_payment_form ' . implode( ' ', $parts ) . ']';
	}

	/**
	 * Render frontend/admin amount controls.
	 *
	 * @param string $currency Currency code.
	 * @param float  $amount Base amount.
	 * @param array  $preset_amounts Preset amounts.
	 * @param bool   $allow_custom_amount Whether custom amount input is shown.
	 * @param bool   $fixed_amount Whether amount is locked.
	 * @param bool   $preview Whether this is an admin preview.
	 * @return void
	 */
	private function render_amount_control( $currency, $amount, array $preset_amounts, $allow_custom_amount, $fixed_amount, $preview = false ) {
		$default_amount = $amount > 0 ? $amount : $this->first_preset_amount( $preset_amounts );

		if ( $fixed_amount ) {
			?>
			<label>
				<span><?php esc_html_e( 'Amount', 'saukipay' ); ?></span>
				<input type="number" name="amount" min="1" step="0.01" required value="<?php echo esc_attr( $default_amount > 0 ? $default_amount : '' ); ?>" <?php disabled( true ); ?>>
				<input type="hidden" name="amount" value="<?php echo esc_attr( $default_amount ); ?>">
			</label>
			<?php
			return;
		}

		if ( empty( $preset_amounts ) ) {
			?>
			<label>
				<span><?php esc_html_e( 'Amount', 'saukipay' ); ?></span>
				<input type="number" name="amount" min="1" step="0.01" required value="<?php echo esc_attr( $default_amount > 0 ? $default_amount : '' ); ?>">
			</label>
			<?php
			return;
		}

		?>
		<div class="saukipay-amount-picker" data-currency="<?php echo esc_attr( $currency ); ?>">
			<div class="saukipay-amount-picker-head">
				<span><?php esc_html_e( 'Donation amount', 'saukipay' ); ?></span>
				<em><?php echo esc_html( $currency ); ?></em>
			</div>
			<input type="hidden" name="amount" value="<?php echo esc_attr( $default_amount ); ?>" <?php disabled( $preview ); ?>>
			<div class="saukipay-amount-grid">
				<?php foreach ( $preset_amounts as $index => $preset_amount ) : ?>
					<button type="button" class="saukipay-amount-option <?php echo 0 === $index && $default_amount === $preset_amount ? 'is-selected' : ''; ?>" data-amount="<?php echo esc_attr( $preset_amount ); ?>" <?php disabled( $preview ); ?>>
						<?php echo esc_html( $currency . ' ' . number_format_i18n( $preset_amount, 2 ) ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<?php if ( $allow_custom_amount ) : ?>
				<label class="saukipay-custom-amount">
					<span class="screen-reader-text"><?php esc_html_e( 'Custom amount', 'saukipay' ); ?></span>
					<input type="number" name="custom_amount" min="1" step="0.01" placeholder="<?php esc_attr_e( 'Enter custom amount', 'saukipay' ); ?>" <?php disabled( $preview ); ?>>
				</label>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Supported currencies for the form builder.
	 *
	 * @return array
	 */
	private function supported_currencies() {
		return array(
			'NGN' => __( 'NGN - Nigerian Naira', 'saukipay' ),
			'USD' => __( 'USD - US Dollar', 'saukipay' ),
			'GBP' => __( 'GBP - British Pound', 'saukipay' ),
			'EUR' => __( 'EUR - Euro', 'saukipay' ),
			'GHS' => __( 'GHS - Ghanaian Cedi', 'saukipay' ),
			'KES' => __( 'KES - Kenyan Shilling', 'saukipay' ),
			'ZAR' => __( 'ZAR - South African Rand', 'saukipay' ),
		);
	}

	/**
	 * Sanitize preset amount list.
	 *
	 * @param string|array $amounts Amount list.
	 * @return array
	 */
	private function sanitize_preset_amounts( $amounts ) {
		if ( is_array( $amounts ) ) {
			$items = $amounts;
		} else {
			$items = preg_split( '/[\s,]+/', (string) $amounts );
		}

		$clean = array();

		foreach ( $items as $item ) {
			$value = (float) preg_replace( '/[^0-9.]/', '', (string) $item );

			if ( $value > 0 ) {
				$clean[] = $value;
			}
		}

		$clean = array_values( array_unique( $clean ) );

		return array_slice( $clean, 0, 12 );
	}

	/**
	 * Convert preset amounts to storage/shortcode string.
	 *
	 * @param array $amounts Preset amounts.
	 * @return string
	 */
	private function preset_amounts_to_string( array $amounts ) {
		$items = array();

		foreach ( $amounts as $amount ) {
			$items[] = rtrim( rtrim( number_format( (float) $amount, 2, '.', '' ), '0' ), '.' );
		}

		return implode( ',', $items );
	}

	/**
	 * Get the first preset amount.
	 *
	 * @param array $amounts Preset amounts.
	 * @return float
	 */
	private function first_preset_amount( array $amounts ) {
		return ! empty( $amounts ) ? (float) reset( $amounts ) : 0;
	}

	/**
	 * Sanitize currency code.
	 *
	 * @param string $currency Currency code.
	 * @return string
	 */
	private function sanitize_currency( $currency ) {
		$currency = strtoupper( sanitize_text_field( $currency ) );
		$allowed  = array_keys( $this->supported_currencies() );

		return in_array( $currency, $allowed, true ) ? $currency : 'NGN';
	}

	/**
	 * Sanitize form width.
	 *
	 * @param string $width Width option.
	 * @return string
	 */
	private function sanitize_width( $width ) {
		$width = sanitize_key( $width );
		return in_array( $width, array( 'compact', 'wide', 'full' ), true ) ? $width : 'wide';
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
		$custom_amount = isset( $_POST['custom_amount'] ) ? (float) wp_unslash( $_POST['custom_amount'] ) : 0;
		$return_url = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : home_url( '/' );

		if ( $custom_amount > 0 ) {
			$amount = $custom_amount;
		}

		if ( '' === $full_name || ! is_email( $email ) || '' === $phone || $amount <= 0 ) {
			$this->payment_error( __( 'Please provide valid payment details.', 'saukipay' ), array(), $return_url, 400 );
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

		if ( is_wp_error( $response ) ) {
			$this->api->log_debug( 'Shortcode payment initialization failed.', array( 'error' => $response->get_error_message() ) );
			$this->payment_error( __( 'Unable to initialize Sauki Pay payment. Please try again.', 'saukipay' ), array( 'error' => $response->get_error_message(), 'error_code' => $response->get_error_code(), 'error_data' => $response->get_error_data() ), $return_url );
		}

		$checkout_url = $this->api->get_checkout_url( $response );

		if ( '' === $checkout_url ) {
			$this->api->log_debug( 'Shortcode payment initialization response missing checkout URL.', $response );
			$this->payment_error( __( 'Unable to initialize Sauki Pay payment. Please try again.', 'saukipay' ), array( 'reason' => 'Checkout URL missing from Sauki Pay response.', 'response' => $response ), $return_url );
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
				'checkout'    => esc_url_raw( $checkout_url ),
				'return_url'  => esc_url_raw( $return_url ),
				'created_at'  => time(),
				'access_code' => $this->api->get_access_code( $response ),
			),
			false
		);

		wp_redirect( esc_url_raw( $checkout_url ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Stop payment flow with admin-only diagnostics.
	 *
	 * @param string $message Public message.
	 * @param array  $debug Debug context.
	 * @param string $return_url URL to redirect back to.
	 * @param int    $status_code HTTP status code.
	 * @return void
	 */
	private function payment_error( $message, array $debug = array(), $return_url = '', $status_code = 500 ) {
		if ( '' !== $return_url ) {
			$args = array(
				'saukipay_result'  => 'failed',
				'saukipay_message' => rawurlencode( $message ),
			);

			if ( current_user_can( 'manage_options' ) && ! empty( $debug ) ) {
				set_transient( 'saukipay_debug_' . get_current_user_id(), $this->redact_debug_data( $debug ), 10 * MINUTE_IN_SECONDS );
				$args['saukipay_debug'] = '1';
			}

			wp_safe_redirect( add_query_arg( $args, $return_url ) );
			exit;
		}

		$display = esc_html( $message );

		if ( current_user_can( 'manage_options' ) && ! empty( $debug ) ) {
			$display .= '<hr><p><strong>' . esc_html__( 'Sauki Pay debug details', 'saukipay' ) . '</strong></p>';
			$display .= '<pre style="white-space:pre-wrap;text-align:left;">' . esc_html( wp_json_encode( $this->redact_debug_data( $debug ), JSON_PRETTY_PRINT ) ) . '</pre>';
		}

		wp_die( $display, esc_html__( 'Sauki Pay payment error', 'saukipay' ), array( 'response' => $status_code ) );
	}

	/**
	 * Redact sensitive data before displaying diagnostics.
	 *
	 * @param mixed $value Value to redact.
	 * @return mixed
	 */
	private function redact_debug_data( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$redacted = array();

		foreach ( $value as $key => $item ) {
			$normalized_key = strtolower( (string) $key );

			if ( false !== strpos( $normalized_key, 'secret' ) || false !== strpos( $normalized_key, 'authorization' ) || false !== strpos( $normalized_key, 'token' ) ) {
				$redacted[ $key ] = '[redacted]';
				continue;
			}

			$redacted[ $key ] = is_array( $item ) ? $this->redact_debug_data( $item ) : $item;
		}

		return $redacted;
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

		if ( current_user_can( 'manage_options' ) && ! empty( $_GET['saukipay_debug'] ) ) {
			$debug = get_transient( 'saukipay_debug_' . get_current_user_id() );

			if ( ! empty( $debug ) ) {
				printf(
					'<details class="saukipay-debug"><summary>%1$s</summary><pre>%2$s</pre></details>',
					esc_html__( 'Sauki Pay debug details', 'saukipay' ),
					esc_html( wp_json_encode( $debug, JSON_PRETTY_PRINT ) )
				);
				delete_transient( 'saukipay_debug_' . get_current_user_id() );
			}
		}
	}

	/**
	 * Get current frontend URL.
	 *
	 * @return string
	 */
	private function current_url() {
		global $wp;

		$path = isset( $wp->request ) ? $wp->request : '';
		$url  = home_url( add_query_arg( array(), $path ) );

		return remove_query_arg( array( 'saukipay_result', 'saukipay_message', 'saukipay_debug' ), $url );
	}
}
