<?php

/**
 * @package       VM Payment - Hydrogen
 * @author        Hydrogen
 * @copyright     Copyright (C) 2024 Hydrogen Ltd. All rights reserved.
 * @version       1.0.0, January 2024
 * @license       GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Direct access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DIRECTORY_SEPARATOR . 'vmpsplugin.php');

class plgVmPaymentHydrogen extends vmPSPlugin
{
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->_loggable  = true;
        $this->_tablepkey = 'id';
        $this->_tableId   = 'id';

        $this->tableFields = array_keys($this->getTableSQLFields());

        $varsToPush = array(
            'test_mode' => array(
                1,
                'int'
            ), // hydrogen.xml (test_mode)
            'live_secret_key' => array(
                '',
                'char'
            ), // hydrogen.xml (live_secret_key)
            'live_public_key' => array(
                '',
                'char'
            ), // hydrogen.xml (live_public_key)
            'test_secret_key' => array(
                '',
                'char'
            ), // hydrogen.xml (test_secret_key)
            'test_public_key' => array(
                '',
                'char'
            ), // hydrogen.xml (test_public_key)
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

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Hydrogen Table');
    }

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

    function getHydrogenSettings($payment_method_id)
    {
        $hydrogen_settings = $this->getPluginMethod($payment_method_id);

        if ($hydrogen_settings->test_mode) {
            $secret_key = $hydrogen_settings->test_secret_key;
            $public_key = $hydrogen_settings->test_public_key;
            $script_src = 'https://hydrogenshared.blob.core.windows.net/paymentgateway/paymentGatewayInegration.js';
        } else {
            $secret_key = $hydrogen_settings->live_secret_key;
            $public_key = $hydrogen_settings->live_public_key;
            $script_src = 'https://hydrogenshared.blob.core.windows.net/paymentgateway/HydrogenPGIntegration.js';
        }

        $secret_key = str_replace(' ', '', $secret_key);
        $public_key = str_replace(' ', '', $public_key);

        return array(
            'secret_key' => $secret_key,
            'public_key' => $public_key,
            'script_src' => $script_src
        );
    }

    function verifyHydrogenTransaction($token, $payment_method_id)
    {
        $transactionStatus        = new stdClass();
        $transactionStatus->error = "";

        // Get Secret Key from settings
        $hydrogen_settings = $this->getHydrogenSettings($payment_method_id);

        // try a file_get verification
        $opts = array(
            'http' => array(
                'method' => "GET",
                'header' => "Authorization: Bearer " . $hydrogen_settings['secret_key']
            )
        );

        $context  = stream_context_create($opts);
        $url      = "https://api.hydrogen.co/transaction/verify/" . $token;
        $response = file_get_contents($url, false, $context);

        // if file_get didn't work, try curl
        if (!$response) {
            curl_setopt($ch, CURLOPT_URL, "https://api.hydrogen.co/transaction/verify/" . $token);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $hydrogen_settings['secret_key']
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, false);

            // Make sure CURL_SSLVERSION_TLSv1_2 is defined as 6
            // cURL must be able to use TLSv1.2 to connect
            // to Hydrogen servers
            if (!defined('CURL_SSLVERSION_TLSv1_2')) {
                define('CURL_SSLVERSION_TLSv1_2', 6);
            }
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
            // exec the cURL
            $response = curl_exec($ch);
            // should be 0
            if (curl_errno($ch)) {
                // curl ended with an error
                $transactionStatus->error = "cURL said:" . curl_error($ch);
            }
            //close connection
            curl_close($ch);
        }

        if ($response) {
            $body = json_decode($response);
            if (!$body->status) {
                // hydrogen has an error message for us
                $transactionStatus->error = "Hydrogen API said: " . $body->message;
            } else {
                // get body returned by Hydrogen API
                $transactionStatus = $body->data;
            }
        } else {
            // no response
            $transactionStatus->error = $transactionStatus->error . " : No response";
        }


        return $transactionStatus;
    }

    function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'orders.php');

        if (!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'currency.php');

        // Get current order info
        $order_info   = $order['details']['BT'];
        $country_code = ShopFunctions::getCountryByID($order_info->virtuemart_country_id, 'country_3_code');


        // Get payment currency
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

        // Prepare data that should be stored in the database
        $dbValues['order_number']                   = $order['details']['BT']->order_number;
        $dbValues['payment_name']                   = $this->renderPluginName($method, $order);
        $dbValues['virtuemart_paymentmethod_id']    = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction']           = $method->cost_per_transaction;
        $dbValues['cost_percent_total']             = $method->cost_percent_total;
        $dbValues['payment_currency']               = $method->payment_currency;
        $dbValues['payment_order_total']            = $totalInPaymentCurrency;
        $dbValues['tax_id']                         = $method->tax_id;
        $dbValues['hydrogen_transaction_reference'] = $dbValues['order_number'] . '-' . date('YmdHis');

        $this->storePSPluginInternalData($dbValues);

        // Return URL - Verify Hydrogen payment
        $return_url = JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . vRequest::getInt('Itemid') . '&lang=' . vRequest::getCmd('lang', '');

        // Hydrogenk Settings
        $payment_method_id = $dbValues['virtuemart_paymentmethod_id']; //vRequest::getInt('virtuemart_paymentmethod_id');
        $hydrogen_settings = $this->getHydrogenSettings($payment_method_id);
        // Hydrogen Gateway HTML code
        $html = '
        <p>Your order is being processed. Please wait...</p>
        <form id="hydrogen-pay-form" action="' . $return_url . '" method="post">
        <script src="https://hydrogenshared.blob.core.windows.net/paymentgateway/paymentGatewayInegration.js"></script>
          <button id="hydrogen-pay-btn" style="display:none" type="button" onclick="openDialogModal()"> Click here </button>
          <input type="hidden" value="' . $payment_method_id . '" name="payment_method_id" />
          <input type="hidden" value="' . $dbValues['hydrogen_transaction_reference'] . '" name="token" />
        </form>

        <script>
        
        function adjustModalHeight() {
            const modalContent = document.getElementById(\'modal\');
            // Remove the \'height\' style property from the div by setting it to auto
            if (modalContent) {
                modalContent.style.height = \'auto\';
                modalContent.style.width  = "26%";
                
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

            if (decimalPlaces === 0) {
                formattedAmount += \'00\';

            } else if (decimalPlaces === 1) {
                formattedAmount += strAmount[1] + \'0\';

            } else if (decimalPlaces === 2) {
                formattedAmount += strAmount[1];
            }

            return formattedAmount;
        }
        var amount = formatAmount("' . $totalInPaymentCurrency['value'] . '");

        let obj = {

            amount: amount/100,
            email: \'' . $order_info->email . '\',
            currency: \'' . $currency_code . '\',
            description: "test desc",
            meta: "test meta",
            callback: "https://hydrogenpay.com/", // Your callback redirect
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
        
        }

        openDialogModal();
          setTimeout(function(){document.getElementById(\'hydrogen-pay-btn\').style.display=\'block\';},10000);

        </script>';

        $cart->_confirmDone   = FALSE;
        $cart->_dataValidated = FALSE;
        $cart->setCartIntoSession();

        vRequest::setVar('html', $html);
    }

    function plgVmOnPaymentResponseReceived(&$html)
    {
        if (!class_exists('VirtueMartCart')) {
            require(VMPATH_SITE . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'cart.php');
        }
        if (!class_exists('shopFunctionsF')) {
            require(VMPATH_SITE . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'shopfunctionsf.php');
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'orders.php');
        }

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

        $transData = $this->verifyHydrogenTransaction($post_data['token'], $post_data['payment_method_id']);
        if (!property_exists($transData, 'error') && property_exists($transData, 'status') && ($transData->status === 'success') && (strpos($transData->reference, $order_number . "-") === 0)) {
            // Update order status - From pending to complete
            $order['order_status']      = 'C';
            $order['customer_notified'] = 1;
            $orderModel->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);

            $html .= $this->getHtmlRow('Total Amount', number_format($transData->amount / 100, 2));
            $html .= $this->getHtmlRow('Status', $transData->status);
            $html .= '</table>' . "\n";
            // add order url
            $url = JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $order_number, FALSE);
            $html .= '<a href="' . JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $order_number, FALSE) . '" class="vm-button-correct">' . vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER') . '</a>';

            // Empty cart
            $cart = VirtueMartCart::getCart();
            $cart->emptyCart();

            return True;
        } else if (property_exists($transData, 'error')) {
            die($transData->error);
        } else {
            $html .= $this->getHtmlRow('Total Amount', number_format($transData->amount / 100, 2));
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

    function plgVmOnUserPaymentCancel()
    {
        return true;
    }

    /**
     * Required functions by Joomla or VirtueMart. Removed code comments due to 'file length'.
     * All copyrights are (c) respective year of author or copyright holder, and/or the author.
     */
    function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }
        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    protected function checkConditions($cart, $method, $cart_prices)
    {
        $this->convert_condition_amount($method);
        // $address     = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        //Added fot shopping mangement
        $address = $cart->ST;
        if (empty($address)) {
            $address = $cart->BT;
        }
        $amount      = $this->getCartAmount($cart_prices);
        $amount_cond = ($amount >= $method->min_amount and $amount <= $method->max_amount or ($method->min_amount <= $amount and ($method->max_amount == 0)));
        $countries   = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }
        if (!is_array($address)) {
            $address                          = array();
            $address['virtuemart_country_id'] = 0;
        }
        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            if ($amount_cond) {
                return TRUE;
            }
        }
        return FALSE;
    }

    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart)
    {
        return $this->OnSelectCheck($cart);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }
}
