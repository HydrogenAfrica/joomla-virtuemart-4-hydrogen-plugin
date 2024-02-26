<?php

/**
 * @package       VM Payment - Hydrogen
 * @author        Hydrogen
 * @copyright     Copyright (C) 2024 Hydrogen Ltd. All rights reserved.
 * @version       1.0.0, January 2024
 * @license       GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Direct access to ' . basename(__FILE__) . ' is not allowed.');

// Check if the parent class vmPSPlugin exists and include it if not
if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DIRECTORY_SEPARATOR . 'vmpsplugin.php');

// Hydrogen payment plugin class
class plgVmPaymentHydrogen extends vmPSPlugin
{

    // Constructor and configuration parameters set
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        // Set Hydrogen plugin properties
        $this->_loggable  = true;
        $this->_tablepkey = 'id';
        $this->_tableId   = 'id';

        $this->tableFields = array_keys($this->getTableSQLFields());

        // Table fields for hydrogen plugin
        $varsToPush = array(
            // Configuration parameters for test mode and live Auth token keys
            'test_mode' => array(
                1,
                'int'
            ), // hydrogen.xml (test_mode)
            'payment_redirect_mode' => array(
                1,
                'int'
            ), // hydrogen.xml (payment_redirect_mode)
            'live_public_key' => array(
                '',
                'char'
            ), // hydrogen.xml (live_public_key)
            'test_public_key' => array(
                '',
                'char'
            ), // hydrogen.xml (test_public_key)

            // Configuration parameters for order statuses and transaction costs
            'status_pending' => array(
                '',
                'char'
            ),
            'status_success' => array(
                '',
                'char'
            ),
            'status_canceled' => array(
                '',
                'char'
            ),

            'min_amount' => array(
                0,
                'int'
            ),
            'max_amount' => array(
                0,
                'int'
            ),
            'cost_per_transaction' => array(
                0,
                'int'
            ),
            'cost_percent_total' => array(
                0,
                'int'
            ),
            'tax_id' => array(
                0,
                'int'
            )
        );

        // Configuration parameters set
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    // Hydrogen database table structure
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Hydrogen Table');
    }

    // Hydrogen database table fields set
    function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'int(11) unsigned NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
            'order_number' => 'char(32) DEFAULT NULL',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
            'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
            'payment_currency' => 'char(3) ',
            'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
            'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
            'tax_id' => 'smallint(11) DEFAULT NULL',
            'hydrogen_transaction_reference' => 'char(32) DEFAULT NULL'
        );

        return $SQLfields;
    }

    // Hydrogen payment settings based on payment method ID (Auth token keys)
    function getHydrogenSettings($payment_method_id)
    {
        $hydrogen_settings = $this->getPluginMethod($payment_method_id);

        if ($hydrogen_settings->test_mode) {
            // $secret_key = $hydrogen_settings->test_secret_key;
            $public_key = $hydrogen_settings->test_public_key;
            $script_src = 'https://hydrogenshared.blob.core.windows.net/paymentgateway/paymentGatewayInegration.js';
            $confirm_payment_url = 'https://qa-api.hydrogenpay.com/bepayment/api/v1/Merchant/confirm-payment';
            $payment_redirect_mode = $hydrogen_settings->payment_redirect_mode;
        } else {
            // $secret_key = $hydrogen_settings->live_secret_key;
            $public_key = $hydrogen_settings->live_public_key;
            $script_src = 'https://hydrogenshared.blob.core.windows.net/paymentgateway/HydrogenPGIntegration.js';
            $confirm_payment_url = 'https://api.hydrogenpay.com/bepay/api/v1/Merchant/confirm-payment';
            $payment_redirect_mode = $hydrogen_settings->payment_redirect_mode;
        }

        // Remove any whitespace from API keys and script
        $public_key = str_replace(' ', '', $public_key);
        $script_src = str_replace(' ', '', $script_src);

        return array(
            'public_key' => $public_key,
            'script_src' => $script_src,
            'confirm_payment_url' => $confirm_payment_url,
            'payment_redirect_mode' => $payment_redirect_mode
        );
    }

    // For popup verification payment
    function verifyHydrogenTransactionPopup($token, $payment_method_id)
    {
        // Initialize transaction status object
        $transactionStatus = new stdClass();
        $transactionStatus->error = "";

        // Get Hydrogen Auth Token Key from settings/payment method
        $hydrogen_settings = $this->getHydrogenSettings($payment_method_id);
        $url = $hydrogen_settings['confirm_payment_url'];
        $auth_key = $hydrogen_settings['public_key'];

        // Check if $token is null or not
        if (!isset($token)) {

            // Initialize transaction status object
            $transactionStatus = new stdClass();
            $transactionStatus->error = "";

            // Get Hydrogen Auth Token Key from settings/payment method
            $hydrogen_settings = $this->getHydrogenSettings($payment_method_id);
            $url = $hydrogen_settings['confirm_payment_url'];
            $auth_key = $hydrogen_settings['public_key'];

            // Parse the URL to extract the TransactionRef value
            $urlParams = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
            parse_str($urlParams, $params);
            $token = null;

            // Check if TransactionRef exists in the URL query string
            if (isset($params['TransactionRef'])) {
                $token = $params['TransactionRef'];
            } else {
                // If TransactionRef is not directly after the '?' in the query string, check for it preceded by '&'
                $queryWithoutQuestionMark = str_replace('?', '&', $urlParams);
                parse_str($queryWithoutQuestionMark, $params);
                if (isset($params['TransactionRef'])) {
                    $token = $params['TransactionRef'];
                }
            }

            // Check if $token is null or not
            if ($token === null) {

                // $response = $this->verifyHydrogenTransactionPopup($token, $payment_method_id);
                $transactionStatus->error = "Token is null";
                return $transactionStatus;
            }

            // Initialize cURL handle
            $ch = curl_init();

            // Set cURL options
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: ' . $auth_key,
                'Content-Type: application/json',
                'Cache-Control: no-cache'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('transactionRef' => $token)));

            // Execute cURL request
            $response = curl_exec($ch);

            // Get HTTP response code
            $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // Check for cURL errors
            if (curl_errno($ch)) {
                // cURL ended with an error
                $transactionStatus->error = "cURL error: " . curl_error($ch);
            }

            // Close cURL connection
            curl_close($ch);

            // Parse response and handle errors
            if ($response_code == 200) {
                // Request was successful
                $response_data = json_decode($response);

                if (isset($response_data->data->status) && $response_data->data->status == "Paid") {
                    // Transaction is paid
                    $transactionStatus = $response_data->data;
                } else {
                    // Transaction is failed
                    $transactionStatus = $response_data->data; // pending or failed
                }
            } else {
                // Request failed
                $transactionStatus->error = "Request failed with HTTP code: " . $response_code . '' . $response;
            }

            return $transactionStatus;
        }

        // Initialize cURL handle
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: ' . $auth_key,
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('transactionRef' => $token)));
        // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('transactionRef' => '31674786_520091e5c1')));

        // Execute cURL request
        $response = curl_exec($ch);

        // Get HTTP response code
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Check for cURL errors
        if (curl_errno($ch)) {
            // cURL ended with an error
            $transactionStatus->error = "cURL error: " . curl_error($ch);
        }

        // Close cURL connection
        curl_close($ch);

        // Parse response and handle errors
        if ($response_code == 200) {
            // Request was successful
            $response_data = json_decode($response);

            if (isset($response_data->data->status) && $response_data->data->status == "Paid") {
                // Transaction is paid
                $transactionStatus = $response_data->data;
            } else {

                // Transaction is failed
                $transactionStatus = $response_data->data; //pending or failed
            }
        } else {
            // Request failed
            $transactionStatus->error = "Request failed with HTTP code: " . $response_code . '' . $response;
        }

        return $transactionStatus;
    }

    // For redirect verification payment
    function verifyHydrogenTransaction($payment_method_id)
    {
        // Initialize transaction status object
        $transactionStatus = new stdClass();
        $transactionStatus->error = "";

        // Get Hydrogen Auth Token Key from settings/payment method
        $hydrogen_settings = $this->getHydrogenSettings($payment_method_id);
        $url = $hydrogen_settings['confirm_payment_url'];
        $auth_key = $hydrogen_settings['public_key'];

        // Parse the URL to extract the TransactionRef value
        $urlParams = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        parse_str($urlParams, $params);
        $token = null;

        // Check if TransactionRef exists in the URL query string
        if (isset($params['TransactionRef'])) {
            $token = $params['TransactionRef'];
        } else {
            // If TransactionRef is not directly after the '?' in the query string, check for it preceded by '&'
            $queryWithoutQuestionMark = str_replace('?', '&', $urlParams);
            parse_str($queryWithoutQuestionMark, $params);
            if (isset($params['TransactionRef'])) {
                $token = $params['TransactionRef'];
            }
        }

        // Check if $token is null or not
        if ($token === null) {

            // $response = $this->verifyHydrogenTransactionPopup($token, $payment_method_id);
            $transactionStatus->error = "Token is null";
            return $transactionStatus;
        }

        // Initialize cURL handle
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: ' . $auth_key,
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('transactionRef' => $token)));

        // Execute cURL request
        $response = curl_exec($ch);

        // Get HTTP response code
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Check for cURL errors
        if (curl_errno($ch)) {
            // cURL ended with an error
            $transactionStatus->error = "cURL error: " . curl_error($ch);
        }

        // Close cURL connection
        curl_close($ch);

        // Parse response and handle errors
        if ($response_code == 200) {
            // Request was successful
            $response_data = json_decode($response);

            if (isset($response_data->data->status) && $response_data->data->status == "Paid") {
                // Transaction is paid
                $transactionStatus = $response_data->data;
            } else {
                // Transaction is failed
                $transactionStatus = $response_data->data; // pending or failed
            }
        } else {
            // Request failed
            $transactionStatus->error = "Request failed with HTTP code: " . $response_code . '' . $response;
        }

        return $transactionStatus;
    }


    // Process confirmed orders
    function plgVmConfirmedOrder($cart, $order)
    {
        // Retrieve the payment method for the order (Hydrogen)
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        // Include necessary VirtueMart models if not already included
        if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'orders.php');

        if (!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'currency.php');

        // Get order information
        $order_info   = $order['details']['BT'];
        $country_code = ShopFunctions::getCountryByID($order_info->virtuemart_country_id, 'country_3_code');

        // Get payment currency and total amount
        $this->getPaymentCurrency($method);
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query
            ->select('currency_code_3')
            ->from($db->quoteName('#__virtuemart_currencies'))
            ->where($db->quoteName('virtuemart_currency_id')
                . ' = ' . $db->quote($method->payment_currency));
        $db->setQuery($query);
        $currency_code = $db->loadResult();

        // Get total amount for the current payment currency
        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $method->payment_currency);

        // Prepare data for database storage
        $dbValues['order_number']                   = $order['details']['BT']->order_number;
        $dbValues['payment_name']                   = $this->renderPluginName($method, $order);
        $dbValues['virtuemart_paymentmethod_id']    = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction']           = $method->cost_per_transaction;
        $dbValues['cost_percent_total']             = $method->cost_percent_total;
        $dbValues['payment_currency']               = $method->payment_currency;
        $dbValues['payment_order_total']            = $totalInPaymentCurrency;
        $dbValues['tax_id']                         = $method->tax_id;
        // $dbValues['hydrogen_payment_reference'] = $dbValues['order_number'] . '-' . date('YmdHis');

        // Store data in the database
        $this->storePSPluginInternalData($dbValues);

        // Return URL for Hydrogen payment verification
        $return_url = JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . vRequest::getInt('Itemid') . '&lang=' . vRequest::getCmd('lang', '');

        // Hydrogen settings and construct HTML code for payment
        $payment_method_id = $dbValues['virtuemart_paymentmethod_id'];
        $hydrogen_settings = $this->getHydrogenSettings($payment_method_id);

        $payment_redirect_mode = $hydrogen_settings['payment_redirect_mode'];

        // Check if redirect parameter is set
        $redirect = vRequest::getInt('redirect', 1);

        // Get the current URL
        // $currentUrl = html_entity_decode(JUri::getInstance()->toString());

        // // Get the server
        // $server = JUri::root();

        // // Output the URL and server as JSON without escaping slashes
        // echo json_encode(['url' => $currentUrl, 'server' => $server], JSON_UNESCAPED_SLASHES);

        if ($payment_redirect_mode == $redirect) {
            // Additional logic for redirection

            // Hydrogen settings and construct HTML code for payment
            $payment_method_id = $dbValues['virtuemart_paymentmethod_id'];
            $hydrogen_settings = $this->getHydrogenSettings($payment_method_id);

            // Function to format the amount
            function formatAmount($amount)
            {
                $strAmount = explode(".", $amount);
                $formattedAmount = $strAmount[0];
                return $formattedAmount;
            }

            $amount = formatAmount($totalInPaymentCurrency['value']);
            $hydrogen_params = array(
                'amount' => $amount,
                'email' => $order_info->email,
                'currency' => $currency_code,
                'description' => "Payment for {$dbValues['order_number']} Order was successful",
                'meta' => "{$order_info->first_name} {$order_info->last_name}",
                'callback' => JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . vRequest::getInt('Itemid') . '&lang=' . vRequest::getCmd('lang', ''),
                'isAPI' => false,
            );

            $secret_key = $hydrogen_settings['public_key'];
            $hydrogen_url = 'https://qa-dev.hydrogenpay.com/qa/bepay/api/v1/merchant/initiate-payment';

            // Initialize cURL
            $curl = curl_init();

            // Set cURL options
            curl_setopt($curl, CURLOPT_URL, $hydrogen_url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($hydrogen_params));
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Authorization: ' . $secret_key,
                'Content-Type: application/json',
                'Cache-Control: no-cache'
            ));

            // Execute cURL request
            $response = curl_exec($curl);
            $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            // Close cURL session
            curl_close($curl);

            if (!$response) {
                // Handle HTTP request failure
                echo "Error: HTTP request failed.";
                return null;
            }

            if ($response_code === 200) {
                // Parse JSON response
                $response_body = json_decode($response);

                // Extract redirect URL from response
                if (isset($response_body->data->url)) {
                    $redirect_url = $response_body->data->url;

                    // Redirect to the obtained URL
                    JFactory::getApplication()->redirect($redirect_url);
                } else {
                    // Redirect URL not found in response
                    echo "Error: Redirect URL not found in response.";
                    return null;
                }
            } else {
                // Handle non-200 HTTP response codes
                echo "Error: Unexpected HTTP response code - {$response_code}";
                return null;
            }
        }

        // Hydrogen Gateway HTML code
        $html = '
        <p>Your order is being processed. Please wait...</p>
        <form id="hydrogen-pay-form" action="' . $return_url . '" method="post">
        <script src="' . $hydrogen_settings['script_src'] . '"></script>
          <button id="hydrogen-pay-btn" style="display:none" type="button" onclick="openDialogModal()"> Click here </button>
          <input type="hidden" value="' . $payment_method_id . '" name="payment_method_id" />
          <input type="hidden" id="hydrogen-transaction-reference" name="token" />
        </form>

        <script>  
        function adjustModalHeight() {
            const modalContent = document.getElementById(\'modal\');
            // Remove the \'height\' style property from the div by setting it to auto
            if (modalContent) {

                modalContent.style.height = "95%";
                // modalContent.style.height = \'auto\';
                // modalContent.style.width  = "31rem";
                // modalContent.style.zIndex = "9";
                // modalContent.style.marginTop = "40px";
                // modalContent.style.marginBottom = "40px"
                
            }

            const modal = document.getElementById(\'myModal\');
            if (modal) {
                modal.style.paddingTop = "1%";
                modal.style.paddingBottom = "0%";
                modal.style.zIndex = "9999"; 
            }

            const iframe = document.querySelector(\'.pgIframe\');
            if (iframe) {
                iframe.style.width = "27rem";
            }

        }

        function adjustModalHeightForMobile() {
            const modalContent = document.getElementById(\'modal\');
            // Add specific styling for mobile view if needed
            if (modalContent) {
                modalContent.style.height = "80%";
                modalContent.style.zIndex = "9";
                modalContent.style.marginTop = "40px";
                modalContent.style.marginBottom = "40px"
            }
        }

        function formatAmount(amount) {
            var strAmount = amount.toString().split(".");
            var decimalPlaces = strAmount[1] === undefined ? 0: strAmount[1].length;
            var formattedAmount = strAmount[0];

            // if (decimalPlaces === 0) {
            //     formattedAmount += \'00\';

            // } else if (decimalPlaces === 1) {
            //     formattedAmount += strAmount[1] + \'0\';

            // } else if (decimalPlaces === 2) {
            //     formattedAmount += strAmount[1];
            // }

            return formattedAmount;
        }
        var amount = formatAmount("' . $totalInPaymentCurrency['value'] . '");

        let obj = {

            amount: amount,
            email: \'' . $order_info->email . '\',
            currency: \'' . $currency_code . '\',
            description: "Payment for ' . $dbValues['order_number'] .  ' Order was successful",
            meta: \'' . $order_info->first_name . ' ' . $order_info->last_name . '\',
            callback: "' . JURI::root() . '", 
            isAPI: false,
        };
        
        // Replace with your actual token
        // let token = "5030219925952571987360C340A592712A3F582D8625"; // For qa or prod

        let token = \'' . $hydrogen_settings['public_key'] . '\'; // For qa or prod
                
        // Define the openDialogModal function
        async function openDialogModal() {
        
            // Call the function from the external module
            let res = handlePgData(obj, token);
            console.log("return transaction ref", await res);

                    if (window.innerWidth > 768) {
                        adjustModalHeight();

                    } else {

                        adjustModalHeightForMobile()

                    }

            const iframe = document.querySelector(\'.pgIframe\');

            var closeButton = document.querySelector(\'.modal .close\');

            // Access the content window of the iframe
            const iframeContentWindow = iframe.contentWindow;

            // Define a callback function to handle the transaction reference
            const handleTransactionRef = function() {
                const transactionRef = getParameterByName(\'TransactionRef\', iframeContentWindow.location.href);
                console.log(\'TransactionRef from iframe:\', transactionRef);
        
                // Check if the TransactionRef is available
                    if (transactionRef !== null) {
                        // Set the value of the hidden input field in the form
                        document.getElementById(\'hydrogen-transaction-reference\').value = transactionRef;
    
                        // Hide the iframe
                        // iframe.style.display = \'none\';

                        closeButton.click();

                        // Trigger form submission
                        submitForm();

                    }
            };

            iframe.onload = handleTransactionRef;
        }

        openDialogModal();

        setTimeout(function(){document.getElementById(\'hydrogen-pay-btn\').style.display=\'block\';},10000);

        // Function to extract parameters from URL
        function getParameterByName(name, url) {
            if (!url) url = window.location.href;
            name = name.replace(/[\[\]]/g, \'\\$&\');
            var regex = new RegExp(\'[?&]\' + name + \'(=([^&#]*)|&|#|$)\'),
            results = regex.exec(url);
            if (!results) return null;
            if (!results[2]) return \'\';
            return decodeURIComponent(results[2].replace(/\\+/g, \' \'));
        }

        // Function to trigger form submission
        function submitForm() {
            document.getElementById(\'hydrogen-pay-form\').submit();
        }

        </script>';

        // Set cart status and HTML response
        $cart->_confirmDone   = FALSE;
        $cart->_dataValidated = FALSE;
        $cart->setCartIntoSession();

        vRequest::setVar('html', $html); // Display at FE
    }

    // Handle payment response received from API (Form submitted)
    function plgVmOnPaymentResponseReceived(&$html)
    {
        // Hydrogen settings and construct HTML code for payment
        $payment_method_id = $dbValues['virtuemart_paymentmethod_id'];
        $hydrogen_settings = $this->getHydrogenSettings($payment_method_id);

        $payment_redirect_mode = $hydrogen_settings['payment_redirect_mode'];

        // Check if redirect parameter is set
        $redirect = vRequest::getInt('redirect', 1);

        // Include required VirtueMart files and load language files
        if (!class_exists('VirtueMartCart')) {
            require(VMPATH_SITE . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'cart.php');
        }

        if (!class_exists('shopFunctionsF')) {
            require(VMPATH_SITE . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'shopfunctionsf.php');
        }

        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'orders.php');
        }

        $hydrogen_settings = $this->getHydrogenSettings($payment_method_id);

        $payment_redirect_mode = $hydrogen_settings['payment_redirect_mode'];

        // Check if redirect parameter is set
        $redirect = vRequest::getInt('redirect', 1);

        VmConfig::loadJLang('com_virtuemart_orders', TRUE);
        $post_data = vRequest::getPost();

        // The payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);
        $order_number                = vRequest::getString('on', 0);
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return NULL;
        }

        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            return NULL;
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return '';
        }

        VmConfig::loadJLang('com_virtuemart');
        $orderModel = VmModel::getModel('orders');
        $order      = $orderModel->getOrder($virtuemart_order_id);

        $payment_name = $this->renderPluginName($method);
        $html = '<table>' . "\n";
        $html .= $this->getHtmlRow('Payment Name', $payment_name);
        $html .= $this->getHtmlRow('Order Number', $order_number);

        // Verify Hydrogen Payment with popup
        $transData = $this->verifyHydrogenTransactionPopup($post_data['token'], $post_data['payment_method_id']); // Verify Hydrogen Payment

        if (!property_exists($transData, 'error') && property_exists($transData, 'status') && ($transData->status === 'Paid')) {
            // Update order status - From pending to complete  // success
            $order['order_status']      = 'C';
            $order['customer_notified'] = 1;
            $orderModel->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);

            // HTML to display transaction details
            $html .= $this->getHtmlRow('Total Amount', number_format($transData->amount, 2));
            $html .= $this->getHtmlRow('Status', $transData->status);
            $html .= '</table>' . "\n";

            // ink to view order details
            $url = JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $order_number, FALSE);
            $html .= '<a href="' . JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $order_number, FALSE) . '" class="vm-button-correct">' . vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER') . '</a>';

            // Empty cart
            $cart = VirtueMartCart::getCart();
            $cart->emptyCart();

            return True;
        } else if (property_exists($transData, 'error')) {

            // Error message if error property exists
            die($transData->error);
        } else {

            // $html .= $this->getHtmlRow('Total Amount', number_format($transData->amount / 100, 2));
            $html .= $this->getHtmlRow('Total Amount', number_format($transData->amount, 2));
            $html .= $this->getHtmlRow('Status', $transData->status);
            $html .= '</table>' . "\n";
            $html .= '<a href="' . JRoute::_('index.php?option=com_virtuemart&view=cart', false) . '" class="vm-button-correct">' . vmText::_('CART_PAGE') . '</a>';

            // Update order status - From pending to canceled
            $order['order_status']      = 'X';
            $order['customer_notified'] = 1;
            $orderModel->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);
        }

        return False;
    }

    // Handle user payment cancellation
    function plgVmOnUserPaymentCancel()
    {
        return true;
    }

    // Calculate the total cost of the transaction
    function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }
        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    // Check if conditions for using the payment method are met
    protected function checkConditions($cart, $method, $cart_prices)
    {
        $this->convert_condition_amount($method);
        // $address     = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        //Added for shopping mangement
        $address = $cart->ST;
        if (empty($address)) {
            $address = $cart->BT; // If shipping address is empty, use billing address
        }

        // Get the total amount of the cart and check if the amount falls within the specified range
        $amount      = $this->getCartAmount($cart_prices);
        $amount_cond = ($amount >= $method->min_amount and $amount <= $method->max_amount or ($method->min_amount <= $amount and ($method->max_amount == 0)));
        $countries   = array();

        // Get the list of countries where the payment method is available
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // Ensure address is an array and contains a country ID
        if (!is_array($address)) {
            $address                          = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }

        // Check if the address country is in the list of allowed countries or if the list is empty
        if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            if ($amount_cond) {
                return TRUE;
            }
        }
        // Return FALSE if any condition is not met
        return FALSE;
    }

    // Install payment plugin table
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    // Check payment method during selection
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart)
    {
        return $this->OnSelectCheck($cart);
    }

    // Display payment method list in the front-end side
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    // Automatically select payment method based on conditions
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    // Show selected payment method in front-end order details
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    // Show selected payment method in order print view
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    // Show selected payment method in order print view
    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    // Declare plugin parameters for VirtueMart 2/3/4
    function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    // Set plugin parameters for payment method in database table
    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }
}