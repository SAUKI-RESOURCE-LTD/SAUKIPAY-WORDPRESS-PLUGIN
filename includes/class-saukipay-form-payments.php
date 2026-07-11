<?php
/**
 * Standalone form payment storage.
 *
 * @package SaukiPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores standalone shortcode payment submissions in a queryable table.
 */
class SaukiPay_Form_Payments {
	const DB_VERSION_OPTION = 'saukipay_form_payments_db_version';
	const DB_VERSION        = '1.0.0';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'saukipay_form_payments';
	}

	/**
	 * Create or update table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reference varchar(100) NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'pending',
			amount decimal(18,2) NOT NULL DEFAULT 0.00,
			currency varchar(10) NOT NULL DEFAULT 'NGN',
			payer_name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(80) NOT NULL DEFAULT '',
			environment varchar(20) NOT NULL DEFAULT 'test',
			checkout_url text NULL,
			access_code varchar(191) NOT NULL DEFAULT '',
			source_url text NULL,
			payment_channel varchar(100) NOT NULL DEFAULT '',
			raw_response longtext NULL,
			verified_data longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			paid_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reference (reference),
			KEY status (status),
			KEY environment (environment),
			KEY currency (currency),
			KEY created_at (created_at),
			KEY email (email)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Ensure table exists after plugin updates.
	 *
	 * @return void
	 */
	public function maybe_install() {
		if ( self::DB_VERSION !== get_option( self::DB_VERSION_OPTION ) ) {
			self::install();
		}
	}

	/**
	 * Insert a payment row.
	 *
	 * @param array $data Payment data.
	 * @return int|false
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now  = current_time( 'mysql' );
		$data = wp_parse_args(
			$data,
			array(
				'reference'       => '',
				'status'          => 'pending',
				'amount'          => 0,
				'currency'        => 'NGN',
				'payer_name'      => '',
				'email'           => '',
				'phone'           => '',
				'environment'     => 'test',
				'checkout_url'    => '',
				'access_code'     => '',
				'source_url'      => '',
				'payment_channel' => '',
				'raw_response'    => null,
				'verified_data'   => null,
				'created_at'      => $now,
				'updated_at'      => $now,
				'paid_at'         => null,
			)
		);

		$inserted = $wpdb->insert(
			self::table_name(),
			$this->sanitize_row( $data ),
			array( '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a payment by reference.
	 *
	 * @param string $reference Reference.
	 * @param array  $data Payment data.
	 * @return bool
	 */
	public function update_by_reference( $reference, array $data ) {
		global $wpdb;

		$reference = sanitize_text_field( $reference );
		if ( '' === $reference ) {
			return false;
		}

		$data['updated_at'] = current_time( 'mysql' );

		$updated = $wpdb->update(
			self::table_name(),
			$this->sanitize_row( $data, false ),
			array( 'reference' => $reference ),
			null,
			array( '%s' )
		);

		return false !== $updated;
	}

	/**
	 * Get one payment by reference.
	 *
	 * @param string $reference Reference.
	 * @return array|null
	 */
	public function get_by_reference( $reference ) {
		global $wpdb;

		$reference = sanitize_text_field( $reference );
		if ( '' === $reference ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE reference = %s LIMIT 1', $reference ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * List payments.
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	public function list( array $filters ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$where   = $this->where_clause( $filters );
		$offset  = ( $filters['page'] - 1 ) * $filters['limit'];

		$sql = $wpdb->prepare(
			'SELECT * FROM ' . self::table_name() . " {$where['sql']} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			array_merge( $where['values'], array( $filters['limit'], $offset ) )
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count payments.
	 *
	 * @param array $filters Filters.
	 * @return int
	 */
	public function count( array $filters ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		$where   = $this->where_clause( $filters );

		$sql = 'SELECT COUNT(*) FROM ' . self::table_name() . " {$where['sql']}"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! empty( $where['values'] ) ) {
			$sql = $wpdb->prepare( $sql, $where['values'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Summary for dashboard cards.
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	public function summary( array $filters ) {
		global $wpdb;

		$filters = $this->normalize_filters( $filters );
		unset( $filters['status'], $filters['search'] );
		$where = $this->where_clause( $filters );

		$sql = 'SELECT currency, COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount,
				SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) AS success_count,
				COALESCE(SUM(CASE WHEN status = "success" THEN amount ELSE 0 END), 0) AS success_amount,
				SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending_count,
				SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed_count
			FROM ' . self::table_name() . " {$where['sql']} GROUP BY currency ORDER BY total_count DESC LIMIT 1"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! empty( $where['values'] ) ) {
			$sql = $wpdb->prepare( $sql, $where['values'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $row ) ? $row : array();
	}

	/**
	 * Normalize filters.
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	private function normalize_filters( array $filters ) {
		$filters = wp_parse_args(
			$filters,
			array(
				'page'        => 1,
				'limit'       => 20,
				'status'      => '',
				'environment' => '',
				'currency'    => '',
				'date_from'   => '',
				'date_to'     => '',
				'search'      => '',
			)
		);

		$filters['page']        = max( 1, absint( $filters['page'] ) );
		$filters['limit']       = max( 1, min( 100, absint( $filters['limit'] ) ) );
		$filters['status']      = in_array( $filters['status'], array( '', 'pending', 'success', 'failed' ), true ) ? $filters['status'] : '';
		$filters['environment'] = in_array( $filters['environment'], array( '', 'test', 'live' ), true ) ? $filters['environment'] : '';
		$filters['currency']    = strtoupper( sanitize_text_field( $filters['currency'] ) );
		$filters['date_from']   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $filters['date_from'] ) ? $filters['date_from'] : '';
		$filters['date_to']     = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $filters['date_to'] ) ? $filters['date_to'] : '';
		$filters['search']      = sanitize_text_field( $filters['search'] );

		return $filters;
	}

	/**
	 * Build where clause.
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	private function where_clause( array $filters ) {
		global $wpdb;

		$clauses = array( '1=1' );
		$values  = array();

		foreach ( array( 'status', 'environment', 'currency' ) as $field ) {
			if ( '' !== $filters[ $field ] ) {
				$clauses[] = "{$field} = %s";
				$values[]  = $filters[ $field ];
			}
		}

		if ( '' !== $filters['date_from'] ) {
			$clauses[] = 'created_at >= %s';
			$values[]  = $filters['date_from'] . ' 00:00:00';
		}

		if ( '' !== $filters['date_to'] ) {
			$clauses[] = 'created_at <= %s';
			$values[]  = $filters['date_to'] . ' 23:59:59';
		}

		if ( '' !== $filters['search'] ) {
			$like      = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$clauses[] = '(reference LIKE %s OR payer_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$values[]  = $like;
			$values[]  = $like;
			$values[]  = $like;
			$values[]  = $like;
		}

		return array(
			'sql'    => 'WHERE ' . implode( ' AND ', $clauses ),
			'values' => $values,
		);
	}

	/**
	 * Sanitize a row for storage.
	 *
	 * @param array $data Row.
	 * @param bool  $with_defaults Whether insert defaults are expected.
	 * @return array
	 */
	private function sanitize_row( array $data, $with_defaults = true ) {
		$row = array();

		$fields = array(
			'reference',
			'status',
			'amount',
			'currency',
			'payer_name',
			'email',
			'phone',
			'environment',
			'checkout_url',
			'access_code',
			'source_url',
			'payment_channel',
			'raw_response',
			'verified_data',
			'created_at',
			'updated_at',
			'paid_at',
		);

		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $data ) && ! $with_defaults ) {
				continue;
			}

			$value = isset( $data[ $field ] ) ? $data[ $field ] : null;

			switch ( $field ) {
				case 'amount':
					$row[ $field ] = (float) $value;
					break;
				case 'email':
					$row[ $field ] = sanitize_email( (string) $value );
					break;
				case 'checkout_url':
				case 'source_url':
					$row[ $field ] = esc_url_raw( (string) $value );
					break;
				case 'raw_response':
				case 'verified_data':
					$row[ $field ] = is_string( $value ) ? $value : wp_json_encode( $value );
					break;
				case 'created_at':
				case 'updated_at':
				case 'paid_at':
					$row[ $field ] = $value ? sanitize_text_field( (string) $value ) : null;
					break;
				default:
					$row[ $field ] = sanitize_text_field( (string) $value );
					break;
			}
		}

		return $row;
	}
}
