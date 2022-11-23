<?php
/**
 * Created by PhpStorm.
 * User: PerfectSolution, Patrick Tolvstein
 * Date: 12/02/2018
 * Time: 12.58
 */

namespace QuickPay;

/**
 * Interface Statuses
 *
 * @package QuickPay
 */
interface Statuses {

	const RECURRING_ACTIVE = 1;
	const RECURRING_INACTIVE = 2;
	const RECURRING_CANCELLED = 3;
	const RECURRING_SUSPENDED = 4;
	const RECURRING_EXPIRED = 5;
	const RECURRING_PENDING = 6;

	const TRANSACTION_DATE_ADDED = 0;
	const TRANSACTION_PAYMENT = 1;
	const TRANSACTION_OUTSTANDING_PAYMENT = 2;
	const TRANSACTION_SKIPPED = 3;
	const TRANSACTION_FAILED = 4;
	const TRANSACTION_CANCELLED = 5;
	const TRANSACTION_SUSPENDED = 6;
	const TRANSACTION_SUSPENDED_FAILED = 7;
	const TRANSACTION_OUTSTANDING_FAILED = 8;
	const TRANSACTION_EXPIRED = 9;
}