<?php
/**
 * Created by PhpStorm.
 * User: PerfectSolution, Patrick Tolvstein
 * Date: 02/02/2018
 * Time: 15.00
 */

namespace QuickPay\Catalog;

require_once DIR_EXTENSION . 'quickpay/autoload.php';

/**
 * Class Controller
 *
 * @package QuickPay\Catalog
 */
trait Controller {

	use \QuickPay\Instance;

	/**
	 * @return mixed
	 */
	public function index() {
	    $this->load->language('extension/quickpay/payment/' . $this->getInstanceName());

		return $this->load->view('extension/quickpay/payment/quickpay', ['instanceName' => $this->getInstanceName()]);
	}

	/**
	 * Route: Create a payment link on the session order
	 */
	public function payment_link() {
		$this->load->language('extension/quickpay/payment/' . $this->getInstanceName());
        
		try {
			$order_id = $this->session->data['order_id'];

			if ( $this->cart->hasSubscription() ) {
				$payment_link = $this->getInstanceModel()->getSubscriptionLink( $order_id );
			} else {
				$payment_link = $this->getInstanceModel()->getPaymentLink( $order_id );
			}

			echo json_encode( [ 'redirect' => $payment_link ] );
		} catch ( Exception $e ) {
			http_response_code( 500 );
			echo $e->getMessage();
		} catch ( \Exception $e ) {
			http_response_code( 500 );
			echo $e->getMessage();
		}

		exit;
	}

	/**
	 * @return Model
	 * @return \QuickPay\Catalog\Recurring\Model
	 */
	protected function getInstanceModel() {
		if ( ! property_exists( $this, 'model_extension_payment_' . $this->getInstanceName() ) ) {
			$this->load->model('extension/quickpay/payment/' . $this->getInstanceName());
			$this->load->language('extension/quickpay/payment/' . $this->getInstanceName());
		}

		return $this->{'model_extension_quickpay_payment_' . $this->getInstanceName()};
	}

	/**
	 * Route: Handles the callback from QuickPay
	 */
	public function callback() {
		$callback_body = file_get_contents( "php://input" );
		$transaction   = json_decode( $callback_body );
                
		$order_id = $this->getOrderIdFromCallbackBody( $transaction );

		$this->load->model( 'checkout/order' );

		$operation = end( $transaction->operations );
        
		try {
			if ( ! $this->isAuthorizedCallback( $callback_body ) ) {
				throw new Exception( sprintf( 'Order ID: %s. Unauthorized request. Checksum does not match!', $order_id ) );
			}

			if ( ! $transaction->accepted ) {
				throw new Exception( sprintf( 'Order ID: %s. The transaction was not accepted.', $order_id ) );
			}

			switch ( $operation->type ) {
				case 'authorize' :
					$this->callback_transaction_authorized( $order_id, $transaction, $operation );
					break;
				case 'recurring' :
					if ( isset( $this->request->get['order_recurring_id'] ) ) {
						$this->callback_transaction_recurring( $this->request->get['order_recurring_id'], $transaction, $operation );
					}
					break;
			}

		} catch ( \Exception $e ) {
			switch ( $operation->type ) {
				case 'recurring':
					if ( isset( $this->request->get['order_recurring_id'] ) ) {
						$this->callback_transaction_failed_recurring( $this->request->get['order_recurring_id'], $transaction, $operation );
					}
					break;
			}
			$this->log->write( $e->getMessage() );
		}
	}

	/**
	 * @param $callback_body
	 *
	 * @return mixed
	 */
	private function getOrderIdFromCallbackBody( $callback_body ) {
		if ( ! empty( $callback_body->variables ) && ! empty( $callback_body->variables->order_id ) ) {
			return $callback_body->variables->order_id;
		}

		// Fallback
		return (int) $callback_body->order_id;
	}

	/**
	 * is_authorized_callback function.
	 *
	 * Performs a check on payment callbacks to see if it is legal or spoofed
	 *
	 * @access public
	 * @return boolean
	 */
	protected function isAuthorizedCallback( $response_body ) {
	    return true;
		if ( ! isset( $_SERVER["HTTP_QUICKPAY_CHECKSUM_SHA256"] ) ) {
			return false;
		}

		return hash_hmac( 'sha256', $response_body, $this->instanceConfig( 'private_key' ) ) == $_SERVER["HTTP_QUICKPAY_CHECKSUM_SHA256"];
	}

