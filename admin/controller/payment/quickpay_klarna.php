<?php

namespace Opencart\Admin\Controller\Extension\QuickPay\Payment;

require_once DIR_EXTENSION . 'quickpay/autoload.php';

/**
 * Class ControllerExtensionPaymentQuickpayKlarna
 */
class QuickPayKlarna extends \Opencart\System\Engine\Controller {

	use \Quickpay\Instance;
	use \Quickpay\Admin\Settings;
	use \Quickpay\Admin\Installer;

	/**
	 * Return the name of the payment instance
	 *
	 * @return string
	 */
	public function getInstanceName() {
		return 'quickpay_klarna';
	}

	/**
	 * @return array
	 */
	protected function getInstanceSettingsFields() {
		return [];
	}

	/**
	 * @return array
	 */
	protected function getInstanceValidationFields() {
		return [];
	}
}