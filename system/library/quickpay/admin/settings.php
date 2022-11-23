<?php

namespace QuickPay\Admin;

/**
 * Trait Settings
 *
 * @package QuickPay\Admin
 */
trait Settings {

	/**
	 * @var array
	 */
	protected $data = [];

	/**
	 * @var array
	 */
	protected $error = [];

	/**
	 * @return mixed
	 */
	public function index() {
		$instance_extension_path = $this->get_extension_instance_path();

		// Load base translations
		$this->load->language( 'extension/quickpay/payment/quickpay' );
		// Load instance specific translations
		$this->load->language( $instance_extension_path );

		$this->document->setTitle( $this->language->get( 'heading_title' ) );

		$this->load->model( 'setting/setting' );

		if ( ( $this->request->server['REQUEST_METHOD'] == 'POST' ) && $this->validate() ) {
			$this->model_setting_setting->editSetting( 'payment_' . $this->getInstanceName(), $this->request->post );

			$this->session->data['success'] = $this->language->get( 'text_success' );

			$this->response->redirect( $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment') );
		}

		$this->populateSettingsFields();

		$this->populateErrorMessages();

		$this->data['instance'] = $this->getInstanceName();

		$this->data['breadcrumbs'] = array();

		$this->data['breadcrumbs'][] = array(
			'text' => $this->language->get( 'text_home' ),
			'href' => $this->url->link( 'common/dashboard', 'user_token=' . $this->session->data['user_token'], true ),
		);

		$this->data['breadcrumbs'][] = array(
			'text' => $this->language->get( 'text_extension' ),
			'href' => $this->url->link( 'marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true ),
		);

		$this->data['breadcrumbs'][] = array(
			'text' => $this->language->get( 'heading_title' ),
			'href' => $this->url->link( 'extension/quickpay/payment/' . $this->getInstanceName(), 'user_token=' . $this->session->data['user_token'], true ),
		);

		$this->data['heading_title'] = $this->language->get( 'heading_title' );

		$this->data['save'] = $this->url->link('extension/quickpay/' . $instance_extension_path . '|save', 'user_token=' . $this->session->data['user_token']);
		$this->data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		$this->data['header']      = $this->load->controller( 'common/header' );
		$this->data['column_left'] = $this->load->controller( 'common/column_left' );
		$this->data['footer']      = $this->load->controller( 'common/footer' );

		$this->data['section'] = [
			'api'     => $this->load->view('extension/quickpay/payment/quickpay/settings/api', $this->data),
			'gateway' => $this->load->view('extension/quickpay/payment/quickpay/settings/gateway', $this->data),
			'others'  => $this->load->view('extension/quickpay/payment/quickpay/settings/others', $this->data),
		];

		return $this->response->setOutput($this->load->view('extension/quickpay/payment/' . $this->getInstanceName(), $this->data));
	}

	public function save(): void {
		$this->load->language( 'extension/quickpay/payment/quickpay' );

		$json = $this->validate();

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_' . $this->getInstanceName(), $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return string
	 */
	protected function get_extension_instance_path() {
		return 'payment/' . $this->getInstanceName();
	}

	/**
	 * @return bool
	 */
	protected function validate() {
		$json = [];

		foreach ( $this->getValidationFields() as $field ) {
			if ( 'warning' === $field ) {
				if ( ! $this->user->hasPermission( 'modify', 'extension/quickpay/' . $this->get_extension_instance_path() ) ) {
					$json['error'] = $this->language->get( 'error_permission' );
				}
			} else {
				$full_field_name = sprintf( 'payment_%s_%s', $this->getInstanceName(), $field );
				if ( ! $this->request->post[ $full_field_name ] ) {
					//$json[ $full_field_name ] = $this->language->get( sprintf( 'error_%s', $field ) );
					$json['error'] = $this->language->get( sprintf( 'error_%s', $field ) );
				}
			}
		}

		return $json;
	}

	/**
	 * @return array
	 */
	public function getValidationFields() {
		return array_merge( $this->getBaseValidationFields(), $this->getInstanceValidationFields() );
	}

	/**
	 * Common validation fields
	 *
	 * @return array
	 */
	private function getBaseValidationFields() {
		return [
			'warning',
			'api_key',
			'private_key',
		];
	}

	/**
	 * Populates data for the settings fields.
	 */
	protected function populateSettingsFields() {
		if ( ! isset( $this->data['settings'] ) || ! is_array( $this->settings ) ) {
			$this->data['settings'] = [];
		}

		foreach ( $this->getSettingsFields() as $field ) {
			$field_name = sprintf( 'payment_%s_%s', $this->getInstanceName(), $field );

			if ( isset( $this->request->post[ $field_name ] ) ) {
				$this->data['settings'][ $field ] = $this->request->post[ $field_name ];
			} else {
				$this->data['settings'][ $field ] = $this->config->get( $field_name );
			}
		}

		if ( ! $this->instanceConfig( 'cron_token' ) ) {
			$this->data['settings']['cron_token'] = md5( mt_rand() );
		}

		if ( $this->request->server['HTTPS'] ) {
			$server = HTTPS_SERVER;
		} else {
			$server = HTTP_SERVER;
		}

		$this->data['settings']['cron_url'] = 'https://' . parse_url( $server, PHP_URL_HOST ) . dirname( parse_url( $server, PHP_URL_PATH ) ) . '/index.php?route=extension/recurring/' . $this->getInstanceName() . '/recurring&cron_token={CRON_TOKEN}';

		// Load order statuses
		$this->load->model( 'localisation/order_status' );
		$this->data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		// Load geo zones
		$this->load->model( 'localisation/geo_zone' );
		$this->data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
	}

	/**
	 * @return array
	 */
	protected function getSettingsFields() {
		return array_merge( $this->getBaseSettingsFields(), $this->getInstanceSettingsFields() );
	}

	/**
	 * @return array
	 */
	protected function getBaseSettingsFields() {
		return [
			'api_key',
			'private_key',
			'total',
			'order_status_id',
			'geo_zone_id',
			'test',
			'status',
			'sort_order',
			'order_number_prefix'
		];
	}

	/**
	 * Populate error messages for UI
	 */
	public function populateErrorMessages() {
		foreach ( $this->getValidationFields() as $check_field ) {
			$merged_field_name = sprintf( 'payment_%s_%s', $this->getInstanceName(), $check_field );

			$this->data[ 'error_' . $check_field ] = isset( $this->error[ $merged_field_name ] ) ? $this->error[ $merged_field_name ] : '';
		}
	}

	/**
	 * @param $key
	 *
	 * @return string
	 */
	protected function i18n( $key ) {
		$instance_specific_translation = $this->language->get( $key . '_' . $key );
		if ( $key === $instance_specific_translation ) {
			return $this->language->get( $key );
		}

		return $instance_specific_translation;
	}

	/**
	 * @return array
	 */
	abstract protected function getInstanceSettingsFields();

	/**
	 * @return array
	 */
	abstract protected function getInstanceValidationFields();
}