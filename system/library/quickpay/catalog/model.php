<?php
/**
 * Created by PhpStorm.
 * User: PerfectSolution, Patrick Tolvstein
 * Date: 05/02/2018
 * Time: 10.19
 */

namespace QuickPay\Catalog;

require_once DIR_EXTENSION . 'quickpay/autoload.php';

/**
 * Trait Model
 *
 * @package QuickPay\Catalog
 */
trait Model {

	use \QuickPay\Instance;

	/**
	 * Model constructor.
	 *
	 * @param $registry
	 */
	public function __construct( $registry ) {
		parent::__construct( $registry );

		$this->load->language('extension/quickpay/payment/' . $this->getInstanceName());
	}

	/**
	 * @throws \QuickPay\API\Exception
	 * @throws \Exception
	 */
	public function getPaymentLink( $order_id ) {
		$this->load->model( 'checkout/order' );
		$order_info = $this->model_checkout_order->getOrder( $order_id );
        
		$client = $this->getClient();
        
		$order_id = $this->session->data['order_id'];
        
		if ( ! $transaction_id = $client->getTransactionId( $order_id, 'payment' ) ) {
			$transaction_id = $this->createPayment( array_merge( $this->getBasePaymentData(), $this->getPaymentData() ) );
		}
        
		$payment_link_data = array_merge( $this->getBasePaymentLinkData(), $this->getPaymentLinkData() );
        
		$payment_link = $this->createPaymentLink( $order_info, $transaction_id, $payment_link_data );
        
		$client->setPaymentLink( $order_id, $transaction_id, $payment_link, 'payment' );

		return $payment_link;
	}

	/**
	 * @return QuickPay
	 */
	public function getClient() {
		if ( ! isset( $this->quickpay ) ) {
			$this->quickpay = new \QuickPay\System\Library\QuickPay($this->registry);
			$this->quickpay->api(':' . $this->instanceConfig( 'api_key' ));
		}

		return $this->quickpay;
	}

	/**
	 * @param array $payment_data
	 *
	 * @return mixed
	 * @throws \QuickPay\API\Exception
	 */
	private function createPayment( $payment_data ) {
  	    $payment_data['order_id'] =$payment_data['order_id'].'_oc';
       
		$client = $this->getClient();

		$response = $client->request->post( 'payments', $payment_data );

		if ( 201 === $response->httpStatus() ) {
			$payment = $response->asObject();

			$client->setTransactionId( $this->getOrderIdFromTransactionVariables( $payment ), $payment->id, 'payment' );
		} else {
			list( $status, $headers, $response ) = $response->asRaw();
			throw new \QuickPay\API\Exception( $response );
		}

		return $payment->id;
	}

	/**
	 * @param $callback_body
	 *
	 * @return mixed
	 */
	private function getOrderIdFromTransactionVariables( $callback_body ) {
		if ( ! empty( $callback_body->variables ) && ! empty( $callback_body->variables->order_id ) ) {
		    $callback_body->variables->order_id = str_replace('_oc','',$callback_body->variables->order_id);
			return $callback_body->variables->order_id;
		}

		// Fallback
        $callback_body->order_id = str_replace('_oc', '', $callback_body->order_id);
		return (int) $callback_body->order_id;
	}

	/**
	 * Return common payment data
	 *
	 * @return array
	 */
	protected function getBasePaymentData() {
		$order_id = $this->session->data['order_id'];
		$this->load->model( 'checkout/order' );
		$order_info = $this->model_checkout_order->getOrder( $order_id );

		return [
			'order_id'         => $this->getClient()->formatOrderId( $order_id, $this->instanceConfig( 'order_number_prefix' ) ),
			'currency'         => $order_info['currency_code'],
			'basket'           => $this->getPaymentBasketData(),
			'shipping'		   => $this->getShippingData(),
			'shipping_address' => $this->getPaymentShippingData( $order_info ),
			'invoice_address'  => $this->getPaymentInvoiceData( $order_info ),
			'test_mode'        => 'test' === $this->instanceConfig( 'test' ),
			'variables'        => [
				'order_id' => $order_id,
			],
		];
	}

