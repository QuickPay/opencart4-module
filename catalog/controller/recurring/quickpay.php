<?php
namespace Opencart\Catalog\Controller\Extension\QuickPay\Recurring;

require_once DIR_EXTENSION . 'quickpay/autoload.php';

class QuickPay extends \Opencart\System\Engine\Controller implements \QuickPay\Statuses {

	use QuickPay\Catalog\Recurring\Controller;

	/**
	 * Return the name of the payment instance
	 *
	 * @return string
	 */
	public function getInstanceName() {
		return 'quickpay';
	}
}