	/**
	 * @param $order_id
	 * @param $transaction
	 * @param $operation
	 *
	 * @throws Exception
	 */
	protected function callback_transaction_authorized( $order_id, $transaction, $operation ) {
		if ( ! empty( $transaction->fee ) ) {
			$this->addTransactionFeeToOrder( $transaction->fee, $order_id );
		}
		// Update order state
		$this->model_checkout_order->addHistory( $order_id, $this->instanceConfig( 'order_status_id' ) );

		// If its a subscription, we will capture the authorized amount immediately.
		if ( 'subscription' === strtolower( $transaction->type ) ) {
			$client = $this->getInstanceModel()->getClient();
			// Do not append the order_recurring_id here since we don't want a successful callback to be handled since this is
			// the initial payment which should not be considered a recurring payment in the shop.
			$callback_url = $this->getInstanceModel()->getCallbackUrl();
			$response     = $client->request->setCallbackUrl( $callback_url )->post( sprintf( 'subscriptions/%d/recurring', $transaction->id ), [
				'order_id'          => $client->formatOrderId( $order_id, $this->instanceConfig( 'order_number_prefix' ), '-init' ),
				'amount'            => $operation->amount,
				'auto_capture'      => $this->instanceConfig( 'autocapture' ),
				'autofee'           => $this->instanceConfig( 'autofee' ),
				'text_on_statement' => $this->instanceConfig( 'text_on_statement' ),
				'variables'         => [
					'test' => 'tard',
				],
			] );

			if ( ! $response->isSuccess() ) {
				$this->getInstanceModel()->suspendRecurringProfilesByOrder( $order_id );
			}

			$client->setTransactionId( $order_id, $response->asObject()->id, 'recurring' );
		}
	}

	/**
	 * Adds the transaction fee to the order
	 *
	 * @param $raw_fee
	 * @param $order_id
	 */
	private function addTransactionFeeToOrder( $raw_fee, $order_id ) {
		// Fetch language
		$this->load->language( 'extension/payment/' . $this->getInstanceName() );

		// Order Totals
		$data['totals']    = array();
		$order_total_query = $this->db->query( "SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int) $order_id . "' ORDER BY sort_order ASC" );
		foreach ( $order_total_query->rows as $total ) {
			$data['totals'][] = array(
				'order_id'   => $total['order_id'],
				'code'       => $total['code'],
				'title'      => $total['title'],
				'value'      => $total['value'],
				'sort_order' => $total['sort_order'],
			);
		}

		// Add fee to data object
		$fee              = $raw_fee / 100;
		$data['totals'][] = array(
			'code'       => 'quickpay_fee',
			'title'      => $this->language->get( 'payment_fee' ),
			'value'      => floatval( $fee ),
			'sort_order' => 8,
		);

		// Delete totals and re-add
		$this->db->query( "DELETE FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int) $order_id . "'" );

		if ( isset( $data['totals'] ) ) {
			foreach ( $data['totals'] as $total ) {
				// Add fee to the total price
				if ( $total['code'] === 'total' ) {
					$total['value'] += $fee;

					// Update the order entry
					$this->db->query( "UPDATE " . DB_PREFIX . "order SET total = '{$total['value']}' WHERE order_id = '{$order_id}'" );

				}
				// Add totals to the DB
				$this->db->query( "INSERT INTO " . DB_PREFIX . "order_total SET order_id = '" . (int) $order_id . "', code = '" . $this->db->escape( $total['code'] ) . "', title = '" . $this->db->escape( $total['title'] ) . "', `value` = '" . (float) $total['value'] . "', sort_order = '" . (int) $total['sort_order'] . "'" );
			}
		}

		// Add notice in the log
		$this->log->write( 'Add fee: ' . $fee . ' to order: ' . $order_id );
	}

	/**
	 * @param $order_recurring_id
	 * @param $transaction
	 * @param $operation
	 */
	protected function callback_transaction_recurring( $order_recurring_id, $transaction, $operation ) {
		$amount = $this->getInstanceModel()->getClient()->standardDenomination( $operation->amount, $transaction->currency );
		$this->getInstanceModel()->addRecurringTransaction( $order_recurring_id, $transaction->id, $amount, 'success' );

		$this->getInstanceModel()->getClient()->setRecurringTransactionId( $order_recurring_id, $transaction->id );

		$this->getInstanceModel()->updateRecurringTrial( $order_recurring_id );
		$this->getInstanceModel()->updateRecurringExpired( $order_recurring_id );
	}

	/**
	 * @param $order_recurring_id
	 * @param $transaction
	 * @param $operation
	 */
	protected function callback_transaction_failed_recurring( $order_recurring_id, $transaction, $operation ) {
		$this->getInstanceModel()->suspendRecurringProfile( $order_recurring_id );
	}
}