<?php

namespace Opencart\Admin\Controller\Extension\QuickPay\Payment;

require_once DIR_EXTENSION . 'quickpay/autoload.php';

/**
 * Class ControllerExtensionQuickpay
 */
class QuickPay extends \Opencart\System\Engine\Controller {

	use \Quickpay\Instance;
	use \Quickpay\Admin\Settings;
	use \Quickpay\Admin\Installer;

	/**
	 * Return the name of the payment instance
	 *
	 * @return string
	 */
	public function getInstanceName() {
		return 'quickpay';
	}
  
	/**
	 * @return array
	 */
	protected function getInstanceSettingsFields() {
		return [
			'autocapture',
			'autofee',
			'text_on_statement',
			'branding_id',
			'payment_methods',
			'cron_token',
		];
	}

	/**
	 * @return array
	 */
	protected function getInstanceValidationFields() {
		return [
			/*'payment_methods',*/
		];
	}
}