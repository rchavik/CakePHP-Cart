<?php
/**
 * Payment Gateway Behavior
 *
 * Attaches payment gateway datasources to a model (usually payments) for IPN and other data API features
 *
 * Supported Model Callbacks:
 *  - beforeIpnValidate($data, $gatewayConfig)
 *  - afterIpnValidate($response)
 *
 * @package default
 * @author Dean
 */
class PaymentGatewayBehavior extends ModelBehavior {
	
	/**
	 * Default settings for the behavior
	 *
	 * @var string
	 */
	var $defaults = array(
		'gateway' => null,
		// For PaypalExpress Only
		'urls' => array(
			'complete_return_url' => 'http://example.com/complete',
			'cancel_return_url' => 'http://example.com/cancel',
			'error_return_url' => 'http://example.com/error',
		)
	);
	
	/**
	 * Settings for the behavior on every model
	 *
	 * @var string
	 */
	var $settings = array();

	/**
	 * Initialize behavior settings
	 *
	 * @param string $Model 
	 * @param string $settings 
	 * @return void
	 * @author Dean
	 */
	function setup(&$Model, $settings = array()) {
		$this->settings[$Model->name] = array_merge($this->defaults, $settings);
	}
	
	/**
	 * If the developer declared the trigger in the model, call it
	 *
	 * @param object $Model instance of model
	 * @param string $trigger name of trigger to call
	 * @access protected
	 */
	function _callback(&$Model, $trigger, $parameters = array()) {
		if (method_exists($Model, $trigger)) {
			return call_user_func_array(array($Model, $trigger), $parameters);
		}
	}
	
	/**
	 * Used to adjust the payment gateway before using the behavior
	 *
	 * @param string $Model 
	 * @param string $gatewayConfig 
	 * @return void
	 * @author Dean
	 */
	public function setGateway(&$Model, $gatewayConfig) {
		$this->settings[$Model->name]['gateway'] = $gatewayConfig;
	}
	
	/**
	 * Used for setting the 'cancel_return_url' and/or 'error_return_url' for PaypalExpress
	 *
	 * @param object $Model 
	 * @param array $urls array('cancel_return_url' => 'http://localhost/cancel', 'error_return_url' => 'http://localhost/error')
	 * @return void
	 * @author Dean
	 */
	public function setUrls(&$Model, $urls) {
		$this->settings[$Model->name]['urls'] = $urls;
	}
	
	/**
	 * Returns an instance of the payment gateway
	 *
	 * @return $PaymentGatewayDatasource instance for calling methods
	 * @author Dean
	 */
	public function loadGateway(&$Model, $gatewayConfig = null) {
		App::import('Model', 'ConnectionManager', false);
		if (!$gatewayConfig) {
			 $gatewayConfig = $this->settings[$Model->name]['gateway'];
		}
		return ConnectionManager::getDataSource($gatewayConfig);
	}	
	
	public function purchase(&$Model, $amount, $data) {
		$this->_callback($Model, 'beforePurchase', array($amount, $data));
		$gateway = $this->loadGateway($Model);
		$gateway->urls = $this->settings[$Model->name]['urls'];
		$success = $gateway->purchase($amount, $data);
		if (!$success) {
			$Model->error = $gateway->error;
		}
		$this->_callback($Model, 'afterPurchase', array($success));
		return $success;
	}
	
	/**
	 * Verifies POST data given by the instant payment notification
	 *
	 * @param array $data Most likely directly $_POST given by the controller.
	 * @return boolean true | false depending on if data received is actually valid from paypal and not from some script monkey
	 */
	public function ipn(&$Model, $data) {
		$this->_callback($Model, 'beforeIpn', array($data, $this->settings[$Model->name]['gateway']));
		if(!empty($data)){
			$gateway = $this->loadGateway($Model);
			$result = $gateway->ipn($data);
			$this->_callback($Model, 'afterIpn', array($result));
			return $result;
		}
		return false;
	}
	
	/**
	 * builds the associative array for paypalitems only if it was a cart upload
	 *
	 * @param raw post data sent back from paypal
	 * @return array of cakePHP friendly association array.
	 */
	public function extractLineItems(&$Model, $data) {
		$gateway = $this->loadGateway($Model);
		return $gateway->extractLineItems($data);
	}
}
?>