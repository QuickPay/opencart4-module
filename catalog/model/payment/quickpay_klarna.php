<?php

namespace Opencart\Catalog\Model\Extension\QuickPay\Payment;

require_once DIR_EXTENSION . 'quickpay/autoload.php';

/**
 * Class ModelExtensionPaymentQuickPay
 */
class QuickPayKlarna extends \Opencart\System\Engine\Model implements \QuickPay\Statuses {
    use \Quickpay\Catalog\Model;
    use \Quickpay\Catalog\Recurring\Model;

    /**
     * Return the data of the payment method
     *
     * @return string
     */
    public function getMethods( $address, $total = 0 ) {
        return $this->getMethodData( $address, $total, 'quickpay_klarna' );
    }

    /**
     * Return the name of the payment instance
     *
     * @return string
     */
    public function getInstanceName() {
        return 'quickpay_klarna';
    }

    /**
     * Return gateway specific payment link data
     *
     * @return array
     */
    public function getPaymentLinkData() {
        return [
            'payment_methods' => 'klarna-payments',
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
