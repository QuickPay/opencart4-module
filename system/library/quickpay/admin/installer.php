<?php

namespace QuickPay\Admin;

/**
 * Trait Installer
 *
 * @package QuickPay\Admin
 */
trait Installer {

	/**
	 * Creates necessary tables on module install
	 */
	public function install() {
		$this->db->query( "
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "quickpay_transaction` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `order_id` int(11) NOT NULL,
		  `transaction_id` int(11) DEFAULT NULL,
		  `payment_link` varchar(150) DEFAULT NULL,
		  `transaction_type` varchar(150) DEFAULT NULL,
		  `date_added` datetime NOT NULL,
		  `date_modified` datetime NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8;" );
	}

	/**  */
	public function uninstall() {
	}

	public function testEvent( $eventRoute, &$data, &$template ) {
		//$template = str_replace('Orders', 'kage', $template);
	}
}