	/**
	 * @return array
	 */
	protected function getPaymentBasketData() {
		$items = 0;
		$item_discount = 0;

		foreach ( $this->cart->getProducts() as $product ) {
			$items += $product['quantity'];
		}
		
		$this->load->model( 'checkout/order' );
		$totals = $this->model_checkout_order->getTotals( $this->session->data['order_id'] );

		if ( $totals ) {
			foreach ( $totals as $total ) {
				if ( $total['code'] == 'coupon' ) {
					if ( !empty( $total['value'] ) ) {
						$item_discount = abs($total['value']) / $items;
					}
				}
			}
		}
		
		$basket = [];
		
		foreach ( $this->cart->getProducts() as $product ) {
			if ( !empty( $item_discount ) ) {
				$product['price'] -= $item_discount;
			}
			
			$basket[] = array(
				'qty'        => $product['quantity'],
				'item_no'    => $product['product_id'],
				'item_name'  => html_entity_decode( $product['name'] ),
				'item_price' => $this->tax->calculate( $product['price'], $product['tax_class_id'] ) * 100,
				'vat_rate'   => $this->calculateBasketItemTaxesRate( $product['price'], $product['tax_class_id'] ),
			);
		}

		return $basket;
	}

	/**
	 * Calculates a single basket item tax rate
	 *
	 * @param $price
	 * @param $tax_class_id
	 *
	 * @return float|int
	 */
	protected function calculateBasketItemTaxesRate( $price, $tax_class_id ) {
		$taxes = $this->tax->getRates( $price, $tax_class_id );
		$rate  = 0;

		if ( ! empty( $taxes ) && is_array( $taxes ) ) {
			foreach ( $taxes as $tax ) {
				$rate += (float) $tax['rate'];
			}
		}

		return $rate > 0 ? ( $rate / 100 ) : $rate;
	}
	
	/**
	 * @return array
	 */
	public function getShippingData() {
		$shipping = [];
		
		$this->load->model( 'checkout/order' );
		$totals = $this->model_checkout_order->getTotals( $this->session->data['order_id'] );
		
		if ( $totals ) {
			foreach ( $totals as $total ) {
				if ( $total['code'] == 'shipping' ) {
					if ( $total['value'] > 0 ) {
						if ( isset( $this->session->data['shipping_method'] ) ) {
							$shipping['company'] = $total['title'];
							$shipping['amount'] = $total['value'];
							$shipping['vat_rate'] = 0;
							
							if ( !empty( $this->session->data['shipping_method']['tax_class_id'] ) ) {
								$tax_rates = $this->tax->getRates($this->session->data['shipping_method']['cost'], $this->session->data['shipping_method']['tax_class_id']);

								foreach ($tax_rates as $tax_rate) {
									if ( empty( $shipping['vat_rate'] ) ) {
										$shipping['amount'] += $tax_rate['amount'];
										$shipping['vat_rate'] = (int)$tax_rate['rate'] / 100;
									}
								}
							}
						}
					}
				}
			}
		}
		
		return $shipping;
	}

