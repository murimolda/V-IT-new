<?php
/**
* WC_Gateway_RBSPaymentAlfabank class
*/
use WoocommerceRBSPaymentAlfabank\Includes\FormFieldsGenerator;
if (!defined('ABSPATH')) {
exit;
}
/**
* RBSPaymentAlfabank Gateway.
* @class    WC_Gateway_RBSPaymentAlfabank
*/
class WC_Gateway_RBSPaymentAlfabank extends WC_Payment_Gateway
{
/**
* Payment gateway instructions.
* @var string
*
*/
protected $instructions;
/**
* Whether the gateway is visible for non-admin users.
* @var boolean
*
*/
public $id = 'alfabank';
public $module_version = "5.3.4";
public $has_fields;
public $supports;
public $method_title;
public $method_description;
public $title;
public $description;
public $merchant;
public $password;
public $test_mode;
public $stage_mode;
public $order_status_paid;
public $send_order;
public $tax_system;
public $tax_type;
public $success_url;
public $fail_url;
public $backToShopUrl;
public $backToShopUrlName;
public $versionFfd;
public $paymentMethodType;
public $paymentObjectType;
public $paymentObjectType_delivery;
public $pData;
public $logging;
public $orderNumberById;
public bool $allowCallbacks;
public string $callbackType = "STATIC";
public $enable_for_methods;
public string $test_url;
public string $prod_url;
public $fesHelper = null;
public $fes_cashboxId;
protected function setup_properties()
{
$this->icon = apply_filters('woocommerce_cod_icon', '');
$this->method_title = RBSPAYMENT_ALFABANK_PAYMENT_NAME;
$this->method_description = __('Allows customers to pay with bank cards through `Alfabank` in your WooCommerce store.', 'woocommerce-gateway-alfabank');
$this->has_fields = false;
}
public $enable_GooglePay;
public function __construct()
{
$this->setup_properties();
$this->supports = array(
'products',
);
if (defined('RBSPAYMENT_ALFABANK_ENABLE_REFUNDS') && RBSPAYMENT_ALFABANK_ENABLE_REFUNDS == true) {
$this->supports[] = 'refunds';
}
$this->init_form_fields();
$this->init_settings();
$this->title = $this->get_option('title');
$this->description = $this->get_option('description');
$this->instructions = $this->get_option('instructions', $this->description);
$this->merchant = $this->get_option('merchant');
$this->password = $this->get_option('password');
if (!empty($this->get_option('token'))) {
$decoded_credentials = base64_decode($this->get_option('token'));
list($l, $p) = explode(':', $decoded_credentials);
$this->merchant = $l;
$this->password = $p;
}
$this->test_mode = $this->get_option('test_mode');
$this->stage_mode = $this->get_option('stage_mode');
$this->description = $this->get_option('description');
$this->order_status_paid = $this->get_option('order_status_paid');
$this->send_order = $this->get_option('send_order');
$this->tax_system = $this->get_option('tax_system');
$this->tax_type = $this->get_option('tax_type');
$this->success_url = $this->get_option('success_url');
$this->fail_url = $this->get_option('fail_url');
$this->backToShopUrl = $this->get_option('backToShopUrl');
$this->backToShopUrlName = $this->get_option('backToShopUrlName');
$this->versionFfd = $this->get_option('versionFfd');
$this->paymentMethodType = $this->get_option('paymentMethodType');
$this->paymentObjectType = $this->get_option('paymentObjectType');
$this->paymentObjectType_delivery = $this->get_option('paymentMethodType_delivery');
$this->fes_cashboxId = $this->get_option('fes_cashboxId');
$this->pData = get_plugin_data(__FILE__);
$this->logging = RBSPAYMENT_ALFABANK_ENABLE_LOGGING;
$this->orderNumberById = true; //false - must be installed WooCommerce Sequential Order Numbers
$this->allowCallbacks = defined('RBSPAYMENT_ALFABANK_ENABLE_CALLBACK') ? RBSPAYMENT_ALFABANK_ENABLE_CALLBACK : true;
$this->callbackType = defined('RBSPAYMENT_ALFABANK_CALLBACK_TYPE') ? RBSPAYMENT_ALFABANK_CALLBACK_TYPE : $this->callbackType;
$this->enable_for_methods = $this->get_option('enable_for_methods', array());
$this->enable_GooglePay = defined('RBSPAYMENT_ALFABANK_ENABLE_FAST_CHECKOUT') ? RBSPAYMENT_ALFABANK_ENABLE_FAST_CHECKOUT : false;;
$this->test_url = RBSPAYMENT_ALFABANK_TEST_URL;
$this->prod_url = RBSPAYMENT_ALFABANK_PROD_URL;
if (defined('RBSPAYMENT_ALFABANK_PROD_URL_ALTERNATIVE_DOMAIN') && defined('RBSPAYMENT_ALFABANK_PROD_URL_ALT_PREFIX')) {
if (substr($this->merchant, 0, strlen(RBSPAYMENT_ALFABANK_PROD_URL_ALT_PREFIX)) == RBSPAYMENT_ALFABANK_PROD_URL_ALT_PREFIX) {
$pattern = '/^https:\/\/[^\/]+/';
$this->prod_url = preg_replace($pattern, rtrim(RBSPAYMENT_ALFABANK_PROD_URL_ALTERNATIVE_DOMAIN, '/'), $this->prod_url);
} else {
$this->allowCallbacks = false;
}
}
add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
add_action('woocommerce_scheduled_subscription_payment_alfabank', array($this, 'process_subscription_payment'), 10, 2);
add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
add_action('woocommerce_api_alfabank', array($this, 'webhook_result'));
add_action('woocommerce_before_checkout_form', array($this, 'display_custom_error_message'), 12);
if ($this->enable_GooglePay && !empty($this->get_option('google_pay_merchantId'))) {
add_action('wp_enqueue_scripts', array($this, 'add_google_pay_script'), 14);
add_action('wp_footer', array($this, 'add_google_pay_button_on_checkout_page'), 15);
add_action('woocommerce_after_add_to_cart_button', array($this, 'add_google_pay_button_on_product_page'), 14);
add_action('wp_ajax_process_google_pay', array($this, 'handle_google_pay_ajax')); // For authorized users
add_action('wp_ajax_nopriv_process_google_pay', array($this, 'handle_google_pay_ajax')); // For unauthorized users
}
}
function handle_google_pay_ajax() {
$payment_token = isset($_POST['paymentToken']) ? sanitize_text_field($_POST['paymentToken']) : '';
$amount = isset($_POST['amount']) ? sanitize_text_field($_POST['amount']) : '';
$currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : '';
$idd_order_from_product_id = isset($_POST['idd_order_from_product_id']) ? sanitize_text_field($_POST['idd_order_from_product_id']) : '';
$billingPayerData = isset($_POST['billingPayerData']) ? $_POST['billingPayerData'] : '';
$email = isset($_POST['email']) ? sanitize_text_field($_POST['email']) : '';
if (empty($payment_token) || empty($amount) || empty($currency)) {
wp_send_json_error(['message' => 'Incorrect request data. Please ensure all fields are filled in.']);
return;
}
if (!empty($idd_order_from_product_id)){
$user_id = get_current_user_id();
$payment_method = $this->id;
$products = [
1 => [$idd_order_from_product_id, 1],
];
$mark_paid = false;
$order_id = $this->create_woocommerce_order($user_id, $payment_method, $products, $mark_paid, $billingPayerData);
if (is_wp_error($order_id)) {
echo 'Error: ' . $order_id->get_error_message();
} else {
}
} else {
$order_id = WC()->session->get('order_awaiting_payment');
if (empty($order_id)) {
$order_id = WC()->session->get('store_api_draft_order');
}
$order = wc_get_order($order_id);
if ($order && strpos($order->get_status(), "draft") !== false) {
$order->update_status('pending', __('Alfabank: DRAFT => PENDING', 'woocommerce'));
$order->set_payment_method($this->id);
$order->set_payment_method_title($this->method_title);
$order->save();
}
}
$data['merchant'] = substr($this->merchant, 0, -4);
$data['orderNumber'] = $order_id . "_" . time();
$data['amount'] = $amount;
$data['currencyCode'] = $currency;
$data['paymentToken'] = $payment_token;
$data['returnUrl'] = get_option('siteurl') . '?wc-api=alfabank' . '&action=result&order_id=' . $order_id;
$data['billingPayerData'] = $billingPayerData;
$data['email'] = $email;
$data['additionalParameters'] = array (
'CMS' => 'Wordpress ' . get_bloginfo('version') . " + woocommerce version: " . wpbo_get_woo_version_number(),
'Module-version' => $this->module_version,
'CMS_paymentType' => 'google_pay',
);
$action_address  = ($this->test_mode != "yes") ? RBSPAYMENT_ALFABANK_PROD_URL : RBSPAYMENT_ALFABANK_TEST_URL;
$gate_url = str_replace("payment/rest", "payment/google", $action_address) . "payment.do";
$headers = array(
'Content-Type: application/json'
);
$response = $this->_sendGatewayData(json_encode($data), $gate_url, $headers);
if (RBSPAYMENT_ALFABANK_ENABLE_LOGGING === true) {
$logData = $data;
$logData['password'] = $logData['paymentToken'] = '**removed from log**';
$this->writeLog("[REQUEST GPAY]: " . $gate_url . ": " . print_r($logData, true) . "\n[RESPONSE]: " . print_r($response, true));
}
header('Content-Type: application/json');
echo $response;
die;
}
function add_google_pay_button_on_product_page() {
$payment_sections = $this->get_option('google_pay_payment_sections');
if (is_product()
&& in_array('PRODUCT_PAGE', $payment_sections)
&& is_user_logged_in()
) {
global $post;
$product = wc_get_product( $post->ID );
if ( $product instanceof WC_Product_Subscription ) {
return;
} elseif ( $product instanceof WC_Product_Variation ) {
return;
} elseif ( $product instanceof WC_Product_Variable ) {
return;
} elseif ( $product instanceof WC_Product_Simple ) {
}
?>
<div id="google-pay-container" style="margin-top: 20px;"></div>
<script>
document.addEventListener("DOMContentLoaded", function() {
const checkGooglePayLoaded = setInterval(function() {
if (typeof google !== 'undefined') {
clearInterval(checkGooglePayLoaded);
initializeGooglePay();
}
}, 100);
});
function initializeGooglePay() {
const paymentsClient = new google.payments.api.PaymentsClient({
environment: googlePayParams.environment,
});
const paymentDataRequest = {
apiVersion: 2,
apiVersionMinor: 0,
allowedPaymentMethods: [{
type: 'CARD',
parameters: {
allowedAuthMethods: ['PAN_ONLY'],
allowedCardNetworks: ['MASTERCARD', 'VISA'],
billingAddressRequired: true,
billingAddressParameters: {
format: "FULL",
phoneNumberRequired: true
}
},
tokenizationSpecification: {
type: 'PAYMENT_GATEWAY',
parameters: {
gateway: googlePayParams.gatewayName,
gatewayMerchantId: googlePayParams.gatewayMerchantId
}
}
}],
merchantInfo: {
merchantId: googlePayParams.merchantId,
merchantName: googlePayParams.merchantName,
},
transactionInfo: {
totalPriceStatus: 'FINAL',
totalPrice: googlePayParams.totalPrice,
currencyCode: googlePayParams.currencyCode,
},
emailRequired: true,
};
const button = paymentsClient.createButton({
buttonColor: googlePayParams.buttonColor,
buttonType: googlePayParams.buttonType,
onClick: function() {
paymentsClient.loadPaymentData(paymentDataRequest).then(function(paymentData) {
console.log(paymentData);
processPayment(paymentData);
}).catch(function(err) {
console.error(err);
});
}
});
document.getElementById('google-pay-container').appendChild(button);
}
function processPayment(paymentData) {
const token = paymentData.paymentMethodData.tokenizationData.token;
const billing = paymentData.paymentMethodData.info.billingAddress;
const encodedToken = btoa(token);
jQuery('body').block({ message: null });
jQuery.ajax({
url: "<?php echo admin_url('admin-ajax.php');?>",
type: 'POST',
data: {
action: 'process_google_pay',
idd_order_from_product_id: <?php echo $product->get_id();?>,
amount: googlePayParams.totalPriceMin, //BPC
currency: googlePayParams.currencyCodeNum, //BPC
paymentToken: encodedToken,
billingPayerData: {
billingCity: billing.locality,
billingCountry: billing.countryCode,
billingAddressLine1: billing.address1,
billingAddressLine2: billing.address2,
billingAddressLine3: billing.address3,
billingPostalCode: billing.postalCode,
billingState: billing.administrativeArea,
},
email: paymentData.email
},
success: function(response) {
if (response.success) {
const returnUrl = "<?php echo get_option('siteurl') . '?wc-api=alfabank&action=callback&showMercy=1&mdOrder='; ?>";
window.location.href = returnUrl + response.data.orderId;
} else {
alert(response.error ? "gtw: " + response.error.message : 'Unknown error');
}
},
error: function(error) {
console.error('AJAX Error:', error);
alert('There was an error sending your payment.');
},
complete: function() {
jQuery('body').unblock();
}
});
}
</script>
<?php
}
}
function add_google_pay_button_on_checkout_page() {
$payment_sections = $this->get_option('google_pay_payment_sections');
if (!empty($this->get_option('google_pay_merchantId'))
&& is_checkout()
&& !is_order_received_page()
&& in_array('CHECKOUT_PAGE', $payment_sections)) {
?>
<script>
document.addEventListener("DOMContentLoaded", function() {
const checkGooglePayLoaded = setInterval(function() {
if (typeof google !== 'undefined') {
clearInterval(checkGooglePayLoaded);
initializeGooglePay();
}
}, 100);
});
function initializeGooglePay() {
const paymentsClient = new google.payments.api.PaymentsClient({
environment: googlePayParams.environment,
});
const paymentDataRequest = {
apiVersion: 2,
apiVersionMinor: 0,
allowedPaymentMethods: [{
type: 'CARD',
parameters: {
allowedAuthMethods: ['PAN_ONLY'],
allowedCardNetworks: ['MASTERCARD', 'VISA'],
billingAddressRequired: true,
billingAddressParameters: {
format: "FULL",
phoneNumberRequired: true
}
},
tokenizationSpecification: {
type: 'PAYMENT_GATEWAY',
parameters: {
gateway: googlePayParams.gatewayName,
gatewayMerchantId: googlePayParams.gatewayMerchantId
}
}
}],
merchantInfo: {
merchantId: googlePayParams.merchantId,
merchantName: googlePayParams.merchantName,
},
transactionInfo: {
totalPriceStatus: 'FINAL',
totalPrice: googlePayParams.totalPrice,
currencyCode: googlePayParams.currencyCode,
},
emailRequired: true,
};
const button = paymentsClient.createButton({
buttonColor: googlePayParams.buttonColor,//'default',
buttonType: googlePayParams.buttonType,//'buy',
onClick: function() {
paymentsClient.loadPaymentData(paymentDataRequest).then(function(paymentData) {
console.log(paymentData);
processPayment(paymentData);
}).catch(function(err) {
console.error(err);
});
}
});
document.getElementById('google-pay-container').appendChild(button);
}
function processPayment(paymentData) {
const token = paymentData.paymentMethodData.tokenizationData.token;
const billing = paymentData.paymentMethodData.info.billingAddress;
const encodedToken = btoa(token);
jQuery('body').block({ message: null });
jQuery.ajax({
url: "<?php echo admin_url('admin-ajax.php');?>",
type: 'POST',
data: {
action: 'process_google_pay',
amount: googlePayParams.totalPriceMin, //BPC
currency: googlePayParams.currencyCodeNum, //BPC
paymentToken: encodedToken,
billingPayerData: {
billingCity: billing.locality,
billingCountry: billing.countryCode,
billingAddressLine1: billing.address1,
billingAddressLine2: billing.address2,
billingAddressLine3: billing.address3,
billingPostalCode: billing.postalCode,
billingState: billing.administrativeArea,
},
email: paymentData.email
},
success: function(response) {
if (response.success) {
const returnUrl = "<?php echo get_option('siteurl') . '?wc-api=alfabank&action=callback&showMercy=1&mdOrder='; ?>";
window.location.href = returnUrl + response.data.orderId;
} else {
alert(response.error ? "gtw: " + response.error.message : 'Unknown error');
}
},
error: function(error) {
console.error('AJAX Error:', error);
alert('There was an error sending your payment.');
},
complete: function() {
jQuery('body').unblock();
}
});
}
document.addEventListener('DOMContentLoaded', () => {
const googlePayContainer = document.getElementById('google-pay-container');
const placePayment = document.getElementById('payment');
function moveGooglePayContainer() {
const totalsWrappers = document.querySelectorAll('.wp-block-woocommerce-checkout-order-summary-block');
const lastTotalsWrapper = totalsWrappers[totalsWrappers.length - 1];
if (googlePayContainer) {
if (placePayment) {
placePayment.parentNode.insertBefore(googlePayContainer, placePayment);
} else if (lastTotalsWrapper) {
lastTotalsWrapper.parentNode.insertBefore(googlePayContainer, lastTotalsWrapper.nextSibling);
} else {
setTimeout(moveGooglePayContainer, 500);
}
}
}
moveGooglePayContainer();
});
</script>
<div id="google-pay-container" style="margin: 20px 0;"></div>
<?php
}
}
function add_google_pay_script() {
$payment_sections = $this->get_option('google_pay_payment_sections');
if (empty($this->get_option('google_pay_merchantId'))) {
}
if (is_product() || is_checkout()) {
wp_enqueue_script('google-pay', 'https://pay.google.com/gp/p/js/pay.js', array(), null, true);
$price_total_formatted = WC()->cart->get_total(); //HTML
$price_total_formatted_ = WC()->cart->get_total('edit');
$currency_code = get_woocommerce_currency();
if (is_product()) {
global $post;
$product = wc_get_product($post->ID);
if ($product && is_a($product, 'WC_Product')) {
$product_price = $product->get_price();
$price_total_formatted_ = $product_price;
}
}
wp_localize_script('google-pay', 'googlePayParams' , array(
'totalPrice' => $price_total_formatted_,
'totalPriceMin' => $price_total_formatted_ * 100,
'currencyCode' => $currency_code,
'currencyCodeNum' => $this->get_numeric_currency_code($currency_code),
'merchantId' => $this->get_option('google_pay_merchantId'),
'merchantName' => $this->get_option('google_pay_merchantName'),
'gatewayMerchantId' => substr($this->merchant, 0, -4),
'gatewayName' => RBSPAYMENT_ALFABANK_GOOGLE_PAY_GATEWAY_NAME,
'environment' => $this->get_option('google_pay_mode'),
'buttonColor' => $this->get_option('google_pay_button_color'),
'buttonType' => $this->get_option('google_pay_button_type')
));
}
}
public function display_custom_error_message()
{
if (WC()->session->get('custom_error_message')) {
wc_print_notices();
WC()->session->__unset('custom_error_message');
}
}
public function init_form_fields()
{
$shipping_methods = array();
if (is_admin())
foreach (WC()->shipping()->load_shipping_methods() as $method) {
$shipping_methods[$method->id] = $method->get_method_title();
}
require_once 'form-fields.php';
$this->form_fields = WoocommerceRBSPaymentAlfabank\Includes\FormFieldsGenerator::generate($this->id);
}
public function is_available()
{
return parent::is_available();
}
public function process_admin_options()
{
if ($this->allowCallbacks == false) {
$this->writeLog("Nothing to update: " . __LINE__);
return parent::process_admin_options();
}
if (isset($_POST['woocommerce_alfabank_test_mode'])) {
$action_adr = $this->test_url;
$gate_url = str_replace("payment/rest", "mportal/mvc/public/merchant/update", $action_adr);
if (defined('RBSPAYMENT_ALFABANK_TEST_URL_ALTERNATIVE_DOMAIN')) {
$pattern = '/^https:\/\/[^\/]+/';
$gate_url = preg_replace($pattern, rtrim(RBSPAYMENT_ALFABANK_TEST_URL_ALTERNATIVE_DOMAIN, '/'), $gate_url);
}
} else {
$action_adr = $this->prod_url;
$gate_url = str_replace("payment/rest", "mportal/mvc/public/merchant/update", $action_adr);
if (defined('RBSPAYMENT_ALFABANK_PROD_URL_ALTERNATIVE_DOMAIN')) {
$pattern = '/^https:\/\/[^\/]+/';
$gate_url = preg_replace($pattern, rtrim(RBSPAYMENT_ALFABANK_PROD_URL_ALTERNATIVE_DOMAIN, '/'), $gate_url);
}
}
$gate_url .= substr($this->merchant, 0, -4);
$callback_addresses_string = "";
if ($this->callbackType != "DYNAMIC") {
$callback_addresses_string = get_option('siteurl') . '?wc-api=alfabank' . '&action=callback';
}
if ($this->allowCallbacks !== false) {
$response = $this->_updateGatewayCallback($this->merchant, $this->password, $gate_url, $callback_addresses_string);
if (RBSPAYMENT_ALFABANK_ENABLE_LOGGING === true) {
$this->writeLog("REQUEST:\n" . $gate_url . "\n[callback_addresses_string]: " . $callback_addresses_string . "\nRESPONSE:\n" . $response);
}
}
parent::process_admin_options();
}
public function _updateGatewayCallback($login, $password, $action_address, $callback_addresses_string = "")
{
$headers = array(
'Content-Type:application/json',
'Authorization: Basic ' . base64_encode($login . ":" . $password)
);
$data['callbacks_enabled'] = true;
$data['callback_type'] = $this->callbackType;
if (!empty($callback_addresses_string)) {
$data['callback_addresses'] = $callback_addresses_string;
}
$data['callback_http_method'] = "GET";
$data['callback_operations'] = "deposited,approved,declinedByTimeout,reversed,refunded";
$response = $this->_sendGatewayData(json_encode($data), $action_address, $headers);
return $response;
}
public function _sendGatewayData($data, $action_address, $headers = array())
{
$curl_opt = array(
CURLOPT_HTTPHEADER => $headers,
CURLOPT_VERBOSE => true,
CURLOPT_SSL_VERIFYHOST => false,
CURLOPT_URL => $action_address,
CURLOPT_RETURNTRANSFER => true,
CURLOPT_POST => true,
CURLOPT_POSTFIELDS => $data,
CURLOPT_HEADER => true
);
$ssl_verify_peer = false;
$curl_opt[CURLOPT_SSL_VERIFYPEER] = $ssl_verify_peer;
$ch = curl_init();
curl_setopt_array($ch, $curl_opt);
$response = curl_exec($ch);
if ($response === false) {
$this->writeLog("The payment gateway is returning an empty response.");
}
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);
return substr($response, $header_size);
}
function writeLog($var, $info = true)
{
if ($this->test_mode != "yes") {
}
$information = "";
if ($var) {
if ($info) {
$information = "\n\n";
$information .= str_repeat("-=", 64);
$information .= "\nDate: " . date('Y-m-d H:i:s');
$information .= "\nWordpress version " . get_bloginfo('version') . "; Woocommerce version: " . wpbo_get_woo_version_number() . "\n";
}
$result = $var;
if (is_array($var) || is_object($var)) {
$result = "\n" . print_r($var, true);
}
$result .= "\n\n";
$path = dirname(__FILE__) . '/../logs/wc_alfabank_' . date('Y-m') . '.log';
error_log($information . $result, 3, $path);
return true;
}
return false;
}
public function process_payment($order_id)
{
$order = wc_get_order($order_id);
if (!empty($_GET['pay_for_order']) && $_GET['pay_for_order'] == 'true') {
$this->generate_form($order_id);
exit();
}
$pay_now_url = $order->get_checkout_payment_url(true);
return array(
'result' => 'success',
'redirect' => $pay_now_url
);
}
public function generate_form($order_id)
{
$order = wc_get_order($order_id);
$amount = $order->get_total() * 100;
$coupons = array();
global $woocommerce;
if (!empty($woocommerce->cart->applied_coupons)) {
foreach ($woocommerce->cart->applied_coupons as $code) {
$coupons[] = new WC_Coupon($code);
}
}
if ($this->test_mode == 'yes') {
$action_adr = $this->test_url;
} else {
$action_adr = $this->prod_url;
}
if ($this->stage_mode == 'two-stage') {
$action_adr .= 'registerPreAuth.do';
} else if ($this->stage_mode == 'one-stage') {
$action_adr .= 'register.do';
}
$order_data = $order->get_data();
$language = substr(get_bloginfo("language"), 0, 2);
switch ($language) {
case  ('uk'):
$language = 'ua';
break;
case ('be'):
$language = 'by';
break;
}
$jsonParams = array(
'CMS' => 'Wordpress ' . get_bloginfo('version') . " + woocommerce version: " . wpbo_get_woo_version_number(),
'Module-Version' => $this->module_version,
);
#BLOCK_PHONE_TRANSFER_START[builder]
if (!empty($order_data['billing']['phone'])) {
$jsonParams['phone'] = $this->cleanPhoneNumber($order_data['billing']['phone']);
}
#BLOCK_PHONE_TRANSFER_END
if (class_exists('CRB')) {
$crb = new CRB();
$crbParams = $crb->processTaxItems($order);
$jsonParams = array_merge($jsonParams, $crbParams);
}
if (defined('RBSPAYMENT_ALFABANK_ENABLE_BACK_URL_SETTINGS')
&& RBSPAYMENT_ALFABANK_ENABLE_BACK_URL_SETTINGS === true
&& !empty($this->backToShopUrl)
) {
$jsonParams['backToShopUrl'] = $this->backToShopUrl;
}
if (class_exists('WoocommerceRBSPaymentAlfabank\\Includes\\Libs\\DiscountHelper')) {
if (!isset($this->fesHelper)) {
$this->fesHelper = new WoocommerceRBSPaymentAlfabank\Includes\Libs\FesHelper();
}
if (!empty($this->fesHelper) && !empty($this->fes_cashboxId)) {
$jsonParams['fes_cashboxId'] = $this->fes_cashboxId;
}
}
$args = array(
'userName' => $this->merchant,
'password' => $this->password,
'amount' => $amount,
'returnUrl' => get_option('siteurl') . '?wc-api=alfabank' . '&action=result&order_id=' . $order_id,
'jsonParams' => json_encode($jsonParams),
);
#BLOCK_PHONE_TRANSFER_START[builder]
if (!empty($order_data['billing']['phone'])) {
  $first_name = trim($order_data['billing']['first_name']);
  $last_name = trim($order_data['billing']['last_name']);
  $full_name = trim(string: $last_name . ' ' . $first_name);

  // Убираем лишние пробелы
  $full_name = preg_replace('/\s+/', ' ', $full_name);

  $args['orderPayerData'] = array(
      'fullName' => $full_name,
      'email' => $order_data['billing']['email'],
      'phone' => '+' . preg_replace('/[^0-9]/', '', $order_data['billing']['phone']),
  );
}
#BLOCK_PHONE_TRANSFER_END

if (defined('RBSPAYMENT_ALFABANK_MANDATORY_CURRENCY') && RBSPAYMENT_ALFABANK_MANDATORY_CURRENCY === true) {
$currency_code = $order->get_currency();
$numeric_code = $this->get_numeric_currency_code($currency_code);
if (!empty($numeric_code)) {
$args['currency'] = $numeric_code;
}
}
if (defined('RBSPAYMENT_ALFABANK_SEND_CLIENT_FULL_INFO') && RBSPAYMENT_ALFABANK_SEND_CLIENT_FULL_INFO === true) {
$billingPayerData = $this->_getBillingPayerData($order_data);
if (!empty($billingPayerData)) {
$args['billingPayerData'] = json_encode($billingPayerData);
}
}
if (!empty($order_data['customer_id'] && $order_data['customer_id'] > 0)) {
$client_email = !empty($order_data['billing']['email']) ? $order_data['billing']['email'] : "";
$args['clientId'] = md5($order_data['customer_id'] . $client_email . get_option('siteurl'));
}
if ($this->callbackType == "DYNAMIC") {
$args['dynamicCallbackUrl'] = get_option('siteurl') . '?wc-api=alfabank' . '&action=callback&dynamic=1&order_id=' . $order_id;
}
if (defined('RBSPAYMENT_ALFABANK_ENABLE_CART_OPTIONS') && RBSPAYMENT_ALFABANK_ENABLE_CART_OPTIONS == true && $this->send_order == 'yes') {
$args['taxSystem'] = $this->tax_system;
$order_bundle = $this->_createOrderBundle($order);
if (class_exists('WoocommerceRBSPaymentAlfabank\\Includes\\Libs\\DiscountHelper')) {
$discountHelper = new \WoocommerceRBSPaymentAlfabank\Includes\Libs\DiscountHelper();
$discount = $discountHelper->discoverDiscount($args['amount'], $order_bundle['cartItems']['items']);
if ($discount != 0) {
$discountHelper->setOrderDiscount($discount);
$recalculatedPositions = $discountHelper->normalizeItems($order_bundle['cartItems']['items']);
$recalculatedAmount = $discountHelper->getResultAmount();
$order_bundle['cartItems']['items'] = $recalculatedPositions;
}
}
if (!empty($order_bundle)) {
$args['orderBundle'] = json_encode($order_bundle);
}
}
if ($this->orderNumberById) {
$args['orderNumber'] = $order_id . '_' . time();
} else {
$args['orderNumber'] = trim(str_replace('#', '', $order->get_order_number())) . "_" . time(); // PLUG-3966, PLUG-4300
}
$headers = array(
'CMS: Wordpress ' . get_bloginfo('version') . " + woocommerce version: " . wpbo_get_woo_version_number(),
'Module-Version: ' . $this->module_version,
);
$response = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr, $headers);
if (RBSPAYMENT_ALFABANK_ENABLE_LOGGING === true) {
$logData = $args;
$logData['password'] = '**removed from log**';
$this->writeLog("[REQUEST]: " . $action_adr . ": \nDATA: " . print_r($logData, true) . "\n[RESPONSE]: " . $response);
}
$response = json_decode($response, true);
if (empty($response['errorCode'])) {
if (RBSPAYMENT_ALFABANK_SKIP_CONFIRMATION_STEP == true) {
wp_redirect($response['formUrl']); //PLUG-4104 Comment this line for redirect via pressing button (step)
exit();
}
} else {
wc_add_notice(__('There was an error while processing payment', 'wc-' . $this->id . '-text-domain') . "<br/>ERRORCODE# " . $response['errorCode'] . " " . $response['errorMessage'], 'error');
wp_safe_redirect($order->get_checkout_payment_url());
exit();
return;
}
}
protected function _createOrderBundle($order)
{
$order_bundle = array();
$order_data = $order->get_data();
$order_items = $order->get_items();
$order_timestamp_created = $order_data['date_created']->getTimestamp();
$items = array();
$itemsCnt = 1;
foreach ($order_items as $value) {
$item = array();
$product_variation_id = $value['variation_id'];
if ($product_variation_id) {
$product = new WC_Product_Variation($value['variation_id']);
$item_code = $itemsCnt . "-" . $value['variation_id'];
} else {
$product = new WC_Product($value['product_id']);
$item_code = $itemsCnt . "-" . $value['product_id'];
}
$product_sku = get_post_meta($value['product_id'], '_sku', true);
$item_code = !empty($product_sku) ? $product_sku : $item_code;
$tax_type = $this->getTaxType($product);
$product_price = round((($value['total'] + $value['total_tax']) / $value['quantity']) * 100);
if ($product->get_type() == 'variation') {
}
$item['positionId'] = $itemsCnt++;
$item['name'] = $value['name'];
if ($this->versionFfd == 'v1_05') {
$item['quantity'] = array(
'value' => $value['quantity'],
'measure' => defined('RBSPAYMENT_ALFABANK_MEASUREMENT_NAME') ? RBSPAYMENT_ALFABANK_MEASUREMENT_NAME : 'pcs'
);
} else {
$item['quantity'] = array(
'value' => $value['quantity'],
'measure' => defined('RBSPAYMENT_ALFABANK_MEASUREMENT_CODE') ? RBSPAYMENT_ALFABANK_MEASUREMENT_CODE : '0'
);
}
$item['itemAmount'] = $product_price * $value['quantity'];
$item['itemCode'] = $item_code;
$item['tax'] = array('taxType' => $tax_type);
$item['itemPrice'] = $product_price;
if (!empty($this->fesHelper) && !empty($this->fes_cashboxId)) {
$tru_code = get_post_meta($value['product_id'], '_fes_truCode', true);
if (!empty($tru_code)) {
$item['itemDetails']['itemDetailsParams'][] = array("name" => "fes_truCode", "value" => $tru_code);
}
}
$attributes = array();
$attributes[] = array("name" => "paymentMethod", "value" => $this->paymentMethodType);
$attributes[] = array("name" => "paymentObject", "value" => $this->paymentObjectType);
$item['itemAttributes']['attributes'] = $attributes;
$items[] = $item;
}
$shipping_total = $order->get_shipping_total();
$shipping_tax = $order->get_shipping_tax();
if ($shipping_total > 0) {
$WC_Order_Item_Shipping = new WC_Order_Item_Shipping();
$itemShipment['positionId'] = $itemsCnt;
$itemShipment['name'] = __('Delivery', 'wc-' . $this->id . '-text-domain');
if ($this->versionFfd == 'v1_05') {
$itemShipment['quantity'] = array(
'value' => 1,
'measure' => defined('RBSPAYMENT_ALFABANK_MEASUREMENT_NAME') ? RBSPAYMENT_ALFABANK_MEASUREMENT_NAME : 'pcs'
);
} else {
$itemShipment['quantity'] = array(
'value' => 1,
'measure' => defined('RBSPAYMENT_ALFABANK_MEASUREMENT_CODE') ? RBSPAYMENT_ALFABANK_MEASUREMENT_CODE : '0'
);
}
$itemShipment['itemAmount'] = $itemShipment['itemPrice'] = $shipping_total * 100;
$itemShipment['itemCode'] = 'delivery';
$itemShipment['tax'] = array('taxType' => $this->getTaxType($WC_Order_Item_Shipping));
$attributes = array();
$attributes[] = array("name" => "paymentMethod", "value" => $this->paymentObjectType_delivery);
$attributes[] = array("name" => "paymentObject", "value" => 4);
$itemShipment['itemAttributes']['attributes'] = $attributes;
$items[] = $itemShipment;
}
$order_bundle['orderCreationDate'] = $order_timestamp_created;
$order_bundle['cartItems'] = array('items' => $items);
if (!empty($order_data['billing']['email'])) {
$order_bundle['customerDetails']['email'] = $order_data['billing']['email'];
}
#BLOCK_PHONE_TRANSFER_START[builder]
if (!empty($order_data['billing']['phone'])) {
$order_bundle['customerDetails']['phone'] = $this->cleanPhoneNumber($order_data['billing']['phone']);
}
#BLOCK_PHONE_TRANSFER_END
return $order_bundle;
}
function getTaxType($product)
{
$tax = new WC_Tax();
if (get_option("woocommerce_calc_taxes") == "no") { // PLUG-4056
$item_rate = -1;
} else {
$base_tax_rates = $tax->get_base_tax_rates($product->get_tax_class(true));
if (!empty($base_tax_rates)) {
$temp = $tax->get_rates($product->get_tax_class());
$rates = array_shift($temp);
$item_rate = round(array_shift($rates));
} else {
$item_rate = -1;
}
}
if ($item_rate == 20) {
$tax_type = 6;
} else if ($item_rate == 18) {
$tax_type = 3;
} else if ($item_rate == 10) {
$tax_type = 2;
} else if ($item_rate == 0) {
$tax_type = 1;
} else if ($item_rate == 5) {
$tax_type = 10;
} else if ($item_rate == 7) {
$tax_type = 12;
} else {
$tax_type = $this->tax_type;
}
return $tax_type;
}
function correctBundleItem(&$item, $discount)
{
$item['itemAmount'] -= $discount;
$diff_price = fmod($item['itemAmount'], $item['quantity']['value']); //0.5 quantity
if ($diff_price != 0) {
$item['itemAmount'] += $item['quantity']['value'] - $diff_price;
}
$item['itemPrice'] = $item['itemAmount'] / $item['quantity']['value'];
}
function _getBillingPayerData($order_data)
{
$billingPayerData = array();
$pattern = '/^[A-Za-z0-9\s\'"!#$%&@^~*+=\-_.,:;<>|，΄´–\/?\\\\{}()\[\]\n]+$/';
if (!empty($order_data['billing']['city']) && preg_match($pattern, $order_data['billing']['city'])) {
$billingPayerData['billingCity'] = $order_data['billing']['city'];
}
if (!empty($order_data['billing']['country']) && preg_match($pattern, $order_data['billing']['country'])) {
$billingPayerData['billingCountry'] = $order_data['billing']['country'];
}
if (!empty($order_data['billing']['address_1']) && preg_match($pattern, $order_data['billing']['address_1'])) {
$billingPayerData['billingAddressLine1'] = $order_data['billing']['address_1'];
}
if (!empty($order_data['billing']['address_2']) && preg_match($pattern, $order_data['billing']['address_2'])) {
$billingPayerData['billingAddressLine2'] = $order_data['billing']['address_2'];
}
if (!empty($order_data['billing']['address_3']) && preg_match($pattern, $order_data['billing']['address_3'])) {
$billingPayerData['billingAddressLine3'] = $order_data['billing']['address_3'];
}
if (!empty($order_data['billing']['postcode']) && preg_match($pattern, $order_data['billing']['postcode'])) {
$billingPayerData['billingPostalCode'] = $order_data['billing']['postcode'];
}
if (!empty($order_data['billing']['state']) && preg_match($pattern, $order_data['billing']['state'])) {
$billingPayerData['billingState'] = $order_data['billing']['state'];
}
return $billingPayerData;
}
function receipt_page($order)
{
$this->generate_form($order);
exit();
}
public function webhook_result()
{
if (isset($_GET['action'])) {
$action = $_GET['action'];
if ($this->test_mode == 'yes') {
$action_adr = $this->test_url;
} else {
$action_adr = $this->prod_url;
}
$action_adr .= 'getOrderStatusExtended.do';
$args = array(
'userName' => $this->merchant,
'password' => $this->password,
);
switch ($action) {
case "result":
$args['orderId'] = isset($_GET['orderId']) ? $_GET['orderId'] : null;
$order_id = $_GET['order_id'];
$order = wc_get_order($order_id);
$response = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr, array());
if (RBSPAYMENT_ALFABANK_ENABLE_LOGGING === true) {
$logData = $args;
$logData['password'] = '**removed from log**';
$this->writeLog("[REQUEST RU]: " . $action_adr . ": " . print_r($logData, true) . "\n[RESPONSE]: " . print_r($response, true));
}
$response = json_decode($response, true);
$orderStatus = $response['orderStatus'];
if ($orderStatus == '1' || $orderStatus == '2') {
if ($this->allowCallbacks === false) {
$order->update_status($this->order_status_paid, "Alfabank: " . __('Payment successful', 'wc-' . $this->id . '-text-domain'));
try {
wc_reduce_stock_levels($order_id);
} catch (Exception $e) {
}
update_post_meta($order_id, 'orderId', $args['orderId']);
$transaction_id = sanitize_text_field($response['authRefNum']);
$order->set_transaction_id($transaction_id);
$order->payment_complete();
}
if (!empty($this->success_url)) {
WC()->cart->empty_cart();
wp_redirect($this->success_url . "?order_id=" . $order_id);
exit;
}
wp_redirect($this->get_return_url($order));
exit;
} else {
$order->update_status('failed', "Alfabank: " . __('Payment failed', 'wc-' . $this->id . '-text-domain'));
if (!empty($this->fail_url)) {
wp_redirect($this->fail_url . "?order_id=" . $order_id);
exit;
}
wc_add_notice(__('There was an error while processing payment', 'wc-' . $this->id . '-text-domain') . "<br/>" . $response['actionCodeDescription'], 'error');
wp_safe_redirect($order->get_checkout_payment_url());
exit;
}
$order->save();
break;
case "callback":
$args['orderId'] = isset($_GET['mdOrder']) ? $_GET['mdOrder'] : null;
$response = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr);
$response = json_decode($response, true);
if (empty($response['orderNumber'])) {
exit;
} else {
$p = explode("_", $response['orderNumber']);
$order_id = $p[0];
}
$order = wc_get_order($order_id);
$orderStatus = $response['orderStatus'];
$this->writeLog("[Incoming cb (" . $order_id . ")]: OrderStatus= " . $orderStatus);
if ($orderStatus == '1' || $orderStatus == '2') {
update_post_meta($order_id, 'orderId', $args['orderId']);
$transaction_id = sanitize_text_field($response['authRefNum']);
$order->set_transaction_id($transaction_id);
if (strpos($order->get_status(), "pending") !== false || strpos($order->get_status(), "failed") !== false) { //PLUG-4415, 4495
$order->update_status($this->order_status_paid, "Alfabank: " . __('Payment successful', 'wc-' . $this->id . '-text-domain'));
$this->writeLog("[VALUE TO SET ORDER_STATUS]: " . $this->order_status_paid); //PLUG-7155
try {
wc_reduce_stock_levels($order_id);
} catch (Exception $e) {
}
$order->payment_complete();
}
if (isset($_GET['showMercy'])) {
if (!empty($this->success_url)) {
WC()->cart->empty_cart();
wp_redirect($this->success_url . "?order_id=" . $order_id);
exit;
}
}
wp_redirect($this->get_return_url($order));
exit;
}
else if ($orderStatus == '4') {
if ( get_post_meta($order_id, 'orderId', true) != $args['orderId'] ) {
$this->writeLog(get_post_meta($order_id, 'orderId', true) . "!=" . $args['orderId']);
exit();
}
$is_part_refunted = $response['paymentAmountInfo']['approvedAmount'] === $response['amount'] && $response['paymentAmountInfo']['refundedAmount'] != 0;
$is_full_refunded = $response['paymentAmountInfo']['approvedAmount'] === $response['paymentAmountInfo']['refundedAmount'];
if($is_full_refunded) {
$refund_amount = $response['amount'] / 100;
$refund_massage = 'REFUNDED_FULL_MESSAGE ' . $refund_amount;
} else if($is_part_refunted) {
$refund_amount = $response['paymentAmountInfo']['refundedAmount'] / 100;
$refund_massage = 'REFUNDED_MESSAGE ' . $refund_amount;
}
$refund_id = wc_create_refund(array(
'amount'   => $refund_amount,
'reason'   => $refund_massage,
'order_id' => $order_id,
));
if (is_wp_error($refund_id)) {
$this->writeLog("REFUND ERROR: " . $refund_id->get_error_message());
} else {
$order->add_order_note($refund_massage, false);
$order->save();
}
exit();
}
else if ($orderStatus == '3') {
if ( get_post_meta($order_id, 'orderId', true) != $args['orderId'] ) {
$this->writeLog(get_post_meta($order_id, 'orderId', true) . "!=" . $args['orderId']);
exit();
}
$is_part_cancel = $response['paymentAmountInfo']['approvedAmount'] > 0 && $response['paymentAmountInfo']['approvedAmount'] < $response['amount'];
$is_full_cancel = $response['paymentAmountInfo']['approvedAmount'] === 0;
if($is_full_cancel) {
$cancel_amount = '';
$cancel_massage = 'CANCEL_FULL_MESSAGE ' . $cancel_amount;
} else if($is_part_cancel) {
$cancel_amount = $response['amount'] - $response['paymentAmountInfo']['approvedAmount'];
$cancel_massage = 'CANCEL_MESSAGE ' . ($cancel_amount / 100);
}
$refund_id = wc_create_refund(array(
'amount'   => $cancel_amount,
'reason'   => $cancel_massage,
'order_id' => $order_id,
));
if (is_wp_error($refund_id)) {
$this->writeLog("REVERSE ERROR: " . $refund_id->get_error_message());
} else {
$order->add_order_note($cancel_massage, false);
$order->save();
}
exit();
}
elseif (empty(get_post_meta($order_id, 'orderId', true))
&& $this->id == $order->get_payment_method()
) {
$this->writeLog(">>" . $order->get_meta('orderId') . "<<");
$order->update_status('failed', "Alfabank: " . __('Payment failed', 'wc-' . $this->id . '-text-domain'));
if (isset($_GET['showMercy'])) {
if (!empty($this->fail_url)) {
wp_redirect($this->fail_url . "?order_id=" . $order_id);
exit;
}
wc_add_notice(__('There was an error while processing payment', 'wc-' . $this->id . '-text-domain') . "<br/>" . $response['actionCodeDescription'], 'error');
wp_safe_redirect($order->get_checkout_payment_url());
exit;
}
} else {
/* noop */
}
$order->save();
break;
}
exit;
}
}
public function process_refund($order_id, $amount = null, $reason = '')
{
$order = wc_get_order($order_id);
if ($amount == "0.00") {
$amount = 0;
} else {
$amount = $amount * 100;
}
$order_key = $order->get_order_key();
$args = array(
'userName' => $this->merchant,
'password' => $this->password,
'orderId' => get_post_meta($order_id, 'orderId', true),
'amount' => $amount
);
if ($this->test_mode == 'yes') {
$action_adr = $this->test_url;
} else {
$action_adr = $this->prod_url;
}
$gose = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr . 'getOrderStatusExtended.do', array());
$res = json_decode($gose, true);
if ($res["orderStatus"] == "2" || $res["orderStatus"] == "4") { //DEPOSITED||REFUNDED
$result = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr . 'refund.do', array());
if (RBSPAYMENT_ALFABANK_ENABLE_LOGGING === true) {
$logData = $args;
$logData['password'] = '**removed from log**';
$this->writeLog("[DEPOSITED REFUND RESPONSE]: " . print_r($logData, true) . " \n" . $result);
}
} elseif ($res["orderStatus"] == "1") { //APPROVED 2x
if ($amount == 0) {
unset($args['amount']);
}
$result = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr . 'reverse.do', array());
if (RBSPAYMENT_ALFABANK_ENABLE_LOGGING === true) {
$logData = $args;
$logData['password'] = '**removed from log**';
$this->writeLog("[APPROVED REVERSE RESPONSE]: " . print_r($logData, true) . " \n" . $result);
}
} else {
return new WP_Error('wc_' . $this->id . '_refund_failed', sprintf(__('Order ID (%s) failed to be refunded. Please contact administrator for more help.', 'wc-' . $this->id . '-text-domain'), $order_id));
}
$response = json_decode($result, true);
if ($response["errorCode"] != "0") {
if ($response["errorCode"] == "7") {
return new WP_Error('wc_' . $this->id . '_refund_failed', "For partial refunds Order state should be in DEPOSITED in Gateway");
}
return new WP_Error('wc_' . $this->id . '_refund_failed', $response["errorMessage"]);
} else {
$result = $this->_sendGatewayData(http_build_query($args, '', '&'), $action_adr . 'getOrderStatusExtended.do', array());
if (RBSPAYMENT_ALFABANK_ENABLE_LOGGING === true) {
$this->writeLog("[FINALE STATE]: " . $result);
}
$response = json_decode($result, true);
$orderStatus = $response['orderStatus'];
if ($orderStatus == '4' || $orderStatus == '3') {
return true;
} elseif ($orderStatus == '1') {
return true;
}
}
return false;
}
/**
* Process subscription payment.
*
* @param float $amount
* @param WC_Order $order
* @return void
*/
public function process_subscription_payment($amount, $order)
{
$payment_result = $this->get_option('result');
if ('success' === $payment_result) {
$order->payment_complete();
} else {
$message = __('Order payment failed. To make a successful payment using RBSPaymentAlfabank Payments, please review the gateway settings.', 'woocommerce-gateway-alfabank');
throw new Exception($message);
}
}
function get_numeric_currency_code($currency_code)
{
$currency_codes = array(
'BYN' => '933',
'BHD' => '048',
'BYR' => '974',
'CAD' => '124',
'CNY' => '156',
'EUR' => '978',
'GBP' => '826',
'HKD' => '344',
'HUF' => '348',
'ILS' => '376',
'JPY' => '392',
'KGS' => '417',
'KRW' => '410',
'KZT' => '398',
'MDL' => '498',
'MYR' => '458',
'OMR' => '512',
'PHP' => '608',
'RON' => '946',
'RUB' => '643',
'RUR' => '810',
'SGD' => '702',
'UAH' => '980',
'USD' => '840',
'NGN' => '566',
'MZN' => '943',
'BGN' => '975',
'BZD' => '084',
'GHS' => '936',
'GNF' => '324',
'XOF' => '952',
'PLN' => '985',
'LSL' => '426',
'TZS' => '834',
'NZD' => '554',
'KHR' => '116',
'TRY' => '949',
'AMD' => '051',
'SAR' => '682',
'AED' => '784',
'COP' => '170',
'AUD' => '036',
'IDR' => '360',
'KWD' => '414',
'JOD' => '400',
'INR' => '356'
);
return isset($currency_codes[$currency_code]) ? $currency_codes[$currency_code] : null;
}
private function cleanPhoneNumber($telephone): string
{
return substr(preg_replace('/\D+/', '', $telephone), 0, 15);
}
function create_woocommerce_order($user_id, $payment_method_id, $product_array, $mark_paid = false, $billingPayerData = '') {
if (empty($product_array)) {
return new WP_Error('empty_cart', 'Product list is empty');
}
$user = get_user_by('ID', $user_id);
if (!$user) {
return new WP_Error('invalid_user', 'User not found');
}
$order = wc_create_order([
'customer_id' => $user_id,
]);
foreach ($product_array as $item) {
if (!is_array($item) || count($item) != 2) {
return new WP_Error('invalid_product_array', 'Invalid product_array format');
}
list($product_id, $quantity) = $item;
$product = wc_get_product($product_id);
if (!$product) {
return new WP_Error('invalid_product', "Product with ID $product_id not found");
}
$order->add_product($product, $quantity);
}
$payment_gateways = WC()->payment_gateways->payment_gateways();
if (isset($payment_gateways[$payment_method_id])) {
$gateway = $payment_gateways[$payment_method_id];
$order->set_payment_method($gateway);
} else {
return new WP_Error('invalid_gateway', 'Payment method not found');
}
$billing = [
'first_name' => get_user_meta($user_id, 'billing_first_name', true) ?: '',
'last_name'  => get_user_meta($user_id, 'billing_last_name', true) ?: '',
'email'      => $user->user_email,
'phone'      => get_user_meta($user_id, 'billing_phone', true) ?: '0000000000',
'address_1'  => get_user_meta($user_id, 'billing_address_1', true) ?: '-',
'address_2'  => get_user_meta($user_id, 'billing_address_2', true) ?: '',
'city'       => get_user_meta($user_id, 'billing_city', true) ?: '-',
'state'      => get_user_meta($user_id, 'billing_state', true) ?: '',
'postcode'   => get_user_meta($user_id, 'billing_postcode', true) ?: '000000',
'country'    => get_user_meta($user_id, 'billing_country', true) ?: '',
];
$order->set_address($billing, 'billing');
$order->calculate_totals();
if ($mark_paid) {
$order->payment_complete();
}
$order->save();
return $order->get_id();
}
}
if (!function_exists('wpbo_get_woo_version_number')) {
function wpbo_get_woo_version_number()
{
if (!function_exists('get_plugins'))
require_once(ABSPATH . 'wp-admin/includes/plugin.php');
$plugin_folder = get_plugins('/' . 'woocommerce');
$plugin_file = 'woocommerce.php';
if (isset($plugin_folder[$plugin_file]['Version'])) {
return $plugin_folder[$plugin_file]['Version'];
} else {
return "Unknown";
}
}
}