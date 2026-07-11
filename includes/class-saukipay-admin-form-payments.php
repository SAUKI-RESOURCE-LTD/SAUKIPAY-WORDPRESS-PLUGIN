<?php
/**
 * Admin form payments page.
 *
 * @package SaukiPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays standalone payment form submissions.
 */
class SaukiPay_Admin_Form_Payments {
	/**
	 * API client.
	 *
	 * @var SaukiPay_API
	 */
	private $api;

	/**
	 * Form payment storage.
	 *
	 * @var SaukiPay_Form_Payments
	 */
	private $payments;

	/**
	 * Constructor.
	 *
	 * @param SaukiPay_API           $api API client.
	 * @param SaukiPay_Form_Payments $payments Payment storage.
	 */
	public function __construct( SaukiPay_API $api, SaukiPay_Form_Payments $payments ) {
		$this->api      = $api;
		$this->payments = $payments;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_saukipay_verify_form_payment', array( $this, 'verify_payment' ) );
	}

	/**
	 * Add submenu.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_submenu_page(
			'saukipay-settings',
			__( 'Form Payments', 'saukipay' ),
			__( 'Form Payments', 'saukipay' ),
			'manage_options',
			'saukipay-form-payments',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin CSS.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'sauki-pay_page_saukipay-form-payments' !== $hook ) {
			return;
		}

		wp_register_style( 'saukipay-form', SAUKIPAY_URL . 'assets/css/saukipay-form.css', array(), SAUKIPAY_VERSION );
		wp_enqueue_style( 'saukipay-form' );
	}

	/**
	 * Verify and update a local form payment.
	 *
	 * @return void
	 */
	public function verify_payment() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to verify Sauki Pay form payments.', 'saukipay' ), 403 );
		}

		check_admin_referer( 'saukipay_verify_form_payment' );

		$reference = isset( $_GET['reference'] ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : '';
		$result    = 'failed';

		if ( '' !== $reference ) {
			$verification = $this->api->verify_payment( $reference );

			if ( ! is_wp_error( $verification ) ) {
				$data   = isset( $verification['data'] ) && is_array( $verification['data'] ) ? $verification['data'] : array();
				$status = isset( $data['status'] ) && 'success' === strtolower( sanitize_text_field( $data['status'] ) ) ? 'success' : 'failed';
				$update = array(
					'status'        => $status,
					'verified_data' => $data,
					'paid_at'       => 'success' === $status ? current_time( 'mysql' ) : null,
				);

				if ( isset( $data['paymentChannel'] ) ) {
					$update['payment_channel'] = sanitize_text_field( $data['paymentChannel'] );
				}

				if ( isset( $data['environment'] ) ) {
					$update['environment'] = sanitize_key( $data['environment'] );
				}

				$this->payments->update_by_reference( $reference, $update );
				$result = 'verified';
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                    => 'saukipay-form-payments',
					'saukipay_form_pay_notice' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$filters     = $this->current_filters();
		$items       = $this->payments->list( $filters );
		$total       = $this->payments->count( $filters );
		$summary     = $this->payments->summary( $filters );
		$total_pages = max( 1, (int) ceil( $total / $filters['limit'] ) );

		?>
		<div class="wrap saukipay-transactions-page">
			<h1><?php esc_html_e( 'Sauki Pay Form Payments', 'saukipay' ); ?></h1>
			<p class="saukipay-admin-muted"><?php esc_html_e( 'View standalone payment form submissions saved locally on this WordPress site.', 'saukipay' ); ?></p>

			<?php $this->render_notice(); ?>
			<?php $this->render_summary( $summary ); ?>
			<?php $this->render_filters( $filters ); ?>
			<?php $this->render_table( $items ); ?>
			<?php $this->render_pagination( $filters, $total_pages ); ?>
		</div>
		<?php
	}

	/**
	 * Current filters.
	 *
	 * @return array
	 */
	private function current_filters() {
		return array(
			'page'        => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
			'limit'       => 20,
			'status'      => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'environment' => isset( $_GET['environment'] ) ? sanitize_key( wp_unslash( $_GET['environment'] ) ) : '',
			'currency'    => isset( $_GET['currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['currency'] ) ) ) : '',
			'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'search'      => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
		);
	}

	/**
	 * Render notice.
	 *
	 * @return void
	 */
	private function render_notice() {
		if ( empty( $_GET['saukipay_form_pay_notice'] ) ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['saukipay_form_pay_notice'] ) );

		if ( 'verified' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Form payment verification completed and the local record was updated.', 'saukipay' ) . '</p></div>';
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Unable to verify the form payment. Please check your Sauki Pay keys and try again.', 'saukipay' ) . '</p></div>';
	}

	/**
	 * Render summary.
	 *
	 * @param array $summary Summary.
	 * @return void
	 */
	private function render_summary( array $summary ) {
		$currency = isset( $summary['currency'] ) ? sanitize_text_field( $summary['currency'] ) : '';
		$cards    = array(
			'total_count'    => __( 'Total form payments', 'saukipay' ),
			'success_count'  => __( 'Successful', 'saukipay' ),
			'pending_count'  => __( 'Pending', 'saukipay' ),
			'failed_count'   => __( 'Failed', 'saukipay' ),
			'success_amount' => __( 'Successful amount', 'saukipay' ),
		);

		echo '<div class="saukipay-admin-summary">';
		foreach ( $cards as $key => $label ) {
			$value = isset( $summary[ $key ] ) ? $summary[ $key ] : 0;
			$text  = false !== strpos( $key, 'amount' ) ? $this->format_money( $value, $currency ) : number_format_i18n( (float) $value );

			printf(
				'<div class="saukipay-admin-card"><span>%1$s</span><strong>%2$s</strong></div>',
				esc_html( $label ),
				esc_html( $text )
			);
		}
		echo '</div>';
	}

	/**
	 * Render filters.
	 *
	 * @param array $filters Filters.
	 * @return void
	 */
	private function render_filters( array $filters ) {
		?>
		<form class="saukipay-admin-filters" method="get">
			<input type="hidden" name="page" value="saukipay-form-payments">
			<label>
				<span><?php esc_html_e( 'Status', 'saukipay' ); ?></span>
				<select name="status">
					<?php $this->render_options( array( '' => __( 'All statuses', 'saukipay' ), 'pending' => __( 'Pending', 'saukipay' ), 'success' => __( 'Success', 'saukipay' ), 'failed' => __( 'Failed', 'saukipay' ) ), $filters['status'] ); ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Environment', 'saukipay' ); ?></span>
				<select name="environment">
					<?php $this->render_options( array( '' => __( 'All environments', 'saukipay' ), 'test' => __( 'Test', 'saukipay' ), 'live' => __( 'Live', 'saukipay' ) ), $filters['environment'] ); ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Currency', 'saukipay' ); ?></span>
				<input type="text" name="currency" value="<?php echo esc_attr( $filters['currency'] ); ?>" placeholder="NGN">
			</label>
			<label>
				<span><?php esc_html_e( 'From', 'saukipay' ); ?></span>
				<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'To', 'saukipay' ); ?></span>
				<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
			</label>
			<label class="saukipay-admin-search">
				<span><?php esc_html_e( 'Search', 'saukipay' ); ?></span>
				<input type="search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Reference, name, email, or phone', 'saukipay' ); ?>">
			</label>
			<button class="button button-primary" type="submit"><?php esc_html_e( 'Filter', 'saukipay' ); ?></button>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=saukipay-form-payments' ) ); ?>"><?php esc_html_e( 'Reset', 'saukipay' ); ?></a>
		</form>
		<?php
	}

	/**
	 * Render select options.
	 *
	 * @param array  $options Options.
	 * @param string $selected Selected.
	 * @return void
	 */
	private function render_options( array $options, $selected ) {
		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
		}
	}

	/**
	 * Render table.
	 *
	 * @param array $items Items.
	 * @return void
	 */
	private function render_table( array $items ) {
		?>
		<table class="widefat striped saukipay-admin-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Reference', 'saukipay' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'saukipay' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'saukipay' ); ?></th>
					<th><?php esc_html_e( 'Status', 'saukipay' ); ?></th>
					<th><?php esc_html_e( 'Environment', 'saukipay' ); ?></th>
					<th><?php esc_html_e( 'Channel', 'saukipay' ); ?></th>
					<th><?php esc_html_e( 'Submitted', 'saukipay' ); ?></th>
					<th><?php esc_html_e( 'Paid', 'saukipay' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'saukipay' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'No standalone form payments found yet.', 'saukipay' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $item ) : ?>
						<?php $this->render_row( $item ); ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render row.
	 *
	 * @param array $item Item.
	 * @return void
	 */
	private function render_row( array $item ) {
		$reference = isset( $item['reference'] ) ? sanitize_text_field( $item['reference'] ) : '';
		$status    = isset( $item['status'] ) ? sanitize_key( $item['status'] ) : '';
		?>
		<tr>
			<td><strong><?php echo esc_html( $reference ); ?></strong></td>
			<td>
				<strong><?php echo esc_html( $item['payer_name'] ?? '—' ); ?></strong><br>
				<span class="saukipay-admin-muted"><?php echo esc_html( $item['email'] ?? '—' ); ?></span><br>
				<span class="saukipay-admin-muted"><?php echo esc_html( $item['phone'] ?? '—' ); ?></span>
			</td>
			<td><?php echo esc_html( $this->format_money( $item['amount'] ?? 0, $item['currency'] ?? '' ) ); ?></td>
			<td><?php echo wp_kses_post( $this->status_badge( $status ) ); ?></td>
			<td><?php echo esc_html( $item['environment'] ?? '—' ); ?></td>
			<td><?php echo esc_html( $item['payment_channel'] ?? '—' ); ?></td>
			<td><?php echo esc_html( $this->format_date( $item['created_at'] ?? '' ) ); ?></td>
			<td><?php echo esc_html( $this->format_date( $item['paid_at'] ?? '' ) ); ?></td>
			<td>
				<?php if ( '' !== $reference ) : ?>
					<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'saukipay_verify_form_payment', 'reference' => rawurlencode( $reference ) ), admin_url( 'admin-post.php' ) ), 'saukipay_verify_form_payment' ) ); ?>"><?php esc_html_e( 'Verify', 'saukipay' ); ?></a>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render pagination.
	 *
	 * @param array $filters Filters.
	 * @param int   $total_pages Total pages.
	 * @return void
	 */
	private function render_pagination( array $filters, $total_pages ) {
		if ( $total_pages <= 1 ) {
			return;
		}

		$args = array_filter(
			array(
				'page'        => 'saukipay-form-payments',
				'status'      => $filters['status'],
				'environment' => $filters['environment'],
				'currency'    => $filters['currency'],
				'date_from'   => $filters['date_from'],
				'date_to'     => $filters['date_to'],
				'search'      => $filters['search'],
			)
		);

		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => add_query_arg( array_merge( $args, array( 'paged' => '%#%' ) ), admin_url( 'admin.php' ) ),
					'format'    => '',
					'current'   => max( 1, absint( $filters['page'] ) ),
					'total'     => $total_pages,
					'prev_text' => __( 'Previous', 'saukipay' ),
					'next_text' => __( 'Next', 'saukipay' ),
				)
			)
		);
		echo '</div></div>';
	}

	/**
	 * Format money.
	 *
	 * @param mixed  $amount Amount.
	 * @param string $currency Currency.
	 * @return string
	 */
	private function format_money( $amount, $currency ) {
		$currency = strtoupper( sanitize_text_field( (string) $currency ) );
		return ( '' !== $currency ? $currency . ' ' : '' ) . number_format_i18n( (float) $amount, 2 );
	}

	/**
	 * Format date.
	 *
	 * @param string $date Date.
	 * @return string
	 */
	private function format_date( $date ) {
		if ( empty( $date ) || '0000-00-00 00:00:00' === $date ) {
			return '—';
		}

		$timestamp = strtotime( $date );
		return $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : sanitize_text_field( $date );
	}

	/**
	 * Status badge.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private function status_badge( $status ) {
		$status = sanitize_key( $status );
		$label  = '' !== $status ? ucfirst( $status ) : __( 'Unknown', 'saukipay' );
		return sprintf( '<span class="saukipay-status saukipay-status-%1$s">%2$s</span>', esc_attr( $status ), esc_html( $label ) );
	}
}