	/**
	 * @return array
	 */
	protected function getPaymentShippingData( $order_info ) {
		if ( !empty( $order_info['shipping_iso_code_3'] ) ) {
			return [
				'name'          => html_entity_decode( $order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'], ENT_QUOTES, 'UTF-8' ),
				'company_name'  => html_entity_decode( $order_info['shipping_company'], ENT_QUOTES, 'UTF-8' ),
				'street'        => html_entity_decode( $order_info['shipping_address_1'], ENT_QUOTES, 'UTF-8' ),
				'zip_code'      => html_entity_decode( $order_info['shipping_postcode'], ENT_QUOTES, 'UTF-8' ),
				'city'          => html_entity_decode( $order_info['shipping_city'], ENT_QUOTES, 'UTF-8' ),
				'country_code'  => html_entity_decode( $order_info['shipping_iso_code_3'], ENT_QUOTES, 'UTF-8' ),
				'phone_number'  => html_entity_decode( $order_info['telephone'], ENT_QUOTES, 'UTF-8' ),
	            'mobile_number' => html_entity_decode( $order_info['telephone'], ENT_QUOTES, 'UTF-8' ),
				'email'         => $order_info['email'],
			];
		} else {
			return $this->getPaymentInvoiceData( $order_info );
		}
	}

	/**
	 * @return array
	 */
	protected function getPaymentInvoiceData( $order_info ) {
		if (empty($order_info['payment_iso_code_3'])) {
			$order_info['payment_iso_code_3'] = $order_info['shipping_iso_code_3'];
		}

		return [
			'name'          => html_entity_decode( $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'], ENT_QUOTES, 'UTF-8' ),
			'street'        => html_entity_decode( $order_info['payment_address_1'], ENT_QUOTES, 'UTF-8' ),
			'zip_code'      => html_entity_decode( $order_info['payment_postcode'], ENT_QUOTES, 'UTF-8' ),
			'city'          => html_entity_decode( $order_info['payment_city'], ENT_QUOTES, 'UTF-8' ),
			'country_code'  => html_entity_decode( $order_info['payment_iso_code_3'], ENT_QUOTES, 'UTF-8' ),
			'phone_number'  => html_entity_decode( $order_info['telephone'], ENT_QUOTES, 'UTF-8' ),
            'mobile_number' => html_entity_decode( $order_info['telephone'], ENT_QUOTES, 'UTF-8' ),
			'email'         => $order_info['email'],
		];
	}

	/**
	 * Returns common payment link data
	 *
	 * @return array
	 */
	protected function getBasePaymentLinkData() {
		$this->load->model( 'checkout/order' );
		$order_info = $this->model_checkout_order->getOrder( $this->session->data['order_id'] );

		return [
			'amount'         => 100 * $this->currency->format( $order_info['total'], $order_info['currency_code'], 0, false ),
			'language'       => $this->language->get( 'code' ),
			'callback_url'   => $this->getCallbackUrl(),
			'continue_url'   => $this->url->link( 'checkout/success', '', 'SSL' ),
			'cancel_url'     => $this->url->link( 'checkout/checkout', '', 'SSL' ),
			'customer_email' => $this->getCustomerEmail(),
		];
	}

	/**
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function getCallbackUrl( $args = [] ) {
		return str_replace( '&amp;', '&', $this->url->link( 'extension/quickpay/payment/' . $this->getInstanceName() . '|callback', $args, 'SSL' ) );
	}

	/**
	 * @return mixed
	 */
	protected function getCustomerEmail() {
		if ( isset( $this->session->data['guest']['email'] ) ) {
			$customer_email = $this->session->data['guest']['email'];
		} else {
			$customer_email = $this->customer->getEmail();
		}

		return $customer_email;
	}

	/**
	 * @param $order_info
	 * @param $transaction_id
	 *
	 * @return string
	 * @throws \QuickPay\API\Exception
	 */
	private function createPaymentLink( $order_info, $transaction_id, $payment_link_data ) {
		$response = $this->getClient()->request->put( sprintf( 'payments/%d/link', $transaction_id ), $payment_link_data );

		if ( ! $response->isSuccess() ) {
			list( $status_code, $headers, $response_data ) = $response->asRaw();
			throw new \QuickPay\API\Exception( $response_data );
		}

		return $response->asObject()->url;
	}

	/**
	 * @param $address
	 * @param $total
	 *
	 * @return array
	 */
	public function getMethod( $address, $total = 0 ) {
		return $this->getMethodData( $address, $total );
	}

	/**
	 * @param $address
	 * @param $total
	 *
	 * @return array
	 */
	public function getMethodData( $address, $total, $code = '') {
		$query = $this->db->query( "SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $this->instanceConfig( 'geo_zone_id' ) . "' AND country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')" );

		if ( $this->instanceConfig( 'total' ) > 0 && $this->instanceConfig( 'total' ) > $total ) {
			$status = false;
		} elseif ( ! $this->instanceConfig( 'geo_zone_id' ) ) {
			$status = true;
		} elseif ( $query->num_rows ) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = array();

		if ( $status ) {
			$method_data = array(
				'code'       => 'quickpay'.$code,
				'title'      => $this->language->get( 'text_title' ),
				'terms'      => '',
				'sort_order' => $this->instanceConfig( 'sort_order' ),
			);
		}

		return $method_data;
	}

	/**
	 * Returns gateway specific payment data
	 *
	 * @return array
	 */
	abstract public function getPaymentData();

	/**
	 * Returns gateway specific payment link data
	 *
	 * @return array
	 */
	abstract public function getPaymentLinkData();
}