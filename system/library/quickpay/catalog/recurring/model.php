<?php
/**
 * Created by PhpStorm.
 * User: PerfectSolution, Patrick Tolvstein
 * Date: 12/02/2018
 * Time: 12.53
 */

namespace QuickPay\Catalog\Recurring;

require_once DIR_EXTENSION . 'quickpay/autoload.php';

/**
 * Trait Model
 *
 * @package QuickPay\Catalog\Recurring
 */
trait Model {

	/**
	 * @return bool
	 */
	public function recurringPayments() {
		return true;
	}

	/**
	 * Checks if the request is secured based on the saved cron token
	 *
	 * @return bool
	 */
	public function validateCRON() {
		if ( ! $this->instanceConfig( 'status' ) ) {
			return false;
		}

		if ( isset( $this->request->get['cron_token'] ) && $this->request->get['cron_token'] === $this->instanceConfig( 'cron_token' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @return array
	 */
	public function nextRecurringPayments() {
		$payments = array();

		$this->load->library( 'quickpay/quickpay' );

		$recurring_sql = "SELECT * FROM `" . DB_PREFIX . "order_recurring` `or` INNER JOIN `" . DB_PREFIX . "quickpay_transaction` qpt ON (qpt.transaction_id = `or`.reference) WHERE `or`.status='" . self::RECURRING_ACTIVE . "'";

		$this->load->model( 'checkout/order' );

		foreach ( $this->db->query( $recurring_sql )->rows as $recurring ) {
			if ( ! $this->paymentIsDue( $recurring['order_recurring_id'] ) ) {
				continue;
			}

			$order_info = $this->model_checkout_order->getOrder( $recurring['order_id'] );

			$price = (float) ( $recurring['trial'] ? $recurring['trial_price'] : $recurring['recurring_price'] );

			$transaction = [
				'amount'            => 100 * $this->currency->format( $price * $recurring['product_quantity'], $order_info['currency_code'], '', false ),
				'auto_capture'      => $this->instanceConfig( 'autocapture' ),
				'auto_fee'          => $this->instanceConfig( 'autofee' ),
				'text_on_statement' => $this->instanceConfig( 'text_on_statement' ),
				'order_id'          => $this->getClient()
				                            ->formatOrderId( $recurring['order_id'], $this->instanceConfig( 'order_number_prefix' ), sprintf( '-%d-%s', $recurring['order_recurring_id'], $this->randomString( 3 ) ) ),
			];

			$payments[] = array(
				'is_free'            => $price == 0,
				'order_id'           => $recurring['order_id'],
				'order_recurring_id' => $recurring['order_recurring_id'],
				'transaction'        => $transaction,
				'transaction_id'     => $recurring['transaction_id'],
			);
		}

		return $payments;
	}

	/**
	 * @param $order_recurring_id
	 *
	 * @return bool
	 */
	private function paymentIsDue( $order_recurring_id ) {
		// We know the recurring profile is active.
		$recurring_info = $this->getRecurring( $order_recurring_id );

		if ( ! $recurring_info ) {
			return;
		}

		if ( isset( $recurring_info['trial'] ) ) {
			$frequency = $recurring_info['trial_frequency'];
			$cycle     = (int) $recurring_info['trial_cycle'];
		} else {
			$frequency = $recurring_info['recurring_frequency'];
			$cycle     = (int) $recurring_info['recurring_cycle'];
		}
		// Find date of last payment
		if ( ! $this->getTotalSuccessfulPayments( $order_recurring_id ) ) {
			$previous_time = strtotime( $recurring_info['date_added'] );
		} else {
			$previous_time = strtotime( $this->getLastSuccessfulRecurringPaymentDate( $order_recurring_id ) );
		}

		switch ( $frequency ) {
			case 'day' :
				$time_interval = 24 * 3600;
				break;
			case 'week' :
				$time_interval = 7 * 24 * 3600;
				break;
			case 'semi_month' :
				$time_interval = 15 * 24 * 3600;
				break;
			case 'month' :
				$time_interval = 30 * 24 * 3600;
				break;
			case 'year' :
				$time_interval = 365 * 24 * 3600;
				break;
		}

		$due_date = date( 'Y-m-d', $previous_time + ( $time_interval * $cycle ) );

		$this_date = date( 'Y-m-d' );

		return $this_date >= $due_date;
	}

	/**
	 * @param $order_recurring_id
	 *
	 * @return mixed
	 */
	private function getRecurring( $order_recurring_id ) {
		$recurring_sql = "SELECT * FROM `" . DB_PREFIX . "order_recurring` WHERE order_recurring_id='" . (int) $order_recurring_id . "'";

		return $this->db->query( $recurring_sql )->row;
	}

	/**
	 * @param $order_recurring_id
	 *
	 * @return mixed
	 */
	private function getTotalSuccessfulPayments( $order_recurring_id ) {
		return $this->db->query( "SELECT COUNT(*) as total FROM `" . DB_PREFIX . "order_recurring_transaction` WHERE order_recurring_id='" . (int) $order_recurring_id . "' AND type='" . self::TRANSACTION_PAYMENT . "'" )->row['total'];
	}

	/**
	 * @param $order_recurring_id
	 *
	 * @return mixed
	 */
	private function getLastSuccessfulRecurringPaymentDate( $order_recurring_id ) {
		return $this->db->query( "SELECT date_added FROM `" . DB_PREFIX . "order_recurring_transaction` WHERE order_recurring_id='" . (int) $order_recurring_id . "' AND type='" . self::TRANSACTION_PAYMENT . "' ORDER BY date_added DESC LIMIT 0,1" )->row['date_added'];
	}

	/**
	 * @param int $length
	 *
	 * @return string
	 */
	private function randomString( $length = 32 ) {
		//our array add all letters and numbers if you wish
		$chars = array(
			'a',
			'b',
			'c',
			'd',
			'e',
			'f',
			'g',
			'h',
			'i',
			'j',
			'k',
			'l',
			'm',
			'n',
			'p',
			'q',
			'r',
			's',
			't',
			'u',
			'v',
			'w',
			'x',
			'y',
			'z',
			'1',
			'2',
			'3',
			'4',
			'5',
			'6',
			'7',
			'8',
			'9',
			'A',
			'B',
			'C',
			'D',
			'E',
			'F',
			'G',
			'H',
			'I',
			'J',
			'K',
			'L',
			'M',
			'N',
			'O',
			'P',
			'Q',
			'R',
			'S',
			'T',
			'U',
			'V',
			'W',
			'X',
			'Y',
			'Z',
		);

		$random_string = '';

		for ( $rand = 0; $rand < $length; $rand ++ ) {
			$random        = rand( 0, count( $chars ) - 1 );
			$random_string .= $chars[ $random ];
		}

		return $random_string;
	}

	/**
	 * @param $order_recurring_id
	 * @param $reference
	 * @param $amount
	 * @param $status
	 */
	public function addRecurringTransaction( $order_recurring_id, $reference, $amount, $status ) {
		if ( $status ) {
			$type = self::TRANSACTION_PAYMENT;
		} else {
			$type = self::TRANSACTION_FAILED;
		}

		$this->db->query( "INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET order_recurring_id='" . (int) $order_recurring_id . "', reference='" . $this->db->escape( $reference ) . "', type='" . (int) $type . "', amount='" . (float) $amount . "', date_added=NOW()" );
	}

	/**
	 * @param $order_recurring_id
	 *
	 * @return bool
	 */
	public function updateRecurringExpired( $order_recurring_id ) {
		$recurring_info = $this->getRecurring( $order_recurring_id );

		if ( $recurring_info['trial'] ) {
			// If we are in trial, we need to check if the trial will end at some point
			$expirable = (bool) $recurring_info['trial_duration'];
		} else {
			// If we are not in trial, we need to check if the recurring will end at some point
			$expirable = (bool) $recurring_info['recurring_duration'];
		}

		// If recurring payment can expire (trial_duration > 0 AND recurring_duration > 0)
		if ( $expirable ) {
			$number_of_successful_payments = $this->getTotalSuccessfulPayments( $order_recurring_id );

			$total_duration = (int) $recurring_info['trial_duration'] + (int) $recurring_info['recurring_duration'];

			// If successful payments exceed total_duration
			if ( $number_of_successful_payments >= $total_duration ) {
				$this->db->query( "UPDATE `" . DB_PREFIX . "order_recurring` SET status='" . self::RECURRING_EXPIRED . "' WHERE order_recurring_id='" . (int) $order_recurring_id . "'" );

				return true;
			}
		}

		return false;
	}

	/**
	 * @param $order_recurring_id
	 *
	 * @return bool
	 */
	public function updateRecurringTrial( $order_recurring_id ) {
		$recurring_info = $this->getRecurring( $order_recurring_id );

		// If recurring payment is in trial and can expire (trial_duration > 0)
		if ( $recurring_info['trial'] && $recurring_info['trial_duration'] ) {
			$number_of_successful_payments = $this->getTotalSuccessfulPayments( $order_recurring_id );

			// If successful payments exceed trial_duration
			if ( $number_of_successful_payments >= $recurring_info['trial_duration'] ) {
				$this->db->query( "UPDATE `" . DB_PREFIX . "order_recurring` SET trial='0' WHERE order_recurring_id='" . (int) $order_recurring_id . "'" );

				return true;
			}
		}

		return false;
	}

	/**
	 * @param $order_id
	 */
	public function suspendRecurringProfilesByOrder( $order_id ) {
		$recurrings = $this->db->query( "SELECT * FROM `" . DB_PREFIX . "order_recurring` WHERE order_id='{$order_id}'" );

		if ( $recurrings->num_rows ) {
			foreach ( $recurrings as $recurring ) {
				$this->suspendRecurringProfile( $recurring['order_recurring_id'] );
			}
		}
	}

	/**
	 * @param $order_recurring_id
	 *
	 * @return bool
	 */
	public function suspendRecurringProfile( $order_recurring_id ) {
		$this->db->query( "UPDATE `" . DB_PREFIX . "order_recurring` SET status='" . self::RECURRING_SUSPENDED . "' WHERE order_recurring_id='" . (int) $order_recurring_id . "'" );

		return true;
	}

	/**
	 * @param $order_id
	 *
	 * @return string
	 * @throws Exception
	 * @throws \QuickPay\API\Exception
	 */
	public function getSubscriptionLink( $order_id ) {
		$this->load->model( 'checkout/order' );
		$order_info = $this->model_checkout_order->getOrder( $order_id );

		$client = $this->getClient();

		$order_id = $this->session->data['order_id'];

		if ( ! $transaction_id = $client->getTransactionId( $order_id, 'subscription' ) ) {
			$transaction_id = $this->createSubscription( array_merge( $this->getBasePaymentData(), [ 'description' => 'OpenCart' ] ) );
		}

		$payment_link_data = $this->getSubscriptionLinkData();

		$payment_link = $this->createSubscriptionLink( $order_info, $transaction_id, $payment_link_data );
		$client->setPaymentLink( $order_id, $transaction_id, $payment_link, 'subscription' );

		if ( $this->cart->hasRecurringProducts() ) {
			foreach ( $this->cart->getRecurringProducts() as $item ) {
				if ( $item['recurring']['trial'] ) {
					$trial_price = $this->tax->calculate( $item['recurring']['trial_price'] * $item['quantity'], $item['tax_class_id'] );
					$trial_amt   = $this->currency->format( $trial_price, $this->session->data['currency'] );
					$trial_text  = sprintf( $this->language->get( 'text_trial' ), $trial_amt, $item['recurring']['trial_cycle'], $item['recurring']['trial_frequency'], $item['recurring']['trial_duration'] );

					$item['recurring']['trial_price'] = $trial_price;
				} else {
					$trial_text = '';
				}

				$recurring_price       = $this->tax->calculate( $item['recurring']['price'] * $item['quantity'], $item['tax_class_id'] );
				$recurring_amt         = $this->currency->format( $recurring_price, $this->session->data['currency'] );
				$recurring_description = $trial_text . sprintf( $this->language->get( 'text_recurring' ), $recurring_amt, $item['recurring']['cycle'], $item['recurring']['frequency'] );

				$item['recurring']['price'] = $recurring_price;

				if ( $item['recurring']['duration'] > 0 ) {
					$recurring_description .= sprintf( $this->language->get( 'text_length' ), $item['recurring']['duration'] );
				}

				if ( ! $item['recurring']['trial'] ) {
					// We need to override this value for the proper calculation in updateRecurringExpired
					$item['recurring']['trial_duration'] = 0;
				}

				$this->createRecurring( $item, $this->session->data['order_id'], $recurring_description, $transaction_id );
			}
		}

		return $payment_link;
	}

	/**
	 * @param array $payment_data
	 *
	 * @return mixed
	 * @throws \QuickPay\API\Exception
	 */
	private function createSubscription( $payment_data ) {
		$client = $this->getClient();

		$response = $client->request->post( 'subscriptions', $payment_data );

		if ( 201 === $response->httpStatus() ) {
			$payment = $response->asObject();

			$client->setTransactionId( $this->getOrderIdFromTransactionVariables( $payment ), $payment->id, 'subscription' );
		} else {
			list( $status, $headers, $response ) = $response->asRaw();
			throw new \QuickPay\API\Exception( $response );
		}

		return $payment->id;
	}

	/**
	 * @return array
	 */
	protected function getSubscriptionLinkData() {
		$this->load->model( 'checkout/order' );
		$order_info = $this->model_checkout_order->getOrder( $this->session->data['order_id'] );

		return [
			'amount'         => 100 * $this->currency->format( $order_info['total'], $order_info['currency_code'], '', false ),
			'language'       => $this->language->get( 'code' ),
			'callback_url'   => $this->url->link( 'extension/payment/' . $this->getInstanceName() . '/callback', '', 'SSL' ),
			'continue_url'   => $this->url->link( 'checkout/success', '', 'SSL' ),
			'cancel_url'     => $this->url->link( 'checkout/checkout', '', 'SSL' ),
			'customer_email' => $this->getCustomerEmail(),
		];
	}

	/**
	 * @param $order_info
	 * @param $transaction_id
	 *
	 * @return string
	 * @throws \QuickPay\API\Exception
	 */
	private function createSubscriptionLink( $order_info, $transaction_id, $subscription_link_data ) {
		$response = $this->getClient()->request->put( sprintf( 'subscriptions/%d/link', $transaction_id ), $subscription_link_data );

		if ( ! $response->isSuccess() ) {
			list( $status_code, $headers, $response_data ) = $response->asRaw();
			throw new \QuickPay\API\Exception( $response_data );
		}

		return $response->asObject()->url;
	}

	/**
	 * @param $recurring
	 * @param $order_id
	 * @param $description
	 * @param $reference
	 *
	 * @return mixed
	 */
	public function createRecurring( $recurring, $order_id, $description, $reference ) {
		$this->db->query( "INSERT INTO `" . DB_PREFIX . "order_recurring` SET `order_id` = '" . (int) $order_id . "', `date_added` = NOW(), `status` = '" . self::RECURRING_ACTIVE . "', `product_id` = '" . (int) $recurring['product_id'] . "', `product_name` = '" . $this->db->escape( $recurring['name'] ) . "', `product_quantity` = '" . $this->db->escape( $recurring['quantity'] ) . "', `recurring_id` = '" . (int) $recurring['recurring']['recurring_id'] . "', `recurring_name` = '" . $this->db->escape( $recurring['recurring']['name'] ) . "', `recurring_description` = '" . $this->db->escape( $description ) . "', `recurring_frequency` = '" . $this->db->escape( $recurring['recurring']['frequency'] ) . "', `recurring_cycle` = '" . (int) $recurring['recurring']['cycle'] . "', `recurring_duration` = '" . (int) $recurring['recurring']['duration'] . "', `recurring_price` = '" . (float) $recurring['recurring']['price'] . "', `trial` = '" . (int) $recurring['recurring']['trial'] . "', `trial_frequency` = '" . $this->db->escape( $recurring['recurring']['trial_frequency'] ) . "', `trial_cycle` = '" . (int) $recurring['recurring']['trial_cycle'] . "', `trial_duration` = '" . (int) $recurring['recurring']['trial_duration'] . "', `trial_price` = '" . (float) $recurring['recurring']['trial_price'] . "', `reference` = '" . $this->db->escape( $reference ) . "'" );

		return $this->db->getLastId();
	}

	/**
	 * @return array
	 */
	protected function getBaseSubscriptionData() {
		return array_merge( $this->getBasePaymentData(), [ 'description' => 'OpenCart' ] );
	}
}