<?php

namespace Opencart\Catalog\Model\Extension\QuickPay\Payment;

require_once DIR_EXTENSION . 'quickpay/autoload.php';

/**
 * Class ModelExtensionPaymentQuickPay
 */
class QuickPayViabill extends \Opencart\System\Engine\Model implements \QuickPay\Statuses {
	use \Quickpay\Catalog\Model;
	use \Quickpay\Catalog\Recurring\Model;

	public function getMethod( $address, $total = 0 ) {
		return $this->getMethodData( $address, $total, '_viabill'  );
	}

	/**
	 * Return the name of the payment instance
	 *
	 * @return string
	 */
	public function getInstanceName() {
		return 'quickpay_viabill';
	}

	/**
	 * Return gateway specific payment link data
	 *
	 * @return array
	 */
	public function getPaymentLinkData() {
		return [
			'payment_methods' => 'viabill',
		];
	}

	/**
	 * Returns gateway specific payment data
	 *
	 * @return array
	 */
	public function getPaymentData() {
		return [
		];
	}
}
