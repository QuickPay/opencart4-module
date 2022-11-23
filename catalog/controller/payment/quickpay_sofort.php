<?php

namespace Opencart\Catalog\Controller\Extension\QuickPay\Payment;

require_once DIR_EXTENSION . 'quickpay/autoload.php';

/**
 * Class ControllerExtensionPaymentQuickPayAnydaysplit
 */
class QuickPaySofort extends \Opencart\System\Engine\Controller implements \QuickPay\Statuses {

	use \QuickPay\Catalog\Controller;

	/**
	 * The name of the instance
	 *
	 * @return string
	 */
	public function getInstanceName() {
		return 'quickpay_sofort';
	}
}