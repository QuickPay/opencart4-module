<?php
/**
 * Created by PhpStorm.
 * User: PerfectSolution, Patrick Tolvstein
 * Date: 05/02/2018
 * Time: 10.31
 */

namespace QuickPay;

/**
 * Trait Instance
 *
 * @package QuickPay
 */
trait Instance {

	/**
	 * Return the name of the payment instance
	 *
	 * @return string
	 */
	abstract public function getInstanceName();

	/**
	 * Convenience wrapper to get settings from the specific instance type
	 *
	 *
	 * @param $config_field
	 *
	 * @return mixed
	 */
	public function instanceConfig( $config_field ) {
		return $this->config->get( sprintf( 'payment_%s_%s', $this->getInstanceName(), $config_field ) );
	}
}