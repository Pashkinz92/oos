<?php
/**
 * @package VirtueMart
 * @subpackage payment
 * @author CM-S.ru
 * @copyright Copyright (C) 2012-2014 CM-S.ru. All rights reserved.
 * @license GNU General Public License version 2 or later
 */

defined('_JEXEC') or die('Restricted access');

include_once 'log_func.php';

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentOos extends vmPSPlugin
{
    function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);

        $jlang = JFactory::getLanguage();
        $jlang->load('plg_vmpayment_oos', JPATH_ADMINISTRATOR, NULL, TRUE);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = array(
            'url_pay_page' => array('', 'char'),
            'merchant_id' => array('', 'char'),
            'merchant_key' => array('', 'char'),
            'url_success' => array('', 'char'),
            'url_fail' => array('', 'char'),
            'price_final' => array('', 'int'),
            'lang' => array('', 'char'),
            'payment_logos' => array('', 'char'),
            'status_pending' => array('', 'char'),
            'status_success' => array('', 'char'),
            'status_canceled' => array('', 'char'),
            'countries' => array('', 'char'),
            'min_amount' => array('', 'float'),
            'max_amount' => array('', 'float'),
            'cost_per_transaction' => array('', 'int'),
            'cost_percent_total' => array('', 'int'),
            'tax_id' => array(0, 'int')
        );

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Oos Table');
    }

    function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL',
            'payment_currency' => 'smallint(1)',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
            'tax_id' => 'smallint(1)',
            'oos_custom' => 'varchar(255)',
            'oos_response_oos_id' => 'varchar(100)',
            'oos_response_pay_for' => 'varchar(100)',
            'oos_response_order_amount' => 'decimal(10,2)',
            'oos_response_paymentDateTime' => 'varchar(100)',
        );

        return $SQLfields;
    }

    function plgVmConfirmedOrder($cart, $order)
    {

        outToLog('PAYMENT: plgVmConfirmedOrder -> start');


        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $session = JFactory::getSession();
        $return_context = $session->getId();
        $order_number = $order['details']['BT']->order_number;
        $this->logInfo('plgVmConfirmedOrder order number: ' . $order_number, 'message');

        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        if (!class_exists('VirtueMartModelCurrency')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
        }

        if (!class_exists('TableVendors')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php');
        }

        $vendorModel = VmModel::getModel('Vendor');
        $vendorModel->setId(1);
        $vendor = $vendorModel->getVendor();
        $vendorModel->addImages($vendor, 1);
        $this->getPaymentCurrency($method);

        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);

        if ($totalInPaymentCurrency <= 0) {
            vmInfo(JText::_('PLG_OOS_VM2_ERROR_1'));
            return false;
        }

        $merchant_id = $this->_getMerchantID($method);
        $merchant_key = $this->_getSecretWord($method);
        $url_success = $this->_getUrlSuccess($method);
        $url_fail = $this->_getUrlFail($method);

        if (empty($merchant_id)) {
            vmInfo(JText::_('PLG_OOS_VM2_ERROR_2'));
            return false;
        }

        $dbValues['order_number'] = $order_number;
        $dbValues['payment_name'] = $this->renderPluginName($method, $order);
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues['oos_custom'] = $return_context;
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
        $dbValues['cost_percent_total'] = $method->cost_percent_total;
        $dbValues['payment_currency'] = $method->payment_currency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $dbValues['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData($dbValues);

        $url = $method->url_pay_page;
        outToLog('PAYMRNT: plgVmConfirmedOrder -> send to OOS: $merchant_key = ' . $merchant_key.' order_number = ' . $order_number.' totalInPaymentCurrency= '.$totalInPaymentCurrency.' url_pay_page = '.$url);
        $message = array(
            "amount" => $totalInPaymentCurrency,
            "details" => "Проверка оплаты через OOS",
            "customerRating" => "5",
            "customerAccount" => "joomla_user",
            "orderId" => $order_number,
            "successUrl" => $url_success,
            "failUrl" => $url_fail,
            "paymentMethod" => "",
            "customerPhone" => "+79210000000",
            "customerEmail" => "me@gmail.com",
            "customerComment" => "",
            "data" => array(
                "user" => "+79210000000",
                "debug" => "1"
            )
        );

        $messageText = json_encode($message);

        $http_params = array(
            "marketPlace" => $merchant_id,
            "message" => base64_encode($messageText),
            "signature" => hash('sha256', $messageText . $merchant_key)
        );
        $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
        $html = '<html><head><title>Redirection to OOS</title></head><body><div style="margin: auto; text-align: center;">';
        $html .= '<form action="' . $url . '" method="post" name="vm_oos_form">';
        $html .= '<p>';
        $html .= '<input name="marketPlace" value=' . $http_params["marketPlace"] . '>';
        $html .= '</p>';
        $html .= '<p>';
        $html .= '<input name="message" value=' . $http_params["message"] . '>';
        $html .= '</p>';
        $html .= '<p>';
        $html .= '<input name="signature" value=' . $http_params["signature"] . '>';
        $html .= '</p>';
        $html .= '<p>';
        $html .= '<input type=submit value="Перейти на платёжную страницу OOS">';
        $html .= '</p>';
        $html .= '</form></div>';
        $html .= '<script type="text/javascript">';
        $html .= 'document.vm_oos_form.submit();';
        $html .= '</script></body></html>';
        $html .= '</body></html>';

        $modelOrder = VmModel::getModel('orders');
        $order['customer_notified'] = 1;
        $order['order_status'] = $method->status_pending;

        outToLog('PAYMENT: plgVmConfirmedOrder - virtuemart_order_id = ' . $virtuemart_order_id);

        $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

        $cart->_confirmDone = false;
        $cart->_dataValidated = false;
        $cart->setCartIntoSession();
        JRequest::setVar('html', $html);

        /******* ******/
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $this->getPaymentCurrency($method);
        $paymentCurrencyId = $method->payment_currency;
    }


    function plgVmOnPaymentResponseReceived(&$html)
    {
        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }

        if (!class_exists('shopFunctionsF')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        }

        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
        $order_number = JRequest::getString('on', 0);

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return null;
        }

        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            return null;
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return '';
        }

        $payment_name = $this->renderPluginName($method);
        $html = $this->_getPaymentResponseHtml($paymentTable, $payment_name);

        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();

        return true;
    }

    function plgVmOnUserPaymentCancel()
    {
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $order_number = JRequest::getString('on', '');
        $virtuemart_paymentmethod_id = JRequest::getInt('pm', '');

        if (empty($order_number) or empty($virtuemart_paymentmethod_id) or !$this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
            return null;
        }

        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            return null;
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return null;
        }

        VmInfo(JText::_('VMPAYMENT_PAYPAL_PAYMENT_CANCELLED'));

        $session = JFactory::getSession();
        $return_context = $session->getId();

        if (strcmp($paymentTable->oos_custom, $return_context) === 0) {
            $this->handlePaymentUserCancel($virtuemart_order_id);
        }

        return true;
    }

    function getMerchantKeyForOos() {
        $db = JFactory::getDBO();
        $q = 'SELECT `payment_params` FROM `#__virtuemart_paymentmethods` WHERE `payment_element`="'."oos".'"';
        $db->setQuery($q);
        $params = $db->loadResult();
        $pattern = '/.*merchant_key=\"([^"]+).*/i';
        $replacement = '$1';
        return preg_replace($pattern, $replacement, $params);
    }

    function decrypt_aes128_ecb_pkcs5($encrypted, $arbitrary_key) {
        $key_md5_binary = hash("md5", $arbitrary_key, true);
        $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key_md5_binary, $encrypted, MCRYPT_MODE_ECB);
        $padSize = ord(substr($decrypted, -1));
        return substr($decrypted, 0, $padSize*-1);
    }

    function plgVmOnPaymentNotification() {
        outToLog('RESPONSE plgVmOnPaymentNotification');
        $merchant_key = $this->getMerchantKeyForOos();

        $post = $_POST['encrypted_request'];
        $decrypted_request = $this->decrypt_aes128_ecb_pkcs5($post, $merchant_key);
        outToLog('RESPONSE plgVmOnPaymentNotification $decrypted_request = '.$decrypted_request);
        $json_request = json_decode($decrypted_request, true);

        if (!$json_request) {
            throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
        }
        $ordersArray = $json_request['payments'];

        outToLog('RESPONSE plgVmOnPaymentNotification count orders = '.count($ordersArray));
        if (!$ordersArray) {
            return false;
        }

        for ($i = 0; $i < count($ordersArray); $i++) {
            $orderRecord = $ordersArray[$i];
            outToLog('RESPONSE: order '.($i + 1).' = '.$orderRecord["orderId"]);
            if (!class_exists('VirtueMartModelOrders')) {
                require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
            }

            $payment = array(
                "oos_id" => $orderRecord['marketPlace'],
                "pay_for" => $orderRecord["orderId"],
                "order_amount" => $orderRecord["amount"],
                "order_currency" => "RUB",
                "paymentDateTime" => date('l jS \of F Y h:i:s A'),
                "state" => $orderRecord['state']
            );

            if (!isset($payment['pay_for'])) {
                continue;
            }

            $order_number = $payment['pay_for'];

            if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
                continue;
            }

            if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
                continue;
            }

            $method = $this->getVmPluginMethod($payments[0]->virtuemart_paymentmethod_id);

            if (!$this->selectedThisElement($method->payment_element)) {
                continue;
            }

            $this->logInfo('oos_data ' . implode('   ', $payment), 'message');

            $error = '';
            $order_amount = $this->to_float($payment['order_amount']);

            $pay_for = $order_number;
            $order_currency = $payment['order_currency'];

            //проверяем pay запрос
            $order = array();
            $modelOrder = VmModel::getModel('orders');
            $order['customer_notified'] = 1;
            $order['order_status'] = $method->status_pending;

            //получаем данные
            $oos_id = $payment['oos_id'];
            outToLog('RESPONSE: oos_id = '.$oos_id.'virtuemart_order_id = '.$virtuemart_order_id.' order_number = '.$order_number.'$order_amount = '.$order_amount);
            if ($merchant_key != $this->_getSecretWord($method)) {
                $error .= JText::_('PLG_OOS_VM2_ERROR_3') . '<br>';
            }
            //производим проверки входных данных
            if (empty($oos_id)) {
                $error .= JText::_('PLG_OOS_VM2_ERROR_3') . '<br>';
            } else {
                if (!is_numeric(intval($oos_id))) {
                    $error .= JText::_('PLG_OOS_VM2_ERROR_4') . '<br>';
                }
            }

            if (empty($order_amount)) {
                $error .= JText::_('PLG_OOS_VM2_ERROR_5') . '<br>';
            } else {
                if (!is_numeric($order_amount)) {
                    $error .= JText::_('PLG_OOS_VM2_ERROR_4') . '<br>';
                }
            }

            if (empty($order_currency)) {
                $error .= JText::_('PLG_OOS_VM2_ERROR_6') . '<br>';
            } else {
                if (strlen($order_currency) > 4) {
                    $error .= JText::_('PLG_OOS_VM2_ERROR_7') . '<br>';
                }
            }


            //если нет ошибок
            if (!$error) {
                if ($pay_for) {
                    //сверяем строчки хеша (присланную и созданную нами)
                    $state = $payment['state'];
                    if ($state == 'err') {
                        $order['order_status'] = $method->status_canceled;
                        outToLog('RESPONSE: order[order_status] = '.$order['order_status'].' comment = '.JText::_('PLG_OOS_VM2_ERROR_11'));
                        $order['comments'] = sprintf(JText::_('PLG_OOS_VM2_ERROR_11'), $order_number);
                    } else if ($state == 'rej') {
                        $order['order_status'] = $method->status_canceled;
                        outToLog('RESPONSE: order[order_status] = '.$order['order_status'].' comment = '.JText::_('PLG_OOS_VM2_ERROR_12'));
                        $order['comments'] = sprintf(JText::_('PLG_OOS_VM2_ERROR_12'), $order_number);
                    } else if ($state == 'ref') {
                        $order['order_status'] = $method->status_success;
                        outToLog('RESPONSE: order[order_status] = '.$order['order_status'].' comment = '.JText::_('PLG_OOS_VM2_ERROR_13'));
                        $order['comments'] = sprintf(JText::_('PLG_OOS_VM2_ERROR_13'), $order_number);
                    } else if ($state == 'exp') {
                        $order['order_status'] = $method->status_pending;
                        outToLog('RESPONSE: order[order_status] = '.$order['order_status'].' comment = '.JText::_('PLG_OOS_VM2_ERROR_14'));
                        $order['comments'] = sprintf(JText::_('PLG_OOS_VM2_ERROR_14'), $order_number);
                    } else if ($state == 'end') {
                        $order['order_status'] = $method->status_success;
                        outToLog('RESPONSE: order[order_status] = '.$order['order_status'].' comment = '.JText::_('PLG_OOS_VM2_ERROR_15'));
                        $order['comments'] = sprintf(JText::_('PLG_OOS_VM2_ERROR_15'), $order_number);
                    }
                } else {
                    //если pay_for - не правильный формат
                    $order['order_status'] = $method->status_canceled;
                    $order['comments'] = JText::_('PLG_OOS_VM2_ERROR_9');
                }
            } else {
                outToLog('$error = '.$error);
                //если есть ошибки
                $order['order_status'] = $method->status_canceled;
                $order['comments'] = JText::_('PLG_OOS_VM2_ERROR_9') . ': ' . $error;
            }

            $this->_storeOosInternalData($method, $payment, $virtuemart_order_id, $payments[0]->virtuemart_paymentmethod_id);
            $this->logInfo('plgVmOnPaymentNotification return new_status: ' . $order['order_status'], 'message');

            $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

            if (isset($payment['return_context'])) {
                $this->emptyCart($payment['return_context'], $order_number);
            }
        }
    }

    function _storeOosInternalData($method, $payment, $virtuemart_order_id, $virtuemart_paymentmethod_id)
    {
        $db = JFactory::getDBO();
        $query = 'SHOW COLUMNS FROM `' . $this->_tablename . '`';
        $db->setQuery($query);
        $columns = $db->loadResultArray(0);

        $response_fields = array();
        $post_msg = '';
        foreach ($payment as $key => $value) {
            $post_msg .= $key . '=' . $value . '<br />';
            $table_key = 'oos_response_' . $key;

            if (in_array($table_key, $columns)) {
                $response_fields[$table_key] = $value;
            }
        }

        $response_fields['payment_name'] = $this->renderPluginName($method);
        $response_fields['order_number'] = $payment['order'];
        $response_fields['virtuemart_order_id'] = $virtuemart_order_id;
        $response_fields['virtuemart_paymentmethod_id'] = $virtuemart_paymentmethod_id;
        $response_fields['oos_custom'] = $payment['ext_details'];

        $this->storePSPluginInternalData($response_fields);
    }

    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id)
    {
        if (!$this->selectedThisByMethodId($payment_method_id)) {
            return null;
        }

        if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
            return '';
        }

        $html = '<table class="adminlist" width="50%">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $code = 'oos_response_';

        foreach ($payments as $payment) {
            $html .= '<tr class="row1"><td>' . JText::_('PLG_OOS_VM2_DATE') . '</td><td align="left">' . $payment->created_on . '</td></tr>';

            foreach ($payment as $key => $value) {
                if (substr($key, 0, strlen($code)) == $code) {
                    $html .= $this->getHtmlRowBE($key, $value);
                }
            }
        }

        $html .= '</table>' . "\n";

        return $html;
    }

    function _getMerchantID($method)
    {
        return $method->merchant_id;
    }

    function _getSecretWord($method)
    {
        return $method->merchant_key;
    }

    function _getUrlSuccess($method)
    {
        return $method->url_success;
    }

    function _getUrlFail($method)
    {
        return $method->url_fail;
    }

    function _getPaymentResponseHtml($paymentTable, $payment_name)
    {
        VmConfig::loadJLang('com_virtuemart');

        $html = '<table>' . "\n";
        $html .= $this->getHtmlRow('PLG_OOS_VM2_PAYMENT_NAME', $payment_name);

        if (!empty($paymentTable)) {
            $html .= $this->getHtmlRow('PLG_OOS_VM2_ORDER_NUMBER', $paymentTable->order_number);
        }

        $html .= '</table>' . "\n";

        return $html;
    }

    protected function checkConditions($cart, $method, $cart_prices)
    {
		outToLog('PAYMENT: checkConditions Select_list_add_oos');
        $this->convert($method);
        $amount = $cart_prices['salesPrice'];
        $address = $cart->ST == 0 ? $cart->BT : $cart->ST;

        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount OR ($method->min_amount <= $amount AND ($method->max_amount == 0)));
        $countries = array();

        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }

        if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            if ($amount_cond) {
				outToLog('PAYMENT: checkConditions return true');
                return true;
            }
        }
		outToLog('PAYMENT: checkConditions return false');
        return false;
    }

    /**
     * @param $method
     */
    function convert($method)
    {

        $method->min_amount = (float)$method->min_amount;
        $method->max_amount = (float)$method->max_amount;
    }

    //функция переводит число в нужный формат
    function to_float($sum)
    {
        if (strpos($sum, "."))
        {
            $sum=round($sum,2);
        }
        else {$sum=$sum.".0";}
        return $sum;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }

        return $method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01);
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
		outToLog('$htmlIn before = '.print_r($htmlIn,true));
		$display = $this->displayListFE($cart, $selected, $htmlIn);
		outToLog('$htmlIn after = '.print_r($htmlIn,true));
        return $display;
    }

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array())
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }
}