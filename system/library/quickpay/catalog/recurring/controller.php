<?php

namespace QuickPay\Catalog\Recurring;

require_once DIR_EXTENSION . 'quickpay/autoload.php';

use QuickPay\API\Exception;


/**
 * Trait Recurring
 *
 * @package QuickPay\Catalog
 */
trait Controller {

	use \QuickPay\Catalog\Controller;

	/**
	 * @return mixed
	 */
	public function index() {
		$this->load->language( 'extension/recurring/' . $this->getInstanceName() );

		$this->load->model( 'account/recurring' );

		if ( isset( $this->request->get['order_recurring_id'] ) ) {
			$order_recurring_id = $this->request->get['order_recurring_id'];
		} else {
			$order_recurring_id = 0;
		}

		$recurring_info = $this->model_account_recurring->getOrderRecurring( $order_recurring_id );

		if ( $recurring_info ) {
			$data['cancel_url'] = html_entity_decode( $this->url->link( 'extension/recurring/' . $this->getInstanceName() . '/cancel', 'order_recurring_id=' . $order_recurring_id, 'SSL' ) );

			$data['continue'] = $this->url->link( 'account/recurring', '', true );

			if ( $recurring_info['status'] == self::RECURRING_ACTIVE ) {
				$data['order_recurring_id'] = $order_recurring_id;
			} else {
				$data['order_recurring_id'] = '';
			}

			return $this->load->view( 'extension/recurring/' . $this->getInstanceName(), $data );
		}
	}

	/**
	 * Let the customer cancel the subscription from the 'My Account' section
	 */
	public function cancel() {
		$this->load->language( 'extension/recurring/squareup' );

		$this->load->model( 'account/recurring' );
		$this->load->model( 'extension/payment/squareup' );

		if ( isset( $this->request->get['order_recurring_id'] ) ) {
			$order_recurring_id = $this->request->get['order_recurring_id'];
		} else {
			$order_recurring_id = 0;
		}

		$json = array();

		$recurring_info = $this->model_account_recurring->getOrderRecurring( $order_recurring_id );

		if ( $recurring_info ) {
			$this->model_account_recurring->editOrderRecurringStatus( $order_recurring_id, self::RECURRING_CANCELLED );

			$this->load->model( 'checkout/order' );

			$order_info = $this->model_checkout_order->getOrder( $recurring_info['order_id'] );

			$this->model_checkout_order->addOrderHistory( $recurring_info['order_id'], $order_info['order_status_id'], $this->language->get( 'text_order_history_cancel' ), true );

			$json['success'] = $this->language->get( 'text_canceled' );
		} else {
			$json['error'] = $this->language->get( 'error_not_found' );
		}

		$this->response->addHeader( 'Content-Type: application/json' );
		$this->response->setOutput( json_encode( $json ) );
	}

	/**
	 * Used by i.e. cronjobs to perform automatic recurring payments
	 */
	public function recurring() {
		$this->load->language( 'extension/payment/' . $this->getInstanceName() );
		$this->load->language( 'extension/recurring/quickpay' );
		$this->load->model( 'checkout/order' );

		if ( ! $this->getInstanceModel()->validateCRON() ) {
			return;
		}

		foreach ( $this->getInstanceModel()->nextRecurringPayments() as $payment ) {
			try {
				if ( ! $payment['is_free'] ) {
					// Append the recurring order ID so the callback can properly handle the callback.
					$callback_url = $this->getInstanceModel()->getCallbackUrl( [ 'order_recurring_id' => $payment['order_recurring_id'] ] );
					$endpoint     = sprintf( '/subscriptions/%d/recurring', $payment['transaction_id'] );
					$response     = $this->getInstanceModel()->getClient()->request->setCallbackUrl( $callback_url )
					                                                               ->post( $endpoint, $payment['transaction'] );
					if ( ! $response->isSuccess() ) {
						list( $status_code, $headers, $response_data ) = $response->asRaw();
						throw new Exception( $response_data );
					}
				}
			} catch ( Exception $e ) {
				$this->getInstanceModel()->suspendRecurringProfile( $payment['order_recurring_id'] );
				$this->model_checkout_order->addOrderHistory( $payment['order_id'], self::TRANSACTION_FAILED, $e->getMessage(), true );
			}
		}
	}
}