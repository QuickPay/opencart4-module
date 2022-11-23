<?php
/**
 * Created by PhpStorm.
 * User: PerfectSolution, Patrick Tolvstein
 * Date: 02/02/2018
 * Time: 12.40
 */

namespace QuickPay;

/**
 * Class Order
 *
 * @package QuickPay
 */
trait Order {

	protected $db;

	protected $currency;

	protected $registry;

	/**
	 * Order constructor.
	 *
	 * @param $registry
	 */
	public function __construct( $registry ) {
		$this->db       = $registry->get( 'db' );
		$this->currency = $registry->get( 'currency' );
		$this->registry = $registry;
	}

	/**
	 * @param $order_id
	 * @param $transactionId
	 *
	 * @return mixed
	 */
	public function setRecurringTransactionId( $order_id, $transaction_id ) {
		$this->setTransactionId( $order_id, $transaction_id, 'recurring' );
	}

	/**
	 * @param $order_id
	 * @param $transaction_id
	 * @param $transaction_type
	 *
	 * @return mixed
	 */
	public function setTransactionId( $order_id, $transaction_id, $transaction_type ) {
		$now = date( 'Y-m-d H:i:s' );

		$exists = $this->db->query( "SELECT id FROM " . DB_PREFIX . "quickpay_transaction WHERE order_id = '{$order_id}' AND transaction_type='{$transaction_type}'" );
		if ( $exists->num_rows ) {
			return $this->db->query( "UPDATE " . DB_PREFIX . "quickpay_transaction SET transaction_id='{$transaction_id}', date_modified = '{$now}' WHERE order_id='{$order_id}' AND transaction_type='{$transaction_type}'" );
		} else {
			return $this->db->query( "INSERT INTO " . DB_PREFIX . "quickpay_transaction (order_id, transaction_id, transaction_type, date_added) VALUES ('{$order_id}', '{$transaction_id}', '{$transaction_type}', '{$now}')" );
		}
	}

	/**
	 * @param $order_id
	 * @param $transaction_id
	 * @param $payment_link
	 * @param $transaction_type
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function setPaymentLink( $order_id, $transaction_id, $payment_link, $transaction_type ) {
		$now = date( 'Y-m-d H:i:s' );

		$exists = $this->db->query( "SELECT id FROM " . DB_PREFIX . "quickpay_transaction WHERE order_id = '{$order_id}' AND transaction_id = '{$transaction_id}' AND transaction_type='{$transaction_type}'" );
		if ( ! $exists->num_rows ) {
			throw new \Exception( sprintf( "No transaction with ID #%s found on order #%s", $transaction_id, $order_id ) );
		}

		return $this->db->query( "UPDATE " . DB_PREFIX . "quickpay_transaction SET payment_link='{$payment_link}', date_modified='{$now}' WHERE order_id='{$order_id}' AND transaction_id='{$transaction_id}' AND transaction_type='{$transaction_type}'" );

	}

	/**
	 * @param $order_id
	 *
	 * @param $transaction_type
	 *
	 * @return mixed
	 */
	public function getTransactionId( $order_id, $transaction_type ) {
		$transaction = $this->db->query( "SELECT transaction_id FROM " . DB_PREFIX . "quickpay_transaction WHERE order_id = '{$order_id}' AND transaction_type='{$transaction_type}'" );

		return isset( $transaction->row['transaction_id'] ) ? (int) $transaction->row['transaction_id'] : null;
	}

	/**
	 * @param $order_id
	 *
	 * @return mixed
	 */
	public function getOrderPaymentLink( $order_id, $transaction_type ) {
		$transaction = $this->db->query( "SELECT payment_link FROM " . DB_PREFIX . "quickpay_transaction WHERE order_id = '{$order_id}' AND transaction_type='{$transaction_type}'" );

		return $transaction->row['payment_link'];
	}

	/**
	 * @param $value
	 * @param $currency
	 *
	 * @return float
	 */
	public function standardDenomination( $value, $currency ) {
		$power = $this->currency->getDecimalPlace( $currency );

		$value = (int) $value;

		return (float) ( $value / pow( 10, $power ) );
	}

	/**
	 * @param        $order_id
	 * @param string $prefix - prefix string to the order number
	 * @param string $append - append string to the order number
	 *
	 * @return string
	 */
	public function formatOrderId( $order_id, $prefix = '', $append = '' ) {
		return $prefix . str_pad( $order_id, 4, '0', STR_PAD_LEFT ) . $append;
	}
}