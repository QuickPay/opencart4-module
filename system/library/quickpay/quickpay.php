<?php

namespace QuickPay\System\Library;

require_once DIR_EXTENSION . 'quickpay/autoload.php';

/**
 * Class QuickPay
 *
 * @package QuickPay
 */
class QuickPay {

	use \QuickPay\Order;

	/**
	 * Contains the QuickPay_Request object
	 *
	 * @type Request
	 * @access public
	 **/
	public $request;


	/**
	 * __construct function.
	 *
	 * Instantiates the main class.
	 * Creates a client which is passed to the request construct.
	 *
	 * @auth_string string Authentication string for QuickPay
	 *
	 * @access      public
	 */
	public function api( $auth_string = '' ) {
		$client        = new \QuickPay\API\Client( $auth_string );
		$this->request = new \QuickPay\API\Request( $client );
	}
}
