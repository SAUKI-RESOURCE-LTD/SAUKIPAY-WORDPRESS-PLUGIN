<?php
/**
 * Admin transactions page.
 *
 * @package SaukiPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a merchant transaction dashboard in wp-admin.
 */
class SaukiPay_Admin_Transactions {
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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_saukipay_verify_transaction', array( $this, 'verify_transaction' ) );
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_submenu_page(
			'saukipay-settings',
			__( 'Transactions', 'saukipay' ),
			__( 'Transactions', 'saukipay' ),
			'manage_options',
			'saukipay-transactions',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Admin hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'sauki-pay_page_saukipay-transactions' !== $hook ) {
			return;
		}

		wp_register_style( 'saukipay-form', SAUKIPAY_URL . 'assets/css/saukipay-form.css', array(), SAUKIPAY_VERSION );
		wp_enqueue_style( 'saukipay-form' );
	}

	/**
	 * Verify a transaction again.
	 *
	 * @return void
	 */
	public function verify_transaction() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to verify Sauki Pay transactions.', 'saukipay' ), 403 );
		}

		check_admin_referer( 'saukipay_verify_transaction' );

		$reference = isset( $_GET['reference'] ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : '';
		$result    = 'failed';

		if ( '' !== $reference ) {
			$verification = $this->api->verify_payment( $reference );
			$result       = is_wp_error( $verification ) ? 'failed' : 'verified';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => 'saukipay-transactions',
					'saukipay_tx_notice' => $result,
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

		$filters      = $this->current_filters();
		$summary      = $this->api->wp_transactions_summary( $this->summary_filters( $filters ) );
		$transactions = $this->api->list_wp_transactions( $filters );
		$items        = is_wp_error( $transactions ) ? array() : $this->extract_items( $transactions );
		$pagination   = is_wp_error( $transactions ) ? array() : $this->extract_pagination( $transactions, $filters );

		?>
		<div class="wrap saukipay-transactions-page">
			<h1><?php esc_html_e( 'Sauki Pay Transactions', 'saukipay' ); ?></h1>
			<p class="saukipay-admin-muted"><?php esc_html_e( 'View Sauki Pay invoices and payments processed across WooCommerce, GiveWP, and standalone payment forms.', 'saukipay' ); ?></p>

			<?php $this->render_notice(); ?>
			<?php $this->render_api_error( $summary ); ?>
			<?php $this->render_api_error( $transactions ); ?>

			<?php $this->render_summary( is_wp_error( $summary ) ? array() : $this->extract_summary( $summary ) ); ?>
			<?php $this->render_filters( $filters ); ?>
			<?php $this->render_table( $items ); ?>
			<?php $this->render_pagination( $pagination, $filters ); ?>
		</div>
		<?php
	}

	/**
	 * Current filters from the request.
	 *
	 * @return array
	 */
	private function current_filters() {
		$filters = array(
			'page'        => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
			'limit'       => 20,
			'status'      => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'environment' => isset( $_GET['environment'] ) ? sanitize_key( wp_unslash( $_GET['environment'] ) ) : '',
			'source'      => isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '',
			'currency'    => isset( $_GET['currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['currency'] ) ) ) : '',
			'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'search'      => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
		);

		if ( ! in_array( $filters['status'], array( '', 'success', 'pending', 'failed' ), true ) ) {
			$filters['status'] = '';
		}

		if ( ! in_array( $filters['environment'], array( '', 'test', 'live' ), true ) ) {
			$filters['environment'] = '';
		}

		if ( ! in_array( $filters['source'], array( '', 'woocommerce', 'givewp', 'shortcode' ), true ) ) {
			$filters['source'] = '';
		}

		return $filters;
	}

	/**
	 * Filters used for summary endpoint.
	 *
	 * @param array $filters Full filters.
	 * @return array
	 */
	private function summary_filters( array $filters ) {
		return array(
			'environment' => $filters['environment'],
			'source'      => $filters['source'],
			'date_from'   => $filters['date_from'],
			'date_to'     => $filters['date_to'],
		);
	}

	/**
	 * Render notices.
	 *
	 * @return void
	 */
	private function render_notice() {
		if ( empty( $_GET['saukipay_tx_notice'] ) ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['saukipay_tx_notice'] ) );

		if ( 'verified' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Transaction verification request completed. Refresh the table to see the latest backend status.', 'saukipay' ) . '</p></div>';
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Unable to verify the transaction. Please check your Sauki Pay keys and try again.', 'saukipay' ) . '</p></div>';
	}

	/**
	 * Render API errors.
	 *
	 * @param mixed $response API response.
	 * @return void
	 */
	private function render_api_error( $response ) {
		if ( ! is_wp_error( $response ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Transactions API unavailable.', 'saukipay' ),
			esc_html( $response->get_error_message() )
		);
	}

	/**
	 * Render summary cards.
	 *
	 * @param array $summary Summary data.
	 * @return void
	 */
	private function render_summary( array $summary ) {
		$cards = array(
			'totalTransactions'      => __( 'Total transactions', 'saukipay' ),
			'successfulTransactions' => __( 'Successful', 'saukipay' ),
			'pendingTransactions'    => __( 'Pending', 'saukipay' ),
			'failedTransactions'     => __( 'Failed', 'saukipay' ),
			'successfulAmount'       => __( 'Successful amount', 'saukipay' ),
		);

		echo '<div class="saukipay-admin-summary">';
		foreach ( $cards as $key => $label ) {
			$value = isset( $summary[ $key ] ) ? $summary[ $key ] : 0;
			printf(
				'<div class="saukipay-admin-card"><span>%1$s</span><strong>%2$s</strong></div>',
				esc_html( $label ),
				esc_html( $this->format_summary_value( $key, $value, $summary ) )
			);
		}
		echo '</div>';
	}

	/**
	 * Render filters.
	 *
	 * @param array $filters Current filters.
	 * @return void
	 */
	private function render_filters( array $filters ) {
		?>
		<form class="saukipay-admin-filters" method="get">
			<input type="hidden" name="page" value="saukipay-transactions">
			<label>
				<span><?php esc_html_e( 'Status', 'saukipay' ); ?></span>
				<select name="status">
					<?php $this->render_options( array( '' => __( 'All statuses', 'saukipay' ), 'success' => __( 'Success', 'saukipay' ), 'pending' => __( 'Pending', 'saukipay' ), 'failed' => __( 'Failed', 'saukipay' ) ), $filters['status'] ); ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Environment', 'saukipay' ); ?></span>
				<select name="environment">
					<?php $this->render_options( array( '' => __( 'All environments', 'saukipay' ), 'test' => __( 'Test', 'saukipay' ), 'live' => __( 'Live', 'saukipay' ) ), $filters['environment'] ); ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Source', 'saukipay' ); ?></span>
				<select name="source">
					<?php $this->render_options( array( '' => __( 'All sources', 'saukipay' ), 'woocommerce' => __( 'WooCommerce', 'saukipay' ), 'givewp' => __( 'GiveWP', 'saukipay' ), 'shortcode' => __( 'Payment form', 'saukipay' ) ), $filters['source'] ); ?>
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
				<input type="search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Reference, email, or phone', 'saukipay' ); ?>">
			</label>
			<button class="button button-primary" type="submit"><?php esc_html_e( 'Filter', 'saukipay' ); ?></button>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=saukipay-transactions' ) ); ?>"><?php esc_html_e( 'Reset', 'saukipay' ); ?></a>
		</form>
		<?php
	}

	/**
	 * Render select options.
	 *
	 * @param array  $options Options.
	 * @param string $selected Selected value.
	 * @return void
	 */
	private function render_options( array $options, $selected ) {
		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
		}
	}

	/**
	 * Render transaction table.
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
					<th><?php esc_html_e( 'Source', 'saukipay' ); ?></th>
					<th><?php esc_html_e( 'Channel', 'saukipay' ); ?></th>
					<th><?php esc_html_e( 'Date', 'saukipay' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'saukipay' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'No transactions found. If the backend API is not live yet, transactions will appear here once /wp/transactions is available.', 'saukipay' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $item ) : ?>
						<?php $this->render_table_row( $item ); ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render transaction row.
	 *
	 * @param array $item Transaction item.
	 * @return void
	 */
	private function render_table_row( array $item ) {
		$reference = isset( $item['reference'] ) ? sanitize_text_field( $item['reference'] ) : '';
		$customer  = isset( $item['customer'] ) && is_array( $item['customer'] ) ? $item['customer'] : array();
		$metadata  = isset( $item['metadata'] ) && is_array( $item['metadata'] ) ? $item['metadata'] : array();
		$status    = isset( $item['status'] ) ? sanitize_key( $item['status'] ) : '';
		$source    = isset( $metadata['source'] ) ? sanitize_key( $metadata['source'] ) : ( isset( $item['source'] ) ? sanitize_key( $item['source'] ) : '' );
		?>
		<tr>
			<td><strong><?php echo esc_html( $reference ); ?></strong></td>
			<td>
				<strong><?php echo esc_html( $customer['payerName'] ?? $customer['name'] ?? '—' ); ?></strong><br>
				<span class="saukipay-admin-muted"><?php echo esc_html( $customer['email'] ?? '—' ); ?></span>
			</td>
			<td><?php echo esc_html( $this->format_money( $item['amount'] ?? 0, $item['currency'] ?? '' ) ); ?></td>
			<td><?php echo wp_kses_post( $this->status_badge( $status ) ); ?></td>
			<td><?php echo esc_html( $item['environment'] ?? '—' ); ?></td>
			<td><?php echo esc_html( $this->source_label( $source ) ); ?></td>
			<td><?php echo esc_html( $item['paymentChannel'] ?? $item['payment_channel'] ?? '—' ); ?></td>
			<td><?php echo esc_html( $this->format_date( $item['paidAt'] ?? $item['paid_at'] ?? $item['createdAt'] ?? $item['created_at'] ?? '' ) ); ?></td>
			<td>
				<?php if ( '' !== $reference ) : ?>
					<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'saukipay_verify_transaction', 'reference' => rawurlencode( $reference ) ), admin_url( 'admin-post.php' ) ), 'saukipay_verify_transaction' ) ); ?>"><?php esc_html_e( 'Verify', 'saukipay' ); ?></a>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render pagination.
	 *
	 * @param array $pagination Pagination.
	 * @param array $filters Filters.
	 * @return void
	 */
	private function render_pagination( array $pagination, array $filters ) {
		$total_pages = isset( $pagination['totalPages'] ) ? absint( $pagination['totalPages'] ) : 1;
		$page        = isset( $pagination['page'] ) ? absint( $pagination['page'] ) : absint( $filters['page'] );

		if ( $total_pages <= 1 ) {
			return;
		}

		$args = array_filter(
			array(
				'page'        => 'saukipay-transactions',
				'status'      => $filters['status'],
				'environment' => $filters['environment'],
				'source'      => $filters['source'],
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
					'current'   => max( 1, $page ),
					'total'     => $total_pages,
					'prev_text' => __( 'Previous', 'saukipay' ),
					'next_text' => __( 'Next', 'saukipay' ),
				)
			)
		);
		echo '</div></div>';
	}

	/**
	 * Extract items from response.
	 *
	 * @param array $response API response.
	 * @return array
	 */
	private function extract_items( array $response ) {
		if ( isset( $response['data']['items'] ) && is_array( $response['data']['items'] ) ) {
			return $response['data']['items'];
		}

		if ( isset( $response['data']['data']['items'] ) && is_array( $response['data']['data']['items'] ) ) {
			return $response['data']['data']['items'];
		}

		if ( isset( $response['items'] ) && is_array( $response['items'] ) ) {
			return $response['items'];
		}

		return array();
	}

	/**
	 * Extract pagination from response.
	 *
	 * @param array $response API response.
	 * @param array $filters Filters.
	 * @return array
	 */
	private function extract_pagination( array $response, array $filters ) {
		$pagination = array();

		if ( isset( $response['data']['pagination'] ) && is_array( $response['data']['pagination'] ) ) {
			$pagination = $response['data']['pagination'];
		} elseif ( isset( $response['data']['data']['pagination'] ) && is_array( $response['data']['data']['pagination'] ) ) {
			$pagination = $response['data']['data']['pagination'];
		} elseif ( isset( $response['pagination'] ) && is_array( $response['pagination'] ) ) {
			$pagination = $response['pagination'];
		}

		return wp_parse_args( $pagination, array( 'page' => $filters['page'], 'limit' => $filters['limit'], 'total' => 0, 'totalPages' => 1 ) );
	}

	/**
	 * Extract summary from response.
	 *
	 * @param array $response API response.
	 * @return array
	 */
	private function extract_summary( array $response ) {
		if ( isset( $response['data']['summary'] ) && is_array( $response['data']['summary'] ) ) {
			return $response['data']['summary'];
		}

		if ( isset( $response['data']['data'] ) && is_array( $response['data']['data'] ) ) {
			return $response['data']['data'];
		}

		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			return $response['data'];
		}

		return is_array( $response ) ? $response : array();
	}

	/**
	 * Format a summary value.
	 *
	 * @param string $key Summary key.
	 * @param mixed  $value Summary value.
	 * @param array  $summary Summary data.
	 * @return string
	 */
	private function format_summary_value( $key, $value, array $summary ) {
		if ( false !== strpos( strtolower( $key ), 'amount' ) ) {
			return $this->format_money( $value, $summary['currency'] ?? '' );
		}

		return number_format_i18n( (float) $value );
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
		$prefix   = '' !== $currency ? $currency . ' ' : '';
		return $prefix . number_format_i18n( (float) $amount, 2 );
	}

	/**
	 * Format date.
	 *
	 * @param string $date Date.
	 * @return string
	 */
	private function format_date( $date ) {
		if ( '' === $date ) {
			return '—';
		}

		$timestamp = strtotime( $date );
		return $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : sanitize_text_field( $date );
	}

	/**
	 * Format status badge.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private function status_badge( $status ) {
		$status = sanitize_key( $status );
		$label  = '' !== $status ? ucfirst( $status ) : __( 'Unknown', 'saukipay' );
		return sprintf( '<span class="saukipay-status saukipay-status-%1$s">%2$s</span>', esc_attr( $status ), esc_html( $label ) );
	}

	/**
	 * Source label.
	 *
	 * @param string $source Source.
	 * @return string
	 */
	private function source_label( $source ) {
		$labels = array(
			'woocommerce' => __( 'WooCommerce', 'saukipay' ),
			'givewp'      => __( 'GiveWP', 'saukipay' ),
			'shortcode'   => __( 'Payment form', 'saukipay' ),
		);

		return isset( $labels[ $source ] ) ? $labels[ $source ] : ( '' !== $source ? $source : '—' );
	}
}
